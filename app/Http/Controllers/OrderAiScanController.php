<?php

namespace App\Http\Controllers;

use App\Models\OrderAiScan;
use App\Services\OrderAi\OrderAiScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderAiScanController extends Controller
{
    public function index(Request $request, OrderAiScanService $scanService)
    {
        $this->authorizeModuleAccess($request);

        $pageConfigs = ['pageHeader' => false];
        $initialScan = null;
        $initialScanState = null;
        $requestedScanId = (int) $request->query('scan', 0);

        if ($requestedScanId > 0) {
            $initialScan = OrderAiScan::query()->findOrFail($requestedScanId);
            $this->authorizeScan($request, $initialScan);
            $initialScanState = $scanService->buildStatusPayload($initialScan);
        }

        return view('content.apps.orders.app-order-ai-scan', [
            'pageConfigs' => $pageConfigs,
            'scanProvider' => (string) config('ai-order-scan.provider', 'mock'),
            'scanModel' => (string) config('ai-order-scan.model', 'gpt-5'),
            'autoTransferEnabled' => filter_var(config('ai-order-scan.auto_transfer', false), FILTER_VALIDATE_BOOL),
            'initialScanId' => $initialScan?->id,
            'initialScanState' => $initialScanState,
        ]);
    }

    public function store(Request $request, OrderAiScanService $scanService): JsonResponse
    {
        $this->authorizeModuleAccess($request);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $scan = $scanService->createScan($validated['file'], $request->user());

        return response()->json([
            'message' => 'Dokument je učitan i spreman za AI obradu.',
            'scan_id' => $scan->id,
            'status' => $scan->status,
            'status_url' => route('app-order-ai-scan-status', ['scan' => $scan->id]),
        ], 201);
    }

    public function status(Request $request, OrderAiScan $scan, OrderAiScanService $scanService): JsonResponse
    {
        $this->authorizeModuleAccess($request);
        $this->authorizeScan($request, $scan);

        if ((string) ($scan->source_origin ?? 'manual') === 'imap') {
            $scan = $scan->fresh();
        } else {
            $scan = $scanService->advance($scan, $request->user());
        }

        return response()->json([
            'message' => 'Status AI skena je osvježen.',
            'data' => $scanService->buildStatusPayload($scan),
        ]);
    }

    private function authorizeScan(Request $request, OrderAiScan $scan): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($this->userCanAccessAiOrderModule($user)) {
            return;
        }

        if ((int) ($scan->user_id ?? 0) !== (int) ($user->id ?? 0)) {
            abort(403);
        }
    }

    private function authorizeModuleAccess(Request $request): void
    {
        $user = $request->user();

        if (!$this->userCanAccessAiOrderModule($user)) {
            abort(403);
        }
    }

    private function userCanAccessAiOrderModule($user): bool
    {
        if ($user === null) {
            return false;
        }

        return method_exists($user, 'canAccessAiOrderModule')
            ? (bool) $user->canAccessAiOrderModule()
            : false;
    }
}
