<?php

namespace App\Support;

use Illuminate\Support\Facades\Log as LaravelLog;

/**
 * Routes Order AI scan diagnostics to their dedicated log file.
 */
class AiScanLog
{
    public static function failed(string $message, array $context = []): void
    {
        $channel = is_file(storage_path('logs/failed_ai_scan.log'))
            ? 'failed-ai-scan'
            : 'ai-scan';
        LaravelLog::channel($channel)->error($message, $context);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return LaravelLog::channel('ai-scan')->{$method}(...$arguments);
    }
}
