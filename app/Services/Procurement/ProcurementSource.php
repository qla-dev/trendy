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

    private function query(bool $identityOnly = false)
    {
        $items = $this->table('items_table', 'tHF_WOExItem');
        $headers = $this->table('table', 'tHF_WOEx');
        $catalog = $this->table('catalog_items_table', 'tHE_SetItem');
        $query = $this->db()->table($items . ' as i')->join($headers . ' as w', 'w.acKey', '=', 'i.acKey')
            ->leftJoin($catalog . ' as c', 'c.acIdent', '=', 'i.acIdent');
        $fields = $identityOnly ? ['acKey','anQId','anNo','anVariant'] : ['acKey','anQId','anNo','anVariant','acIdent','acDescr','acNote','acFieldSE','acUM','anQty1','anQty','anPlanQty','anNormQty'];
        $query->select(array_map(fn ($field) => 'i.' . $field, array_values(array_intersect($fields, $this->columns($items)))));
        foreach (['acNote','acFieldSE'] as $noteColumn) {
            if (in_array($noteColumn,$this->columns($items),true)) {
                $query->whereRaw("UPPER(COALESCE(i.$noteColumn, '')) NOT LIKE ? ESCAPE '!'", ['%REZERVACIJA!_DODATNIH!_SIROVINA%']);
            }
        }
        foreach ($identityOnly ? [] : array_unique(['acKeyView','acRefNo1','acLnkKey','acLnkKeyView','anLnkNo','acStatusMF','anPlanQty','adDate','adDateIns','adTimeIns', config('procurement.schedule_field', 'adSchedStartTime')]) as $column) {
            if (in_array($column, $this->columns($headers), true)) $query->addSelect('w.' . $column . ' as header_' . $column);
        }
        foreach ($identityOnly ? [] : ['acSetOfItem','anVAT','acSupplier','acName','acUM','acCurrency','anPriceSupp','anPrice','anBuyPrice'] as $column) {
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
            if (str_starts_with($id, 'catalog_')) {
                $code = base64_decode(substr($id, 8), true);
                $catalog = $code === false ? [] : $this->catalog($code, true);
                if (count($catalog) !== 1 || $catalog[0]['id'] !== $id) throw new InvalidArgumentException('Materijal nije dostupan u katalogu.');
                $result[$id] = $catalog[0];
                continue;
            }
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
        $note = trim((string) ($row['acNote'] ?? ''));
        if ($note === '') $note = trim((string) ($row['acFieldSE'] ?? ''));
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
        $created = $row['header_adDate'] ?? $row['header_adDateIns'] ?? $row['header_adTimeIns'] ?? null;
        return [
            'id' => rtrim(strtr(base64_encode(json_encode($identity)), '+/', '-_'), '='),
            'work_order_key' => $identity[0], 'item_qid' => $identity[1], 'item_no' => $identity[2], 'variant' => $identity[3],
            'work_order' => trim((string) ($row['header_acKeyView'] ?? $row['header_acRefNo1'] ?? $row['acKey'])),
            'material' => trim((string) $row['acIdent']), 'name' => trim((string) ($row['acDescr'] ?? $row['catalog_acName'] ?? '')),
            'dimensions' => $note, 'pieces' => isset($row['header_anPlanQty']) ? WorkOrderClosingCalculator::decimal($row['header_anPlanQty']) : null,
            'required' => $quantity, 'unit' => trim((string) ($row['acUM'] ?? '')),
            'vat_rate' => (string) ($row['catalog_anVAT'] ?? config('workorders.catalog_product_defaults.vat_rate', 17)),
            'supplier' => trim((string) ($row['catalog_acSupplier'] ?? '')),
            'price' => $this->suggestedPrice($row),
            'required_date' => $requiredDate,
            'inquiry_deadline' => $created ? CarbonImmutable::parse($created, config('app.timezone'))->subDays(7)->format('Y-m-d') : null,
            'created_date' => $created ? CarbonImmutable::parse($created, config('app.timezone'))->format('Y-m-d') : null,
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

    private function orderLinkTable(): string
    {
        if ($this->linkTable === null) {
            $this->linkTable = '';
            foreach (array_unique([config('workorders.work_order_order_item_link_table', 'vHF_LinkWOExOrderItem'), 'vHF_LinkWOExOrderItem', 'tHF_LinkWOExOrderItem']) as $candidate) {
                $table = config('workorders.schema', 'dbo') . '.' . $candidate;
                if ($this->columns($table) !== []) { $this->linkTable = $table; break; }
            }
        }
        return $this->linkTable;
    }

    private function identity(array $row): string
    {
        $parts = [trim((string) $row['acKey'])];
        foreach (['anQId','anNo','anVariant'] as $field) $parts[] = isset($row[$field]) ? (string) $row[$field] : null;
        return rtrim(strtr(base64_encode(json_encode($parts)), '+/', '-_'), '=');
    }

    private function sqlText(string $text): string
    {
        // SQL literals are used only for ledger identities, avoiding SQL Server's parameter ceiling.
        return ($this->db()->getDriverName() === 'sqlsrv' ? 'N' : '') . "'" . str_replace("'", "''", $text) . "'";
    }

    private function identityPredicate(string $id): ?string
    {
        $parts = json_decode(base64_decode(strtr($id, '-_', '+/'), true) ?: '', true);
        if (!is_array($parts) || array_keys($parts) !== [0,1,2,3] || !is_string($parts[0]) || $parts[0] === '' || strlen($parts[0]) > 255) return null;
        foreach (array_slice($parts, 1) as $part) if ($part !== null && (!is_string($part) || !preg_match('/^\d{1,20}$/D', $part))) return null;
        $columns = $this->columns($this->table('items_table', 'tHF_WOExItem'));
        $terms = ['i.acKey = ' . $this->sqlText($parts[0])];
        if ($parts[1] !== null && in_array('anQId', $columns, true)) $terms[] = 'i.anQId = ' . $parts[1];
        elseif ($parts[2] !== null && in_array('anNo', $columns, true)) {
            $terms[] = 'i.anNo = ' . $parts[2];
            if ($parts[3] !== null && in_array('anVariant', $columns, true)) $terms[] = 'i.anVariant = ' . $parts[3];
        } else return null;
        return '(' . implode(' AND ', $terms) . ')';
    }

    private function identitiesPredicate(array $ids): string
    {
        $terms = array_values(array_filter(array_map(fn ($id) => $this->identityPredicate($id), array_unique($ids))));
        return $terms ? '(' . implode(' OR ', $terms) . ')' : '1 = 0';
    }

    private function quantityExpression(): string
    {
        $terms = [];
        foreach (array_intersect(['anQty1','anQty','anPlanQty','anNormQty'], $this->columns($this->table('items_table', 'tHF_WOExItem'))) as $field) {
            $number = $this->db()->getDriverName() === 'sqlsrv' ? "CAST(ROUND(TRY_CONVERT(decimal(38,12), i.$field), 6, 1) AS decimal(28,6))" : "(CAST(CAST(i.$field AS REAL) * 1000000 AS INTEGER) / 1000000.0)";
            $terms[] = "NULLIF($number, 0)";
        }
        return $terms ? 'COALESCE(' . implode(', ', $terms) . ', 0)' : '0';
    }

    private function headerExpression(array $fields, string $fallback = 'NULL'): string
    {
        $columns = $this->columns($this->table('table', 'tHF_WOEx'));
        $fields = array_map(fn ($field) => 'w.' . $field, array_values(array_intersect($fields, $columns)));
        if ($fields && $fallback !== 'NULL') $fields[] = $fallback;
        return count($fields) > 1 ? 'COALESCE(' . implode(', ', $fields) . ')' : ($fields[0] ?? $fallback);
    }

    private function searchExpression(): string
    {
        $fields = ['i.acKey','i.acIdent'];
        $itemColumns=$this->columns($this->table('items_table','tHF_WOExItem'));
        $catalogColumns=$this->columns($this->table('catalog_items_table','tHE_SetItem'));
        if(in_array('acDescr',$itemColumns,true)) $fields[]=in_array('acName',$catalogColumns,true)?'COALESCE(i.acDescr,c.acName)':'i.acDescr';
        elseif(in_array('acName',$catalogColumns,true)) $fields[]='c.acName';
        foreach (array_intersect(['acNote','acFieldSE','acUM'], $this->columns($this->table('items_table', 'tHF_WOExItem'))) as $field) $fields[] = 'i.' . $field;
        foreach (array_intersect(['acKeyView','acRefNo1','acLnkKey','acLnkKeyView','anLnkNo'], $this->columns($this->table('table', 'tHF_WOEx'))) as $field) $fields[] = 'w.' . $field;
        $sqlite = $this->db()->getDriverName() === 'sqlite';
        return implode($sqlite ? " || ' ' || " : " + N' ' + ", array_map(fn ($field) => 'COALESCE(CAST(' . $field . ' AS ' . ($sqlite ? 'TEXT' : 'nvarchar(max)') . "), '')", $fields));
    }

    private function requirementQuery(array $filters, array $progress, bool $identityOnly = false)
    {
        $query = $this->query($identityOnly);
        $like = fn ($text) => '%' . str_replace(['!','%','_'], ['!!','!%','!_'], mb_strtolower(trim($text))) . '%';
        if (!empty($filters['q'])) {
            $search = $like($filters['q']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(' . $this->searchExpression() . ") LIKE ? ESCAPE '!'", [$search]);
                if ($table = $this->orderLinkTable()) {
                    $q->orWhereExists(function ($links) use ($table, $search) {
                        $links->selectRaw('1')->from($table . ' as search_links')->whereColumn('search_links.acKey', 'i.acKey')->where(function ($match) use ($search) {
                            foreach (array_intersect(['acLnkKey','acLnkKeyView','anLnkNo'], $this->columns($this->orderLinkTable())) as $field) {
                                $type = $this->db()->getDriverName() === 'sqlite' ? 'TEXT' : 'nvarchar(max)';
                                $match->orWhereRaw("LOWER(CAST(search_links.$field AS $type)) LIKE ? ESCAPE '!'", [$search]);
                            }
                        });
                    });
                }
            });
        }
        if (!empty($filters['unit'])) $query->whereRaw('UPPER(i.acUM) = ?', [strtoupper($filters['unit'])]);
        if (!empty($filters['material'])) $query->where('i.acIdent', $filters['material']);
        if (!empty($filters['work_order'])) $query->whereRaw('LOWER(' . $this->headerExpression(['acKeyView','acRefNo1'], 'i.acKey') . ") LIKE ? ESCAPE '!'", [$like($filters['work_order'])]);
        if (!empty($filters['selected_only'])) $query->whereRaw($this->identitiesPredicate($filters['selected_ids'] ?? []));
        $created = $this->headerExpression(['adDate','adDateIns','adTimeIns']);
        $scheduled = $this->headerExpression([config('procurement.schedule_field', 'adSchedStartTime')]);
        $lead = max(0, (int) config('procurement.lead_days', 7));
        foreach (['created_from'=>[$created,0,false], 'created_to'=>[$created,0,true], 'from'=>[$scheduled,$lead,false], 'to'=>[$scheduled,$lead,true]] as $field=>[$expression,$days,$end]) {
            if (!empty($filters[$field])) $query->whereRaw($expression . ($end ? ' < ?' : ' >= ?'), [CarbonImmutable::parse($filters[$field])->addDays($days + ($end ? 1 : 0))->format('Y-m-d')]);
        }
        if (!empty($filters['supplier'])) $query->whereRaw($this->identitiesPredicate(array_keys(array_filter($progress, fn ($state) => in_array($filters['supplier'], $state['suppliers'] ?? [], true)))));
        if (!empty($filters['status'])) {
            $quantity = $this->quantityExpression();
            $ordered = $received = $late = [];
            foreach ($progress as $id=>$state) {
                if (!$identity = $this->identityPredicate($id)) continue;
                foreach (['ordered','received'] as $field) {
                    $number = (string) ($state[$field] ?? '0');
                    if (!preg_match('/^\d{1,26}(?:\.\d{1,6})?$/D', $number)) throw new InvalidArgumentException('Neispravna količina nabavke.');
                    if (bccomp($number,'0',6) !== 0) ${$field}[] = "WHEN $identity THEN $number";
                }
                if (!empty($state['late'])) $late[] = $identity;
            }
            $ordered = $ordered ? '(CASE ' . implode(' ', $ordered) . ' ELSE 0 END)' : '0';
            $received = $received ? '(CASE ' . implode(' ', $received) . ' ELSE 0 END)' : '0';
            $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
            switch ($filters['status']) {
                case 'zero': $query->whereRaw("$quantity <= 0"); break;
                case 'not_ordered': $query->whereRaw("$quantity > 0 AND $received <= 0 AND $ordered <= 0"); break;
                case 'part_ordered': $query->whereRaw("$quantity > 0 AND $received <= 0 AND $ordered > 0 AND $ordered < $quantity"); break;
                case 'ordered': $query->whereRaw("$quantity > 0 AND $received <= 0 AND $ordered >= $quantity"); break;
                case 'partial': $query->whereRaw("$quantity > 0 AND $received > 0 AND $received < $quantity"); break;
                case 'received': $query->whereRaw("$quantity > 0 AND $received >= $quantity"); break;
                case 'late':
                    $query->where(function ($q) use ($late,$quantity,$received,$scheduled,$today,$lead) {
                        if ($late) $q->whereRaw('(' . implode(' OR ', $late) . ')');
                        $q->orWhereRaw("($quantity > $received AND $scheduled < ?)", [$today->addDays($lead)->format('Y-m-d')]);
                    }); break;
                case 'soon':
                    $query->whereRaw("$scheduled >= ? AND $scheduled < ? AND NOT ($quantity > 0 AND $received >= $quantity)", [$today->addDays($lead)->format('Y-m-d'),$today->addDays($lead + config('procurement.due_soon_days',7) + 1)->format('Y-m-d')]); break;
            }
        }
        return $query;
    }

    public function requirementCount(array $filters, array $progress = []): int
    {
        return $this->requirementQuery($filters, $progress, true)->count();
    }

    public function requirementIds(array $filters, array $progress = []): array
    {
        // Only four identity columns cross the connection; no material details, links or stock are loaded.
        return $this->requirementQuery($filters, $progress, true)->get()->map(fn ($row) => $this->identity((array) $row))->all();
    }

    private function primeLinks($raw): void
    {
        $table = $this->orderLinkTable();
        if (!$table) return;
        foreach (array_chunk($raw->pluck('acKey')->unique()->all(), 500) as $keys) {
            foreach ($keys as $key) $this->linkCache[trim($key)] = [];
            foreach ($this->db()->table($table)->whereIn('acKey', $keys)->orderBy('acLnkKey')->orderBy('anLnkNo')->get() as $row) {
                if (trim((string) ($row->acLnkKey ?? '')) === '') continue;
                $this->linkCache[trim($row->acKey)][] = ['order_key'=>trim($row->acLnkKey),'order_number'=>trim($row->acLnkKeyView ?? $row->acLnkKey),'position'=>(string)($row->anLnkNo ?? '')];
            }
            foreach ($keys as $key) $this->linkCache[trim($key)] = collect($this->linkCache[trim($key)])->unique(fn ($link) => $link['order_key'].':'.$link['position'])->values()->all();
        }
    }

    public function requirements(array $filters, array $progress = []): LengthAwarePaginator
    {
        $query = $this->requirementQuery($filters, $progress);
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $sort = $filters['sort'] ?? 'work_order';
        $order = $this->headerExpression(['acLnkKeyView','acLnkKey'], "''");
        $position = $this->headerExpression(['anLnkNo'], 'i.anNo');
        if (in_array($sort,['order','position'],true) && $table = $this->orderLinkTable()) {
            $link = $this->db()->table($table.' as sort_links')->whereColumn('sort_links.acKey','i.acKey')->where('sort_links.acLnkKey','<>','')->orderBy('sort_links.acLnkKey')->orderBy('sort_links.anLnkNo')->limit(1);
            if ($sort === 'order') {
                $field = in_array('acLnkKeyView',$this->columns($table),true) ? 'COALESCE(sort_links.acLnkKeyView,sort_links.acLnkKey)' : 'sort_links.acLnkKey';
                $query->orderByRaw('COALESCE((' . $link->selectRaw($field)->toSql() . "), $order) $direction", $link->getBindings());
            } else $query->orderByRaw('COALESCE((' . $link->select('sort_links.anLnkNo')->toSql() . "), $position) $direction", $link->getBindings());
        } else {
            $expression = match ($sort) {
                'material'=>'i.acIdent', 'required'=>$this->quantityExpression(), 'required_date'=>$this->headerExpression([config('procurement.schedule_field','adSchedStartTime')]),
                'order'=>$order, 'position'=>$position, default=>$this->headerExpression(['acKeyView','acRefNo1'],'i.acKey'),
            };
            $query->orderByRaw($expression . ' ' . $direction);
        }
        $query->orderBy('i.acKey')->orderBy('i.anNo');
        $page = max(1,(int)($filters['page'] ?? 1));
        $length = (int)($filters['per_page'] ?? 10);
        $length = in_array($length,[10,25,50,100],true) ? $length : 10;
        if (!empty($filters['_all'])) {
            // Explicitly selected RN allocations are needed in full when preparing their document.
            $raw = $query->get();
            $rows = new LengthAwarePaginator($raw,$raw->count(),max(1,$raw->count()),1);
        } else $rows = $query->paginate($length,['*'],'page',$page)->withQueryString();
        $this->primeLinks($rows->getCollection());
        $today = CarbonImmutable::now(config('app.timezone'))->format('Y-m-d');
        $rows->setCollection($rows->getCollection()->map(function ($raw) use ($progress,$today) {
            $item = $this->map((array)$raw);
            $item += $progress[$item['id']] ?? ['ordered'=>'0','received'=>'0','purchases'=>[],'suppliers'=>[],'late'=>false];
            $item['remaining'] = bccomp($item['required'],$item['ordered'],6)>0 ? bcsub($item['required'],$item['ordered'],6) : '0';
            $item['missing'] = bccomp($item['required'],$item['received'],6)>0 ? bcsub($item['required'],$item['received'],6) : '0';
            $item['state'] = bccomp($item['required'],'0',6)<=0 ? 'zero' : (bccomp($item['received'],$item['required'],6)>=0 ? 'received' : (bccomp($item['received'],'0',6)>0 ? 'partial' : (bccomp($item['ordered'],'0',6)>0 ? (bccomp($item['remaining'],'0',6)>0 ? 'part_ordered' : 'ordered') : 'not_ordered')));
            $item['late'] = $item['late'] || (bccomp($item['missing'],'0',6)>0 && $item['required_date'] && $item['required_date']<$today);
            return $item;
        }));
        return $rows;
    }

    public function stock(array $codes): array
    {
        $table = $this->table('stock_table', 'tHE_Stock');
        if ($this->columns($table) === []) return [];
        $stock = [];
        foreach (array_chunk(array_values(array_unique($codes)), 500) as $chunk) {
            foreach ($this->db()->table($table)->where('acWarehouse', config('procurement.warehouse'))->whereIn('acIdent', $chunk)
                ->select('acIdent')->selectRaw('SUM(anStock) as quantity')->groupBy('acIdent')->get() as $row) {
                $stock[trim($row->acIdent)] = WorkOrderClosingCalculator::decimal($row->quantity);
            }
        }
        return $stock;
    }

    public function catalog(string $search = '', bool $exact = false): array
    {
        $this->linkCache['catalog'] = [];
        $query = $this->db()->table($this->table('catalog_items_table', 'tHE_SetItem'))->whereIn('acSetOfItem', self::MATERIAL_SETS);
        if ($exact) $query->where('acIdent', $search);
        elseif ($search !== '') $query->where(fn ($q) => $q->where('acIdent', 'like', '%'.$search.'%')->orWhere('acName', 'like', '%'.$search.'%'));
        return $query->orderBy('acIdent')->limit(100)->get()->map(function ($row) {
            $raw = (array) $row;
            $mapped = $this->map(['acKey'=>'catalog','anNo'=>0,'acIdent'=>$row->acIdent,'acDescr'=>$row->acName,'acUM'=>$row->acUM ?? '']
                + collect($raw)->mapWithKeys(fn ($v,$k) => ['catalog_'.$k=>$v])->all());
            $mapped['id'] = 'catalog_'.base64_encode(trim($row->acIdent));
            $mapped['stock_order'] = true;
            return $mapped;
        })->all();
    }

    public function releases(array $purchase): array
    {
        $qids = [];
        foreach ($purchase['items'] as $item) foreach ($item['allocations'] ?? [['source'=>$item['source']]] as $allocation) {
            if (empty($allocation['source']['stock_order']) && !empty($allocation['source']['item_qid'])) $qids[] = $allocation['source']['item_qid'];
        }
        if (!$qids) return [];
        $schema = config('workorders.schema','dbo');
        $links = $schema.'.tHF_LinkMoveItemWOExItem';
        if ($this->columns($links) === []) return [];
        return $this->db()->table($links.' as l')->join($schema.'.tHE_Move as m','m.acKey','=','l.acKey')
            ->join($this->table('move_items_table','tHE_MoveItem').' as i',fn($j)=>$j->on('i.acKey','=','l.acKey')->on('i.anNo','=','l.anNo'))
            ->whereIn('l.anWOExItemQid',array_unique($qids))->where('m.acDocType','6400')->where('m.adDate','>=',substr($purchase['created_at'],0,10))
            ->select('m.acKey','m.adDate','i.anNo','i.acIdent','i.anQty','i.acUM','l.anWOExItemQid')->distinct()->orderBy('m.adDate')->get()
            ->map(fn($row)=>(array)$row)->all();
    }

    protected function links(array $row): array
    {
        $key = trim((string) $row['acKey']);
        if (isset($this->linkCache[$key])) {
            if ($this->linkCache[$key] !== []) return $this->linkCache[$key];
            return !empty($row['header_acLnkKey']) ? [['order_key'=>trim($row['header_acLnkKey']), 'order_number'=>trim($row['header_acLnkKeyView'] ?? $row['header_acLnkKey']), 'position'=>(string)($row['header_anLnkNo'] ?? '')]] : [];
        }
        $this->orderLinkTable();
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

    public function supplierDetails(string $supplier): array
    {
        $table = config('workorders.schema','dbo').'.tHE_SetSubj';
        $fields = array_values(array_intersect($this->columns($table), ['acSubject','acName2','acAddress','acPost','acCity','acCountry','acCode','acJIBCode','acRegNo','acPhone']));
        if (!$fields) return [];
        $row = (array) ($this->db()->table($table)->where('acSubject',$supplier)->first($fields) ?? []);
        if (!$row) return [];
        $value = fn ($field) => trim((string) ($row[$field] ?? ''));
        $post = $value('acPost');
        $city = $value('acCity');
        $posts = config('workorders.schema','dbo').'.tHE_SetPostCode';
        if ($post !== '' && $city === '' && in_array('acName',$this->columns($posts),true)) {
            $city = trim((string) $this->db()->table($posts)->where('acPost',$post)->value('acName'));
        }
        $postal = trim($post.' '.$city);
        if ($city !== '' && str_contains(mb_strtolower($value('acAddress')),mb_strtolower($city))) $postal = '';
        return ['name'=>$value('acName2'),'address'=>$value('acAddress'),'postal_line'=>$postal,'country'=>$value('acCountry'),
            'jib'=>$value('acJIBCode'),'vat'=>$value('acCode'),'registration'=>$value('acRegNo'),'phone'=>$value('acPhone')];
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
