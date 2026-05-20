<?php

namespace App\Http\Controllers;

use App\Models\OrderAiScan;
use App\Models\Order;
use App\Services\OrderAi\PantheonOrderTransferService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderController extends WorkOrderController
{
    public function ordersLinkageIndex(Request $request)
    {
        return parent::ordersLinkageIndex($request);
    }

    public function ordersLinkageData(Request $request): JsonResponse
    {
        if (!$this->canAccessOrdersList($request->user())) {
            return response()->json([
                'message' => 'Nemate dozvolu za pristup upravljanju narudžbama.',
                'data' => [],
                'meta' => [
                    'count' => 0,
                    'page' => 1,
                    'limit' => $this->resolveOrdersListLimit((int) $request->input('limit', $request->input('length', 10))),
                    'total' => 0,
                    'filtered_total' => 0,
                    'last_page' => 1,
                ],
            ], 403);
        }

        try {
            $requestedLimit = (int) $request->input('limit', $request->input('length', 10));
            $resolvedLimit = $this->resolveOrdersListLimit($requestedLimit);
            $requestedPage = (int) $request->input('page', 0);
            $requestedStart = (int) $request->input('start', 0);

            if ($requestedPage < 1) {
                $requestedPage = (int) floor(max(0, $requestedStart) / $resolvedLimit) + 1;
            }

            $requestedPage = max(1, $requestedPage);
            $filters = $this->extractOrdersListFilters($request);
            $sort = $this->extractOrdersListSort($request);

            $total = (int) $this->newOrdersListBaseQuery()->count();
            $filteredTotal = (int) $this->newOrdersListFilteredQuery($filters)->count();
            $filteredQuery = $this->newOrdersListQuery($filters);
            $lastPage = $resolvedLimit > 0 ? max(1, (int) ceil($filteredTotal / $resolvedLimit)) : 1;

            $this->applyOrdersListSort($filteredQuery, $sort);

            $rows = $filteredQuery
                ->forPage($requestedPage, $resolvedLimit)
                ->get()
                ->map(function ($row) {
                    $rawOrderNumber = $this->resolveOrdersListDisplayNumber(
                        $row->narudzbaRaw ?? '',
                        $row->narudzbaView ?? ''
                    );
                    $workOrderCount = max(0, (int) ($row->brojRN ?? 0));

                    return [
                        'narudzba' => $rawOrderNumber,
                        'order_number' => $rawOrderNumber,
                        'klijent' => trim((string) ($row->narucitelj ?? '')),
                        'narucitelj' => trim((string) ($row->narucitelj ?? '')),
                        'prijevoznik' => trim((string) ($row->prijevoznik ?? '')),
                        'datum' => $this->normalizeOrdersListDate($row->datum ?? null),
                        'totalKolicina' => $row->totalKolicina !== null ? (float) $row->totalKolicina : 0.0,
                        'jedinica' => trim((string) ($row->jedinica ?? '')),
                        'brojPozicija' => max(0, (int) ($row->brojPozicija ?? 0)),
                        'brojRN' => $workOrderCount,
                        'linkage_state' => $workOrderCount > 0 ? 'linked' : 'missing',
                        'linkage_label' => $workOrderCount > 0 ? 'Povezano' : 'Bez RN',
                        'linkage_tone' => $workOrderCount > 0 ? 'success' : 'secondary',
                    ];
                })
                ->values()
                ->all();

            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'data' => $rows,
                'meta' => [
                    'count' => count($rows),
                    'page' => $requestedPage,
                    'limit' => $resolvedLimit,
                    'total' => $total,
                    'filtered_total' => $filteredTotal,
                    'last_page' => $lastPage,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Order list query failed.', [
                'message' => $exception->getMessage(),
                'filters' => $request->except(['_token']),
            ]);

            return response()->json([
                'message' => 'Greška pri učitavanju narudžbi.',
                'data' => [],
                'meta' => [
                    'count' => 0,
                    'page' => 1,
                    'limit' => $this->resolveOrdersListLimit((int) $request->input('limit', $request->input('length', 10))),
                    'total' => 0,
                    'filtered_total' => 0,
                    'last_page' => 1,
                ],
            ], 500);
        }
    }

    public function ordersLinkagePositions(Request $request)
    {
        return parent::ordersLinkagePositions($request);
    }

    public function ordersLinkageWorkOrders(Request $request)
    {
        return parent::ordersLinkageWorkOrders($request);
    }

    public function ordersLinkageWorkOrdersApi(Request $request): JsonResponse
    {
        return parent::ordersLinkageWorkOrdersApi($request);
    }

    public function destroyLinkedOrder(Request $request): JsonResponse
    {
        return parent::destroyLinkedOrder($request);
    }

    public function inspectMaintenance(Request $request): JsonResponse
    {
        if (!app()->environment('local') || !$this->hasValidMaintenanceToken($request)) {
            return response()->json([
                'message' => 'Not authorized.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'doc_type' => ['nullable', 'string', 'max:16'],
            'order_key' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid maintenance inspect request.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $docType = strtoupper(trim((string) ($validated['doc_type'] ?? config('ai-order-scan.default_doc_type', '0110'))));
        $orderKey = trim((string) ($validated['order_key'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 10);

        try {
            $workOrdersTable = (string) config('workorders.schema', 'dbo') . '.' . (string) config('workorders.table', 'tHF_WOEx');
            $ordersQuery = DB::connection('sqlsrv')
                ->table(Order::qualifiedSourceTableName() . ' as ord')
                ->select([
                    'ord.acKey',
                    'ord.acKeyView',
                    'ord.acDocType',
                    'ord.acConsignee',
                    'ord.acReceiver',
                    'ord.acCurrency',
                    'ord.anValue',
                    'ord.anForPay',
                    'ord.adDate',
                ])
                ->selectRaw(
                    '(SELECT COUNT(*) FROM ' . Order::qualifiedItemTableName() . ' item WHERE item.acKey = ord.acKey) as item_count'
                );

            if ($orderKey !== '') {
                $ordersQuery->where(function ($query) use ($orderKey) {
                    $query
                        ->where('ord.acKey', $orderKey)
                        ->orWhere('ord.acKeyView', $orderKey);
                });
            } else {
                $ordersQuery->where('ord.acDocType', $docType);
            }

            $recentOrders = $ordersQuery
                ->orderByDesc('ord.acKey')
                ->limit($limit)
                ->get()
                ->map(function ($row) use ($workOrdersTable) {
                    $orderKey = (string) ($row->acKey ?? '');
                    $workOrderCount = (int) DB::connection('sqlsrv')
                        ->table($workOrdersTable)
                        ->where('acLnkKey', $orderKey)
                        ->count();

                    return [
                        'acKey' => $orderKey,
                        'acKeyView' => (string) ($row->acKeyView ?? ''),
                        'acDocType' => (string) ($row->acDocType ?? ''),
                        'acConsignee' => (string) ($row->acConsignee ?? ''),
                        'acReceiver' => (string) ($row->acReceiver ?? ''),
                        'acCurrency' => (string) ($row->acCurrency ?? ''),
                        'anValue' => $row->anValue,
                        'anForPay' => $row->anForPay,
                        'adDate' => $row->adDate,
                        'item_count' => (int) ($row->item_count ?? 0),
                        'work_order_count' => $workOrderCount,
                        'visible_in_order_list' => $workOrderCount > 0,
                    ];
                })
                ->values()
                ->all();

            $orderListFilters = $orderKey !== '' ? ['search' => $orderKey] : [];
            $orderListPreviewQuery = $this->newOrdersListQuery($orderListFilters);
            $this->applyOrdersListSort($orderListPreviewQuery, ['by' => 'datum', 'dir' => 'desc']);

            $orderListPreviewRows = $orderListPreviewQuery
                ->limit($limit)
                ->get()
                ->map(function ($row) {
                    $displayOrderNumber = $this->resolveOrdersListDisplayNumber(
                        $row->narudzbaRaw ?? '',
                        $row->narudzbaView ?? ''
                    );

                    return [
                        'narudzba' => $displayOrderNumber,
                        'narucitelj' => (string) ($row->narucitelj ?? ''),
                        'prijevoznik' => (string) ($row->prijevoznik ?? ''),
                        'datum' => $this->normalizeOrdersListDate($row->datum ?? null),
                        'totalKolicina' => $row->totalKolicina !== null ? (float) $row->totalKolicina : 0.0,
                        'jedinica' => (string) ($row->jedinica ?? ''),
                        'brojPozicija' => (int) ($row->brojPozicija ?? 0),
                        'brojRN' => (int) ($row->brojRN ?? 0),
                    ];
                })
                ->values()
                ->all();

            $recentScans = OrderAiScan::query()
                ->orderByDesc('id')
                ->limit($limit)
                ->get([
                    'id',
                    'status',
                    'source_file_name',
                    'pantheon_order_key',
                    'pantheon_order_view',
                    'processing_step',
                    'transferred_at',
                    'created_at',
                ])
                ->map(function (OrderAiScan $scan) {
                    return [
                        'id' => (int) $scan->id,
                        'status' => (string) $scan->status,
                        'source_file_name' => (string) ($scan->source_file_name ?? ''),
                        'pantheon_order_key' => $scan->pantheon_order_key,
                        'pantheon_order_view' => $scan->pantheon_order_view,
                        'processing_step' => (string) ($scan->processing_step ?? ''),
                        'transferred_at' => optional($scan->transferred_at)->toDateTimeString(),
                        'created_at' => optional($scan->created_at)->toDateTimeString(),
                    ];
                })
                ->values()
                ->all();

            return response()->json([
                'message' => 'Maintenance inspect data loaded.',
                'data' => [
                    'doc_type' => $docType,
                    'recent_orders' => $recentOrders,
                    'order_list_preview' => $orderListPreviewRows,
                    'recent_scans' => $recentScans,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Local maintenance inspect failed.', [
                'doc_type' => $docType,
                'order_key' => $orderKey,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Maintenance inspect failed.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function destroyByKeyMaintenance(Request $request): JsonResponse
    {
        if (!app()->environment('local') || !$this->hasValidMaintenanceToken($request)) {
            return response()->json([
                'message' => 'Not authorized.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'order_key' => ['required', 'string', 'max:255'],
            'scan_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid maintenance delete request.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $orderKey = trim((string) ($validated['order_key'] ?? ''));

        if ($orderKey === '') {
            return response()->json([
                'message' => 'Order key is required.',
            ], 422);
        }

        try {
            $deleted = DB::connection('sqlsrv')->transaction(function () use ($orderKey) {
                $deletedItems = DB::connection('sqlsrv')
                    ->table(Order::qualifiedItemTableName())
                    ->where('acKey', $orderKey)
                    ->delete();

                $deletedOrders = DB::connection('sqlsrv')
                    ->table(Order::qualifiedSourceTableName())
                    ->where('acKey', $orderKey)
                    ->delete();

                return [
                    'order_items' => $deletedItems,
                    'orders' => $deletedOrders,
                ];
            }, 3);

            if (!empty($validated['scan_id'])) {
                $scan = OrderAiScan::query()->find((int) $validated['scan_id']);

                if ($scan !== null) {
                    $scan->forceFill([
                        'status' => 'completed',
                        'processing_step' => 'Pogresno kreirana Pantheon narudzba je obrisana. Spremno za novi transfer.',
                        'pantheon_order_key' => null,
                        'pantheon_order_view' => null,
                        'pantheon_order_qid' => null,
                        'transferred_at' => null,
                        'completed_at' => now(),
                    ])->save();
                }
            }

            $defaultDocType = (string) config('ai-order-scan.default_doc_type', '0110');
            $lastOrder = DB::connection('sqlsrv')
                ->table(Order::qualifiedSourceTableName())
                ->select('acKey', 'acKeyView', 'acDocType')
                ->where('acDocType', $defaultDocType)
                ->orderByDesc('acKey')
                ->first();

            Log::info('Local maintenance order delete executed.', [
                'order_key' => $orderKey,
                'deleted' => $deleted,
                'scan_id' => $validated['scan_id'] ?? null,
                'last_order' => $lastOrder,
            ]);

            return response()->json([
                'message' => 'Order cleanup completed.',
                'data' => [
                    'order_key' => $orderKey,
                    'deleted' => $deleted,
                    'last_order' => $lastOrder,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Local maintenance order delete failed.', [
                'order_key' => $orderKey,
                'scan_id' => $validated['scan_id'] ?? null,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Order cleanup failed.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request, PantheonOrderTransferService $transferService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'scan_id' => ['nullable', 'integer', 'min:1'],
            'payload' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravan zahtjev za kreiranje narudžbe.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $scan = null;
        $normalizedPayload = [];

        if (!empty($validated['scan_id'])) {
            $scan = OrderAiScan::query()->find((int) $validated['scan_id']);

            if ($scan === null) {
                return response()->json([
                    'message' => 'AI sken nije pronađen.',
                ], 404);
            }

            $this->authorizeScanAccess($request, $scan);

            if ($scan->pantheon_order_key) {
                return response()->json([
                    'message' => 'Narudžba je već prebačena u Pantheon.',
                    'data' => [
                        'pantheon_order_key' => (string) $scan->pantheon_order_key,
                        'pantheon_order_view' => (string) ($scan->pantheon_order_view ?? ''),
                        'pantheon_order_qid' => $scan->pantheon_order_qid,
                    ],
                ]);
            }

            $normalizedPayload = is_array($scan->normalized_payload) ? $scan->normalized_payload : [];
        } elseif (is_array($validated['payload'] ?? null)) {
            $normalizedPayload = $validated['payload'];
        }

        if (empty($normalizedPayload)) {
            return response()->json([
                'message' => 'Nema pripremljenog payloada za kreiranje narudžbe.',
            ], 422);
        }

        $result = $transferService->createFromNormalizedPayload($normalizedPayload, $request->user());

        if ($scan !== null) {
            $scan->forceFill([
                'status' => 'transferred',
                'processing_step' => 'Narudžba je ručno prebačena u Pantheon.',
                'progress_current' => 100,
                'pantheon_transfer_payload' => $result,
                'pantheon_order_key' => $result['pantheon_order_key'] ?? null,
                'pantheon_order_view' => $result['pantheon_order_view'] ?? null,
                'pantheon_order_qid' => $result['pantheon_order_qid'] ?? null,
                'transferred_at' => now(),
                'completed_at' => now(),
                'error_message' => null,
            ])->save();
        }

        return response()->json([
            'message' => 'Narudžba je uspješno kreirana u Pantheonu.',
            'data' => [
                'pantheon_order_key' => $result['pantheon_order_key'] ?? '',
                'pantheon_order_view' => $result['pantheon_order_view'] ?? '',
                'pantheon_order_qid' => $result['pantheon_order_qid'] ?? null,
                'item_count' => $result['item_count'] ?? 0,
            ],
        ], 201);
    }

    protected function orderTableName(): string
    {
        return Order::sourceTableName();
    }

    protected function orderItemTableName(): string
    {
        return Order::sourceItemTableName();
    }

    protected function workOrderOrderItemLinkTableName(): string
    {
        return Order::sourceLinkTableName();
    }

    protected function orderTableColumns(): array
    {
        return Order::sourceColumns();
    }

    protected function orderItemTableColumns(): array
    {
        return Order::itemColumns();
    }

    protected function workOrderOrderItemLinkTableColumns(): array
    {
        return Order::linkColumns();
    }

    protected function newOrderTableQuery(): Builder
    {
        return Order::newSourceQuery();
    }

    protected function newOrderItemTableQuery(): Builder
    {
        return Order::newItemQuery();
    }

    protected function newWorkOrderOrderItemLinkTableQuery(): Builder
    {
        return Order::newLinkQuery();
    }

    protected function qualifiedOrderTableName(): string
    {
        return Order::qualifiedSourceTableName();
    }

    protected function qualifiedOrderItemTableName(): string
    {
        return Order::qualifiedItemTableName();
    }

    protected function qualifiedWorkOrderOrderItemLinkTableName(): string
    {
        return Order::qualifiedLinkTableName();
    }

    private function newOrdersListBaseQuery(): Builder
    {
        $query = DB::connection('sqlsrv')
            ->table(Order::qualifiedSourceTableName() . ' as ord')
            ->whereRaw("NULLIF(LTRIM(RTRIM(ISNULL(ord.acKey, ''))), '') <> ''");

        $docType = $this->ordersListDocumentType();

        if ($docType !== '' && $this->ordersListColumnExists('acDocType')) {
            $query->where('ord.acDocType', $docType);
        }

        return $query;
    }

    private function newOrdersListFilteredQuery(array $filters): Builder
    {
        $query = $this->newOrdersListBaseQuery();
        $this->applyOrdersListFilters($query, $filters);

        return $query;
    }

    private function newOrdersListQuery(array $filters): Builder
    {
        $query = $this->newOrdersListFilteredQuery($filters);
        $itemAggregateQuery = $this->newOrdersListItemAggregateQuery();
        $workOrderAggregateQuery = $this->newOrdersListWorkOrderAggregateQuery();
        $orderComparableExpression = $this->canonicalOrdersListComparableExpression(
            "COALESCE(NULLIF(LTRIM(RTRIM(ISNULL(ord.acKeyView, ''))), ''), NULLIF(LTRIM(RTRIM(ISNULL(ord.acKey, ''))), ''))"
        );

        $query->leftJoinSub($itemAggregateQuery, 'item_agg', function ($join) {
            $join->on('item_agg.order_key', '=', 'ord.acKey');
        });

        $query->leftJoinSub($workOrderAggregateQuery, 'wo_agg', function ($join) use ($orderComparableExpression) {
            $join->whereRaw('wo_agg.order_number_key = ' . $orderComparableExpression);
        });

        return $query
            ->selectRaw("NULLIF(LTRIM(RTRIM(ISNULL(ord.acKey, ''))), '') as narudzbaRaw")
            ->selectRaw("NULLIF(LTRIM(RTRIM(ISNULL(ord.acKeyView, ''))), '') as narudzbaView")
            ->selectRaw("COALESCE(NULLIF(LTRIM(RTRIM(ISNULL(ord.acConsignee, ''))), ''), NULLIF(LTRIM(RTRIM(ISNULL(ord.acReceiver, ''))), ''), '') as narucitelj")
            ->selectRaw("COALESCE(NULLIF(LTRIM(RTRIM(ISNULL(ord.acReceiver, ''))), ''), NULLIF(LTRIM(RTRIM(ISNULL(ord.acConsignee, ''))), ''), '') as prijevoznik")
            ->selectRaw('CAST(COALESCE(wo_agg.datumRN, CAST(ord.adDate as date)) as date) as datum')
            ->selectRaw('COALESCE(item_agg.totalKolicina, 0) as totalKolicina')
            ->selectRaw("COALESCE(item_agg.jedinica, '') as jedinica")
            ->selectRaw('COALESCE(item_agg.brojPozicija, 0) as brojPozicija')
            ->selectRaw('COALESCE(wo_agg.brojRN, 0) as brojRN');
    }

    private function newOrdersListItemAggregateQuery(): Builder
    {
        return DB::connection('sqlsrv')
            ->table(Order::qualifiedItemTableName() . ' as oi')
            ->whereRaw("NULLIF(LTRIM(RTRIM(ISNULL(oi.acKey, ''))), '') <> ''")
            ->selectRaw("NULLIF(LTRIM(RTRIM(ISNULL(oi.acKey, ''))), '') as order_key")
            ->selectRaw('COUNT(*) as brojPozicija')
            ->selectRaw('COALESCE(SUM(COALESCE(oi.anQty, 0)), 0) as totalKolicina')
            ->selectRaw("COALESCE(MAX(NULLIF(LTRIM(RTRIM(ISNULL(oi.acUM, ''))), '')), '') as jedinica")
            ->groupBy('oi.acKey');
    }

    private function newOrdersListWorkOrderAggregateQuery(): Builder
    {
        $workOrdersTable = (string) config('workorders.schema', 'dbo') . '.' . (string) config('workorders.table', 'tHF_WOEx');
        $relationExpression = $this->canonicalOrdersListComparableExpression(
            "COALESCE(NULLIF(LTRIM(RTRIM(ISNULL(wo.acLnkKey, ''))), ''), NULLIF(LTRIM(RTRIM(ISNULL(wo.acLnkKeyView, ''))), ''))"
        );

        return DB::connection('sqlsrv')
            ->table($workOrdersTable . ' as wo')
            ->whereRaw($relationExpression . " <> ''")
            ->selectRaw($relationExpression . ' as order_number_key')
            ->selectRaw('COUNT(*) as brojRN')
            ->selectRaw('MIN(CAST(wo.adDate as date)) as datumRN')
            ->groupByRaw($relationExpression);
    }

    private function applyOrdersListFilters(Builder $query, array $filters): void
    {
        $orderItemsTable = Order::qualifiedItemTableName();
        $kupac = trim((string) ($filters['kupac'] ?? ''));
        $primatelj = trim((string) ($filters['primatelj'] ?? ''));
        $proizvod = trim((string) ($filters['proizvod'] ?? ''));
        $vezniDok = trim((string) ($filters['vezni_dok'] ?? ''));
        $prioritet = trim((string) ($filters['prioritet'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        if ($kupac !== '') {
            $query->where(function (Builder $customerQuery) use ($kupac) {
                $customerQuery
                    ->where('ord.acConsignee', 'like', '%' . $kupac . '%')
                    ->orWhere('ord.acReceiver', 'like', '%' . $kupac . '%');
            });
        }

        if ($primatelj !== '') {
            $query->where(function (Builder $receiverQuery) use ($primatelj) {
                $receiverQuery
                    ->where('ord.acReceiver', 'like', '%' . $primatelj . '%')
                    ->orWhere('ord.acConsignee', 'like', '%' . $primatelj . '%');
            });
        }

        if ($vezniDok !== '') {
            $documentSearchVariants = $this->buildOrdersListOrderNumberSearchVariants($vezniDok);

            $query->where(function (Builder $documentQuery) use ($documentSearchVariants) {
                $this->applyOrdersListOrderNumberVariantSearch($documentQuery, $documentSearchVariants);
            });
        }

        $priorityColumn = $this->ordersListPriorityColumn();

        if ($prioritet !== '' && $priorityColumn !== null) {
            $query->where('ord.' . $priorityColumn, $prioritet);
        }

        $planStartColumn = $this->ordersListFirstExistingColumn(['adDate', 'adDateIns']);
        $planEndColumn = $this->ordersListFirstExistingColumn(['adDeliveryDeadline', 'adDateValid', 'adDateOut', 'adDateDoc']);
        $dateColumn = $this->ordersListFirstExistingColumn(['adDate', 'adDateIns']);

        $this->applyOrdersListDateRange(
            $query,
            $planStartColumn !== null ? 'ord.' . $planStartColumn : null,
            (string) ($filters['plan_pocetak_od'] ?? ''),
            (string) ($filters['plan_pocetak_do'] ?? '')
        );
        $this->applyOrdersListDateRange(
            $query,
            $planEndColumn !== null ? 'ord.' . $planEndColumn : null,
            (string) ($filters['plan_kraj_od'] ?? ''),
            (string) ($filters['plan_kraj_do'] ?? '')
        );
        $this->applyOrdersListDateRange(
            $query,
            $dateColumn !== null ? 'ord.' . $dateColumn : null,
            (string) ($filters['datum_od'] ?? ''),
            (string) ($filters['datum_do'] ?? '')
        );

        if ($proizvod !== '') {
            $query->whereExists(function (Builder $itemQuery) use ($orderItemsTable, $proizvod) {
                $itemQuery
                    ->selectRaw('1')
                    ->from($orderItemsTable . ' as oi')
                    ->whereColumn('oi.acKey', 'ord.acKey')
                    ->where(function (Builder $productQuery) use ($proizvod) {
                        $productQuery
                            ->where('oi.acIdent', 'like', '%' . $proizvod . '%')
                            ->orWhere('oi.acName', 'like', '%' . $proizvod . '%');
                    });
            });
        }

        if ($search !== '') {
            $orderNumberSearchVariants = $this->buildOrdersListOrderNumberSearchVariants($search);

            $query->where(function (Builder $searchQuery) use ($orderItemsTable, $search, $orderNumberSearchVariants) {
                $searchQuery
                    ->where('ord.acConsignee', 'like', '%' . $search . '%')
                    ->orWhere('ord.acReceiver', 'like', '%' . $search . '%')
                    ->orWhereExists(function (Builder $itemQuery) use ($orderItemsTable, $search) {
                        $itemQuery
                            ->selectRaw('1')
                            ->from($orderItemsTable . ' as oi')
                            ->whereColumn('oi.acKey', 'ord.acKey')
                            ->where(function (Builder $productQuery) use ($search) {
                                $productQuery
                                    ->where('oi.acIdent', 'like', '%' . $search . '%')
                                    ->orWhere('oi.acName', 'like', '%' . $search . '%');
                            });
                    });

                if (!empty($orderNumberSearchVariants)) {
                    $searchQuery->orWhere(function (Builder $orderNumberQuery) use ($orderNumberSearchVariants) {
                        $this->applyOrdersListOrderNumberVariantSearch($orderNumberQuery, $orderNumberSearchVariants);
                    });
                }
            });
        }
    }

    private function applyOrdersListDateRange(Builder $query, ?string $column, string $from, string $to): void
    {
        if ($column === null) {
            return;
        }

        $from = trim($from);
        $to = trim($to);

        if ($from !== '') {
            $query->whereRaw('CAST(' . $column . ' as date) >= ?', [$from]);
        }

        if ($to !== '') {
            $query->whereRaw('CAST(' . $column . ' as date) <= ?', [$to]);
        }
    }

    private function extractOrdersListFilters(Request $request): array
    {
        $rawSearch = $request->input('search.value', $request->input('search', ''));

        return [
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
            'prioritet' => trim((string) $request->input('prioritet', '')),
            'search' => is_string($rawSearch) ? trim($rawSearch) : '',
        ];
    }

    private function extractOrdersListSort(Request $request): array
    {
        $sortBy = strtolower(trim((string) $request->input('sort_by', '')));
        $sortDir = strtolower(trim((string) $request->input('sort_dir', 'desc')));

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        if ($sortBy === '') {
            $columnIndex = $request->input('order.0.column');
            $direction = strtolower(trim((string) $request->input('order.0.dir', '')));

            if (in_array($direction, ['asc', 'desc'], true)) {
                $sortDir = $direction;
            }

            $sortBy = match ((int) $columnIndex) {
                0 => 'narudzba',
                1 => 'narucitelj',
                2 => 'prijevoznik',
                3 => 'datum',
                4 => 'kolicina',
                5 => 'broj_pozicija',
                6 => 'broj_rn',
                default => '',
            };
        }

        return [
            'by' => $sortBy,
            'dir' => $sortDir,
        ];
    }

    private function applyOrdersListSort(Builder $query, array $sort): void
    {
        $sortBy = strtolower(trim((string) ($sort['by'] ?? '')));
        $direction = strtolower(trim((string) ($sort['dir'] ?? 'desc')));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        switch ($sortBy) {
            case 'narudzba':
            case 'order_number':
                $query->orderBy('ord.acKey', $direction);
                break;
            case 'narucitelj':
            case 'klijent':
                $query->orderBy('narucitelj', $direction)->orderBy('ord.acKey', 'desc');
                break;
            case 'prijevoznik':
                $query->orderBy('prijevoznik', $direction)->orderBy('ord.acKey', 'desc');
                break;
            case 'datum':
                $query->orderBy('datum', $direction)->orderBy('ord.acKey', 'desc');
                break;
            case 'kolicina':
                $query->orderBy('totalKolicina', $direction)->orderBy('ord.acKey', 'desc');
                break;
            case 'broj_pozicija':
                $query->orderBy('brojPozicija', $direction)->orderBy('ord.acKey', 'desc');
                break;
            case 'broj_rn':
                $query->orderBy('brojRN', $direction)->orderBy('ord.acKey', 'desc');
                break;
            default:
                $query->orderBy('datum', 'desc')->orderBy('ord.acKey', 'desc');
                break;
        }
    }

    private function resolveOrdersListLimit(?int $requestedLimit = null): int
    {
        $maxLimit = 100;
        $defaultLimit = 10;
        $limit = $requestedLimit ?? $defaultLimit;

        if ($limit < 1) {
            return $defaultLimit;
        }

        return min($limit, $maxLimit);
    }

    private function ordersListDocumentType(): string
    {
        return strtoupper(trim((string) config('ai-order-scan.default_doc_type', '0110')));
    }

    private function ordersListColumnExists(string $column): bool
    {
        return in_array($column, Order::sourceColumns(), true);
    }

    private function ordersListFirstExistingColumn(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($this->ordersListColumnExists((string) $candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    private function ordersListPriorityColumn(): ?string
    {
        return $this->ordersListFirstExistingColumn([
            'anDeliveryPriority',
            'anPriority',
            'acPriority',
        ]);
    }

    private function normalizeOrdersListComparableExpression(string $sqlExpression): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(CAST(($sqlExpression) AS NVARCHAR(64)), ''), '-', ''), ' ', ''), '/', ''), '.', ''), '_', ''))";
    }

    private function canonicalOrdersListComparableExpression(string $sqlExpression): string
    {
        $normalizedExpression = $this->normalizeOrdersListComparableExpression($sqlExpression);

        return "(CASE WHEN LEN($normalizedExpression) = 13 AND SUBSTRING($normalizedExpression, 7, 1) = '0' THEN STUFF($normalizedExpression, 7, 1, '') ELSE $normalizedExpression END)";
    }

    private function resolveOrdersListDisplayNumber(mixed $rawValue, mixed $viewValue = null): string
    {
        $raw = trim((string) $rawValue);
        $view = trim((string) $viewValue);

        if ($view !== '') {
            return $view;
        }

        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\d{13}$/', $raw) === 1) {
            return substr($raw, 0, 2) . '-' . substr($raw, 2, 4) . '-' . substr($raw, 6);
        }

        return $raw;
    }

    private function applyOrdersListOrderNumberVariantSearch(Builder $query, array $variants): void
    {
        $variants = array_values(array_filter(array_map(function ($variant) {
            return trim((string) $variant);
        }, $variants), function ($variant) {
            return $variant !== '';
        }));

        if (empty($variants)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $hasCondition = false;

        foreach ($variants as $variant) {
            if ($hasCondition) {
                $query
                    ->orWhere('ord.acKey', 'like', '%' . $variant . '%')
                    ->orWhere('ord.acKeyView', 'like', '%' . $variant . '%')
                    ->orWhere('ord.acRefNo1', 'like', '%' . $variant . '%');

                continue;
            }

            $query
                ->where('ord.acKey', 'like', '%' . $variant . '%')
                ->orWhere('ord.acKeyView', 'like', '%' . $variant . '%')
                ->orWhere('ord.acRefNo1', 'like', '%' . $variant . '%');

            $hasCondition = true;
        }
    }

    private function buildOrdersListOrderNumberSearchVariants(string $search): array
    {
        $rawSearch = trim($search);
        $variants = [];

        if ($rawSearch !== '') {
            $variants[] = $rawSearch;
        }

        foreach ($this->ordersListOrderNumberDigitSearchVariants($rawSearch) as $digits) {
            $variants[] = $digits;

            foreach ($this->ordersListHyphenatedOrderNumberSearchVariants($digits) as $hyphenatedVariant) {
                $variants[] = $hyphenatedVariant;
            }
        }

        return array_values(array_unique(array_filter($variants, function ($variant) {
            return trim((string) $variant) !== '';
        })));
    }

    private function ordersListOrderNumberDigitSearchVariants(string $search): array
    {
        $digits = preg_replace('/\D+/', '', trim($search));

        if (!is_string($digits) || $digits === '') {
            return [];
        }

        $variants = [$digits];

        if (strlen($digits) === 12) {
            $variants[] = substr($digits, 0, 6) . '0' . substr($digits, 6);
        }

        if (strlen($digits) === 13 && substr($digits, 6, 1) === '0') {
            $variants[] = substr($digits, 0, 6) . substr($digits, 7);
        }

        return array_values(array_unique(array_filter($variants, function ($variant) {
            return trim((string) $variant) !== '';
        })));
    }

    private function ordersListHyphenatedOrderNumberSearchVariants(string $digits): array
    {
        $digits = preg_replace('/\D+/', '', $digits);

        if (!is_string($digits) || strlen($digits) < 3) {
            return [];
        }

        $variants = [
            substr($digits, 0, 2) . '-' . substr($digits, 2),
        ];

        if (strlen($digits) > 6) {
            $variants[] = substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6);
        }

        return array_values(array_unique($variants));
    }

    private function normalizeOrdersListDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        return substr($stringValue, 0, 10);
    }

    private function canAccessOrdersList(mixed $user = null): bool
    {
        if (is_object($user) && method_exists($user, 'isAdmin')) {
            return (bool) $user->isAdmin();
        }

        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }

    private function authorizeScanAccess(Request $request, OrderAiScan $scan): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if (($user->role ?? null) === 'admin') {
            return;
        }

        if ((int) ($scan->user_id ?? 0) !== (int) ($user->id ?? 0)) {
            abort(403);
        }
    }

    private function hasValidMaintenanceToken(Request $request): bool
    {
        $provided = trim((string) $request->header('X-Codex-Token', ''));
        $expected = hash_hmac('sha256', '__codex.orders.by-key', (string) config('app.key'));

        return $provided !== '' && hash_equals($expected, $provided);
    }
}
