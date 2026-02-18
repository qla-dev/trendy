<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkOrderController extends Controller
{
    public function invoiceList()
    {
        $pageConfigs = ['pageHeader' => false];

        try {
            return view('/content/apps/invoice/app-invoice-list', [
                'pageConfigs' => $pageConfigs,
                'radniNalozi' => [],
                'statusStats' => $this->fetchStatusStats(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order list query failed.', [
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return view('/content/apps/invoice/app-invoice-list', [
                'pageConfigs' => $pageConfigs,
                'radniNalozi' => [],
                'statusStats' => $this->emptyStatusStats(),
                'error' => 'Greska pri ucitavanju radnih naloga iz baze.',
            ]);
        }
    }

    public function invoicePreview(Request $request, ?string $id = null)
    {
        $pageConfigs = ['pageHeader' => false];
        $workOrderId = $id ?? $request->query('id');

        if (!$workOrderId) {
            return redirect()->route('app-invoice-list');
        }

        try {
            $workOrder = $this->findMappedWorkOrder((string) $workOrderId, true);

            if (!$workOrder) {
                return redirect()->route('app-invoice-list')
                    ->with('error', 'Radni nalog nije pronadjen.');
            }

            $raw = $workOrder['raw'] ?? [];
            unset($workOrder['raw']);

            $sender = [
                'name' => (string) $this->value($raw, ['acConsignee', 'acReceiver', 'acPartner'], $workOrder['klijent'] ?? ''),
                'address' => (string) $this->value($raw, ['acAddress', 'acConsigneeAddress', 'acAddress1'], ''),
                'phone' => (string) $this->value($raw, ['acPhone', 'acConsigneePhone', 'acPhone1'], ''),
                'email' => (string) $this->value($raw, ['acEmail', 'acConsigneeEmail'], ''),
            ];

            $recipient = [
                'name' => (string) $this->value($raw, ['acReceiver', 'acConsignee', 'acPartner'], ''),
                'address' => (string) $this->value($raw, ['acReceiverAddress', 'acAddress2', 'acAddress'], ''),
                'phone' => (string) $this->value($raw, ['acReceiverPhone', 'acPhone2', 'acPhone'], ''),
                'email' => (string) $this->value($raw, ['acReceiverEmail', 'acEmail'], ''),
            ];

            return view('/content/apps/invoice/app-invoice-preview', [
                'pageConfigs' => $pageConfigs,
                'workOrder' => $workOrder,
                'sender' => $sender,
                'recipient' => $recipient,
                'invoiceNumber' => (string) ($workOrder['broj_naloga'] ?? ''),
                'issueDate' => $this->displayDate($workOrder['datum_kreiranja'] ?? null),
                'dueDate' => $this->displayDate($workOrder['datum_zavrsetka'] ?? null),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order preview query failed.', [
                'id' => $workOrderId,
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('app-invoice-list')
                ->with('error', 'Greska pri ucitavanju detalja radnog naloga.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $requestedLimit = $request->filled('limit')
                ? (int) $request->input('limit')
                : ($request->filled('length') ? (int) $request->input('length') : null);

            $limit = $this->resolveLimit($requestedLimit);
            $page = $request->integer('page', 0);

            if ($page < 1 && $request->filled('start')) {
                $start = max(0, (int) $request->input('start'));
                $page = (int) floor($start / $limit) + 1;
            }

            if ($page < 1) {
                $page = 1;
            }

            $filters = $this->extractFilters($request);
            $result = $this->fetchWorkOrders($limit, $page, $filters);
            $statusStats = $this->fetchStatusStats($filters);

            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'data' => $result['data'],
                'statusStats' => $statusStats,
                'meta' => array_merge($result['meta'], [
                    'connection' => config('database.default'),
                    'table' => $this->qualifiedTableName(),
                ]),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order API list query failed.', [
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to fetch work orders from database.',
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $workOrder = $this->findMappedWorkOrder($id);

            if (!$workOrder) {
                return response()->json([
                    'message' => 'Work order not found.',
                ], 404);
            }

            return response()->json([
                'data' => $workOrder,
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order API show query failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to fetch work order from database.',
            ], 500);
        }
    }

    private function fetchWorkOrders(?int $limit = null, ?int $page = null, array $filters = []): array
    {
        $columns = $this->tableColumns();
        $resolvedLimit = $this->resolveLimit($limit);
        $resolvedPage = max(1, (int) ($page ?? 1));

        $total = (clone $this->newTableQuery())->count();
        $query = $this->newTableQuery();

        $this->applyFilters($query, $columns, $filters);
        $filteredTotal = (clone $query)->count();
        $this->applyDefaultOrdering($query, $columns);

        $data = $query
            ->forPage($resolvedPage, $resolvedLimit)
            ->get()
            ->map(function ($row) {
                return $this->mapRow((array) $row);
            })
            ->values()
            ->all();

        return [
            'data' => $data,
            'meta' => [
                'count' => count($data),
                'page' => $resolvedPage,
                'limit' => $resolvedLimit,
                'total' => (int) $total,
                'filtered_total' => (int) $filteredTotal,
                'last_page' => $resolvedLimit > 0 ? (int) ceil($filteredTotal / $resolvedLimit) : 1,
            ],
        ];
    }

    private function findMappedWorkOrder(string $id, bool $includeRaw = false): ?array
    {
        $row = $this->findWorkOrderRow($id);

        if (!$row) {
            return null;
        }

        return $this->mapRow($row, $includeRaw);
    }

    private function findWorkOrderRow(string $id): ?array
    {
        $columns = $this->tableColumns();
        $query = $this->newTableQuery();
        $hasCondition = false;

        if (in_array('acRefNo1', $columns, true)) {
            $query->where('acRefNo1', $id);
            $hasCondition = true;
        }

        if (in_array('anNo', $columns, true) && is_numeric($id)) {
            if ($hasCondition) {
                $query->orWhere('anNo', (int) $id);
            } else {
                $query->where('anNo', (int) $id);
                $hasCondition = true;
            }
        }

        if (in_array('acKey', $columns, true)) {
            if ($hasCondition) {
                $query->orWhere('acKey', $id);
            } else {
                $query->where('acKey', $id);
                $hasCondition = true;
            }
        }

        if (in_array('id', $columns, true)) {
            if ($hasCondition) {
                $query->orWhere('id', $id);
            } else {
                $query->where('id', $id);
                $hasCondition = true;
            }
        }

        if (!$hasCondition) {
            return null;
        }

        $row = $query->first();

        return $row ? (array) $row : null;
    }

    private function mapRow(array $row, bool $includeRaw = false): array
    {
        $brojNaloga = (string) $this->value($row, ['acRefNo1', 'acKey', 'anNo', 'id'], 'N/A');
        $id = $this->value($row, ['acRefNo1', 'acKey'], null);

        if ($id === null || $id === '' || ((is_int($id) || is_float($id) || is_numeric((string) $id)) && (float) $id === 0.0)) {
            $id = $this->value($row, ['anNo', 'id'], $brojNaloga);
        }

        $status = $this->mapStatus($this->value($row, ['acStatus', 'status'], 'N/A'));
        $priority = $this->mapPriority($this->value($row, ['acPriority', 'acWayOfSale', 'priority'], 'Srednji'));
        $createdDate = $this->normalizeDate($this->value($row, ['adDate', 'adDateIns', 'created_at']));
        $endDate = $this->normalizeDate($this->value($row, ['adDeliveryDeadline', 'adDateOut', 'actual_end']));

        $mapped = [
            'responsive_id' => '',
            'id' => $id,
            'broj_naloga' => $brojNaloga,
            'naziv' => (string) $this->value($row, ['acDocType', 'acName', 'title'], 'Radni nalog'),
            'opis' => (string) $this->value($row, ['acNote', 'acStatement', 'acDescr', 'description'], ''),
            'status' => $status,
            'prioritet' => $priority,
            'datum_kreiranja' => $createdDate,
            'datum_zavrsetka' => $endDate,
            'dodeljen_korisnik' => (string) $this->value($row, ['anClerk', 'created_by', 'acUser'], ''),
            'klijent' => (string) $this->value($row, ['acConsignee', 'acReceiver', 'client_name', 'acPartner'], 'N/A'),
            'vrednost' => $this->normalizeNumber($this->value($row, ['anValue', 'anDocValue', 'total'], 0)),
            'valuta' => (string) $this->value($row, ['acCurrency', 'currency'], 'BAM'),
            'magacin' => (string) $this->value($row, ['acWarehouse', 'linked_document', 'acWarehouseFrom'], ''),
        ];

        if ($includeRaw) {
            $mapped['raw'] = $row;
        }

        return $mapped;
    }

    private function fetchStatusStats(array $filters = []): array
    {
        $stats = $this->emptyStatusStats();
        $columns = $this->tableColumns();
        $statusColumn = $this->firstExistingColumn($columns, ['acStatus', 'status']);

        if ($statusColumn === null) {
            return $stats;
        }

        $query = $this->newTableQuery();
        $filtersWithoutStatus = $filters;
        unset($filtersWithoutStatus['status']);
        $this->applyFilters($query, $columns, $filtersWithoutStatus);

        $stats['svi'] = (int) (clone $query)->count();

        $rows = (clone $query)
            ->select($statusColumn, DB::raw('COUNT(*) as total'))
            ->groupBy($statusColumn)
            ->get();

        foreach ($rows as $row) {
            $mappedStatus = $this->mapStatus($row->{$statusColumn} ?? null);
            $bucket = $this->statusBucket($mappedStatus);

            if ($bucket !== null && array_key_exists($bucket, $stats)) {
                $stats[$bucket] += (int) $row->total;
            }
        }

        return $stats;
    }

    private function extractFilters(Request $request): array
    {
        $rawSearch = $request->input('search.value');

        if ($rawSearch === null) {
            $rawSearch = $request->input('search');
        }

        return [
            'status' => trim((string) $request->input('status', '')),
            'kupac' => trim((string) $request->input('kupac', '')),
            'primatelj' => trim((string) $request->input('primatelj', '')),
            'proizvod' => trim((string) $request->input('proizvod', '')),
            'plan_pocetak_od' => trim((string) $request->input('plan_pocetak_od', '')),
            'plan_pocetak_do' => trim((string) $request->input('plan_pocetak_do', '')),
            'plan_kraj_od' => trim((string) $request->input('plan_kraj_od', '')),
            'plan_kraj_do' => trim((string) $request->input('plan_kraj_do', '')),
            'datum_od' => trim((string) $request->input('datum_od', '')),
            'datum_do' => trim((string) $request->input('datum_do', '')),
            'vezni_dok' => trim((string) $request->input('vezni_dok', '')),
            'search' => is_string($rawSearch) ? trim($rawSearch) : '',
        ];
    }

    private function applyFilters(Builder $query, array $columns, array $filters): void
    {
        $statusColumn = $this->firstExistingColumn($columns, ['acStatus', 'status']);

        if ($statusColumn !== null && !empty($filters['status'])) {
            $this->applyStatusFilter($query, $statusColumn, (string) $filters['status']);
        }

        $this->applyLikeAny(
            $query,
            $this->existingColumns($columns, ['acConsignee', 'acReceiver', 'acPartner']),
            (string) ($filters['kupac'] ?? '')
        );

        $this->applyLikeAny(
            $query,
            $this->existingColumns($columns, ['acReceiver', 'acConsignee', 'anClerk', 'acPartner']),
            (string) ($filters['primatelj'] ?? '')
        );

        $this->applyLikeAny(
            $query,
            $this->existingColumns($columns, ['acNote', 'acStatement', 'acDescr', 'acName', 'acDocType']),
            (string) ($filters['proizvod'] ?? '')
        );

        $this->applyLikeAny(
            $query,
            $this->existingColumns($columns, ['acRefNo1', 'acKey']),
            (string) ($filters['vezni_dok'] ?? '')
        );

        $startDateColumn = $this->firstExistingColumn($columns, ['adDate', 'adDateIns']);
        $endDateColumn = $this->firstExistingColumn($columns, ['adDeliveryDeadline', 'adDateOut']);

        $this->applyDateRangeFilter(
            $query,
            $startDateColumn,
            (string) ($filters['plan_pocetak_od'] ?? ''),
            (string) ($filters['plan_pocetak_do'] ?? '')
        );
        $this->applyDateRangeFilter(
            $query,
            $endDateColumn,
            (string) ($filters['plan_kraj_od'] ?? ''),
            (string) ($filters['plan_kraj_do'] ?? '')
        );
        $this->applyDateRangeFilter(
            $query,
            $startDateColumn,
            (string) ($filters['datum_od'] ?? ''),
            (string) ($filters['datum_do'] ?? '')
        );

        $search = (string) ($filters['search'] ?? '');

        if ($search !== '') {
            $this->applyLikeAny(
                $query,
                $this->existingColumns($columns, ['acRefNo1', 'acKey', 'acConsignee', 'acReceiver', 'acNote', 'acStatement', 'acDescr', 'acStatus']),
                $search
            );
        }
    }

    private function applyStatusFilter(Builder $query, string $column, string $statusFilter): void
    {
        $normalized = strtolower(trim($statusFilter));
        $allowedStatuses = [
            'planiran',
            'otvoren',
            'rezerviran',
            'raspisan',
            'u_radu',
            'djelimicno_zakljucen',
            'zakljucen',
        ];

        if ($normalized === '' || $normalized === 'svi' || !in_array($normalized, $allowedStatuses, true)) {
            return;
        }

        $query->where(function (Builder $statusQuery) use ($column, $normalized) {
            if ($normalized === 'planiran') {
                $statusQuery->whereIn($column, ['N', 'PLANIRAN', 'NOVO']);
                return;
            }

            if ($normalized === 'otvoren') {
                $statusQuery->whereIn($column, ['O', 'OTVOREN']);
                return;
            }

            if ($normalized === 'rezerviran') {
                $statusQuery->whereIn($column, ['R', 'REZERVIRAN']);
                return;
            }

            if ($normalized === 'raspisan') {
                $statusQuery->whereIn($column, ['S', 'RASPISAN']);
                return;
            }

            if ($normalized === 'u_radu') {
                $statusQuery->whereIn($column, ['P', 'I', 'U TOKU', 'U RADU']);
                return;
            }

            if ($normalized === 'djelimicno_zakljucen') {
                $statusQuery->where($column, 'like', '%DJELIM%')
                    ->orWhere($column, 'like', '%DELIM%');
                return;
            }

            if ($normalized === 'zakljucen') {
                $statusQuery->whereIn($column, ['F', 'ZAKLJUCEN', 'ZAVRSENO'])
                    ->orWhere($column, 'like', '%ZAKLJ%')
                    ->orWhere($column, 'like', '%ZAVRS%');
            }
        });
    }

    private function applyDateRangeFilter(Builder $query, ?string $column, string $from, string $to): void
    {
        if ($column === null) {
            return;
        }

        $fromDate = $this->normalizeDateInput($from);
        $toDate = $this->normalizeDateInput($to);

        if ($fromDate !== null) {
            $query->whereDate($column, '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->whereDate($column, '<=', $toDate);
        }
    }

    private function applyLikeAny(Builder $query, array $columns, string $value): void
    {
        $value = trim($value);

        if ($value === '' || empty($columns)) {
            return;
        }

        $query->where(function (Builder $textQuery) use ($columns, $value) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $textQuery->where($column, 'like', '%' . $value . '%');
                    continue;
                }

                $textQuery->orWhere($column, 'like', '%' . $value . '%');
            }
        });
    }

    private function calculateStatusStats(array $workOrders): array
    {
        $stats = $this->emptyStatusStats();
        $stats['svi'] = count($workOrders);

        foreach ($workOrders as $workOrder) {
            $bucket = $this->statusBucket((string) ($workOrder['status'] ?? ''));

            if ($bucket !== null && array_key_exists($bucket, $stats)) {
                $stats[$bucket]++;
            }
        }

        return $stats;
    }

    private function emptyStatusStats(): array
    {
        return [
            'svi' => 0,
            'planiran' => 0,
            'otvoren' => 0,
            'rezerviran' => 0,
            'raspisan' => 0,
            'u_radu' => 0,
            'djelimicno_zakljucen' => 0,
            'zakljucen' => 0,
        ];
    }

    private function statusBucket(string $status): ?string
    {
        $normalized = strtolower(trim($status));

        if ($normalized === '' || $normalized === 'n/a') {
            return null;
        }

        if (str_contains($normalized, 'planiran') || str_contains($normalized, 'novo')) {
            return 'planiran';
        }

        if (str_contains($normalized, 'otvoren')) {
            return 'otvoren';
        }

        if (str_contains($normalized, 'rezerviran')) {
            return 'rezerviran';
        }

        if (str_contains($normalized, 'raspisan')) {
            return 'raspisan';
        }

        if (str_contains($normalized, 'u toku') || str_contains($normalized, 'u radu')) {
            return 'u_radu';
        }

        if (str_contains($normalized, 'djelimicno') || str_contains($normalized, 'djelomicno')) {
            return 'djelimicno_zakljucen';
        }

        if (str_contains($normalized, 'zavrseno') || str_contains($normalized, 'zakljucen')) {
            return 'zakljucen';
        }

        return null;
    }

    private function mapStatus(mixed $status): string
    {
        if ($status === null || $status === '') {
            return 'N/A';
        }

        $statusString = trim((string) $status);
        $normalizedStatus = strtoupper($statusString);

        $statusMap = [
            'F' => 'Zavrseno',
            'P' => 'U toku',
            'I' => 'U toku',
            'N' => 'Novo',
            'C' => 'Otkazano',
            'D' => 'Nacrt',
            'O' => 'Otvoren',
            'R' => 'Rezerviran',
            'S' => 'Raspisan',
            'PLANIRAN' => 'Planiran',
            'OTVOREN' => 'Otvoren',
            'REZERVIRAN' => 'Rezerviran',
            'RASPISAN' => 'Raspisan',
            'U TOKU' => 'U toku',
            'U RADU' => 'U toku',
            'ZAVRSENO' => 'Zavrseno',
            'ZAKLJUCEN' => 'Zavrseno',
        ];

        return $statusMap[$normalizedStatus] ?? $statusString;
    }

    private function mapPriority(mixed $priority): string
    {
        if ($priority === null || $priority === '') {
            return 'Srednji';
        }

        $priorityString = trim((string) $priority);
        $normalizedPriority = strtoupper($priorityString);

        $priorityMap = [
            'V' => 'Visok',
            'Z' => 'Visok',
            'VISOK' => 'Visok',
            'HIGH' => 'Visok',
            'S' => 'Srednji',
            'M' => 'Srednji',
            'SREDNJI' => 'Srednji',
            'MEDIUM' => 'Srednji',
            'D' => 'Nizak',
            'N' => 'Nizak',
            'NIZAK' => 'Nizak',
            'LOW' => 'Nizak',
        ];

        return $priorityMap[$normalizedPriority] ?? $priorityString;
    }

    private function applyDefaultOrdering(Builder $query, array $columns): void
    {
        foreach (['adDate', 'adDateIns', 'adTimeIns', 'anNo'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderByDesc($column);
            }
        }
    }

    private function tableColumns(): array
    {
        return DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->tableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();
    }

    private function existingColumns(array $columns, array $candidates): array
    {
        return array_values(array_filter($candidates, function ($candidate) use ($columns) {
            return in_array($candidate, $columns, true);
        }));
    }

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function value(array $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if ($value === null || $value === '') {
                continue;
            }

            return $value;
        }

        return $default;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->format('Y-m-d');
            }

            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable $exception) {
            $stringValue = substr((string) $value, 0, 10);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stringValue) === 1) {
                return $stringValue;
            }

            return null;
        }
    }

    private function normalizeDateInput(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function displayDate(?string $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d.m.Y');
        } catch (Throwable $exception) {
            return '';
        }
    }

    private function normalizeNumber(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        return $value ?? 0;
    }

    private function newTableQuery(): Builder
    {
        return DB::table($this->qualifiedTableName());
    }

    private function resolveLimit(?int $requestedLimit = null): int
    {
        $maxLimit = max(1, (int) config('workorders.max_limit', 100));
        $defaultLimit = max(1, (int) config('workorders.default_limit', 10));
        $limit = $requestedLimit ?? $defaultLimit;

        if ($limit < 1) {
            return $defaultLimit;
        }

        return min($limit, $maxLimit);
    }

    private function tableSchema(): string
    {
        return (string) config('workorders.schema', 'dbo');
    }

    private function tableName(): string
    {
        return (string) config('workorders.table', 'tHF_WOEx');
    }

    private function qualifiedTableName(): string
    {
        return $this->tableSchema() . '.' . $this->tableName();
    }
}
