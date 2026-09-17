<?php

namespace App\Http\Controllers;

use App\Services\WorkOrder\DeliveryPriorityOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionPlanController extends Controller
{
    private function admin($user): bool
    {
        return $user && method_exists($user, 'isAdmin')
            ? $user->isAdmin()
            : strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }

    public function index(Request $request)
    {
        $priorityOptions = app(DeliveryPriorityOptions::class)->all();

        return view('content.apps.production.app-production-plan', [
            'pageConfigs' => ['pageHeader' => false],
            'planConfig' => [
                'dataUrl' => route('app-production-plan-data'),
                'operationsUrl' => route('app-production-plan-operations', ['id' => '__RN__']),
                'fieldUrl' => route('app-production-plan-field', ['id' => '__RN__']),
                'exportUrl' => route('app-production-plan-export'),
                'previewUrl' => route('app-invoice-preview', ['id' => '__RN__']),
                'canEdit' => $this->admin($request->user()),
                'priorityOptions' => $priorityOptions,
            ],
        ]);
    }

    public function data(Request $request)
    {
        try {
            $filters = (array) $request->input('filter', []);
            $schema = config('workorders.schema', 'dbo');
            $status = "UPPER(LTRIM(RTRIM(wo.acStatusMF)))";

            $query = DB::table($schema . '.tHF_WOEx as wo')
                ->leftJoin($schema . '.tHE_SetDeliveryPriority as priority', 'priority.anPriority', '=', 'wo.anPriority')
                ->selectRaw("wo.acKey id, wo.acKeyView rn, ISNULL(wo.acConsignee, wo.acReceiver) narucitelj, COALESCE(NULLIF(LTRIM(RTRIM(priority.acName)), ''), N'Nedefinisan') prioritet, CAST(wo.adDate AS date) datum, wo.acLnkKeyView narudzba, wo.anLnkNo pozicija, wo.adSchedStartTime pocetak, wo.adSchedEndTime kraj, wo.acIdent proizvod, wo.anPlanQty plan_kol, wo.anProducedQty izr_kol, wo.acName naziv, wo.acNote napomena, $status status_code, CASE $status WHEN 'D' THEN N'Raspisan' WHEN 'O' THEN N'Otvoren' WHEN 'E' THEN N'U radu' WHEN 'P' THEN N'U toku' WHEN 'R' THEN N'Djelomično zaključen' WHEN 'F' THEN N'Zaključen' WHEN 'I' THEN N'Zaključen' WHEN 'Z' THEN N'Zaključen' WHEN 'N' THEN N'Novo' WHEN 'C' THEN N'Otkazano' ELSE N'Nedefinisan' END status_rn, CASE $status WHEN 'F' THEN 'green' WHEN 'I' THEN 'green' WHEN 'Z' THEN 'green' WHEN 'E' THEN 'yellow' WHEN 'P' THEN 'orange' WHEN 'D' THEN 'orange' WHEN 'S' THEN 'orange' WHEN 'O' THEN 'purple' WHEN 'N' THEN 'purple' WHEN 'R' THEN 'teal' WHEN 'C' THEN 'grey' ELSE 'red' END plan_row_color");

            $query->addSelect(DB::raw("CASE wo.anPriority WHEN 1 THEN 'red' WHEN 5 THEN 'yellow' WHEN 7 THEN 'teal' WHEN 10 THEN 'green' WHEN 15 THEN 'purple' ELSE 'grey' END AS priority_row_color"));

            $filterMap = [
                'rn' => 'wo.acKeyView',
                'narucitelj' => 'wo.acConsignee',
                'proizvod' => 'wo.acIdent',
                'status_rn' => 'wo.acStatusMF',
                'narudzba' => 'wo.acLnkKeyView',
            ];

            foreach ($filterMap as $key => $column) {
                $value = trim((string) ($filters[$key] ?? ''));
                if ($value !== '') {
                    $query->where($column, 'like', '%' . $value . '%');
                }
            }

            $search = trim((string) $request->input('search.value', ''));
            if ($search !== '') {
                $like = '%' . $search . '%';
                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery->where('wo.acKeyView', 'like', $like)
                        ->orWhere('wo.acConsignee', 'like', $like)
                        ->orWhere('wo.acReceiver', 'like', $like)
                        ->orWhere('priority.acName', 'like', $like)
                        ->orWhere('wo.acLnkKeyView', 'like', $like)
                        ->orWhere('wo.acIdent', 'like', $like)
                        ->orWhere('wo.acName', 'like', $like)
                        ->orWhere('wo.acNote', 'like', $like)
                        ->orWhere('wo.acStatusMF', 'like', $like)
                        ->orWhereRaw('CONVERT(char(10), wo.adDate, 104) LIKE ?', [$like])
                        ->orWhereRaw('CONVERT(char(10), wo.adSchedStartTime, 104) LIKE ?', [$like])
                        ->orWhereRaw('CONVERT(char(10), wo.adSchedEndTime, 104) LIKE ?', [$like])
                        ->orWhereRaw('CAST(wo.anLnkNo AS nvarchar(50)) LIKE ?', [$like])
                        ->orWhereRaw('CAST(wo.anPlanQty AS nvarchar(50)) LIKE ?', [$like])
                        ->orWhereRaw('CAST(wo.anProducedQty AS nvarchar(50)) LIKE ?', [$like]);
                });
            }

            $priority = trim((string) ($filters['prioritet'] ?? ''));
            if ($priority !== '' && ctype_digit($priority)) {
                $query->where('wo.anPriority', (int) $priority);
            }

            foreach ([['datum_od', '>='], ['datum_do', '<=']] as [$key, $operator]) {
                $value = trim((string) ($filters[$key] ?? ''));
                if ($value !== '') {
                    $query->whereDate('wo.adSchedStartTime', $operator, $value);
                }
            }

            $week = (int) ($filters['kw'] ?? 0);
            $year = (int) ($filters['year'] ?? now()->year);
            if ($year <= 0) {
                $year = now()->year;
            }

            if ($week) {
                $weekRange = $this->applyWeekFilter($query, $year, $week);
            } else {
                $query->whereYear('wo.adSchedStartTime', $year);
            }

            $total = (clone $query)->count();
            $sorts = [
                'rn' => 'wo.acKeyView', 'narucitelj' => 'wo.acConsignee', 'prioritet' => 'wo.anPriority',
                'datum' => 'wo.adDate', 'narudzba' => 'wo.acLnkKeyView', 'pozicija' => 'wo.anLnkNo',
                'pocetak' => 'wo.adSchedStartTime', 'kraj' => 'wo.adSchedEndTime', 'proizvod' => 'wo.acIdent',
                'plan_kol' => 'wo.anPlanQty', 'izr_kol' => 'wo.anProducedQty', 'naziv' => 'wo.acName',
                'napomena' => 'wo.acNote',
            ];
            $sort = $sorts[$request->input('sort', 'pocetak')] ?? 'wo.adSchedStartTime';
            $direction = $request->input('dir') === 'asc' ? 'asc' : 'desc';
            if (isset($weekRange) && $sort === 'wo.adSchedStartTime' && $direction === 'desc') {
                $query->orderByRaw(
                    "CASE WHEN CAST(wo.adSchedStartTime AS date) >= ? AND CAST(wo.adSchedStartTime AS date) <= ? AND UPPER(LTRIM(RTRIM(wo.acStatusMF))) NOT IN ('F', 'I', 'Z') THEN 0 ELSE 1 END",
                    [$weekRange['previous_start']->toDateString(), $weekRange['previous_end']->toDateString()]
                );
            }

            $rows = $query->orderBy($sort, $direction)
                ->offset(max(0, (int) $request->input('start', 0)))
                ->limit(min(100, max(10, (int) $request->input('length', 25))))
                ->get();

            $keys = $rows->pluck('id')->filter()->values();
            $totalOperations = $keys->isEmpty() ? collect() : DB::table($schema . '.tHF_WOExRegOper')
                ->whereIn('acKey', $keys)
                ->selectRaw('acKey, COUNT(*) n')
                ->groupBy('acKey')
                ->pluck('n', 'acKey');
            $finishedOperations = $keys->isEmpty() ? collect() : DB::table($schema . '.tHF_WOExItem')
                ->whereIn('acKey', $keys)
                ->whereIn('acTaskState', ['F', 'Z', 'C', 'D'])
                ->selectRaw('acKey, COUNT(*) n')
                ->groupBy('acKey')
                ->pluck('n', 'acKey');

            foreach ($rows as $row) {
                $row->is_previous_week_open_order = isset($weekRange)
                    && $this->isPreviousWeekOpenOrder($row, $weekRange['previous_start'], $weekRange['previous_end']);
                if ($row->is_previous_week_open_order) {
                    $row->plan_row_color = 'red';
                }
                $row->progress = ($totalOperations[$row->id] ?? 0)
                    ? min(100, round(($finishedOperations[$row->id] ?? 0) / $totalOperations[$row->id] * 100))
                    : 0;
            }

            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $rows,
            ]);
        } catch (\Throwable $exception) {
            Log::error('RN plan error', ['message' => $exception->getMessage()]);

            return response()->json(['message' => 'Greška pri učitavanju plana proizvodnje.'], 500);
        }
    }

    /**
     * Include the selected ISO week and unfinished orders from the previous ISO week.
     *
     * @return array{previous_start: Carbon, previous_end: Carbon}
     */
    private function applyWeekFilter($query, int $year, int $week): array
    {
        $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $previousWeekStart = $weekStart->copy()->subWeek()->startOfWeek();
        $previousWeekEnd = $previousWeekStart->copy()->endOfWeek();
        $status = DB::raw("UPPER(LTRIM(RTRIM(wo.acStatusMF)))");

        $query->where(function ($scheduleQuery) use ($weekStart, $weekEnd, $previousWeekStart, $previousWeekEnd, $status) {
            $scheduleQuery->where(function ($currentWeekQuery) use ($weekStart, $weekEnd) {
                $currentWeekQuery->whereDate('wo.adSchedStartTime', '>=', $weekStart)
                    ->whereDate('wo.adSchedStartTime', '<=', $weekEnd);
            })->orWhere(function ($previousWeekQuery) use ($previousWeekStart, $previousWeekEnd, $status) {
                $previousWeekQuery->whereDate('wo.adSchedStartTime', '>=', $previousWeekStart)
                    ->whereDate('wo.adSchedStartTime', '<=', $previousWeekEnd)
                    ->whereNotIn($status, ['F', 'I', 'Z']);
            });
        });

        return ['previous_start' => $previousWeekStart, 'previous_end' => $previousWeekEnd];
    }

    private function isPreviousWeekOpenOrder($row, Carbon $previousWeekStart, Carbon $previousWeekEnd): bool
    {
        if (in_array(strtoupper(trim((string) ($row->status_code ?? ''))), ['F', 'I', 'Z'], true) || empty($row->pocetak)) {
            return false;
        }

        $scheduledStart = Carbon::parse($row->pocetak);

        return $scheduledStart->greaterThanOrEqualTo($previousWeekStart)
            && $scheduledStart->lessThanOrEqualTo($previousWeekEnd);
    }

    public function export(Request $request)
    {
        try {
            $filtered = $request->input('scope', 'filtered') !== 'all';
            $filters = $filtered ? (array) $request->input('filter', []) : [];
            $schema = config('workorders.schema', 'dbo');
            $status = "UPPER(LTRIM(RTRIM(wo.acStatusMF)))";

            $query = DB::table($schema . '.tHF_WOEx as wo')
                ->leftJoin($schema . '.tHE_SetDeliveryPriority as priority', 'priority.anPriority', '=', 'wo.anPriority')
                ->selectRaw("wo.acKey id, wo.acKeyView rn, ISNULL(wo.acConsignee, wo.acReceiver) narucitelj, COALESCE(NULLIF(LTRIM(RTRIM(priority.acName)), ''), N'Nedefinisan') prioritet, CAST(wo.adDate AS date) datum, wo.acLnkKeyView narudzba, wo.anLnkNo pozicija, wo.adSchedStartTime pocetak, wo.adSchedEndTime kraj, wo.acIdent proizvod, wo.anPlanQty plan_kol, wo.anProducedQty izr_kol, wo.acName naziv, wo.acNote napomena, $status status_code")
                ->addSelect(DB::raw("CASE wo.anPriority WHEN 1 THEN 'red' WHEN 5 THEN 'yellow' WHEN 7 THEN 'teal' WHEN 10 THEN 'green' WHEN 15 THEN 'purple' ELSE 'grey' END AS priority_row_color"));

            if ($filtered) {
                $filterMap = [
                    'rn' => 'wo.acKeyView', 'narucitelj' => 'wo.acConsignee', 'proizvod' => 'wo.acIdent',
                    'status_rn' => 'wo.acStatusMF', 'narudzba' => 'wo.acLnkKeyView',
                ];

                foreach ($filterMap as $key => $column) {
                    $value = trim((string) ($filters[$key] ?? ''));
                    if ($value !== '') {
                        $query->where($column, 'like', '%' . $value . '%');
                    }
                }

                $priority = trim((string) ($filters['prioritet'] ?? ''));
                if ($priority !== '' && ctype_digit($priority)) {
                    $query->where('wo.anPriority', (int) $priority);
                }

                foreach ([['datum_od', '>='], ['datum_do', '<=']] as [$key, $operator]) {
                    $value = trim((string) ($filters[$key] ?? ''));
                    if ($value !== '') {
                        $query->whereDate('wo.adSchedStartTime', $operator, $value);
                    }
                }

                $year = (int) ($filters['year'] ?? now()->year);
                if ($year <= 0) {
                    $year = now()->year;
                }

                $week = (int) ($filters['kw'] ?? 0);
                if ($week) {
                    $weekRange = $this->applyWeekFilter($query, $year, $week);
                } else {
                    $query->whereYear('wo.adSchedStartTime', $year);
                }
            }

            $sorts = ['rn' => 'wo.acKeyView', 'narucitelj' => 'wo.acConsignee', 'prioritet' => 'wo.anPriority', 'datum' => 'wo.adDate', 'narudzba' => 'wo.acLnkKeyView', 'pozicija' => 'wo.anLnkNo', 'pocetak' => 'wo.adSchedStartTime', 'kraj' => 'wo.adSchedEndTime', 'proizvod' => 'wo.acIdent', 'plan_kol' => 'wo.anPlanQty', 'izr_kol' => 'wo.anProducedQty', 'naziv' => 'wo.acName', 'napomena' => 'wo.acNote'];
            $sort = $sorts[$request->input('sort', 'datum')] ?? 'wo.adDate';
            $direction = $request->input('dir') === 'asc' ? 'asc' : 'desc';
            $rows = $query->orderBy($sort, $direction)->get();

            $keys = $rows->pluck('id')->filter()->values();
            $totalOperations = collect();
            $finishedOperations = collect();
            foreach ($keys->chunk(2000) as $keyChunk) {
                $totalOperations = $totalOperations->replace(
                    DB::table($schema . '.tHF_WOExRegOper')->whereIn('acKey', $keyChunk)->selectRaw('acKey, COUNT(*) n')->groupBy('acKey')->pluck('n', 'acKey')
                );
                $finishedOperations = $finishedOperations->replace(
                    DB::table($schema . '.tHF_WOExItem')->whereIn('acKey', $keyChunk)->whereIn('acTaskState', ['F', 'Z', 'C', 'D'])->selectRaw('acKey, COUNT(*) n')->groupBy('acKey')->pluck('n', 'acKey')
                );
            }
            foreach ($rows as $row) {
                $row->is_previous_week_open_order = isset($weekRange)
                    && $this->isPreviousWeekOpenOrder($row, $weekRange['previous_start'], $weekRange['previous_end']);
                $row->progress = ($totalOperations[$row->id] ?? 0) ? min(100, round(($finishedOperations[$row->id] ?? 0) / $totalOperations[$row->id] * 100)) : 0;
            }

            $includeColours = $request->boolean('include_colours', true);
            $includeFilterSummary = $request->boolean('include_filter_summary', true);
            $filename = 'plan-proizvodnje-' . now()->format('Y-m-d-His') . '.xls';

            return response($this->excelXml($rows, $filters, $filtered, $includeColours, $includeFilterSummary), 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Throwable $exception) {
            Log::error('Production plan export failed', ['message' => $exception->getMessage()]);
            abort(500, 'Izvoz plana proizvodnje nije uspio.');
        }
    }

    private function excelXml($rows, array $filters, bool $filtered, bool $includeColours, bool $includeFilterSummary): string
    {
        $escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $cell = static fn ($value, string $style = ''): string => '<Cell' . ($style ? ' ss:StyleID="' . $style . '"' : '') . '><Data ss:Type="String">' . htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</Data></Cell>';
        $styles = '<Styles><Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#283046" ss:Pattern="Solid"/></Style><Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14" ss:Color="#283046"/></Style><Style ss:ID="red"><Interior ss:Color="#FFD6DC" ss:Pattern="Solid"/></Style><Style ss:ID="yellow"><Interior ss:Color="#FFF0A3" ss:Pattern="Solid"/></Style><Style ss:ID="teal"><Interior ss:Color="#BCEFE5" ss:Pattern="Solid"/></Style><Style ss:ID="green"><Interior ss:Color="#C5F1D2" ss:Pattern="Solid"/></Style><Style ss:ID="purple"><Interior ss:Color="#E8D4FF" ss:Pattern="Solid"/></Style><Style ss:ID="grey"><Interior ss:Color="#DDE2E8" ss:Pattern="Solid"/></Style></Styles>';
        $xml = '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . $styles . '<Worksheet ss:Name="Plan proizvodnje"><Table>';
        $xml .= '<Row><Cell ss:StyleID="Title"><Data ss:Type="String">Plan proizvodnje - radni nalozi</Data></Cell></Row>';

        if ($includeFilterSummary) {
            $summary = $filtered ? $this->filterSummary($filters) : 'Kompletan plan - svi radni nalozi';
            $xml .= '<Row><Cell><Data ss:Type="String">Filteri: ' . $escape($summary) . '</Data></Cell></Row><Row></Row>';
        }

        $headers = ['Napredak', 'RN', 'Naručitelj', 'Prioritet', 'Datum', 'Narudžba', 'Br. poz.', 'Poč. termin', 'Kraj termin', 'Proizvod', 'Plan. kol.', 'Izr. kol.', 'Naziv', 'Napomena', 'Status RN'];
        $xml .= '<Row>' . implode('', array_map(fn ($header) => $cell($header, 'Header'), $headers)) . '</Row>';
        foreach ($rows as $row) {
            $style = $includeColours
                ? ($row->is_previous_week_open_order ?? false ? 'red' : (string) $row->priority_row_color)
                : '';
            $values = [$row->progress . '%', $row->rn, $row->narucitelj, $row->prioritet, $row->datum, $row->narudzba, $row->pozicija, $row->pocetak, $row->kraj, $row->proizvod, $row->plan_kol, $row->izr_kol, $row->naziv, $row->napomena, $row->status_code];
            $xml .= '<Row>' . implode('', array_map(fn ($value) => $cell($value, $style), $values)) . '</Row>';
        }

        return $xml . '</Table></Worksheet></Workbook>';
    }

    private function filterSummary(array $filters): string
    {
        $labels = ['rn' => 'RN', 'narucitelj' => 'Naručitelj', 'prioritet' => 'Prioritet', 'proizvod' => 'Proizvod', 'status_rn' => 'Status RN', 'narudzba' => 'Narudžba', 'year' => 'Godina', 'kw' => 'Kalendarska sedmica', 'datum_od' => 'Datum od', 'datum_do' => 'Datum do'];
        $parts = [];
        foreach ($labels as $key => $label) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $label . ': ' . $value;
            }
        }

        return $parts ? implode('; ', $parts) : 'Nema aktivnih filtera';
    }

    public function updateField(Request $request, string $id)
    {
        if (!$this->admin($request->user())) {
            return response()->json(['message' => 'Nemate dozvolu za izmjene.'], 403);
        }

        $map = [
            'narucitelj' => 'acConsignee', 'datum' => 'adDate', 'narudzba' => 'acLnkKeyView',
            'pozicija' => 'anLnkNo', 'pocetak' => 'adSchedStartTime', 'kraj' => 'adSchedEndTime',
            'proizvod' => 'acIdent', 'plan_kol' => 'anPlanQty', 'izr_kol' => 'anProducedQty',
            'naziv' => 'acName', 'napomena' => 'acNote',
        ];
        $field = (string) $request->input('field');
        if (!isset($map[$field])) {
            return response()->json(['message' => 'Polje nije dozvoljeno.'], 422);
        }

        DB::table(config('workorders.schema', 'dbo') . '.tHF_WOEx')
            ->where('acKey', $id)
            ->update([
                $map[$field] => $request->input('value'),
                'adTimeChg' => now(),
                'anUserChg' => (int) $request->user()->id,
            ]);

        return response()->json(['message' => 'Sačuvano.']);
    }

    public function operations(string $id)
    {
        return response()->json(['data' => []]);
    }
}
