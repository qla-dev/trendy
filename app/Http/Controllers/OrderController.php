<?php

namespace App\Http\Controllers;

use App\Models\OrderAiScan;
use App\Models\Order;
use App\Services\OrderAi\PantheonOrderTransferService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
