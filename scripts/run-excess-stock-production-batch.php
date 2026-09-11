<?php

use App\Services\WorkOrder\ExcessStockService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$service = $app->make(ExcessStockService::class);
$db = $service->connection('ai_order_target');
$report = fopen(__DIR__ . '/../tmp/excess-production-u-radu-2026-batch.jsonl', 'ab');

foreach ($service->assignPreviewForStatuses($db, ['E'], '26-')['assignments'] as $assignment) {
    try {
        $added = $service->assign($db, $assignment, 0, false, ['E']);
        if ($added > 0) fwrite($report, json_encode(['rn' => $assignment['work_order']['acKeyView'], 'added' => $added], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    } catch (Throwable $exception) {
        fwrite($report, json_encode(['rn' => $assignment['work_order']['acKeyView'], 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }
}

fclose($report);
