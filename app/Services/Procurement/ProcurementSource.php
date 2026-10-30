<?php

namespace App\Services\Procurement;

use App\Services\WorkOrder\WorkOrderClosingCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

class ProcurementSource
{
    private const MATERIAL_SETS = ['011','020','021','022','023','024','025','026','101','102','103','104','10A','111','120','134','13S','13X','H1'];
    private array $columns = [];
    private array $linkCache = [];
    private ?string $linkTable = null;

    private function db()
    {
        return DB::connection(config('services.work_order.target_connection', 'sqlsrv'));
    }

    private function table(string $key, string $default): string
    {
        return config('workorders.schema', 'dbo') . '.' . config('workorders.' . $key, $default);
    }

    protected function columns(string $table): array
    {
        $connection = (string) config('services.work_order.target_connection','sqlsrv');
        $identity = [$connection,config("database.connections.$connection.host"),config("database.connections.$connection.database"),$table];
        return $this->columns[$table] ??= Cache::remember('procurement-schema-' . hash('sha256',json_encode($identity)),300,fn()=> $this->db()->getSchemaBuilder()->getColumnListing($table));
    }

    private function query()
    {
        $items = $this->table('items_table', 'tHF_WOExItem');
        $headers = $this->table('table', 'tHF_WOEx');
        $catalog = $this->table('catalog_items_table', 'tHE_SetItem');
        $query = $this->db()->table($items . ' as i')->join($headers . ' as w', 'w.acKey', '=', 'i.acKey')
            ->leftJoin($catalog . ' as c', 'c.acIdent', '=', 'i.acIdent')->select('i.*');
        foreach (['acNote','acFieldSE'] as $noteColumn) {
            if (in_array($noteColumn,$this->columns($items),true)) {
                $query->whereRaw("UPPER(COALESCE(i.$noteColumn, '')) NOT LIKE ? ESCAPE '!'", ['%REZERVACIJA!_DODATNIH!_SIROVINA%']);
            }
        }
        foreach (array_unique(['acKeyView','acRefNo1','acLnkKey','acLnkKeyView','anLnkNo','acStatusMF', config('procurement.schedule_field', 'adSchedStartTime')]) as $column) {
            if (in_array($column, $this->columns($headers), true)) $query->addSelect('w.' . $column . ' as header_' . $column);
        }
        foreach (['acSetOfItem','anVAT','acSupplier','acName','acUM','acCurrency','anPriceSupp','anPrice','anBuyPrice'] as $column) {
            if (in_array($column, $this->columns($catalog), true)) $query->addSelect('c.' . $column . ' as catalog_' . $column);
        }
        // Same precedence as WorkOrderController::resolveItemKindForPreview.
        $query->where(function ($q) {
            $q->whereIn('c.acSetOfItem', self::MATERIAL_SETS)->orWhere(function ($q) {
                $q->where('i.acOperationType', 'M')->where(function ($q) { $q->whereNull('c.acSetOfItem')->orWhere('c.acSetOfItem', '<>', 'OPR'); });
            });
        })->where(function ($q) { $q->whereNull('w.acStatusMF')->orWhere('w.acStatusMF', '<>', 'Z'); });
        return $query;
    }

    public function materials(string $search, string $sort, string $direction, string $unit = '')
    {
        $query = $this->query();
        if ($unit !== '') $query->where('i.acUM', $unit);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                foreach (['i.acKey','i.acIdent','i.acDescr','w.acLnkKey'] as $column) $q->orWhere($column, 'like', '%' . $search . '%');
            });
        }
        $sorts = ['work_order' => 'i.acKey', 'material' => 'i.acIdent', 'order' => 'w.acLnkKey', 'position' => 'i.anNo'];
        $paginator = $query->orderBy($sorts[$sort] ?? 'i.acKey', $direction === 'asc' ? 'asc' : 'desc')->orderBy('i.anNo')->paginate(40)->withQueryString();
        $paginator->setCollection($paginator->getCollection()->map(fn ($row) => $this->map((array) $row)));
        return $paginator;
    }

    public function selected(array $ids): array
    {
        $result = [];
        foreach (array_unique($ids) as $id) {
            if (!preg_match('/^[A-Za-z0-9_-]{1,1000}$/D', $id)) throw new InvalidArgumentException('Neispravna stavka sastavnice.');
            $identity = json_decode(base64_decode(strtr($id, '-_', '+/'), true) ?: '', true);
            if (!is_array($identity) || array_keys($identity) !== [0, 1, 2, 3] || !is_string($identity[0])) throw new InvalidArgumentException('Neispravna stavka sastavnice.');
            [$key, $qid, $no, $variant] = $identity;
            if (strlen($key) > 255 || $key === '') throw new InvalidArgumentException('Neispravan ključ radnog naloga.');
            foreach ([$qid, $no, $variant] as $part) {
                if ($part !== null && (!is_string($part) || !preg_match('/^\d{1,20}$/D', $part))) throw new InvalidArgumentException('Neispravan identifikator sastavnice.');
            }
            if ($qid === null && $no === null) throw new InvalidArgumentException('Stavka nema jednoznačan identifikator.');
            $query = $this->query()->where('i.acKey', $key);
            if ($qid !== null) $query->where('i.anQId', $qid);
            else {
                $query->where('i.anNo', $no);
                if ($variant !== null) $query->where('i.anVariant', $variant);
            }
            $rows = $query->limit(2)->get();
            if ($rows->count() !== 1) throw new InvalidArgumentException('Izvorna materijalna stavka nije dostupna ili nije jednoznačna.');
            $mapped = $this->map((array) $rows->first());
            if ($mapped['id'] !== $id) throw new InvalidArgumentException('Izvorna stavka je promijenjena. Ponovo otvorite listu.');
            $result[$id] = $mapped;
        }
        return $result;
    }

    public function map(array $row): array
    {
        $identity = [trim((string) $row['acKey']), isset($row['anQId']) ? (string) $row['anQId'] : null,
            isset($row['anNo']) ? (string) $row['anNo'] : null, isset($row['anVariant']) ? (string) $row['anVariant'] : null];
        $note = trim((string) ($row['acNote'] ?? $row['acFieldSE'] ?? ''));
        $note = preg_replace('/^PLANNED_(?:BOM|RAW)\|/', '', $note);
        $quantity = '0';
        foreach (['anQty1','anQty','anPlanQty','anNormQty'] as $field) {
            if (isset($row[$field]) && is_numeric($row[$field])) {
                $value = WorkOrderClosingCalculator::decimal($row[$field]);
                if (bccomp($value, '0', 6) !== 0) { $quantity = $value; break; }
            }
        }
        $schedule = $row['header_' . config('procurement.schedule_field', 'adSchedStartTime')] ?? null;
        $requiredDate = $schedule ? CarbonImmutable::parse($schedule, config('app.timezone'))->subDays(max(0, (int) config('procurement.lead_days', 7)))->format('Y-m-d') : null;
        return [
            'id' => rtrim(strtr(base64_encode(json_encode($identity)), '+/', '-_'), '='),
            'work_order_key' => $identity[0], 'item_qid' => $identity[1], 'item_no' => $identity[2], 'variant' => $identity[3],
            'work_order' => trim((string) ($row['header_acKeyView'] ?? $row['header_acRefNo1'] ?? $row['acKey'])),
            'material' => trim((string) $row['acIdent']), 'name' => trim((string) ($row['acDescr'] ?? $row['catalog_acName'] ?? '')),
            'dimensions' => $note, 'required' => $quantity, 'unit' => trim((string) ($row['acUM'] ?? '')),
            'vat_rate' => (string) ($row['catalog_anVAT'] ?? config('workorders.catalog_product_defaults.vat_rate', 17)),
            'supplier' => trim((string) ($row['catalog_acSupplier'] ?? '')),
            'price' => $this->suggestedPrice($row),
            'required_date' => $requiredDate,
            'links' => $this->links($row),
        ];
    }

    private function suggestedPrice(array $row): string
    {
        $unit = strtoupper(trim((string) ($row['acUM'] ?? '')));
        $currency = strtoupper((string) config('workorders.catalog_product_defaults.purchase_currency', 'KM'));
        if ($unit === '' || $unit !== strtoupper(trim((string) ($row['catalog_acUM'] ?? '')))
            || $currency !== strtoupper(trim((string) ($row['catalog_acCurrency'] ?? '')))) return '';
        foreach (['anPriceSupp','anPrice','anBuyPrice'] as $field) {
            $raw = $row['catalog_' . $field] ?? null;
            if (!is_numeric($raw)) continue;
            $price = WorkOrderClosingCalculator::decimal($raw);
            if (bccomp($price, '0', 12) <= 0 || bccomp($price, '999999999999.999999', 12) > 0) continue;
            return bcadd($price, '0.0000005', 6);
        }
        return '';
    }

    public function requirements(array $filters, array $progress = []): LengthAwarePaginator
    {
        $raw = $this->query()->orderBy('i.acKey')->orderBy('i.anNo')->get();
        // Prime all WO links in bounded batches instead of querying for every material row.
        $keys = $raw->pluck('acKey')->unique()->values()->all();
        if ($raw->isNotEmpty()) $this->links((array) $raw->first());
        if ($this->linkTable) {
            foreach (array_chunk($keys, 500) as $chunk) {
                foreach ($chunk as $key) $this->linkCache[trim($key)] = [];
                foreach ($this->db()->table($this->linkTable)->whereIn('acKey', $chunk)->orderBy('acLnkKey')->orderBy('anLnkNo')->get() as $link) {
                    if (trim((string) ($link->acLnkKey ?? '')) === '') continue;
                    $this->linkCache[trim($link->acKey)][] = ['order_key'=>trim($link->acLnkKey), 'order_number'=>trim($link->acLnkKeyView ?? $link->acLnkKey), 'position'=>(string) ($link->anLnkNo ?? '')];
                }
                foreach ($chunk as $key) $this->linkCache[trim($key)] = collect($this->linkCache[trim($key)])->unique(fn($link)=>$link['order_key'].':'.$link['position'])->values()->all();
            }
        }
        $today = CarbonImmutable::now(config('app.timezone'))->format('Y-m-d');
        $soon = CarbonImmutable::now(config('app.timezone'))->addDays(config('procurement.due_soon_days', 7))->format('Y-m-d');
        $rows = $raw->map(function ($row) use ($progress, $today) {
            $item = $this->map((array) $row);
            $state = $progress[$item['id']] ?? ['ordered'=>'0','received'=>'0','purchases'=>[],'suppliers'=>[],'late'=>false];
            $item += $state;
            $item['remaining'] = bccomp($item['required'], $item['ordered'], 6) > 0 ? bcsub($item['required'], $item['ordered'], 6) : '0';
            $item['missing'] = bccomp($item['required'], $item['received'], 6) > 0 ? bcsub($item['required'], $item['received'], 6) : '0';
            $item['state'] = bccomp($item['required'], '0', 6) <= 0 ? 'zero' : (bccomp($item['received'], $item['required'], 6) >= 0 ? 'received' : (bccomp($item['received'], '0', 6) > 0 ? 'partial' : (bccomp($item['ordered'], '0', 6) > 0 ? (bccomp($item['remaining'], '0', 6) > 0 ? 'part_ordered' : 'ordered') : 'not_ordered')));
            $item['late'] = $item['late'] || ($item['missing'] !== '0' && bccomp($item['missing'], '0', 6) > 0 && $item['required_date'] && $item['required_date'] < $today);
            return $item;
        })->filter(function ($row) use ($filters, $soon, $today) {
            $q = mb_strtolower(trim($filters['q'] ?? ''));
            $search = implode(' ', [$row['work_order'], $row['work_order_key'], $row['material'], $row['name'], $row['dimensions'], $row['unit'], ...array_column($row['links'], 'order_number'), ...array_column($row['links'], 'position')]);
            $status = $filters['status'] ?? '';
            return ($q === '' || str_contains(mb_strtolower($search), $q))
                && (empty($filters['unit']) || strtoupper($row['unit']) === strtoupper($filters['unit']))
                && (empty($filters['supplier']) || in_array($filters['supplier'], $row['suppliers'], true))
                && (empty($filters['work_order']) || str_contains(mb_strtolower($row['work_order']), mb_strtolower($filters['work_order'])))
                && (empty($filters['from']) || ($row['required_date'] && $row['required_date'] >= $filters['from']))
                && (empty($filters['to']) || ($row['required_date'] && $row['required_date'] <= $filters['to']))
                && ($status === '' || ($status === 'late' ? $row['late'] : ($status === 'soon' ? ($row['required_date'] && $row['required_date'] >= $today && $row['required_date'] <= $soon && $row['state'] !== 'received') : $row['state'] === $status)))
                && (empty($filters['selected_only']) || in_array($row['id'], $filters['selected_ids'] ?? [], true));
        });
        $sort = $filters['sort'] ?? 'work_order';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 1 : -1;
        $rows = $rows->sort(function ($a, $b) use ($sort, $direction) {
            if ($sort === 'required') return $direction * bccomp($a['required'],$b['required'],6) ?: strcmp($a['id'],$b['id']);
            $field = fn ($r) => $sort === 'position' ? ($r['links'][0]['position'] ?? '') : ($sort === 'order' ? ($r['links'][0]['order_number'] ?? '') : ($r[$sort] ?? $r['work_order']));
            return $direction * strnatcasecmp((string) $field($a), (string) $field($b)) ?: strcmp($a['id'], $b['id']);
        })->values();
        $page = max(1, (int) ($filters['page'] ?? 1));
        return new LengthAwarePaginator($rows->forPage($page, 40)->values(), $rows->count(), 40, $page, ['path'=>request()->url(), 'query'=>request()->query()]);
    }

    protected function links(array $row): array
    {
        $key = trim((string) $row['acKey']);
        if (isset($this->linkCache[$key])) {
            if ($this->linkCache[$key] !== []) return $this->linkCache[$key];
            return !empty($row['header_acLnkKey']) ? [['order_key'=>trim($row['header_acLnkKey']), 'order_number'=>trim($row['header_acLnkKeyView'] ?? $row['header_acLnkKey']), 'position'=>(string)($row['header_anLnkNo'] ?? '')]] : [];
        }
        if ($this->linkTable === null) {
            // Same discovery candidates as WorkOrderOrderItemLink, on the active target connection.
            $this->linkTable = '';
            foreach (array_unique([config('workorders.work_order_order_item_link_table', 'vHF_LinkWOExOrderItem'), 'vHF_LinkWOExOrderItem', 'tHF_LinkWOExOrderItem']) as $candidate) {
                $qualified = config('workorders.schema', 'dbo') . '.' . $candidate;
                if ($this->columns($qualified) !== []) { $this->linkTable = $qualified; break; }
            }
        }
        $links = $this->linkTable === '' ? [] : $this->db()->table($this->linkTable)->where('acKey', $row['acKey'])->get()->map(fn ($link) => [
            'order_key' => trim((string) ($link->acLnkKey ?? '')),
            'order_number' => trim((string) ($link->acLnkKeyView ?? $link->acLnkKey ?? '')),
            'position' => (string) ($link->anLnkNo ?? ''),
        ])->filter(fn ($link) => $link['order_key'] !== '')->unique(fn ($link) => $link['order_key'] . ':' . $link['position'])->values()->all();
        if ($links === [] && !empty($row['header_acLnkKey'])) $links[] = [
            'order_key' => trim($row['header_acLnkKey']), 'order_number' => trim($row['header_acLnkKeyView'] ?? $row['header_acLnkKey']),
            'position' => (string) ($row['header_anLnkNo'] ?? ''),
        ];
        return $this->linkCache[$key] = $links;
    }

    public function suppliers(string $search = ''): array
    {
        // Existing Pantheon subject identity, restricted to subjects already used as material suppliers.
        return $this->db()->table($this->table('catalog_items_table', 'tHE_SetItem') . ' as c')
            ->join(config('workorders.schema', 'dbo') . '.tHE_SetSubj as s', 's.acSubject', '=', 'c.acSupplier')
            ->where('s.acSubject', 'like', '%' . $search . '%')->select('s.acSubject')->distinct()->orderBy('s.acSubject')
            ->limit(100)->pluck('acSubject')->map(fn ($s) => trim($s))->all();
    }

    public function validSupplier(string $supplier): bool
    {
        return $this->db()->table(config('workorders.schema', 'dbo') . '.tHE_SetSubj')->where('acSubject', $supplier)->exists();
    }

    public function warehouses(): array
    {
        return $this->db()->table($this->table('stock_table', 'tHE_Stock'))->whereNotNull('acWarehouse')->distinct()->orderBy('acWarehouse')->pluck('acWarehouse')->map(fn ($value) => trim($value))->filter()->values()->all();
    }

    public function validWarehouse(string $warehouse): bool
    {
        return in_array($warehouse, $this->warehouses(), true);
    }
}
