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
                ->selectRaw("wo.acKey id, wo.acKeyView rn, ISNULL(wo.acConsignee, wo.acReceiver) narucitelj, COALESCE(NULLIF(LTRIM(RTRIM(priority.acName)), ''), N'Nedefinisan') prioritet, CAST(wo.adDate AS date) datum, wo.acLnkKeyView narudzba, wo.anLnkNo pozicija, wo.adSchedStartTime pocetak, wo.adSchedEndTime kraj, wo.acIdent proizvod, wo.anPlanQty plan_kol, wo.anProducedQty izr_kol, wo.acName naziv, wo.acNote napomena, CASE $status WHEN 'D' THEN N'Raspisan' WHEN 'O' THEN N'Otvoren' WHEN 'E' THEN N'U radu' WHEN 'P' THEN N'U toku' WHEN 'R' THEN N'Djelomično zaključen' WHEN 'F' THEN N'Zaključen' WHEN 'I' THEN N'Zaključen' WHEN 'Z' THEN N'Zaključen' WHEN 'N' THEN N'Novo' ELSE N'Nedefinisan' END status_rn");

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

            $priority = trim((string) ($filters['prioritet'] ?? ''));
            if ($priority !== '' && ctype_digit($priority)) {
                $query->where('wo.anPriority', (int) $priority);
            }

            foreach ([['datum_od', '>='], ['datum_do', '<=']] as [$key, $operator]) {
                $value = trim((string) ($filters[$key] ?? ''));
                if ($value !== '') {
                    $query->whereDate('wo.adDate', $operator, $value);
                }
            }

            $week = (int) ($filters['kw'] ?? 0);
            $year = (int) ($filters['year'] ?? 0);
            if ($week && $year) {
                $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek();
                $query->whereDate('wo.adDate', '>=', $weekStart)
                    ->whereDate('wo.adDate', '<=', $weekStart->copy()->endOfWeek());
            }

            $total = (clone $query)->count();
            $sorts = [
                'rn' => 'wo.acKeyView', 'narucitelj' => 'wo.acConsignee', 'prioritet' => 'wo.anPriority',
                'datum' => 'wo.adDate', 'narudzba' => 'wo.acLnkKeyView', 'pozicija' => 'wo.anLnkNo',
                'pocetak' => 'wo.adSchedStartTime', 'kraj' => 'wo.adSchedEndTime', 'proizvod' => 'wo.acIdent',
                'plan_kol' => 'wo.anPlanQty', 'izr_kol' => 'wo.anProducedQty', 'naziv' => 'wo.acName',
                'napomena' => 'wo.acNote',
            ];
            $sort = $sorts[$request->input('sort', 'datum')] ?? 'wo.adDate';
            $direction = $request->input('dir') === 'asc' ? 'asc' : 'desc';
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
