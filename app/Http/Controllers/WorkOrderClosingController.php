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
                $request->validated()['operations'] ?? [],
                (int) ($user->id ?? 0),
                trim((string) ($user->username ?? '')),
                $request->validated()['materials'] ?? [],
                $request->validated()['receipts'] ?? null,
                trim((string) ($user->name ?? ''))
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
                'message' => $this->userFacingCloseError($exception, $status),
            ], $status);
        }
    }

    private function userFacingCloseError(Throwable $exception, int $status): string
    {
        $message = $exception->getMessage();
        $normalized = mb_strtolower($message);

        if (str_contains($normalized, 'duplicate key') || str_contains($normalized, 'unique index')) {
            return 'Nije moguće kreirati dokument jer je njegov broj u međuvremenu već zauzet. '
                . 'Nijedna promjena nije sačuvana. Pokušajte ponovo zatvoriti radni nalog.';
        }

        // Očekivana poslovna validacija je sigurna za prikaz korisniku.
        if ($status === 422) {
            return $message;
        }

        return 'Zatvaranje radnog naloga nije uspjelo. Nijedna promjena nije sačuvana. Pokušajte ponovo ili kontaktirajte podršku.';
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
