<?php

namespace App\Support;

use App\Models\OrderAiScan;
use Illuminate\Support\Carbon;

class AiTokenNavbarCounter
{
    public function currentMonthTotal(?Carbon $moment = null): int
    {
        try {
            $now = $moment ? $moment->copy() : Carbon::now();

            return (int) OrderAiScan::query()
                ->where(function ($query) {
                    $query
                        ->where('credits_spent', '>', 0)
                        ->orWhereNotNull('processed_at')
                        ->orWhere('status', 'failed');
                })
                ->whereRaw('COALESCE(processed_at, completed_at, created_at) >= ?', [
                    $now->copy()->startOfMonth()->toDateTimeString(),
                ])
                ->whereRaw('COALESCE(processed_at, completed_at, created_at) <= ?', [
                    $now->copy()->endOfMonth()->toDateTimeString(),
                ])
                ->sum('billed_tokens');
        } catch (\Throwable $exception) {
            return 0;
        }
    }
}
