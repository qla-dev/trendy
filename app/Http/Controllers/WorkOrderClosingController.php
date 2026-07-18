<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloseWorkOrderRequest;
use App\Services\WorkOrder\PantheonWorkerSearchService;
use App\Services\WorkOrder\WorkOrderClosingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkOrderClosingController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            return $this->runWithWorkOrderTargetConnection(fn () => $next($request));
        });
    }

    public function workers(Request $request, PantheonWorkerSearchService $workers): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        return response()->json([
            'data' => $workers->search($term),
        ]);
    }

    public function close(CloseWorkOrderRequest $request, string $id, WorkOrderClosingService $closing): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $closing->close(
                $id,
                $request->validated()['operations'],
                (int) ($user->id ?? 0),
                trim((string) ($user->name ?? '')),
                $request->validated()['materials'] ?? [],
                $request->validated()['receipts'] ?? []
            );

            return response()->json(['message' => $result['message'], 'data' => $result]);
        } catch (Throwable $exception) {
            Log::error('Work order closing failed.', [
                'work_order' => $id,
                'user_id' => (int) ($request->user()->id ?? 0),
                'failed_step' => $this->failedStep($exception->getMessage()),
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
                'input' => [
                    'operations' => $request->input('operations', []),
                    'materials' => $request->input('materials', []),
                    'receipts' => $request->input('receipts', []),
                ],
                'trace' => $exception->getTraceAsString(),
            ]);

            $status = $exception instanceof \InvalidArgumentException || $exception instanceof \RuntimeException ? 422 : 500;
            return response()->json([
                'message' => $status === 422 ? $exception->getMessage() : 'Zatvaranje radnog naloga nije uspjelo. Sve promjene su poništene.',
            ], $status);
        }
    }

    private function failedStep(string $message): string
    {
        $normalized = mb_strtolower($message);
        return match (true) {
            str_contains($normalized, 'ne pripada radnom nalogu') => 'operation_item_link_validation',
            str_contains($normalized, 'radnik') => 'worker_validation',
            str_contains($normalized, 'operacij') => 'operation_document',
            str_contains($normalized, 'proizvod'), str_contains($normalized, 'prijem') => 'finished_goods_receipt',
            str_contains($normalized, 'status') => 'work_order_status',
            default => 'closing_transaction',
        };
    }
}
