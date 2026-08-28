<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionPlanController extends Controller
{
    private const COLUMNS = [
        'broj_narudzbenice' => 'Broj narudžbenice', 'kupac' => 'Kupac',
        'narudzbenica_kupca' => 'Narudžbenica kupca', 'broj_pozicije' => 'Br. poz.',
        'sifra_artikla' => 'Šifra artikla', 'naziv' => 'Naziv', 'naruceno' => 'Naručeno',
        'izradeno' => 'Razlika', 'datum_isporuke' => 'Datum isporuke',
        'datum_narudzbe' => 'Datum narudžbe', 'dobavljac' => 'Dobavljač',
        'status_dobavljaca' => 'Status dobavljača', 'status_rn' => 'Status RN',
        'faza_izrade' => 'Faza izrade',
    ];

    public function index()
    {
        return view('content.apps.production.app-production-plan', [
            'pageConfigs' => ['pageHeader' => false],
            'planConfig' => [
                'dataUrl' => route('app-production-plan-data'),
                'exportUrl' => route('app-production-plan-export'),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        try {
            $limit = min(100, max(10, (int) $request->input('length', 25)));
            $start = max(0, (int) $request->input('start', 0));
            $filters = $this->filters($request);
            $query = $this->planQuery($filters);
            $total = (clone $query)->count();
            $sort = array_key_exists($request->input('sort'), self::COLUMNS) ? $request->input('sort') : 'datum_isporuke';
            $direction = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $rows = $this->sort($query, $sort, $direction)->offset($start)->limit($limit)->get();

            return response()->json([
                'draw' => (int) $request->input('draw', 0), 'recordsTotal' => $total,
                'recordsFiltered' => $total, 'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            Log::error('Učitavanje plana proizvodnje nije uspjelo.', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Plan proizvodnje se trenutno ne može učitati.', 'data' => []], 500);
        }
    }

    public function export(Request $request)
    {
        $scope = $request->input('opseg') === 'redovi' ? 'redovi' : 'cijela_lista';
        $applyFilters = $request->boolean('primijeni_filter');
        $from = max(1, (int) $request->input('pocetni_red', 1));
        $to = max($from, (int) $request->input('zavrsni_red', $from));
        $to = min($to, 50000);
        $filters = $applyFilters ? $this->filters($request) : [];
        $query = $this->sort($this->planQuery($filters), 'datum_isporuke', 'desc');

        if ($scope === 'redovi') {
            $query->offset($from - 1)->limit($to - $from + 1);
        }

        $rows = $query->get();
        $fileName = 'plan-proizvodnje-' . now()->format('Y-m-d-His') . '.xls';
        return response($this->excelXml($rows), 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    private function planQuery(array $filters): Builder
    {
        $schema = config('workorders.schema', 'dbo');
        $query = DB::table($schema . '.tHE_OrderItem as oi')
            ->join($schema . '.tHE_Order as ord', 'ord.acKey', '=', 'oi.acKey')
            ->leftJoinSub($this->workOrderPositionQuery(), 'wo', function ($join) {
                $join->on('wo.order_key', '=', 'oi.acKey')
                    ->on('wo.order_position', '=', 'oi.anNo');
            })
            ->leftJoinSub($this->incomingDocumentByOrderItemQuery(), 'ulazni', function ($join) {
                $join->on('ulazni.order_item_qid', '=', 'oi.anQId');
            })
            ->selectRaw("LTRIM(RTRIM(ISNULL(ord.acKeyView, ord.acKey))) as broj_narudzbenice")
            ->selectRaw("LTRIM(RTRIM(ISNULL(ord.acConsignee, ord.acReceiver))) as kupac")
            ->selectRaw("LTRIM(RTRIM(ISNULL(ord.acDoc1, ''))) as narudzbenica_kupca")
            ->selectRaw("CAST(ISNULL(oi.anNo, 0) AS varchar(20)) as broj_pozicije")
            ->selectRaw("LTRIM(RTRIM(ISNULL(oi.acIdent, ''))) as sifra_artikla")
            ->selectRaw("LTRIM(RTRIM(ISNULL(oi.acName, ''))) as naziv")
            ->selectRaw('ISNULL(oi.anQty, 0) as naruceno')
            // Pantheonova kolona "Raz." je preostala količina: naručeno minus otpremljeno.
            ->selectRaw('ISNULL(oi.anQty, 0) - ISNULL(oi.anQtyDispDoc, 0) as izradeno')
            ->selectRaw('CAST(COALESCE(oi.adDeliveryDeadline, oi.adDeliveryDate, ord.adDeliveryDeadline) AS date) as datum_isporuke')
            ->selectRaw('CAST(ord.adDate AS date) as datum_narudzbe')
            ->selectRaw("LTRIM(RTRIM(COALESCE(NULLIF(oi.acDept, ''), NULLIF(ord.acDept, ''), ''))) as dobavljac")
            ->selectRaw("ISNULL(ulazni.broj_dokumenta, '') as status_dobavljaca")
            ->selectRaw("ISNULL(wo.status_rn, '') as status_rn")
            ->selectRaw("ISNULL(wo.faza_izrade, '') as faza_izrade");

        $filterExpressions = $this->filterExpressions();
        $exactNumberColumns = [
            'broj_pozicije' => 'oi.anNo',
            'naruceno' => 'oi.anQty',
            'izradeno' => 'ISNULL(oi.anQty, 0) - ISNULL(oi.anQtyDispDoc, 0)',
        ];
        foreach (self::COLUMNS as $key => $label) {
            $value = trim((string) ($filters[$key] ?? ''));

            if ($value !== '' && isset($exactNumberColumns[$key])) {
                $numericValue = str_replace(',', '.', $value);

                if (is_numeric($numericValue)) {
                    $query->whereRaw($exactNumberColumns[$key] . ' = ?', [(float) $numericValue]);
                } else {
                    $query->whereRaw('1 = 0');
                }

                continue;
            }

            if ($value !== '' && isset($filterExpressions[$key])) {
                $query->whereRaw($filterExpressions[$key] . ' like ?', ['%' . $value . '%']);
            }
        }
        $this->dateFilter($query, 'COALESCE(oi.adDeliveryDeadline, oi.adDeliveryDate, ord.adDeliveryDeadline)', $filters['datum_isporuke_od'] ?? '', $filters['datum_isporuke_do'] ?? '');
        $this->dateFilter($query, 'ord.adDate', $filters['datum_narudzbe_od'] ?? '', $filters['datum_narudzbe_do'] ?? '');
        if (($search = trim((string) ($filters['pretraga'] ?? ''))) !== '') {
            $digitsOnlySearch = preg_replace('/\D+/', '', $search);

            $query->where(function ($q) use ($search, $digitsOnlySearch, $filterExpressions) {
                foreach ($filterExpressions as $expression) {
                    $q->orWhereRaw($expression . ' like ?', ['%' . $search . '%']);
                }

                if ($digitsOnlySearch !== '') {
                    $q->orWhereRaw("REPLACE(REPLACE(ISNULL(ord.acKeyView, ''), '-', ''), ' ', '') like ?", ['%' . $digitsOnlySearch . '%'])
                        ->orWhereRaw("REPLACE(REPLACE(ISNULL(ord.acKey, ''), '-', ''), ' ', '') like ?", ['%' . $digitsOnlySearch . '%']);
                }
            });
        }
        return $query;
    }

    private function workOrderPositionQuery(): Builder
    {
        $schema = config('workorders.schema', 'dbo');
        $statusCode = "UPPER(LTRIM(RTRIM(ISNULL(work_order.acStatusMF, work_order.acStatus))))";

        return DB::table($schema . '.tHF_WOEx as work_order')
            ->selectRaw('work_order.acLnkKey as order_key')
            ->selectRaw('work_order.anLnkNo as order_position')
            // Koristi isto značenje šifri statusa kao Upravljanje nalozima.
            ->selectRaw("MAX($statusCode) as status_rn_kod")
            ->selectRaw("CASE MAX($statusCode)
                WHEN 'F' THEN N'Zaključen'
                WHEN 'P' THEN N'U toku'
                WHEN 'I' THEN N'Zaključen'
                WHEN 'N' THEN N'Novo'
                WHEN 'C' THEN N'Otkazano'
                WHEN 'D' THEN N'Raspisan'
                WHEN 'O' THEN N'Otvoren'
                WHEN 'R' THEN N'Djelomično zaključen'
                WHEN 'S' THEN N'Raspisan'
                WHEN 'E' THEN N'U radu'
                WHEN 'Z' THEN N'Zaključen'
                ELSE N'Nedefinisan status'
            END as status_rn")
            ->selectRaw("MAX(LTRIM(RTRIM(ISNULL(work_order.acCostDrv, '')))) as faza_izrade")
            ->whereNotNull('work_order.acLnkKey')
            ->groupBy('work_order.acLnkKey', 'work_order.anLnkNo');
    }

    private function incomingDocumentByOrderItemQuery(): Builder
    {
        $schema = config('workorders.schema', 'dbo');
        return DB::table($schema . '.tHE_LinkMoveItemOrderItem as link')
            ->join($schema . '.tHE_MoveItem as move_item', 'move_item.anQId', '=', 'link.anMoveItemQId')
            ->join($schema . '.tHE_Move as move', 'move.acKey', '=', 'move_item.acKey')
            ->where(function ($q) { $q->where('move.acDocType', '1OTV')->orWhere('move.acKeyView', 'like', '%-1OTV-%'); })
            ->selectRaw('link.anOrderItemQId as order_item_qid')
            ->selectRaw("STRING_AGG(LTRIM(RTRIM(move.acKeyView)), ', ') as broj_dokumenta")
            ->groupBy('link.anOrderItemQId');
    }

    private function filterExpressions(): array
    {
        return [
            'broj_narudzbenice' => "LTRIM(RTRIM(ISNULL(ord.acKeyView, ord.acKey)))",
            'kupac' => "LTRIM(RTRIM(ISNULL(ord.acConsignee, ord.acReceiver)))",
            'narudzbenica_kupca' => "LTRIM(RTRIM(ISNULL(ord.acDoc1, '')))",
            'broj_pozicije' => 'CAST(ISNULL(oi.anNo, 0) AS varchar(20))',
            'sifra_artikla' => "LTRIM(RTRIM(ISNULL(oi.acIdent, '')))",
            'naziv' => "LTRIM(RTRIM(ISNULL(oi.acName, '')))",
            'naruceno' => 'CAST(ISNULL(oi.anQty, 0) AS varchar(40))',
            'izradeno' => 'CAST(ISNULL(oi.anQty, 0) - ISNULL(oi.anQtyDispDoc, 0) AS varchar(40))',
            'dobavljac' => "LTRIM(RTRIM(COALESCE(NULLIF(oi.acDept, ''), NULLIF(ord.acDept, ''), '')))",
            'status_dobavljaca' => "ISNULL(ulazni.broj_dokumenta, '')",
            // Tekst se prikazuje korisniku, a šifra ostaje dostupna samo za pretragu/filter.
            'status_rn' => "CONCAT(ISNULL(wo.status_rn, ''), ' ', ISNULL(wo.status_rn_kod, ''))",
            'faza_izrade' => "ISNULL(wo.faza_izrade, '')",
        ];
    }

    private function filters(Request $request): array
    {
        $filters = $request->input('filter', []);
        $filters = is_array($filters) ? $filters : [];
        $tableSearch = trim((string) $request->input('brza_pretraga', $request->input('search.value', '')));

        if (trim((string) ($filters['pretraga'] ?? '')) === '' && $tableSearch !== '') {
            $filters['pretraga'] = $tableSearch;
        }

        return $filters;
    }
    private function dateFilter(Builder $query, string $column, string $from, string $to): void
    {
        if ($from !== '') $query->whereRaw('CAST(' . $column . ' AS date) >= ?', [$from]);
        if ($to !== '') $query->whereRaw('CAST(' . $column . ' AS date) <= ?', [$to]);
    }
    private function sort(Builder $query, string $column, string $direction): Builder
    {
        $expressions = array_merge($this->filterExpressions(), [
            'datum_isporuke' => 'COALESCE(oi.adDeliveryDeadline, oi.adDeliveryDate, ord.adDeliveryDeadline)',
            'datum_narudzbe' => 'ord.adDate',
        ]);
        $expression = $expressions[$column] ?? $expressions['datum_isporuke'];
        $direction = $direction === 'desc' ? 'DESC' : 'ASC';
        $emptyCondition = in_array($column, ['datum_isporuke', 'datum_narudzbe'], true)
            ? $expression . ' IS NULL'
            : $expression . " IS NULL OR " . $expression . " = ''";

        return $query
            ->orderByRaw('CASE WHEN ' . $emptyCondition . ' THEN 1 ELSE 0 END')
            ->orderByRaw($expression . ' ' . $direction)
            ->orderByRaw('oi.anQId ASC');
    }
    private function excelXml($rows): string
    {
        $esc = fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Plan proizvodnje"><Table><Row>';
        foreach (self::COLUMNS as $label) $xml .= '<Cell><Data ss:Type="String">' . $esc($label) . '</Data></Cell>';
        $xml .= '</Row>';
        foreach ($rows as $row) { $xml .= '<Row>'; foreach (array_keys(self::COLUMNS) as $key) $xml .= '<Cell><Data ss:Type="String">' . $esc($row->{$key} ?? '') . '</Data></Cell>'; $xml .= '</Row>'; }
        return $xml . '</Table></Worksheet></Workbook>';
    }
}
