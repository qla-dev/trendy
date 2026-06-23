<?php

namespace App\Console\Commands;

use App\Models\OrderAiScan;
use App\Services\OrderAi\OrderAiScanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ComparePdfExtractionCommand extends Command
{
    private ?array $orderAiScanColumns = null;

    protected $signature = 'trendy:compare-pdf-extraction
        {path : Path to the PDF file}
        {--save-json : Save the comparison report as JSON}
        {--output= : Optional custom JSON output path}';

    protected $description = 'Compare the legacy AI raw-PDF workflow against the new digital PDF extraction pipeline.';

    public function handle(OrderAiScanService $scanService): int
    {
        $resolvedPath = $this->resolveInputPath((string) $this->argument('path'));

        if ($resolvedPath === null || !is_file($resolvedPath)) {
            $this->error('PDF file was not found.');

            return self::FAILURE;
        }

        $bytes = @file_get_contents($resolvedPath);

        if (!is_string($bytes) || $bytes === '') {
            $this->error('Unable to read the PDF file.');

            return self::FAILURE;
        }

        $mimeType = $this->detectMimeType($resolvedPath);

        $legacy = $this->runStrategy(
            $scanService,
            basename($resolvedPath),
            $bytes,
            $mimeType,
            'legacy_raw'
        );
        $digital = $this->runStrategy(
            $scanService,
            basename($resolvedPath),
            $bytes,
            $mimeType,
            'auto'
        );
        $report = $this->buildReport($resolvedPath, $mimeType, $legacy, $digital);

        $this->renderReport($report);

        if ($this->option('save-json') || trim((string) $this->option('output')) !== '') {
            $outputPath = $this->writeJsonReport($report);
            $this->line('');
            $this->info('JSON report saved to: ' . $outputPath);
        }

        return self::SUCCESS;
    }

    private function runStrategy(
        OrderAiScanService $scanService,
        string $originalName,
        string $binaryContent,
        string $mimeType,
        string $providerInputMode
    ): array {
        $previousInputMode = (string) config('ai-order-scan.digital_pdf.provider_input_mode', 'auto');
        config(['ai-order-scan.digital_pdf.provider_input_mode' => $providerInputMode]);

        try {
            $attempts = 0;
            $scan = $scanService->createScanFromBinary(
                $originalName,
                $binaryContent,
                $mimeType,
                null,
                [
                    'source_origin' => 'comparison',
                    'status' => 'extracting',
                    'processing_step' => 'Comparison extraction in progress.',
                    'progress_current' => 25,
                    'progress_total' => 100,
                ]
            );
            $strategyStartedAt = microtime(true);

            while (true) {
                $attempts++;

                try {
                    $providerResult = $providerInputMode === 'legacy_raw'
                        ? $scanService->executeExtraction($scan, true)
                        : $scanService->executeExtraction($scan);
                    $finalized = $scanService->finalizeExtractionResult($scan, $providerResult, null, false);
                    $totalDurationMs = (int) round((microtime(true) - $strategyStartedAt) * 1000);
                    $scan = $this->persistSuccessfulComparisonScan($scan, $providerResult, $finalized);

                    return [
                        'strategy' => $providerInputMode === 'legacy_raw' ? 'legacy_ai' : 'digital_pdf',
                        'scan_id' => $scan->id,
                        'status' => (string) ($scan->status ?? ''),
                        'provider' => (string) ($providerResult['provider'] ?? ''),
                        'model' => (string) ($providerResult['model'] ?? ''),
                        'credits_spent' => (float) ($providerResult['credits_spent'] ?? 0),
                        'raw_response' => $providerResult['raw_response'] ?? null,
                        'prepared_document' => $finalized['prepared_document'] ?? [],
                        'payload' => $finalized['normalized_payload'] ?? [],
                        'validation_report' => $finalized['validation_report'] ?? [],
                        'timings' => [
                            'extraction_duration_ms' => (int) ($finalized['extraction_duration_ms'] ?? 0),
                            'ai_duration_ms' => (int) ($finalized['ai_duration_ms'] ?? 0),
                            'validation_duration_ms' => (int) ($finalized['validation_duration_ms'] ?? 0),
                            'total_duration_ms' => $totalDurationMs,
                        ],
                        'usage' => [
                            'total_tokens' => (int) data_get($providerResult, 'raw_response.usage.total_tokens', 0),
                            'prompt_tokens' => (int) data_get($providerResult, 'raw_response.usage.prompt_tokens', 0),
                            'completion_tokens' => (int) data_get($providerResult, 'raw_response.usage.completion_tokens', 0),
                        ],
                        'projection' => $this->projectComparableFields(
                            is_array($finalized['normalized_payload'] ?? null) ? $finalized['normalized_payload'] : [],
                            is_array($finalized['prepared_document'] ?? null) ? $finalized['prepared_document'] : [],
                            is_array($finalized['validation_report'] ?? null) ? $finalized['validation_report'] : []
                        ),
                        'attempts' => $attempts,
                        'failed' => false,
                    ];
                } catch (\Throwable $exception) {
                    $context = method_exists($exception, 'context') ? (array) $exception->context() : [];

                    if ($attempts < 2 && $this->isRetryableProviderFailure($exception, $context)) {
                        if ($scan->exists) {
                            $this->markFailedComparisonScan($scan, $exception, $context, true);
                        }

                        usleep(2_000_000);
                        continue;
                    }

                    if ($scan->exists) {
                        $this->markFailedComparisonScan($scan, $exception, $context);
                        $scan->refresh();
                    }

                    return [
                        'strategy' => $providerInputMode === 'legacy_raw' ? 'legacy_ai' : 'digital_pdf',
                        'scan_id' => $scan->id,
                        'status' => (string) ($scan->status ?? 'failed'),
                        'provider' => (string) ($context['provider'] ?? config('ai-order-scan.provider', '')),
                        'model' => (string) ($context['model'] ?? config('ai-order-scan.model', '')),
                        'credits_spent' => 0.0,
                        'raw_response' => $context['raw_response'] ?? null,
                        'prepared_document' => [],
                        'payload' => [],
                        'validation_report' => [
                            'errors' => [$exception->getMessage()],
                            'warnings' => [],
                        ],
                        'timings' => [
                            'extraction_duration_ms' => 0,
                            'ai_duration_ms' => 0,
                            'validation_duration_ms' => 0,
                            'total_duration_ms' => 0,
                        ],
                        'usage' => [
                            'total_tokens' => (int) data_get($context, 'raw_response.usage.total_tokens', 0),
                            'prompt_tokens' => (int) data_get($context, 'raw_response.usage.prompt_tokens', 0),
                            'completion_tokens' => (int) data_get($context, 'raw_response.usage.completion_tokens', 0),
                        ],
                        'projection' => $this->projectComparableFields([], [], []),
                        'attempts' => $attempts,
                        'failed' => true,
                        'failure_message' => $exception->getMessage(),
                    ];
                }
            }
        } finally {
            config(['ai-order-scan.digital_pdf.provider_input_mode' => $previousInputMode]);
        }
    }

    private function persistSuccessfulComparisonScan(OrderAiScan $scan, array $providerResult, array $finalized): OrderAiScan
    {
        $attributes = $this->filterOrderAiScanAttributes([
            'provider' => (string) ($providerResult['provider'] ?? $scan->provider ?? ''),
            'model' => (string) ($providerResult['model'] ?? $scan->model ?? ''),
            'provider_task_id' => trim((string) ($providerResult['provider_task_id'] ?? '')) ?: null,
            'raw_provider_response' => $providerResult['raw_response'] ?? null,
            'normalized_payload' => $finalized['normalized_payload'] ?? [],
            'pantheon_transfer_payload' => null,
            'credits_spent' => (float) ($providerResult['credits_spent'] ?? 0),
            'status' => 'completed',
            'processing_step' => 'Comparison extraction completed.',
            'progress_current' => 100,
            'processed_at' => now(),
            'completed_at' => now(),
            'error_message' => null,
            'page_count' => (int) ($finalized['page_count'] ?? $scan->page_count ?? 0),
            'billed_tokens' => (int) ($finalized['billed_tokens'] ?? $scan->billed_tokens ?? 0),
            'extraction_method' => trim((string) ($finalized['extraction_method'] ?? '')) ?: null,
            'raw_extracted_text' => (string) ($finalized['raw_extracted_text'] ?? ''),
            'extraction_payload' => $finalized['extraction_payload'] ?? null,
            'validation_warnings' => is_array($finalized['validation_warnings'] ?? null) ? $finalized['validation_warnings'] : [],
            'validation_errors' => is_array($finalized['validation_errors'] ?? null) ? $finalized['validation_errors'] : [],
            'confidence_score' => (float) ($finalized['confidence_score'] ?? 0),
            'extraction_duration_ms' => (int) ($finalized['extraction_duration_ms'] ?? 0),
            'ai_duration_ms' => (int) ($finalized['ai_duration_ms'] ?? 0),
            'validation_duration_ms' => (int) ($finalized['validation_duration_ms'] ?? 0),
        ]);

        $scan->forceFill($attributes)->save();

        return $scan->fresh();
    }

    private function markFailedComparisonScan(
        OrderAiScan $scan,
        \Throwable $exception,
        array $context,
        bool $willRetry = false
    ): void {
        $attributes = $this->filterOrderAiScanAttributes([
            'provider' => (string) ($context['provider'] ?? $scan->provider ?? ''),
            'model' => (string) ($context['model'] ?? $scan->model ?? ''),
            'provider_task_id' => trim((string) ($context['provider_task_id'] ?? '')) ?: null,
            'raw_provider_response' => $context['raw_response'] ?? null,
            'status' => $willRetry ? 'extracting' : 'failed',
            'processing_step' => $willRetry
                ? 'Comparison extraction retry scheduled.'
                : 'Comparison extraction failed.',
            'progress_current' => $willRetry ? 25 : 100,
            'completed_at' => $willRetry ? null : now(),
            'error_message' => $exception->getMessage(),
        ]);

        $scan->forceFill($attributes)->save();
    }

    private function filterOrderAiScanAttributes(array $attributes): array
    {
        if ($this->orderAiScanColumns === null) {
            $columns = Schema::connection('mysql')->getColumnListing('order_ai_scans');
            $this->orderAiScanColumns = array_fill_keys($columns, true);
        }

        return array_filter(
            $attributes,
            fn (string $key): bool => (bool) ($this->orderAiScanColumns[$key] ?? false),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function buildReport(string $resolvedPath, string $mimeType, array $legacy, array $digital): array
    {
        $fieldComparisons = [];

        foreach ([
            'supplier_name',
            'document_order_number',
            'invoice_number',
            'invoice_date',
            'currency',
            'total_net_amount',
            'total_gross_amount',
            'number_of_extracted_pages',
            'number_of_extracted_line_items',
        ] as $field) {
            $fieldComparisons[] = $this->buildFieldComparison(
                $field,
                $legacy['projection'][$field] ?? null,
                $digital['projection'][$field] ?? null
            );
        }

        $lineItemsComparison = $this->compareLineItems(
            $legacy['projection']['line_items'] ?? [],
            $digital['projection']['line_items'] ?? []
        );

        return [
            'file' => [
                'path' => $resolvedPath,
                'mime_type' => $mimeType,
            ],
            'legacy' => $legacy,
            'digital' => $digital,
            'field_comparisons' => $fieldComparisons,
            'line_items' => $lineItemsComparison,
            'validation_errors' => [
                'legacy' => is_array($legacy['validation_report']['errors'] ?? null) ? $legacy['validation_report']['errors'] : [],
                'digital' => is_array($digital['validation_report']['errors'] ?? null) ? $digital['validation_report']['errors'] : [],
            ],
        ];
    }

    private function renderReport(array $report): void
    {
        $this->info('PDF Extraction Comparison');
        $this->line('File: ' . (string) data_get($report, 'file.path', ''));
        $this->line('Mime: ' . (string) data_get($report, 'file.mime_type', 'application/pdf'));
        $this->line(sprintf(
            'Saved scans: legacy #%s, digital #%s',
            $this->stringifyValue(data_get($report, 'legacy.scan_id')),
            $this->stringifyValue(data_get($report, 'digital.scan_id'))
        ));
        $this->line('');

        $this->table(
            ['Field', 'Legacy AI', 'Digital PDF', 'Match'],
            array_map(function (array $comparison) {
                return [
                    $comparison['field'],
                    $this->stringifyValue($comparison['legacy']),
                    $this->stringifyValue($comparison['digital']),
                    $comparison['match'] ? 'yes' : 'no',
                ];
            }, $report['field_comparisons'] ?? [])
        );

        $this->line('');
        $this->info('Timing and Cost');
        $this->table(
            ['Metric', 'Legacy AI', 'Digital PDF'],
            [
                ['Extraction (ms)', data_get($report, 'legacy.timings.extraction_duration_ms', 0), data_get($report, 'digital.timings.extraction_duration_ms', 0)],
                ['AI (ms)', data_get($report, 'legacy.timings.ai_duration_ms', 0), data_get($report, 'digital.timings.ai_duration_ms', 0)],
                ['Validation (ms)', data_get($report, 'legacy.timings.validation_duration_ms', 0), data_get($report, 'digital.timings.validation_duration_ms', 0)],
                ['Total (ms)', data_get($report, 'legacy.timings.total_duration_ms', 0), data_get($report, 'digital.timings.total_duration_ms', 0)],
                ['Total tokens', data_get($report, 'legacy.usage.total_tokens', 0), data_get($report, 'digital.usage.total_tokens', 0)],
                ['Credits spent', data_get($report, 'legacy.credits_spent', 0), data_get($report, 'digital.credits_spent', 0)],
                ['Method', data_get($report, 'legacy.prepared_document.extraction_method', ''), data_get($report, 'digital.prepared_document.extraction_method', '')],
                ['Attempts', data_get($report, 'legacy.attempts', 1), data_get($report, 'digital.attempts', 1)],
                ['Status', data_get($report, 'legacy.status', ''), data_get($report, 'digital.status', '')],
            ]
        );

        $this->line('');
        $this->info('Line Items');
        $this->table(
            ['#', 'Legacy article', 'Digital article', 'Legacy qty', 'Digital qty', 'Legacy net', 'Digital net', 'Match', 'Diffs'],
            array_map(function (array $itemComparison) {
                return [
                    $itemComparison['index'],
                    $this->stringifyValue(data_get($itemComparison, 'legacy.article_number')),
                    $this->stringifyValue(data_get($itemComparison, 'digital.article_number')),
                    $this->stringifyValue(data_get($itemComparison, 'legacy.quantity')),
                    $this->stringifyValue(data_get($itemComparison, 'digital.quantity')),
                    $this->stringifyValue(data_get($itemComparison, 'legacy.net_unit_price')),
                    $this->stringifyValue(data_get($itemComparison, 'digital.net_unit_price')),
                    $itemComparison['match'] ? 'yes' : 'no',
                    implode(', ', data_get($itemComparison, 'mismatch_fields', [])),
                ];
            }, $report['line_items']['comparisons'] ?? [])
        );

        $this->line('');
        $this->line('Extra digital line items: ' . count($report['line_items']['extra_digital_items'] ?? []));
        $this->line('Missing digital line items: ' . count($report['line_items']['missing_digital_items'] ?? []));

        $legacyErrors = is_array($report['validation_errors']['legacy'] ?? null) ? $report['validation_errors']['legacy'] : [];
        $digitalErrors = is_array($report['validation_errors']['digital'] ?? null) ? $report['validation_errors']['digital'] : [];

        if ($legacyErrors !== [] || $digitalErrors !== []) {
            $this->line('');
            $this->warn('Validation Errors');

            foreach ($legacyErrors as $error) {
                $this->line('Legacy: ' . $error);
            }

            foreach ($digitalErrors as $error) {
                $this->line('Digital: ' . $error);
            }
        }

        if (data_get($report, 'legacy.failed') || data_get($report, 'digital.failed')) {
            $this->line('');
            $this->warn('Strategy Failures');

            if (data_get($report, 'legacy.failed')) {
                $this->line('Legacy: ' . (string) data_get($report, 'legacy.failure_message', 'Unknown failure'));
            }

            if (data_get($report, 'digital.failed')) {
                $this->line('Digital: ' . (string) data_get($report, 'digital.failure_message', 'Unknown failure'));
            }
        }
    }

    private function projectComparableFields(array $payload, array $preparedDocument, array $validationReport): array
    {
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        $detectedFields = is_array($validationReport['detected_fields'] ?? null) ? $validationReport['detected_fields'] : [];
        $documentTotals = is_array($validationReport['document_totals'] ?? null) ? $validationReport['document_totals'] : [];
        $items = array_values(array_filter(array_map(function ($item) {
            if (!is_array($item)) {
                return null;
            }

            return [
                'article_number' => trim((string) ($item['product_code'] ?? '')),
                'description' => trim((string) ($item['product_name'] ?? '')),
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit' => trim((string) ($item['unit'] ?? '')),
                'net_unit_price' => (float) ($item['unit_price'] ?? 0),
                'line_total' => (float) ($item['line_total'] ?? 0),
                'vat_rate' => (float) ($item['vat_rate'] ?? 0),
            ];
        }, $payload['items'] ?? [])));

        return [
            'supplier_name' => trim((string) ($order['supplier_name'] ?? '')),
            'document_order_number' => trim((string) ($order['external_document_number'] ?? $detectedFields['document_number'] ?? '')),
            'invoice_number' => trim((string) ($detectedFields['invoice_number'] ?? '')),
            'invoice_date' => trim((string) ($detectedFields['invoice_date'] ?? '')),
            'currency' => trim((string) ($order['currency'] ?? $detectedFields['currency'] ?? '')),
            'total_net_amount' => (float) (($summary['subtotal'] ?? 0) ?: ($documentTotals['net_total'] ?? 0)),
            'total_gross_amount' => (float) (($summary['grand_total'] ?? 0) ?: ($documentTotals['gross_total'] ?? 0)),
            'number_of_extracted_pages' => (int) ($preparedDocument['effective_page_count'] ?? $order['page_count'] ?? 0),
            'number_of_extracted_line_items' => count($items),
            'line_items' => $items,
        ];
    }

    private function compareLineItems(array $legacyItems, array $digitalItems): array
    {
        $maxCount = max(count($legacyItems), count($digitalItems));
        $comparisons = [];

        for ($index = 0; $index < $maxCount; $index++) {
            $legacyItem = is_array($legacyItems[$index] ?? null) ? $legacyItems[$index] : null;
            $digitalItem = is_array($digitalItems[$index] ?? null) ? $digitalItems[$index] : null;
            $mismatchFields = $this->resolveLineItemMismatchFields($legacyItem, $digitalItem);
            $comparisons[] = [
                'index' => $index + 1,
                'legacy' => $legacyItem,
                'digital' => $digitalItem,
                'match' => $mismatchFields === [],
                'mismatch_fields' => $mismatchFields,
            ];
        }

        return [
            'comparisons' => $comparisons,
            'missing_digital_items' => array_slice($legacyItems, count($digitalItems)),
            'extra_digital_items' => array_slice($digitalItems, count($legacyItems)),
        ];
    }

    private function resolveLineItemMismatchFields(?array $legacyItem, ?array $digitalItem): array
    {
        if ($legacyItem === null && $digitalItem === null) {
            return [];
        }

        if ($legacyItem === null) {
            return ['missing_legacy_item'];
        }

        if ($digitalItem === null) {
            return ['missing_digital_item'];
        }

        $fieldMap = [
            'article_number',
            'description',
            'quantity',
            'unit',
            'net_unit_price',
            'line_total',
            'vat_rate',
        ];
        $mismatches = [];

        foreach ($fieldMap as $field) {
            if (!$this->valuesMatch($legacyItem[$field] ?? null, $digitalItem[$field] ?? null)) {
                $mismatches[] = $field;
            }
        }

        return $mismatches;
    }

    private function valuesMatch(mixed $left, mixed $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            return abs((float) $left - (float) $right) < 0.0001;
        }

        return $left === $right;
    }

    private function buildFieldComparison(string $field, mixed $legacyValue, mixed $digitalValue): array
    {
        return [
            'field' => $field,
            'legacy' => $legacyValue,
            'digital' => $digitalValue,
            'match' => $legacyValue === $digitalValue,
        ];
    }

    private function writeJsonReport(array $report): string
    {
        $customOutput = trim((string) $this->option('output'));

        if ($customOutput !== '') {
            $outputPath = $this->resolveOutputPath($customOutput);
        } else {
            $outputPath = storage_path('app/extraction-comparisons/' . now()->format('Ymd_His') . '_' . Str::uuid() . '.json');
        }

        $directory = dirname($outputPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $outputPath,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $outputPath;
    }

    private function resolveInputPath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (is_file($path)) {
            return realpath($path) ?: $path;
        }

        $candidate = base_path($path);

        if (is_file($candidate)) {
            return realpath($candidate) ?: $candidate;
        }

        return null;
    }

    private function resolveOutputPath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:\\\\|^\//', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function detectMimeType(string $path): string
    {
        $mimeType = function_exists('mime_content_type') ? mime_content_type($path) : null;

        if (is_string($mimeType) && trim($mimeType) !== '') {
            return trim($mimeType);
        }

        return 'application/pdf';
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function isRetryableProviderFailure(\Throwable $exception, array $context): bool
    {
        $message = Str::lower(trim($exception->getMessage()));

        if (str_contains($message, 'rate limit')) {
            return true;
        }

        if (str_contains($message, 'nije vratio validan json')) {
            $errorCode = (string) data_get($context, 'raw_response.choices.0.error.code', '');
            $errorType = Str::lower((string) data_get($context, 'raw_response.choices.0.error.metadata.error_type', ''));
            $errorMessage = Str::lower((string) data_get($context, 'raw_response.choices.0.error.message', ''));

            return $errorCode === '429'
                || str_contains($errorType, 'rate_limit')
                || str_contains($errorMessage, 'rate');
        }

        return false;
    }
}
