<?php

namespace App\Support;

use Illuminate\Support\Facades\Log as LaravelLog;

/**
 * Routes Order AI scan diagnostics to their dedicated log file.
 */
class AiScanLog
{
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return LaravelLog::channel('ai-scan')->{$method}(...$arguments);
    }
}
