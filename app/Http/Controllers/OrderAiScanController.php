<?php

namespace App\Http\Controllers;

use App\Models\OrderAiScan;
use App\Services\OrderAi\OrderAiScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderAiScanController extends Controller
{
    public function index()
    {
        $pageConfigs = ['pageHeader' => false];

        return view('content.apps.orders.app-order-ai-scan', [
            'pageConfigs' => $pageConfigs,
            'scanProvider' => (string) config('ai-order-scan.provider', 'mock'),
            'scanModel' => (string) config('ai-order-scan.model', 'gpt-5'),
            'autoTransferEnabled' => filter_var(config('ai-order-scan.auto_transfer', false), FILTER_VALIDATE_BOOL),
        ]);
    }

    public function store(Request $request, OrderAiScanService $scanService): JsonResponse
    {
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
        $this->authorizeScan($request, $scan);

        $scan = $scanService->advance($scan, $request->user());

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

        if (($user->role ?? null) === 'admin') {
            return;
        }

        if ((int) ($scan->user_id ?? 0) !== (int) ($user->id ?? 0)) {
            abort(403);
        }
    }
}
