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
        return parent::ordersLinkageData($request);
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
