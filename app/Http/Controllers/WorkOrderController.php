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
    private ?array $deliveryPriorityMap = null;

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
            $workOrderItems = $this->fetchMappedWorkOrderItems($raw);
            $workOrderItemResources = $this->fetchMappedWorkOrderItemResources($raw);
            $workOrderRegOperations = $this->fetchMappedWorkOrderRegOperations($raw);
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

            $workOrderMeta = $this->buildWorkOrderMetadata(
                $raw,
                $workOrder,
                $workOrderItems,
                $workOrderItemResources,
                $workOrderRegOperations
            );

            return view('/content/apps/invoice/app-invoice-preview', [
                'pageConfigs' => $pageConfigs,
                'workOrder' => $workOrder,
                'workOrderItems' => $workOrderItems,
                'workOrderItemResources' => $workOrderItemResources,
                'workOrderRegOperations' => $workOrderRegOperations,
                'workOrderMeta' => $workOrderMeta,
                'sender' => $sender,
                'recipient' => $recipient,
                'invoiceNumber' => (string) ($workOrder['broj_naloga'] ?? ''),
                'issueDate' => $this->displayDate($workOrder['datum_kreiranja'] ?? null),
                'plannedStartDate' => $this->formatMetaDateTime($this->value($raw, ['adSchedStartTime'], null)),
                'dueDate' => $this->displayDate($workOrder['datum_zavrsetka'] ?? null),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order preview query failed.', [
                'id' => $workOrderId,
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'items_table' => $this->qualifiedItemTableName(),
                'item_resources_table' => $this->qualifiedItemResourcesTableName(),
                'reg_operations_table' => $this->qualifiedRegOperationsTableName(),
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('app-invoice-list')
                ->with('error', 'Greska pri ucitavanju detalja radnog naloga.');
        }
    }

    public function invoicePrint(Request $request, ?string $id = null)
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
            $workOrderItems = $this->fetchMappedWorkOrderItems($raw);
            $workOrderItemResources = $this->fetchMappedWorkOrderItemResources($raw);
            $workOrderRegOperations = $this->fetchMappedWorkOrderRegOperations($raw);
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

            $workOrderMeta = $this->buildWorkOrderMetadata(
                $raw,
                $workOrder,
                $workOrderItems,
                $workOrderItemResources,
                $workOrderRegOperations
            );

            return view('/content/apps/invoice/app-invoice-print', [
                'pageConfigs' => $pageConfigs,
                'workOrder' => $workOrder,
                'workOrderItems' => $workOrderItems,
                'workOrderItemResources' => $workOrderItemResources,
                'workOrderRegOperations' => $workOrderRegOperations,
                'workOrderMeta' => $workOrderMeta,
                'sender' => $sender,
                'recipient' => $recipient,
                'invoiceNumber' => $this->formatWorkOrderNumberForCalendar((string) ($workOrder['broj_naloga'] ?? '')),
                'issueDate' => $this->displayDate($workOrder['datum_kreiranja'] ?? null),
                'plannedStartDate' => $this->formatMetaDateTime($this->value($raw, ['adSchedStartTime'], null)),
                'dueDate' => $this->displayDate($workOrder['datum_zavrsetka'] ?? null),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order print query failed.', [
                'id' => $workOrderId,
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'items_table' => $this->qualifiedItemTableName(),
                'item_resources_table' => $this->qualifiedItemResourcesTableName(),
                'reg_operations_table' => $this->qualifiedRegOperationsTableName(),
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('app-invoice-list')
                ->with('error', 'Greska pri ucitavanju print prikaza radnog naloga.');
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
            $workOrder = $this->findMappedWorkOrder($id, true);

            if (!$workOrder) {
                return response()->json([
                    'message' => 'Work order not found.',
                ], 404);
            }

            $raw = $workOrder['raw'] ?? [];
            $workOrder['items'] = $this->fetchMappedWorkOrderItems($raw);
            $workOrder['item_resources'] = $this->fetchMappedWorkOrderItemResources($raw);
            $workOrder['reg_operations'] = $this->fetchMappedWorkOrderRegOperations($raw);
            unset($workOrder['raw']);

            return response()->json([
                'data' => $workOrder,
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order API show query failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'items_table' => $this->qualifiedItemTableName(),
                'item_resources_table' => $this->qualifiedItemResourcesTableName(),
                'reg_operations_table' => $this->qualifiedRegOperationsTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to fetch work order from database.',
            ], 500);
        }
    }

    public function calendar(Request $request): JsonResponse
    {
        try {
            $startDate = $this->normalizeDateInput((string) $request->input('start', ''));
            $endDate = $this->normalizeDateInput((string) $request->input('end', ''));
            $statusBuckets = $this->normalizeCalendarStatuses($request->input('statuses', []));

            return response()->json([
                'data' => $this->fetchCalendarEvents($startDate, $endDate, $statusBuckets),
                'statusStats' => $this->fetchCalendarStatusStats($startDate, $endDate),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order API calendar query failed.', [
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to fetch calendar work orders from database.',
            ], 500);
        }
    }

    private function fetchWorkOrders(?int $limit = null, ?int $page = null, array $filters = []): array
    {
        $columns = $this->tableColumns();
        $moneyColumns = $this->monetaryColumns($columns);
        $resolvedLimit = $this->resolveLimit($limit);
        $resolvedPage = max(1, (int) ($page ?? 1));

        $total = (clone $this->newTableQuery())->count();
        $query = $this->newTableQuery();

        $this->applyFilters($query, $columns, $filters);
        $filteredTotal = (clone $query)->count();
        $hasMoneyValues = $this->hasMonetaryValues($query, $moneyColumns);
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
                'has_money_column' => !empty($moneyColumns),
                'has_money_values' => $hasMoneyValues,
            ],
        ];
    }

    private function fetchCalendarEvents(?string $startDate, ?string $endDate, array $statusBuckets): array
    {
        $columns = $this->tableColumns();
        $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF']);
        $dateColumn = $this->firstExistingColumn($columns, ['adDate', 'adDateIns']);
        $query = $this->newTableQuery();

        if ($dateColumn !== null) {
            if ($startDate !== null) {
                $query->whereDate($dateColumn, '>=', $startDate);
            }

            if ($endDate !== null) {
                $query->whereDate($dateColumn, '<', $endDate);
            }
        }

        $this->applyStatusBucketsFilter($query, $statusColumn, $statusBuckets);
        $this->applyDefaultOrdering($query, $columns);

        return $query
            ->get()
            ->map(function ($row) {
                $mappedRow = $this->mapRow((array) $row);
                $workOrderId = trim((string) ($mappedRow['id'] ?? ''));
                $start = $mappedRow['datum_kreiranja'] ?? null;

                if ($workOrderId === '' || $start === null) {
                    return null;
                }

                $displayNumber = $this->formatWorkOrderNumberForCalendar(
                    (string) ($mappedRow['broj_naloga'] ?? $workOrderId)
                );
                $status = (string) ($mappedRow['status'] ?? '');
                $bucket = $this->statusBucket($status) ?? 'planiran';

                return [
                    'id' => $workOrderId,
                    'title' => $displayNumber,
                    'start' => $start,
                    'allDay' => true,
                    'extendedProps' => [
                        'calendar' => $bucket,
                        'status' => $status,
                        'workOrderId' => $workOrderId,
                        'workOrderNumber' => (string) ($mappedRow['broj_naloga'] ?? $workOrderId),
                        'previewUrl' => route('app-invoice-preview', ['id' => $workOrderId]),
                    ],
                ];
            })
            ->filter(function ($event) {
                return $event !== null;
            })
            ->values()
            ->all();
    }

    private function fetchCalendarStatusStats(?string $startDate, ?string $endDate): array
    {
        $stats = $this->emptyStatusStats();
        $columns = $this->tableColumns();
        $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF']);
        $dateColumn = $this->firstExistingColumn($columns, ['adDate', 'adDateIns']);

        if ($statusColumn === null) {
            return $stats;
        }

        $query = $this->newTableQuery();

        if ($dateColumn !== null) {
            if ($startDate !== null) {
                $query->whereDate($dateColumn, '>=', $startDate);
            }

            if ($endDate !== null) {
                $query->whereDate($dateColumn, '<', $endDate);
            }
        }

        $stats['svi'] = (int) (clone $query)->count();

        $rows = (clone $query)
            ->select($statusColumn, DB::raw('COUNT(*) as total'))
            ->groupBy($statusColumn)
            ->get();

        foreach ($rows as $row) {
            $resolvedStatus = $this->resolveStatus($row->{$statusColumn} ?? null);
            $bucket = $resolvedStatus['bucket'] ?? null;

            if ($bucket !== null && array_key_exists($bucket, $stats)) {
                $stats[$bucket] += (int) $row->total;
            }
        }

        return $stats;
    }

    private function normalizeCalendarStatuses(mixed $statuses): array
    {
        $allowedBuckets = array_values(array_filter(array_keys($this->emptyStatusStats()), function ($bucket) {
            return $bucket !== 'svi';
        }));

        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }

        if (!is_array($statuses)) {
            return [];
        }

        $normalized = array_map(function ($status) {
            return strtolower(trim((string) $status));
        }, $statuses);

        return array_values(array_unique(array_filter($normalized, function ($status) use ($allowedBuckets) {
            return in_array($status, $allowedBuckets, true);
        })));
    }

    private function applyStatusBucketsFilter(Builder $query, ?string $statusColumn, array $statusBuckets): void
    {
        if ($statusColumn === null || empty($statusBuckets)) {
            return;
        }

        $statusAliases = [];

        foreach ($statusBuckets as $bucket) {
            $statusAliases = array_merge($statusAliases, $this->statusAliasesByBucket($bucket));
        }

        $statusAliases = array_values(array_unique($statusAliases));

        if (empty($statusAliases)) {
            return;
        }

        $query->whereIn($statusColumn, $statusAliases);
    }

    private function formatWorkOrderNumberForCalendar(string $workOrderNumber): string
    {
        $rawValue = trim($workOrderNumber);

        if ($rawValue === '') {
            return 'N/A';
        }

        if (str_contains($rawValue, '-')) {
            return $rawValue;
        }

        $digits = preg_replace('/\D+/', '', $rawValue);

        if (strlen($digits) === 13) {
            return substr($digits, 0, 2) . '-' . substr($digits, 2, 5) . '-' . substr($digits, 7);
        }

        return $rawValue;
    }

    private function fetchMappedWorkOrderItems(array $workOrderRow): array
    {
        $columns = $this->itemTableColumns();

        if (!in_array('acKey', $columns, true)) {
            return [];
        }

        $workOrderKey = trim((string) $this->value($workOrderRow, ['acKey'], ''));

        if ($workOrderKey === '') {
            return [];
        }

        $query = $this->newItemTableQuery()->where('acKey', $workOrderKey);

        foreach (['anNo', 'anVariant', 'adTimeIns'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy($column);
            }
        }

        return $query
            ->get()
            ->map(function ($row) {
                return $this->mapItemRow((array) $row);
            })
            ->values()
            ->all();
    }

    private function fetchMappedWorkOrderItemResources(array $workOrderRow): array
    {
        $columns = $this->itemResourcesTableColumns();
        $workOrderKey = trim((string) $this->value($workOrderRow, ['acKey'], ''));

        if ($workOrderKey === '') {
            return [];
        }

        $itemQIdColumn = $this->firstExistingColumn($columns, ['anWOExItemQId', 'anItemQId', 'anQIdItem']);
        $query = DB::table($this->qualifiedItemResourcesTableName() . ' as r')
            ->select('r.*');

        if ($itemQIdColumn !== null) {
            $itemQIdField = 'r.' . $itemQIdColumn;
            $itemQIds = $this->newItemTableQuery()
                ->where('acKey', $workOrderKey)
                ->pluck('anQId')
                ->map(function ($id) {
                    return is_numeric((string) $id) ? (int) $id : null;
                })
                ->filter(function ($id) {
                    return $id !== null;
                })
                ->values()
                ->all();

            if (empty($itemQIds)) {
                return [];
            }

            $query->whereIn($itemQIdField, $itemQIds)
                ->leftJoin($this->qualifiedItemTableName() . ' as i', 'i.anQId', '=', $itemQIdField)
                ->addSelect([
                    'i.anNo as __item_no',
                    'i.acIdent as __item_ident',
                    'i.acDescr as __item_descr',
                    'i.acUM as __item_um',
                    'i.anQty as __item_qty',
                    'i.anPlanQty as __item_plan_qty',
                ]);
        } else {
            $linkColumn = $this->firstExistingColumn($columns, ['acKey', 'acWOKey', 'acDocKey', 'acLnkKey']);

            if ($linkColumn === null) {
                return [];
            }

            $query->where('r.' . $linkColumn, $workOrderKey);
        }

        foreach (['anWOExItemQId', 'anNo', 'anLineNo', 'anResNo', 'anVariant', 'adTimeIns'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy('r.' . $column);
            }
        }

        return $query
            ->get()
            ->map(function ($row) {
                return $this->mapItemResourceRow((array) $row);
            })
            ->values()
            ->all();
    }

    private function fetchMappedWorkOrderRegOperations(array $workOrderRow): array
    {
        $columns = $this->regOperationsTableColumns();
        $linkColumn = $this->firstExistingColumn($columns, ['acKey', 'acWOKey', 'acDocKey', 'acLnkKey']);

        if ($linkColumn === null) {
            return [];
        }

        $workOrderKey = trim((string) $this->value($workOrderRow, ['acKey'], ''));

        if ($workOrderKey === '') {
            return [];
        }

        if ($linkColumn === null) {
            return $this->fetchMappedOperationsFromItems($workOrderKey);
        }

        $query = $this->newRegOperationsTableQuery()->where($linkColumn, $workOrderKey);

        foreach (['anNo', 'anVariant', 'adDate', 'adTimeIns'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy($column);
            }
        }

        $operations = $query
            ->get()
            ->map(function ($row) {
                return $this->mapRegOperationRow((array) $row);
            })
            ->values()
            ->all();

        if (!empty($operations)) {
            return $operations;
        }

        return $this->fetchMappedOperationsFromItems($workOrderKey);
    }

    private function fetchMappedOperationsFromItems(string $workOrderKey): array
    {
        $columns = $this->itemTableColumns();

        if (!in_array('acOperationType', $columns, true)) {
            return [];
        }

        $query = $this->newItemTableQuery()
            ->where('acKey', $workOrderKey)
            ->whereRaw("LTRIM(RTRIM(ISNULL(acOperationType, ''))) <> ''");

        foreach (['anNo', 'anVariant', 'adTimeIns'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy($column);
            }
        }

        return $query
            ->get()
            ->map(function ($row) {
                return $this->mapOperationFromItemRow((array) $row);
            })
            ->values()
            ->all();
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

        $status = $this->mapStatus($this->value($row, ['acStatusMF'], 'N/A'));
        $priority = $this->mapPriority($this->value($row, ['anPriority', 'acPriority', 'acWayOfSale', 'priority'], 5));
        $createdDate = $this->normalizeDate($this->value($row, ['adDate', 'adDateIns', 'created_at']));
        $endDate = $this->normalizeDate($this->value($row, ['adDeliveryDeadline', 'adDateOut', 'actual_end']));
        $rawAmount = $this->value($row, $this->moneyValueCandidates(), null);
        $amount = $this->normalizeNullableNumber($rawAmount);

        $mapped = [
            'responsive_id' => '',
            'id' => $id,
            'broj_naloga' => $brojNaloga,
            'naziv' => (string) $this->value($row, ['acName', 'acDescr', 'title'], 'Radni nalog'),
            'opis' => (string) $this->value($row, ['acNote', 'acStatement', 'acDescr', 'description'], ''),
            'status' => $status,
            'prioritet' => $priority,
            'datum_kreiranja' => $createdDate,
            'datum_zavrsetka' => $endDate,
            'dodeljen_korisnik' => (string) $this->value($row, ['anClerk', 'created_by', 'acUser'], ''),
            'klijent' => (string) $this->value($row, ['acConsignee', 'acReceiver', 'client_name', 'acPartner'], 'N/A'),
            'vrednost' => $amount,
            'valuta' => $amount === null ? '' : (string) $this->value($row, ['acCurrency', 'currency'], 'BAM'),
            'magacin' => (string) $this->value($row, ['acWarehouse', 'linked_document', 'acWarehouseFrom'], ''),
        ];

        if ($includeRaw) {
            $mapped['raw'] = $row;
        }

        return $mapped;
    }

    private function buildWorkOrderMetadata(
        array $raw,
        array $workOrder,
        array $workOrderItems,
        array $workOrderItemResources,
        array $workOrderRegOperations
    ): array {
        $unit = (string) $this->valueTrimmed($raw, ['acUM'], '');
        $planQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anPlanQty'], null));
        $producedQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anProducedQty'], null));
        $seriesQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anQtySeries'], null));
        $planWasteQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anPlanWasteQty'], null));
        $wasteQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anWasteQty'], null));
        $planScrapQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anPlanScrapQty'], null));
        $scrapQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anScrapQty'], null));
        $workTime = $this->toFloatOrNull($this->valueTrimmed($raw, ['anWorkTime'], null));
        $throTime = $this->toFloatOrNull($this->valueTrimmed($raw, ['anThroTime'], null));
        $repairQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anRepairQty'], null));

        $completionPercent = null;
        if ($planQty !== null && $producedQty !== null && abs($planQty) > 0.000001) {
            $completionPercent = ($producedQty / $planQty) * 100;
        }

        $itemTotal = count($workOrderItems);
        $finishedItems = count(array_filter($workOrderItems, function (array $item) {
            return strtolower(trim((string) ($item['zavrseno'] ?? ''))) === 'da';
        }));
        $itemsCompletionPercent = $itemTotal > 0 ? ($finishedItems / $itemTotal) * 100 : null;

        $progressPercent = $completionPercent ?? $itemsCompletionPercent ?? 0.0;
        $progressPercent = max(0.0, min(100.0, $progressPercent));
        $progressLabel = $completionPercent !== null ? 'Realizacija po količini' : 'Realizacija po završenim stavkama';

        $statusBucket = $this->statusBucket((string) ($workOrder['status'] ?? ''));
        $statusToneMap = [
            'planiran' => 'primary',
            'otvoren' => 'success',
            'rezerviran' => 'warning',
            'raspisan' => 'info',
            'u_radu' => 'warning',
            'djelimicno_zakljucen' => 'orange',
            'zakljucen' => 'danger',
        ];
        $statusTone = $statusBucket !== null ? ($statusToneMap[$statusBucket] ?? 'secondary') : 'secondary';

        $priorityCode = (int) ($this->toFloatOrNull($this->valueTrimmed($raw, ['anPriority'], null)) ?? 0);
        $priorityTone = 'warning';
        if ($priorityCode === 1) {
            $priorityTone = 'danger';
        } elseif ($priorityCode >= 10) {
            $priorityTone = 'info';
        }

        $highlights = array_values(array_filter([
            ['label' => 'Status', 'value' => (string) ($workOrder['status'] ?? 'N/A'), 'tone' => $statusTone],
            ['label' => 'Prioritet', 'value' => (string) ($workOrder['prioritet'] ?? 'N/A'), 'tone' => $priorityTone],
            ['label' => 'Tip dokumenta', 'value' => (string) $this->valueTrimmed($raw, ['acDocTypeView', 'acDocType'], ''), 'tone' => 'slate'],
            ['label' => 'Šifra proizvoda', 'value' => (string) $this->valueTrimmed($raw, ['acIdent'], ''), 'tone' => 'slate'],
            ['label' => 'Naziv proizvoda', 'value' => (string) $this->valueTrimmed($raw, ['acName'], ''), 'tone' => 'slate'],
            ['label' => 'Varijanta', 'value' => (string) $this->valueTrimmed($raw, ['acProdVariant', 'anVariant'], ''), 'tone' => 'slate'],
            ['label' => 'Lokacija', 'value' => (string) $this->valueTrimmed($raw, ['acLocation', 'acDept'], ''), 'tone' => 'slate'],
            ['label' => 'Plan ID', 'value' => (string) $this->valueTrimmed($raw, ['acPlanIDView', 'acPlanID'], ''), 'tone' => 'slate'],
        ], function (array $entry) {
            return trim((string) ($entry['value'] ?? '')) !== '';
        }));

        $kpis = [
            ['label' => 'Planirana količina', 'value' => $this->formatMetaNumber($planQty), 'unit' => $unit],
            ['label' => 'Izrađena količina', 'value' => $this->formatMetaNumber($producedQty), 'unit' => $unit],
            ['label' => 'Serija', 'value' => $this->formatMetaNumber($seriesQty), 'unit' => $unit],
            ['label' => 'Popravka', 'value' => $this->formatMetaNumber($repairQty), 'unit' => $unit],
            ['label' => 'Plan otpad', 'value' => $this->formatMetaNumber($planWasteQty), 'unit' => $unit],
            ['label' => 'Otpad', 'value' => $this->formatMetaNumber($wasteQty), 'unit' => $unit],
            ['label' => 'Plan skart', 'value' => $this->formatMetaNumber($planScrapQty), 'unit' => $unit],
            ['label' => 'Skart', 'value' => $this->formatMetaNumber($scrapQty), 'unit' => $unit],
            ['label' => 'Vrijeme rada', 'value' => $this->formatMetaNumber($workTime), 'unit' => 'h'],
            ['label' => 'Vrijeme protoka', 'value' => $this->formatMetaNumber($throTime), 'unit' => 'h'],
            ['label' => 'Stavke', 'value' => (string) $itemTotal, 'unit' => ''],
            ['label' => 'Materijali', 'value' => (string) count($workOrderItemResources), 'unit' => ''],
            ['label' => 'Operacije', 'value' => (string) count($workOrderRegOperations), 'unit' => ''],
        ];

        $timelineRows = [
            ['label' => 'Datum naloga', 'raw' => $this->value($raw, ['adDate'], null)],
            ['label' => 'Planirani start', 'raw' => $this->value($raw, ['adSchedStartTime'], null)],
            ['label' => 'Planirani kraj', 'raw' => $this->value($raw, ['adSchedEndTime'], null)],
            ['label' => 'Zavrsetak WO', 'raw' => $this->value($raw, ['adWOFinishDate'], null)],
            ['label' => 'Datum veze', 'raw' => $this->value($raw, ['adLnkDate'], null)],
            ['label' => 'Vrijeme unosa', 'raw' => $this->value($raw, ['adTimeIns'], null)],
            ['label' => 'Vrijeme izmjene', 'raw' => $this->value($raw, ['adTimeChg'], null)],
        ];
        $timeline = $this->sortTimelineRowsChronologically($timelineRows);

        $traceability = [
            ['label' => 'RN ključ', 'value' => (string) $this->valueTrimmed($raw, ['acKeyView', 'acKey'], '-')],
            ['label' => 'Vezni dokument', 'value' => (string) $this->valueTrimmed($raw, ['acLnkKeyView', 'acLnkKey'], '-')],
            ['label' => 'Vezni broj', 'value' => (string) $this->valueTrimmed($raw, ['anLnkNo'], '-')],
            ['label' => 'Nadređeni RN', 'value' => (string) $this->valueTrimmed($raw, ['acParentWOView', 'acParentWO'], '-')],
            ['label' => 'Nadređena količina', 'value' => $this->formatMetaNumber($this->toFloatOrNull($this->valueTrimmed($raw, ['anParentWOQty'], null)))],
            ['label' => 'QID', 'value' => (string) $this->valueTrimmed($raw, ['anQId'], '-')],
            ['label' => 'QID CA', 'value' => (string) $this->valueTrimmed($raw, ['anQIdCA'], '-')],
            ['label' => 'Korisnik unosa', 'value' => (string) $this->valueTrimmed($raw, ['anUserIns'], '-')],
            ['label' => 'Korisnik izmjene', 'value' => (string) $this->valueTrimmed($raw, ['anUserChg'], '-')],
            ['label' => 'Nosilac troška', 'value' => (string) $this->valueTrimmed($raw, ['acCostDrv'], '-')],
            ['label' => 'Izvor kreiranja', 'value' => (string) $this->valueTrimmed($raw, ['acCreateFrom'], '-')],
            ['label' => 'Tip kroja', 'value' => (string) $this->valueTrimmed($raw, ['acCropType'], '-')],
        ];

        $flags = [
            ['label' => 'Povrat', 'value' => $this->formatMetaFlag($this->valueTrimmed($raw, ['acReversal'], null)), 'tone' => $this->flagTone($this->valueTrimmed($raw, ['acReversal'], null))],
            ['label' => 'Prijem završen', 'value' => $this->formatMetaFlag($this->valueTrimmed($raw, ['acReceiveFinished'], null)), 'tone' => $this->flagTone($this->valueTrimmed($raw, ['acReceiveFinished'], null))],
            ['label' => 'SN transfer', 'value' => $this->formatMetaFlag($this->valueTrimmed($raw, ['anSNTransfer'], null)), 'tone' => $this->flagTone($this->valueTrimmed($raw, ['anSNTransfer'], null))],
        ];

        return [
            'highlights' => $highlights,
            'kpis' => $kpis,
            'timeline' => $timeline,
            'traceability' => $traceability,
            'flags' => $flags,
            'progress' => [
                'label' => $progressLabel,
                'percent' => $progressPercent,
                'display' => $this->formatMetaNumber($progressPercent, 1) . ' %',
            ],
        ];
    }

    private function sortTimelineRowsChronologically(array $rows): array
    {
        $sortable = [];

        foreach ($rows as $index => $row) {
            $timestamp = $this->metaDateTimestamp($row['raw'] ?? null);
            $sortable[] = [
                'label' => (string) ($row['label'] ?? ''),
                'raw' => $row['raw'] ?? null,
                'timestamp' => $timestamp,
                'index' => $index,
            ];
        }

        usort($sortable, static function (array $a, array $b): int {
            $aTs = $a['timestamp'];
            $bTs = $b['timestamp'];

            if ($aTs === null && $bTs === null) {
                return $a['index'] <=> $b['index'];
            }

            if ($aTs === null) {
                return 1;
            }

            if ($bTs === null) {
                return -1;
            }

            if ($aTs === $bTs) {
                return $a['index'] <=> $b['index'];
            }

            return $aTs <=> $bTs;
        });

        return array_map(function (array $row): array {
            return [
                'label' => $row['label'],
                'value' => $this->formatMetaDateTime($row['raw'] ?? null),
            ];
        }, $sortable);
    }

    private function metaDateTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $dateTime = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);

            return $dateTime->getTimestamp();
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function mapItemRow(array $row): array
    {
        $taskState = strtoupper(trim((string) $this->value($row, ['acTaskState'], '')));
        $isFinished = in_array($taskState, ['F', 'Z', 'C', 'D'], true);

        return [
            'id' => $this->value($row, ['anQId', 'anNo'], null),
            'ac_key' => trim((string) $this->value($row, ['acKey'], '')),
            'alternativa' => (string) $this->value($row, ['anVariant'], ''),
            'pozicija' => (string) $this->value($row, ['anNo'], ''),
            'artikal' => (string) $this->value($row, ['acIdent'], ''),
            'opis' => (string) $this->value($row, ['acDescr'], ''),
            'napomena' => (string) $this->value($row, ['acNote'], ''),
            'kolicina' => $this->normalizeNumber($this->value($row, ['anQty', 'anPlanQty'], 0)),
            'mj' => (string) $this->value($row, ['acUM'], ''),
            'serija' => $this->normalizeNumber($this->value($row, ['anQtySE', 'anBatch'], 0)),
            'normativna_osnova' => $this->normalizeNumber($this->value($row, ['anQtyBase', 'anQtyBase3'], 0)),
            'aktivno' => ((int) $this->value($row, ['anActive'], 0)) === 1 ? 'Da' : 'Ne',
            'zavrseno' => $isFinished ? 'Da' : 'Ne',
            'va' => (string) $this->value($row, ['acFieldSA', 'acFieldSE'], ''),
            'prim_klas' => (string) $this->value($row, ['acFieldSB'], ''),
            'sek_klas' => (string) $this->value($row, ['acFieldSC'], ''),
        ];
    }

    private function mapItemResourceRow(array $row): array
    {
        return [
            'id' => $this->value($row, ['anQId', 'anNo', 'anLineNo'], null),
            'item_qid' => $this->value($row, ['anWOExItemQId'], null),
            'pozicija' => (string) $this->valueTrimmed($row, ['anNo', 'anLineNo', 'anResNo', '__item_no'], ''),
            'materijal' => (string) $this->valueTrimmed($row, ['acResursID', 'acIdent', 'acResIdent', 'acResource', 'acCode', '__item_ident'], ''),
            'naziv' => (string) $this->valueTrimmed($row, ['acResType', 'acDescr', 'acName', 'acResDescr', '__item_descr', '__item_ident'], ''),
            'kolicina' => $this->normalizeNumber($this->valueTrimmed($row, ['anQty', 'anPlanQty', 'anNormQty', '__item_qty', '__item_plan_qty'], 0)),
            'mj' => (string) $this->valueTrimmed($row, ['acUM', 'acUMRes', '__item_um'], ''),
            'napomena' => (string) $this->valueTrimmed($row, ['acNote'], ''),
        ];
    }

    private function mapRegOperationRow(array $row): array
    {
        return [
            'id' => $this->value($row, ['anQId', 'anNo', 'anRegNo'], null),
            'alternativa' => (string) $this->value($row, ['anVariant', 'anVariantSubLvl'], ''),
            'pozicija' => (string) $this->value($row, ['anNo', 'anItemNo', 'anRegNo'], ''),
            'operacija' => (string) $this->value($row, ['acOperation', 'acOper', 'acOperationType', 'acIdent'], ''),
            'naziv' => (string) $this->value($row, ['acName', 'acDescr', 'acOperationName'], ''),
            'napomena' => (string) $this->value($row, ['acNote'], ''),
            'mj' => (string) $this->value($row, ['acUM', 'acUMTime'], ''),
            'mj_vrij' => $this->normalizeNumber($this->value($row, ['anQty', 'anWorkTime', 'anTime', 'anDuration'], 0)),
            'normativna_osnova' => $this->normalizeNumber($this->value($row, ['anNormQty', 'anQtyBase', 'anPlanQty'], 0)),
            'va' => (string) $this->value($row, ['acFieldSA', 'acVA'], ''),
            'prim_klas' => (string) $this->value($row, ['acFieldSB'], ''),
            'sek_klas' => (string) $this->value($row, ['acFieldSC'], ''),
        ];
    }

    private function mapOperationFromItemRow(array $row): array
    {
        $timeUnit = strtoupper((string) $this->valueTrimmed($row, ['acUMTime'], ''));
        $mjVrij = $timeUnit === 'H' ? 'Sat' : (string) $this->valueTrimmed($row, ['acUMTime'], '');
        $normative = $this->normalizeNumber($this->valueTrimmed($row, ['anBatch', 'anQtyBase', 'anQtyBase3'], 0));
        $va = (string) $this->valueTrimmed($row, ['acFieldSE', 'acFieldSA', 'acFieldSB'], '');

        if ($va === '') {
            $va = 'OPR';
        }

        return [
            'id' => $this->value($row, ['anQId', 'anNo'], null),
            'alternativa' => (string) $this->valueTrimmed($row, ['anVariant'], ''),
            'pozicija' => (string) $this->valueTrimmed($row, ['anNo'], ''),
            'operacija' => (string) $this->valueTrimmed($row, ['acIdent'], ''),
            'naziv' => (string) $this->valueTrimmed($row, ['acDescr', 'acName', 'acIdent'], ''),
            'napomena' => (string) $this->valueTrimmed($row, ['acNote'], ''),
            'mj' => (string) $this->valueTrimmed($row, ['acUM'], ''),
            'mj_vrij' => $mjVrij,
            'normativna_osnova' => $normative,
            'va' => $va,
            'prim_klas' => (string) $this->valueTrimmed($row, ['acFieldSB'], ''),
            'sek_klas' => (string) $this->valueTrimmed($row, ['acFieldSC'], ''),
        ];
    }

    private function fetchStatusStats(array $filters = []): array
    {
        $stats = $this->emptyStatusStats();
        $columns = $this->tableColumns();
        $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF']);

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
            $resolvedStatus = $this->resolveStatus($row->{$statusColumn} ?? null);
            $bucket = $resolvedStatus['bucket'] ?? null;

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
        $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF']);

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
        $allowedStatuses = array_filter(array_keys($this->emptyStatusStats()), function ($status) {
            return $status !== 'svi';
        });

        if ($normalized === '' || $normalized === 'svi' || !in_array($normalized, $allowedStatuses, true)) {
            return;
        }

        $statusAliases = $this->statusAliasesByBucket($normalized);

        if (empty($statusAliases)) {
            return;
        }

        $query->where(function (Builder $statusQuery) use ($column, $statusAliases) {
            $statusQuery->whereIn($column, $statusAliases);
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

    private function statusCodeMap(): array
    {
        return [
            'F' => ['label' => "Zavr\u{0161}eno", 'bucket' => 'zakljucen'],
            'P' => ['label' => 'U toku', 'bucket' => 'u_radu'],
            'I' => ['label' => "Zavr\u{0161}eno", 'bucket' => 'zakljucen'],
            'N' => ['label' => 'Novo', 'bucket' => 'planiran'],
            'C' => ['label' => 'Otkazano', 'bucket' => null],
            'D' => ['label' => 'Raspisan', 'bucket' => 'raspisan'],
            'O' => ['label' => 'Otvoren', 'bucket' => 'otvoren'],
            'R' => ['label' => "Djelimi\u{010D}no zavr\u{0161}eno", 'bucket' => 'djelimicno_zakljucen'],
            'S' => ['label' => 'Raspisan', 'bucket' => 'raspisan'],
            'E' => ['label' => 'U radu', 'bucket' => 'u_radu'],
            'Z' => ['label' => "Zavr\u{0161}eno", 'bucket' => 'zakljucen'],
        ];
    }

    private function statusBucket(string $status): ?string
    {
        $statusString = trim($status);

        if ($statusString === '' || strtolower($statusString) === 'n/a') {
            return null;
        }

        $statusCode = strtoupper($statusString);
        $statusMeta = $this->statusCodeMap()[$statusCode] ?? null;

        if ($statusMeta !== null) {
            return $statusMeta['bucket'] ?? null;
        }

        $normalized = strtolower($statusString);

        if ($normalized === 'planiran' || $normalized === 'novo') {
            return 'planiran';
        }

        if ($normalized === 'otvoren') {
            return 'otvoren';
        }

        if ($normalized === 'rezerviran') {
            return 'rezerviran';
        }

        if ($normalized === 'raspisan') {
            return 'raspisan';
        }

        if ($normalized === 'u toku' || $normalized === 'u radu') {
            return 'u_radu';
        }

        if ($normalized === "djelimi\u{010D}no zavr\u{0161}eno" || $normalized === 'djelimicno zavrseno') {
            return 'djelimicno_zakljucen';
        }

        if ($normalized === "zavr\u{0161}eno" || $normalized === 'zavrseno') {
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
        $statusCode = strtoupper($statusString);
        $statusMeta = $this->statusCodeMap()[$statusCode] ?? null;

        if ($statusMeta === null) {
            return $statusString;
        }

        return (string) ($statusMeta['label'] ?? $statusString);
    }

    private function resolveStatus(mixed $status): array
    {
        if ($status === null || $status === '') {
            return [
                'label' => 'N/A',
                'bucket' => null,
            ];
        }

        $statusString = trim((string) $status);
        $statusCode = strtoupper($statusString);
        $statusMeta = $this->statusCodeMap()[$statusCode] ?? null;

        if ($statusMeta !== null) {
            return [
                'label' => (string) ($statusMeta['label'] ?? $statusString),
                'bucket' => $statusMeta['bucket'] ?? null,
            ];
        }

        $label = $this->mapStatus($statusString);

        return [
            'label' => $label,
            'bucket' => $this->statusBucket($label),
        ];
    }

    private function statusAliasesByBucket(string $bucket): array
    {
        return array_values(array_keys(array_filter($this->statusCodeMap(), function ($statusMeta) use ($bucket) {
            return ($statusMeta['bucket'] ?? null) === $bucket;
        })));
    }

    private function mapPriority(mixed $priority): string
    {
        $priorityMap = $this->deliveryPriorityMap();

        if ($priority === null || $priority === '') {
            return $priorityMap[5] ?? '5 - Uobičajeni prioritet';
        }

        if (is_int($priority) || is_float($priority) || is_numeric((string) $priority)) {
            $priorityCode = (int) $priority;
            return $priorityMap[$priorityCode] ?? (string) $priorityCode;
        }

        $priorityString = trim((string) $priority);
        $normalizedPriority = strtoupper($priorityString);

        if (preg_match('/^\d+\s*-\s*/', $priorityString) === 1) {
            return $priorityString;
        }

        $legacyMap = [
            'V' => 1,
            'Z' => 1,
            'VISOK' => 1,
            'HIGH' => 1,
            'S' => 5,
            'M' => 5,
            'SREDNJI' => 5,
            'MEDIUM' => 5,
            'D' => 10,
            'N' => 10,
            'NIZAK' => 10,
            'LOW' => 10,
        ];

        if (array_key_exists($normalizedPriority, $legacyMap)) {
            $priorityCode = $legacyMap[$normalizedPriority];
            return $priorityMap[$priorityCode] ?? (string) $priorityCode;
        }

        return $priorityString;
    }

    private function deliveryPriorityMap(): array
    {
        if ($this->deliveryPriorityMap !== null) {
            return $this->deliveryPriorityMap;
        }

        $fallbackMap = [
            1 => '1 - Visoki prioritet',
            5 => '5 - Uobičajeni prioritet',
            10 => '10 - Niski prioritet',
            15 => '15 - Uzorci',
        ];

        try {
            $rows = DB::table($this->tableSchema() . '.tHE_SetDeliveryPriority')
                ->select(['anPriority', 'acPriority', 'acName', 'abActive'])
                ->where('abActive', 1)
                ->orderBy('anPriority')
                ->get();

            $mapped = $rows
                ->mapWithKeys(function ($row) {
                    $code = (int) ($row->anPriority ?? 0);
                    $label = trim((string) ($row->acPriority ?? ''));

                    if ($label === '') {
                        $name = trim((string) ($row->acName ?? ''));
                        $label = $name !== '' ? ($code . ' - ' . $name) : (string) $code;
                    }

                    return [$code => $label];
                })
                ->all();

            $this->deliveryPriorityMap = !empty($mapped)
                ? array_replace($fallbackMap, $mapped)
                : $fallbackMap;
        } catch (Throwable $exception) {
            $this->deliveryPriorityMap = $fallbackMap;
        }

        return $this->deliveryPriorityMap;
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

    private function itemTableColumns(): array
    {
        return DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->itemTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();
    }

    private function itemResourcesTableColumns(): array
    {
        return DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->itemResourcesTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();
    }

    private function regOperationsTableColumns(): array
    {
        return DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->regOperationsTableName())
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

    private function valueTrimmed(array $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);

                if ($value === '') {
                    continue;
                }

                return $value;
            }

            return $value;
        }

        return $default;
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = trim(str_replace(',', '.', $value));

            if ($normalized === '' || !is_numeric($normalized)) {
                return null;
            }

            return (float) $normalized;
        }

        if (is_numeric((string) $value)) {
            return (float) $value;
        }

        return null;
    }

    private function formatMetaNumber(?float $value, int $precision = 3): string
    {
        if ($value === null) {
            return '-';
        }

        $formatted = number_format($value, $precision, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        if ($formatted === '-0') {
            return '0';
        }

        return $formatted;
    }

    private function formatMetaDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        try {
            $dateTime = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);

            if ($dateTime->format('H:i:s') === '00:00:00') {
                return $dateTime->format('d.m.Y');
            }

            return $dateTime->format('d.m.Y H:i');
        } catch (Throwable $exception) {
            return '-';
        }
    }

    private function formatMetaFlag(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $normalized = strtoupper(trim((string) $value));

        if (in_array($normalized, ['Y', 'D', '1', 'TRUE', 'T'], true)) {
            return 'Da';
        }

        if (in_array($normalized, ['N', '0', 'FALSE', 'F'], true)) {
            return 'Ne';
        }

        return (string) $value;
    }

    private function flagTone(mixed $value): string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));

        if (in_array($normalized, ['Y', 'D', '1', 'TRUE', 'T'], true)) {
            return 'success';
        }

        if (in_array($normalized, ['N', '0', 'FALSE', 'F'], true)) {
            return 'secondary';
        }

        return 'info';
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

    private function normalizeNullableNumber(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeNumber($value);
    }

    private function moneyValueCandidates(): array
    {
        return [
            'anValue',
            'anDocValue',
            'total',
            'anAmount',
            'anTotal',
            'anNetValue',
            'anGrossValue',
            'anPrcValue',
            'anPrice',
        ];
    }

    private function monetaryColumns(array $columns): array
    {
        return $this->existingColumns($columns, $this->moneyValueCandidates());
    }

    private function hasMonetaryValues(Builder $query, array $moneyColumns): bool
    {
        if (empty($moneyColumns)) {
            return false;
        }

        $valueQuery = clone $query;
        $valueQuery->where(function (Builder $amountQuery) use ($moneyColumns) {
            foreach ($moneyColumns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';

                $amountQuery->{$method}(function (Builder $singleColumnQuery) use ($column) {
                    $singleColumnQuery
                        ->whereNotNull($column)
                        ->where($column, '<>', 0);
                });
            }
        });

        return $valueQuery->exists();
    }

    private function newTableQuery(): Builder
    {
        return DB::table($this->qualifiedTableName());
    }

    private function newItemTableQuery(): Builder
    {
        return DB::table($this->qualifiedItemTableName());
    }

    private function newItemResourcesTableQuery(): Builder
    {
        return DB::table($this->qualifiedItemResourcesTableName());
    }

    private function newRegOperationsTableQuery(): Builder
    {
        return DB::table($this->qualifiedRegOperationsTableName());
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

    private function itemTableName(): string
    {
        return (string) config('workorders.items_table', 'tHF_WOExItem');
    }

    private function itemResourcesTableName(): string
    {
        return (string) config('workorders.item_resources_table', 'tHF_WOExItemResources');
    }

    private function regOperationsTableName(): string
    {
        return (string) config('workorders.reg_operations_table', 'tHF_WOExRegOper');
    }

    private function qualifiedTableName(): string
    {
        return $this->tableSchema() . '.' . $this->tableName();
    }

    private function qualifiedItemTableName(): string
    {
        return $this->tableSchema() . '.' . $this->itemTableName();
    }

    private function qualifiedItemResourcesTableName(): string
    {
        return $this->tableSchema() . '.' . $this->itemResourcesTableName();
    }

    private function qualifiedRegOperationsTableName(): string
    {
        return $this->tableSchema() . '.' . $this->regOperationsTableName();
    }
}
