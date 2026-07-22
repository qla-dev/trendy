<?php

namespace App\Support;

use App\Models\OrderAiScan;
use App\Services\OrderAi\Support\OrderAiDocumentMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AiTokenNavbarCounter
{
    public function __construct(private OrderAiDocumentMetrics $documentMetrics)
    {
    }

    public function currentMonthTotal(?Carbon $moment = null): int
    {
        try {
            $now = $moment ? $moment->copy() : Carbon::now();

            return $this->totalForPeriod(
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth()
            );
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    public function totalForPeriod(Carbon $start, Carbon $end): int
    {
        return (int) $this->historyBaseQuery()
            ->whereRaw($this->historyEventTimestampExpression() . ' >= ?', [$start->toDateTimeString()])
            ->whereRaw($this->historyEventTimestampExpression() . ' <= ?', [$end->toDateTimeString()])
            ->get([
                'status',
                'page_count',
                'transferred_at',
                'pantheon_order_key',
                'pantheon_order_view',
                'pantheon_order_qid',
            ])
            ->sum(fn (OrderAiScan $scan): int => $this->billedTokensFor($scan));
    }

    public function historyBaseQuery(): Builder
    {
        return OrderAiScan::query()
            ->where(function (Builder $query) {
                $query
                    ->where('credits_spent', '>', 0)
                    ->orWhereNotNull('processed_at')
                    ->orWhere('status', 'failed');
            });
    }

    public function historyEventTimestampExpression(): string
    {
        return 'COALESCE(processed_at, completed_at, created_at)';
    }

    public function billedTokensFor(OrderAiScan $scan): int
    {
        return $this->hasTransferResult($scan, (string) ($scan->status ?? ''))
            ? $this->documentMetrics->calculateBilledTokens(max(0, (int) ($scan->page_count ?? 0)))
            : 0;
    }

    public function hasTransferResult(OrderAiScan $scan, string $status = ''): bool
    {
        return $scan->transferred_at !== null
            || trim((string) ($scan->pantheon_order_key ?? '')) !== ''
            || trim((string) ($scan->pantheon_order_view ?? '')) !== ''
            || (int) ($scan->pantheon_order_qid ?? 0) > 0
            || trim($status) === 'transferred';
    }
}
