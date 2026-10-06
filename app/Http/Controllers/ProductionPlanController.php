<?php

namespace App\Http\Controllers;

use App\Services\WorkOrder\DeliveryPriorityOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionPlanController extends Controller
{
    private function applyCreatorFilter($query, array $filters): void
    {
        $id = trim((string) ($filters['kreirao'] ?? ''));
        if ($id === '') return;
        $query->where('wo.anUserIns', (int) $id);
    }

    private function creatorNames($ids)
    {
        if ($ids->isEmpty()) return collect();
        $names = \App\Models\User::query()->whereIn('id', $ids)
            ->get(['id', 'name', 'username'])->mapWithKeys(fn ($user) => [
                $user->id => trim((string) $user->name) ?: trim((string) $user->username),
            ])->filter();
        $missing = $ids->reject(fn ($id) => $names->has($id))->values();
        if ($missing->isNotEmpty()) {
            $contacts = DB::table(config('workorders.schema', 'dbo') . '.tHE_SetSubjContact')
                ->whereIn('anUserID', $missing)->orderBy('anQId')
                ->get(['anUserID', 'acName', 'acSurname', 'acUserId']);
            foreach ($contacts as $contact) {
                if ($names->has($contact->anUserID)) continue;
                $name = trim(trim((string) $contact->acName) . ' ' . trim((string) $contact->acSurname));
                $name = $name ?: trim((string) $contact->acUserId);
                if ($name !== '') $names->put($contact->anUserID, $name);
            }
        }
        return $names;
    }

    private function creatorOptions()
    {
        $ids = DB::table(config('workorders.schema', 'dbo') . '.tHF_WOEx')
            ->distinct()->pluck('anUserIns')->filter()->unique()->values();
        $names = $this->creatorNames($ids);
        return $ids->map(fn ($id) => (object) [
            'id' => $id, 'name' => $names->get($id, 'Korisnik #' . $id), 'username' => '',
        ])->sortBy('name')->values();
    }

    private function attachCreators($rows): void
    {
        $names = $this->creatorNames($rows->pluck('creator_id')->filter()->unique()->values());
        foreach ($rows as $row) {
            $id = $row->creator_id ?? null;
            $row->kreirao = empty($id) ? '' : $names->get($id, 'Korisnik #' . $id);
        }
    }

    private function attachWeldingData($rows, string $schema): void
    {
        $keys = $rows->pluck('id')->filter()->values();
        $work = $keys->isEmpty() ? collect() : DB::table($schema . '.tHF_WOExItemWork as work')
            ->join($schema . '.tHF_WOExItem as item', 'item.anQId', '=', 'work.anWOExItemQid')
            ->join($schema . '.tHE_SetItem as catalog', 'catalog.acIdent', '=', 'work.acIdent')
            ->whereIn('item.acKey', $keys)
            ->where(function ($query) {
                $query->where('catalog.acName', 'like', '%zavarivanje%')
                    ->orWhere('catalog.acName', 'like', '%welding%');
            })
            ->get(['item.acKey', 'work.adDate', 'work.anTime', 'work.acWorker'])
            ->groupBy('acKey');
        $workerCodes = $work->flatten(1)->pluck('acWorker')->filter()->unique()->values();
        $workerNames = $workerCodes->isEmpty() ? collect() : DB::table($schema . '.tHR_Prsn')
            ->whereIn('acWorker', $workerCodes)
            ->selectRaw("acWorker, LTRIM(RTRIM(CONCAT(acName, ' ', acSurname))) AS name")
            ->pluck('name', 'acWorker');
        foreach ($rows as $row) {
            $entries = $work->get($row->id, collect());
            $row->datum_zavarivanja = $entries->max('adDate');
            $row->utroseno_vrijeme = $entries->isEmpty() ? null : $entries->sum('anTime');
            $row->zavarivac = $entries->pluck('acWorker')->filter()->unique()->map(fn ($code) => $workerNames->get($code) ?: $code)->implode(', ');
            // No confirmed source for the spreadsheet's material-order date.
            $row->materijal_narucen = null;
        }
    }

    private function admin($user): bool
    {
        return $user && method_exists($user, 'isAdmin')
            ? $user->isAdmin()
            : strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }

    public function index(Request $request)
    {
        $priorityOptions = app(DeliveryPriorityOptions::class)->all();
        $trendyGermanyNumberOptions = DB::table(config('workorders.schema', 'dbo') . '.tHF_WOEx')
            ->selectRaw('DISTINCT LTRIM(RTRIM(ISNULL(acConsignee, acReceiver))) AS customer')
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(acConsignee, acReceiver)))) LIKE 'TRENDY GERMANY%'")
            ->pluck('customer')
            ->map(function ($customer) {
                return preg_match('/-(\d+)\s*$/', trim((string) $customer), $matches) ? $matches[1] : null;
            })
            ->filter()
            ->unique()
            ->sortBy(fn ($number) => (int) $number)
            ->values()
            ->map(fn ($number) => ['code' => (string) $number, 'label' => '-' . $number]);
        $otherCustomerOptions = DB::table(config('workorders.schema', 'dbo') . '.tHF_WOEx')
            ->selectRaw('DISTINCT UPPER(LTRIM(RTRIM(ISNULL(acConsignee, acReceiver)))) AS customer')
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(acConsignee, acReceiver)))) NOT LIKE 'TRENDY GERMANY%'")
            ->orderBy('customer')->pluck('customer')->filter()
            ->map(fn ($customer) => ['code' => $customer, 'label' => $customer])->values();
        $statusLabels = [
            'D' => 'Raspisan', 'O' => 'Otvoren', 'E' => 'U radu', 'P' => 'U toku',
            'R' => 'Djelomično zaključen', 'F' => 'Zaključen', 'I' => 'Zaključen',
            'Z' => 'Zaključen', 'N' => 'Novo', 'C' => 'Otkazano', 'S' => 'Raspisan',
        ];
        $statusOptions = DB::table(config('workorders.schema', 'dbo') . '.tHF_WOEx')
            ->selectRaw('DISTINCT UPPER(LTRIM(RTRIM(acStatusMF))) AS code')
            ->whereNotNull('acStatusMF')
            ->whereRaw("LTRIM(RTRIM(acStatusMF)) <> ''")
            ->orderBy('code')
            ->pluck('code')
            ->map(fn ($code) => [
                'code' => $code,
                'label' => ($statusLabels[$code] ?? $code) . ' (' . $code . ')',
            ]);

        return view('content.apps.production.app-production-plan', [
            'pageConfigs' => ['pageHeader' => false],
            'planConfig' => [
                'dataUrl' => route('app-production-plan-data'),
                'operationsUrl' => route('app-production-plan-operations', ['id' => '__RN__']),
                'fieldUrl' => route('app-production-plan-field', ['id' => '__RN__']),
                'exportUrl' => route('app-production-plan-export'),
                'costDriverOptionsUrl' => route('app-invoice-protection-options', ['id' => '__RN__']),
                'previewUrl' => route('app-invoice-preview', ['id' => '__RN__']),
                'canEdit' => $this->admin($request->user()),
                'priorityOptions' => $priorityOptions,
                'trendyGermanyNumberOptions' => $trendyGermanyNumberOptions,
                'otherCustomerOptions' => $otherCustomerOptions,
                'statusOptions' => $statusOptions,
                'creatorOptions' => $this->creatorOptions(),
            ],
        ]);
    }

    public function data(Request $request)
    {
        try {
            $filters = (array) $request->input('filter', []);
            $schema = config('workorders.schema', 'dbo');
            $status = "UPPER(LTRIM(RTRIM(wo.acStatusMF)))";
            $deliveryDate = $this->deliveryDateExpression();

            $query = DB::table($schema . '.tHF_WOEx as wo')
                ->leftJoin($schema . '.tHE_SetDeliveryPriority as priority', 'priority.anPriority', '=', 'wo.anPriority')
                ->leftJoin($schema . '.tHE_Order as sales_order', 'sales_order.acKey', '=', 'wo.acLnkKey')
                ->leftJoin($schema . '.tHE_OrderItem as order_item', function ($join) {
                    $join->on('order_item.acKey', '=', 'wo.acLnkKey')->on('order_item.anNo', '=', 'wo.anLnkNo');
                })
                ->selectRaw("wo.acKey id, wo.acKeyView rn, ISNULL(wo.acConsignee, wo.acReceiver) narucitelj, COALESCE(NULLIF(LTRIM(RTRIM(priority.acName)), ''), N'Nedefinisan') prioritet, CAST(wo.adDate AS date) datum, wo.acLnkKey narudzba_key, wo.acLnkKeyView narudzba, sales_order.acDoc1 broj_narudzbe_kupca, wo.anLnkNo pozicija, wo.adSchedStartTime pocetak, wo.adSchedEndTime kraj, $deliveryDate datum_isporuke, wo.acIdent proizvod, wo.anPlanQty plan_kol, wo.anProducedQty izr_kol, wo.acName naziv, wo.acNote napomena, $status status_code, CASE $status WHEN 'D' THEN N'Raspisan' WHEN 'S' THEN N'Raspisan' WHEN 'O' THEN N'Otvoren' WHEN 'E' THEN N'U radu' WHEN 'P' THEN N'U toku' WHEN 'R' THEN N'Djelomično zaključen' WHEN 'F' THEN N'Zaključen' WHEN 'I' THEN N'Zaključen' WHEN 'Z' THEN N'Zaključen' WHEN 'N' THEN N'Novo' WHEN 'C' THEN N'Otkazano' ELSE N'Nedefinisan' END status_rn, CASE $status WHEN 'F' THEN 'green' WHEN 'I' THEN 'green' WHEN 'Z' THEN 'green' WHEN 'E' THEN 'yellow' WHEN 'P' THEN 'orange' WHEN 'D' THEN 'orange' WHEN 'S' THEN 'orange' WHEN 'O' THEN 'purple' WHEN 'N' THEN 'purple' WHEN 'R' THEN 'teal' WHEN 'C' THEN 'grey' ELSE 'red' END plan_row_color");

            $query->addSelect(DB::raw("CASE wo.anPriority WHEN 1 THEN 'red' WHEN 5 THEN 'yellow' WHEN 7 THEN 'teal' WHEN 10 THEN 'green' WHEN 15 THEN 'purple' ELSE 'grey' END AS priority_row_color"))
                ->addSelect('wo.acCostDrv as nositelj_troska')
                ->addSelect('wo.acIdent as sifra_crtez')
                ->addSelect('wo.anUserIns as creator_id')
                ->selectRaw("CASE WHEN sales_order.acCurrency = 'EUR' THEN order_item.anPrice END AS cijena_eur, CASE WHEN sales_order.acCurrency = 'EUR' THEN order_item.anPrice * wo.anPlanQty END AS ukupno_eur");

            $filterMap = [
                'rn' => 'wo.acKeyView',
                'proizvod' => 'wo.acIdent',
                'narudzba' => 'wo.acLnkKeyView',
            ];

            foreach ($filterMap as $key => $column) {
                $value = trim((string) ($filters[$key] ?? ''));
                if ($value !== '') {
                    $query->where($column, 'like', '%' . $value . '%');
                }
            }

            $this->applyCreatorFilter($query, $filters);
            $this->applySelectionFilters($query, $filters);

            $this->applyCustomerDateFilters($query, $filters);

            $this->applyStatusFilter($query, $filters);

            $search = trim((string) $request->input('search.value', ''));
            if ($search !== '') {
                $like = '%' . $search . '%';
                $query->where(function ($searchQuery) use ($like, $deliveryDate) {
                    $searchQuery->where('wo.acKeyView', 'like', $like)
                        ->orWhere('wo.acConsignee', 'like', $like)
                        ->orWhere('wo.acReceiver', 'like', $like)
                        ->orWhere('priority.acName', 'like', $like)
                        ->orWhere('wo.acLnkKeyView', 'like', $like)
                        ->orWhere('wo.acIdent', 'like', $like)
                        ->orWhere('wo.acName', 'like', $like)
                        ->orWhere('wo.acNote', 'like', $like)
                        ->orWhere('wo.acCostDrv', 'like', $like)
                        ->orWhere('wo.acStatusMF', 'like', $like)
                        ->orWhereRaw('CONVERT(char(10), wo.adDate, 104) LIKE ?', [$like])
                        ->orWhereRaw('CONVERT(char(10), wo.adSchedStartTime, 104) LIKE ?', [$like])
                        ->orWhereRaw('CONVERT(char(10), wo.adSchedEndTime, 104) LIKE ?', [$like])
                        ->orWhereRaw("CONVERT(char(10), $deliveryDate, 104) LIKE ?", [$like])
                        ->orWhereRaw('CAST(wo.anLnkNo AS nvarchar(50)) LIKE ?', [$like])
                        ->orWhereRaw('CAST(wo.anPlanQty AS nvarchar(50)) LIKE ?', [$like])
                        ->orWhereRaw('CAST(wo.anProducedQty AS nvarchar(50)) LIKE ?', [$like]);
                });
            }

            foreach ([['datum_od', '>='], ['datum_do', '<=']] as [$key, $operator]) {
                $value = trim((string) ($filters[$key] ?? ''));
                if ($value !== '' && !($request->boolean('filter.week_dates_auto') && !empty($filters['kw']))) {
                    $query->whereDate('wo.adSchedStartTime', $operator, $value);
                }
            }

            foreach ([['isporuka_od', '>='], ['isporuka_do', '<=']] as [$key, $operator]) {
                $value = trim((string) ($filters[$key] ?? ''));
                if ($value !== '') {
                    $query->whereRaw("CAST($deliveryDate AS date) $operator ?", [$value]);
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
                'sifra_crtez' => 'wo.acIdent', 'cijena_eur' => 'cijena_eur', 'ukupno_eur' => 'ukupno_eur',
                'rn' => 'wo.acKeyView', 'narucitelj' => 'wo.acConsignee', 'prioritet' => 'wo.anPriority',
                'datum' => 'wo.adDate', 'narudzba' => 'wo.acLnkKeyView', 'broj_narudzbe_kupca' => 'sales_order.acDoc1', 'pozicija' => 'wo.anLnkNo',
                'pocetak' => 'wo.adSchedStartTime', 'kraj' => 'wo.adSchedEndTime', 'datum_isporuke' => DB::raw($deliveryDate), 'proizvod' => 'wo.acIdent',
                'plan_kol' => 'wo.anPlanQty', 'izr_kol' => 'wo.anProducedQty', 'naziv' => 'wo.acName',
                'napomena' => 'wo.acNote', 'nositelj_troska' => 'wo.acCostDrv', 'status_rn' => DB::raw("UPPER(LTRIM(RTRIM(wo.acStatusMF)))"),
            ];
            $sort = $sorts[$request->input('sort', 'pocetak')] ?? 'wo.adSchedStartTime';
            $direction = $request->input('dir') === 'asc' ? 'asc' : 'desc';
            if (isset($weekRange) && $sort === 'wo.adSchedStartTime' && $direction === 'desc') {
                $query->orderByRaw(
                    "CASE WHEN CAST(wo.adSchedStartTime AS date) < ? AND UPPER(LTRIM(RTRIM(wo.acStatusMF))) NOT IN ('F', 'I', 'Z') THEN 0 ELSE 1 END",
                    [$weekRange['start']->toDateString()]
                );
            }

            $rows = $query->orderBy($sort, $direction)
                ->orderBy('wo.acKey', $direction)
                ->offset(max(0, (int) $request->input('start', 0)))
                ->limit(min(100, max(10, (int) $request->input('length', 25))))
                ->get();

            $this->attachCreators($rows);
            $this->attachWeldingData($rows, $schema);
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
                    && $this->isEarlierWeekOpenOrder($row, $weekRange['start']);
                $row->plan_row_color = $row->is_previous_week_open_order
                    ? 'red'
                    : $row->priority_row_color;
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
     * Include the selected ISO week and unfinished orders from earlier weeks in
     * the selected calendar year. Other active filters still apply.
     *
     * @return array{start: Carbon}
     */
    private function applyWeekFilter($query, int $year, int $week): array
    {
        $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $status = DB::raw("UPPER(LTRIM(RTRIM(wo.acStatusMF)))");

        $query->where(function ($scheduleQuery) use ($weekStart, $weekEnd, $yearStart, $status) {
            $scheduleQuery->where(function ($currentWeekQuery) use ($weekStart, $weekEnd) {
                $currentWeekQuery->whereDate('wo.adSchedStartTime', '>=', $weekStart)
                    ->whereDate('wo.adSchedStartTime', '<=', $weekEnd);
            })->orWhere(function ($previousWeekQuery) use ($yearStart, $weekStart, $status) {
                $previousWeekQuery->whereDate('wo.adSchedStartTime', '>=', $yearStart)
                    ->whereDate('wo.adSchedStartTime', '<', $weekStart)
                    ->whereNotIn($status, ['F', 'I', 'Z']);
            });
        });

        return ['start' => $weekStart];
    }

    private function isEarlierWeekOpenOrder($row, Carbon $weekStart): bool
    {
        if (in_array(strtoupper(trim((string) ($row->status_code ?? ''))), ['F', 'I', 'Z'], true) || empty($row->pocetak)) {
            return false;
        }

        $scheduledStart = Carbon::parse($row->pocetak);

        return $scheduledStart->lessThan($weekStart);
    }

    private function applyStatusFilter($query, array $filters): void
    {
        $status = DB::raw('UPPER(LTRIM(RTRIM(wo.acStatusMF)))');
        $hasSelection = array_key_exists('status_rn', $filters);
        $selection = $filters['status_rn'] ?? '';

        if ($selection === '__all__' || $selection === '__ALL__') {
            return;
        }

        if ($selection === '__none__' || (is_array($selection) && count($selection) === 0)) {
            $query->whereRaw('1 = 0');
            return;
        }

        if (is_array($selection)) {
            $selectedStatuses = array_values(array_unique(array_filter(array_map(
                fn ($value) => is_scalar($value) ? strtoupper(trim((string) $value)) : '',
                $selection
            ), fn ($value) => $value !== '')));
            if ($selectedStatuses) {
                $query->whereIn($status, $selectedStatuses);
            } else {
                $query->whereRaw('1 = 0');
            }
            return;
        }

        $selected = strtoupper(trim((string) $selection));
        if ($selected !== '' && $selected !== '__ALL__') {
            $query->where($status, $selected);
            return;
        }

        if ($hasSelection && $selected === '') {
            return;
        }

        $query->where(function ($active) use ($status) {
            $active->whereNull('wo.acStatusMF')->orWhereNotIn($status, ['F', 'I', 'Z']);
        });
    }

    private function applySelectionFilters($query, array $filters): void
    {
        foreach (['prioritet' => 'wo.anPriority'] as $key => $column) {
            $selection = $filters[$key] ?? '';
            if ($selection === '' || $selection === null) {
                continue;
            }
            if ($selection === '__none__' || $selection === []) {
                $query->whereRaw('1 = 0');
                continue;
            }
            $values = array_values(array_filter((array) $selection, function ($value) use ($key) {
                return is_scalar($value) && ($key !== 'prioritet' || ctype_digit((string) $value));
            }));
            if ($values) {
                $query->whereIn($column, $values);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
    }

    private function deliveryDateExpression(): string
    {
        return "COALESCE(order_item.adDeliveryDeadline, order_item.adDeliveryDate, sales_order.adDeliveryDeadline, sales_order.adDeliveryDate)";
    }

    private function applyCustomerDateFilters($query, array $filters): void
    {
        $deliveryDate = $this->deliveryDateExpression();
        $selectedGermanyNumbers = $filters['trendy_germany_numbers'] ?? '';
        $filterGermanyNumbers = is_array($selectedGermanyNumbers) && count($selectedGermanyNumbers) > 0;
        $noGermanyNumbers = $selectedGermanyNumbers === '__none__' || (is_array($selectedGermanyNumbers) && count($selectedGermanyNumbers) === 0);
        $germanyNumbers = $filterGermanyNumbers
            ? array_values(array_filter($selectedGermanyNumbers, fn ($number) => is_scalar($number) && ctype_digit((string) $number)))
            : [];
        $customers = [
            'grob' => 'GROB%',
            'trendy_germany' => 'TRENDY GERMANY%',
        ];
        $ranges = [];
        foreach ($customers as $key => $pattern) {
            $from = trim((string) ($filters[$key . '_date_from'] ?? ''));
            $to = trim((string) ($filters[$key . '_date_to'] ?? ''));
            $selectionKey = $key . '_customers';
            $selection = $filters[$selectionKey] ?? '';
            $hasSelection = array_key_exists($selectionKey, $filters);
            $ranges[] = compact('pattern', 'from', 'to', 'selection', 'hasSelection');
        }

        if (!collect($ranges)->contains(fn ($range) => $range['from'] !== '' || $range['to'] !== '' || $range['hasSelection']) && !$filterGermanyNumbers && !$noGermanyNumbers) {
            return;
        }

        $query->where(function ($customerQuery) use ($ranges, $germanyNumbers, $filterGermanyNumbers, $noGermanyNumbers, $deliveryDate) {
            foreach ($ranges as $range) {
                $isGermany = $range['pattern'] === 'TRENDY GERMANY%';
                $customerQuery->orWhere(function ($rowQuery) use ($range, $isGermany, $germanyNumbers, $filterGermanyNumbers, $noGermanyNumbers, $deliveryDate) {
                    $customerSql = 'UPPER(LTRIM(RTRIM(ISNULL(wo.acConsignee, wo.acReceiver))))';
                    if (!$range['hasSelection']) {
                        $rowQuery->whereRaw($customerSql . ' LIKE ?', [$range['pattern']]);
                    } elseif ($range['selection'] === '__none__' || $range['selection'] === []) {
                        $rowQuery->whereRaw('1 = 0');
                    } elseif ($isGermany) {
                        $rowQuery->whereRaw($customerSql . ' LIKE ?', ['TRENDY GERMANY%']);
                        $selected = (array) $range['selection'];
                        if ($range['selection'] !== '' && $range['selection'] !== '__all__') {
                            $rowQuery->where(function ($companies) use ($selected, $customerSql) {
                                if (in_array('germany', $selected, true)) {
                                    $companies->orWhereRaw($customerSql . ' NOT LIKE ?', ['%GMBH%']);
                                }
                                if (in_array('germany_gmbh', $selected, true)) {
                                    $companies->orWhereRaw($customerSql . ' LIKE ?', ['%GMBH%']);
                                }
                                if (!array_intersect(['germany', 'germany_gmbh'], $selected)) {
                                    $companies->whereRaw('1 = 0');
                                }
                            });
                        }
                    } else {
                        $rowQuery->whereRaw($customerSql . ' NOT LIKE ?', ['TRENDY GERMANY%']);
                        if ($range['selection'] !== '' && $range['selection'] !== '__all__') {
                            $selected = array_values(array_filter((array) $range['selection'], 'is_scalar'));
                            $rowQuery->whereIn(DB::raw($customerSql), $selected);
                        }
                    }
                    if ($isGermany && $noGermanyNumbers) {
                        $rowQuery->whereRaw('1 = 0');
                    } elseif ($isGermany && $filterGermanyNumbers) {
                        $rowQuery->where(function ($numberQuery) use ($germanyNumbers) {
                            foreach ($germanyNumbers as $number) {
                                $numberQuery->orWhereRaw('UPPER(LTRIM(RTRIM(ISNULL(wo.acConsignee, wo.acReceiver)))) LIKE ?', ['%-' . $number]);
                            }
                        });
                    }
                    if ($range['from'] !== '') {
                        $rowQuery->whereRaw("CAST($deliveryDate AS date) >= ?", [$range['from']]);
                    }
                    if ($range['to'] !== '') {
                        $rowQuery->whereRaw("CAST($deliveryDate AS date) <= ?", [$range['to']]);
                    }
                });
            }
        });
    }

    public function export(Request $request)
    {
        try {
            $filtered = $request->input('scope', 'filtered') !== 'all';
            $filters = $filtered ? (array) $request->input('filter', []) : [];
            $schema = config('workorders.schema', 'dbo');
            $status = "UPPER(LTRIM(RTRIM(wo.acStatusMF)))";
            $deliveryDate = $this->deliveryDateExpression();

            $query = DB::table($schema . '.tHF_WOEx as wo')
                ->leftJoin($schema . '.tHE_SetDeliveryPriority as priority', 'priority.anPriority', '=', 'wo.anPriority')
                ->leftJoin($schema . '.tHE_Order as sales_order', 'sales_order.acKey', '=', 'wo.acLnkKey')
                ->leftJoin($schema . '.tHE_OrderItem as order_item', function ($join) {
                    $join->on('order_item.acKey', '=', 'wo.acLnkKey')->on('order_item.anNo', '=', 'wo.anLnkNo');
                })
                ->selectRaw("wo.acKey id, wo.acKeyView rn, ISNULL(wo.acConsignee, wo.acReceiver) narucitelj, COALESCE(NULLIF(LTRIM(RTRIM(priority.acName)), ''), N'Nedefinisan') prioritet, CAST(wo.adDate AS date) datum, wo.acLnkKey narudzba_key, wo.acLnkKeyView narudzba, sales_order.acDoc1 broj_narudzbe_kupca, wo.anLnkNo pozicija, wo.adSchedStartTime pocetak, wo.adSchedEndTime kraj, $deliveryDate datum_isporuke, wo.acIdent proizvod, wo.anPlanQty plan_kol, wo.anProducedQty izr_kol, wo.acName naziv, wo.acNote napomena, $status status_code")
                ->addSelect(DB::raw("CASE wo.anPriority WHEN 1 THEN 'red' WHEN 5 THEN 'yellow' WHEN 7 THEN 'teal' WHEN 10 THEN 'green' WHEN 15 THEN 'purple' ELSE 'grey' END AS priority_row_color"))
                ->addSelect('wo.acCostDrv as nositelj_troska')
                ->addSelect('wo.acIdent as sifra_crtez')
                ->addSelect('wo.anUserIns as creator_id')
                ->selectRaw("CASE WHEN sales_order.acCurrency = 'EUR' THEN order_item.anPrice END AS cijena_eur, CASE WHEN sales_order.acCurrency = 'EUR' THEN order_item.anPrice * wo.anPlanQty END AS ukupno_eur");

            if ($filtered) {
                $filterMap = [
                    'rn' => 'wo.acKeyView', 'proizvod' => 'wo.acIdent',
                    'narudzba' => 'wo.acLnkKeyView',
                ];

                foreach ($filterMap as $key => $column) {
                    $value = trim((string) ($filters[$key] ?? ''));
                    if ($value !== '') {
                        $query->where($column, 'like', '%' . $value . '%');
                    }
                }

                $this->applyCreatorFilter($query, $filters);
                $this->applySelectionFilters($query, $filters);

                $this->applyCustomerDateFilters($query, $filters);

                $this->applyStatusFilter($query, $filters);

                foreach ([['datum_od', '>='], ['datum_do', '<=']] as [$key, $operator]) {
                    $value = trim((string) ($filters[$key] ?? ''));
                    if ($value !== '' && !($request->boolean('filter.week_dates_auto') && !empty($filters['kw']))) {
                        $query->whereDate('wo.adSchedStartTime', $operator, $value);
                    }
                }

                foreach ([['isporuka_od', '>='], ['isporuka_do', '<=']] as [$key, $operator]) {
                    $value = trim((string) ($filters[$key] ?? ''));
                    if ($value !== '') {
                        $query->whereRaw("CAST($deliveryDate AS date) $operator ?", [$value]);
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

            $sorts = ['sifra_crtez' => 'wo.acIdent', 'cijena_eur' => 'cijena_eur', 'ukupno_eur' => 'ukupno_eur',
                'rn' => 'wo.acKeyView', 'narucitelj' => 'wo.acConsignee', 'prioritet' => 'wo.anPriority', 'datum' => 'wo.adDate', 'narudzba' => 'wo.acLnkKeyView', 'broj_narudzbe_kupca' => 'sales_order.acDoc1', 'pozicija' => 'wo.anLnkNo', 'pocetak' => 'wo.adSchedStartTime', 'kraj' => 'wo.adSchedEndTime', 'datum_isporuke' => DB::raw($deliveryDate), 'proizvod' => 'wo.acIdent', 'plan_kol' => 'wo.anPlanQty', 'izr_kol' => 'wo.anProducedQty', 'naziv' => 'wo.acName', 'napomena' => 'wo.acNote', 'nositelj_troska' => 'wo.acCostDrv'];
            $sort = $sorts[$request->input('sort', 'pocetak')] ?? 'wo.adSchedStartTime';
            $direction = $request->input('dir') === 'asc' ? 'asc' : 'desc';
            if (isset($weekRange) && $sort === 'wo.adSchedStartTime' && $direction === 'desc') {
                $query->orderByRaw(
                    "CASE WHEN CAST(wo.adSchedStartTime AS date) < ? AND UPPER(LTRIM(RTRIM(wo.acStatusMF))) NOT IN ('F', 'I', 'Z') THEN 0 ELSE 1 END",
                    [$weekRange['start']->toDateString()]
                );
            }
            $rows = $query->orderBy($sort, $direction)
                ->orderBy('wo.acKey', $direction)->get();

            $this->attachCreators($rows);
            foreach ($rows->chunk(1000) as $chunk) $this->attachWeldingData($chunk, $schema);

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
                    && $this->isEarlierWeekOpenOrder($row, $weekRange['start']);
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

        $headers = ['Napredak', 'RN', 'Naručitelj', 'Prioritet', 'Datum', 'Narudžba', 'Br. narudžbe kupca', 'Br. poz.', 'Poč. termin', 'Kraj termin', 'Datum isporuke', 'Proizvod', 'Plan. kol.', 'Izr. kol.', 'Naziv', 'Nositelj troška', 'Napomena', 'Status RN', 'Šifra-crtež', 'Datum zavarivanja', 'Utrošeno vrijeme (min)', 'Materijal naručen', 'Zavarivač', 'Cijena artikla/kom (EUR)', 'Ukupno (EUR)', 'Kreirao RN'];
        $xml .= '<Row>' . implode('', array_map(fn ($header) => $cell($header, 'Header'), $headers)) . '</Row>';
        foreach ($rows as $row) {
            $style = $includeColours
                ? ($row->is_previous_week_open_order ?? false ? 'red' : (string) $row->priority_row_color)
                : '';
            $values = [$row->progress . '%', $row->rn, $row->narucitelj, $row->prioritet, $this->europeanDate($row->datum), $row->narudzba, $row->broj_narudzbe_kupca, $row->pozicija, $this->europeanDate($row->pocetak), $this->europeanDate($row->kraj), $this->europeanDate($row->datum_isporuke), $row->proizvod, $this->displayQuantity($row->plan_kol), $this->displayQuantity($row->izr_kol), $row->naziv, $row->nositelj_troska, $row->napomena, $row->status_code, ($row->sifra_crtez ?? $row->proizvod), $this->europeanDate(($row->datum_zavarivanja ?? null)), $this->displayQuantity(($row->utroseno_vrijeme ?? null)), $this->europeanDate(($row->materijal_narucen ?? null)), ($row->zavarivac ?? ''), $this->displayQuantity(($row->cijena_eur ?? null)), $this->displayQuantity(($row->ukupno_eur ?? null)), ($row->kreirao ?? '')];
            $xml .= '<Row>' . implode('', array_map(fn ($value) => $cell($value, $style), $values)) . '</Row>';
        }

        return $xml . '</Table></Worksheet></Workbook>';
    }

    private function europeanDate($value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d.m.Y');
        } catch (\Throwable $exception) {
            return (string) $value;
        }
    }

    private function displayQuantity($value): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    private function filterSummary(array $filters): string
    {
        $labels = ['kreirao' => 'Kreirao RN (ID)', 'rn' => 'RN', 'prioritet' => 'Prioritet', 'proizvod' => 'Proizvod', 'status_rn' => 'Status RN', 'narudzba' => 'Narudžba', 'year' => 'Godina', 'kw' => 'Kalendarska sedmica', 'datum_od' => 'Početni termin od', 'datum_do' => 'Početni termin do', 'isporuka_od' => 'Datum isporuke od', 'isporuka_do' => 'Datum isporuke do', 'grob_date_from' => 'Ostali naručitelji datum isporuke od', 'grob_date_to' => 'Ostali naručitelji datum isporuke do', 'trendy_germany_date_from' => 'Trendy naručitelji datum isporuke od', 'trendy_germany_date_to' => 'Trendy naručitelji datum isporuke do'];
        $parts = [];
        foreach ($labels as $key => $label) {
            $raw = $filters[$key] ?? '';
            $value = is_array($raw) ? implode(', ', $raw) : trim((string) $raw);
            if ($value === '__none__') {
                $value = 'Nijedan';
            }
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
            'nositelj_troska' => 'acCostDrv',
        ];
        $field = (string) $request->input('field');
        if (!isset($map[$field])) {
            return response()->json(['message' => 'Polje nije dozvoljeno.'], 422);
        }

        $value = $request->input('value');
        if ($field === 'nositelj_troska') {
            $request->validate(['value' => 'nullable|string|max:255']);
            if (trim((string) $value) !== '') {
                $catalogueCode = DB::table(config('workorders.schema', 'dbo') . '.' . config('workorders.protection_catalogue_table', 'tHE_CostDrv'))
                    ->where('acCostDrv', $value)->value('acCostDrv');
                if ($catalogueCode === null) {
                    return response()->json(['message' => 'Odaberite važeći nositelj troška iz ponuđenih prijedloga.'], 422);
                }
                $value = $catalogueCode;
            } else {
                $value = '';
            }
        }

        DB::table(config('workorders.schema', 'dbo') . '.tHF_WOEx')
            ->where('acKey', $id)
            ->update([
                $map[$field] => $value,
                'adTimeChg' => now(),
                'anUserChg' => (int) $request->user()->id,
            ]);

        return response()->json(['message' => 'Sačuvano.']);
    }

    public function operations(string $id)
    {
        abort_unless(DB::table(config('workorders.schema', 'dbo') . '.tHF_WOEx')->where('acKey', $id)->exists(), 404);

        try {
            return response()->json(['data' => app(WorkOrderController::class)->productionPlanDetails($id)]);
        } catch (\Throwable $exception) {
            Log::error('Production plan details failed.', ['id' => $id, 'message' => $exception->getMessage()]);
            return response()->json(['message' => 'Detalji radnog naloga trenutno nisu dostupni.'], 500);
        }
    }
}
