<?php

namespace App\Jobs;

use App\Models\OrderAiScan;
use App\Services\OrderAi\OrderAiScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessImportedOrderAiScanJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(private readonly int $scanId)
    {
    }

    public function handle(OrderAiScanService $scanService): void
    {
        $scan = OrderAiScan::query()->find($this->scanId);

        if (!$scan) {
            Log::warning('AI inbox scan job skipped because the scan record no longer exists.', [
                'scan_id' => $this->scanId,
            ]);

            return;
        }

        Log::info('AI inbox scan job started.', [
            'scan_id' => $this->scanId,
            'attempts' => $this->attempts(),
            'status' => (string) ($scan->status ?? ''),
        ]);

        $scanService->processUntilReviewed($scan);

        $scan->refresh();

        Log::info('AI inbox scan job completed.', [
            'scan_id' => $this->scanId,
            'attempts' => $this->attempts(),
            'status' => (string) ($scan->status ?? ''),
            'processing_step' => (string) ($scan->processing_step ?? ''),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $scan = OrderAiScan::query()->find($this->scanId);

        if (!$scan) {
            return;
        }

        $scan->forceFill([
            'status' => 'failed',
            'processing_step' => 'AI inbox obrada nije uspjela.',
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ])->save();

        Log::warning('AI inbox scan job failed.', [
            'scan_id' => $this->scanId,
            'message' => $exception->getMessage(),
        ]);
    }
}
