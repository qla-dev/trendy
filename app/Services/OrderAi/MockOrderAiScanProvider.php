<?php

namespace App\Services\OrderAi;

use App\Models\OrderAiScan;
use App\Services\OrderAi\Contracts\OrderAiScanProvider;
use App\Services\OrderAi\Support\OrderAiDocumentPreparationService;
use App\Services\OrderAi\Support\OrderAiDocumentMetrics;
use Illuminate\Support\Facades\Storage;

class MockOrderAiScanProvider implements OrderAiScanProvider
{
    public function supportsLiveTransfer(): bool
    {
        return false;
    }

    public function scan(OrderAiScan $scan): array
    {
        $disk = (string) config('ai-order-scan.storage_disk', 'local');
        $rawContent = '';
        $preview = '';

        if (Storage::disk($disk)->exists($scan->source_file_path)) {
            $rawContent = (string) Storage::disk($disk)->get($scan->source_file_path);
        }

        if ($this->isTextLikeMime((string) ($scan->source_mime_type ?? ''))) {
            $preview = mb_substr(trim($rawContent), 0, 1500);
        }

        $preparationStartedAt = microtime(true);
        $preparedDocument = app(OrderAiDocumentPreparationService::class)->prepareDocument(
            (string) ($scan->document_profile ?? ''),
            (string) ($scan->source_file_name ?? 'document'),
            (string) ($scan->source_mime_type ?? 'application/octet-stream'),
            $rawContent
        );
        $extractionDurationMs = max(
            (int) round((microtime(true) - $preparationStartedAt) * 1000),
            (int) ($preparedDocument['extraction_duration_ms'] ?? 0)
        );
        $normalizedPayload = $this->buildFallbackPayload($scan, $rawContent);

        return [
            'provider' => 'mock',
            'model' => 'mock-local-parser',
            'credits_spent' => 0.0,
            'raw_response' => [
                'warning' => 'OpenAI provider is not configured. Mock extraction was used.',
                'preview' => $preview,
            ],
            'normalized_payload' => $normalizedPayload,
            'prepared_document' => $preparedDocument,
            'extraction_duration_ms' => $extractionDurationMs,
            'ai_duration_ms' => 0,
        ];
    }

    private function buildFallbackPayload(OrderAiScan $scan, string $rawContent): array
    {
        $documentMetrics = app(OrderAiDocumentMetrics::class)->resolveForStoredFile(
            (string) config('ai-order-scan.storage_disk', 'local'),
            (string) ($scan->source_file_path ?? ''),
            (string) ($scan->source_mime_type ?? ''),
            (string) ($scan->source_file_name ?? '')
        );
        $decoded = json_decode($rawContent, true);

        if (is_array($decoded) && isset($decoded['order'], $decoded['items']) && is_array($decoded['items'])) {
            if (!isset($decoded['order']['page_count'])) {
                $decoded['order']['page_count'] = $documentMetrics['page_count'];
            }

            return $this->normalizePayload($decoded, [
                'Mock provider loaded a JSON file directly. Verify values before transfer.',
            ]);
        }

        $filename = pathinfo((string) $scan->source_file_name, PATHINFO_FILENAME);
        $filename = trim(str_replace(['_', '-'], ' ', $filename));

        $customerName = $filename !== '' ? mb_convert_case($filename, MB_CASE_TITLE, 'UTF-8') : 'Nepoznat kupac';

        return $this->normalizePayload([
            'order' => [
                'customer_name' => $customerName,
                'supplier_name' => '',
                'page_count' => $documentMetrics['page_count'],
                'receiver_name' => $customerName,
                'contact_name' => '',
                'external_document_number' => '',
                'document_type' => '',
                'currency' => (string) config('ai-order-scan.default_currency', 'KM'),
                'delivery_deadline' => '',
                'note' => '',
                'way_of_sale' => (string) config('ai-order-scan.default_way_of_sale', 'D'),
                'confidence' => 0.18,
                'warnings' => [],
            ],
            'items' => [],
            'summary' => [
                'subtotal' => 0,
                'vat_total' => 0,
                'grand_total' => 0,
            ],
        ], [
            'Mock provider is active. Connect OPENROUTER_API_KEY or OPENAI_API_KEY before using automatic Pantheon transfer.',
            'No structured order lines were extracted from this file.',
        ]);
    }

    private function normalizePayload(array $payload, array $warnings): array
    {
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $items = array_values(array_filter(array_map(function ($item, int $index) {
            if (!is_array($item)) {
                return null;
            }

            return [
                'line_number' => (int) ($item['line_number'] ?? ($index + 1)),
                'product_code' => trim((string) ($item['product_code'] ?? '')),
                'product_name' => trim((string) ($item['product_name'] ?? '')),
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit' => trim((string) ($item['unit'] ?? config('ai-order-scan.default_unit', 'KO'))),
                'delivery_deadline' => trim((string) ($item['delivery_deadline'] ?? '')),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'line_total' => (float) ($item['line_total'] ?? 0),
                'vat_rate' => (float) ($item['vat_rate'] ?? config('ai-order-scan.default_vat_rate', 17)),
                'vat_code' => trim((string) ($item['vat_code'] ?? config('ai-order-scan.default_vat_code', 'P1'))),
                'discount_percent' => (float) ($item['discount_percent'] ?? 0),
                'priority' => trim((string) ($item['priority'] ?? '')),
                'note' => trim((string) ($item['note'] ?? '')),
            ];
        }, $payload['items'] ?? [], array_keys($payload['items'] ?? []))));

        return [
            'order' => [
                'customer_name' => trim((string) ($order['customer_name'] ?? '')),
                'supplier_name' => trim((string) ($order['supplier_name'] ?? '')),
                'page_count' => max(0, (int) ($order['page_count'] ?? 0)),
                'receiver_name' => trim((string) ($order['receiver_name'] ?? ($order['customer_name'] ?? ''))),
                'contact_name' => trim((string) ($order['contact_name'] ?? '')),
                'external_document_number' => trim((string) ($order['external_document_number'] ?? '')),
                'document_type' => trim((string) ($order['document_type'] ?? '')),
                'currency' => trim((string) ($order['currency'] ?? config('ai-order-scan.default_currency', 'KM'))),
                'delivery_deadline' => trim((string) ($order['delivery_deadline'] ?? '')),
                'note' => trim((string) ($order['note'] ?? '')),
                'way_of_sale' => trim((string) ($order['way_of_sale'] ?? config('ai-order-scan.default_way_of_sale', 'D'))),
                'confidence' => (float) ($order['confidence'] ?? 0),
                'warnings' => array_values(array_unique(array_filter(array_merge(
                    is_array($order['warnings'] ?? null) ? array_map('strval', $order['warnings']) : [],
                    $warnings
                )))),
            ],
            'items' => $items,
            'summary' => [
                'subtotal' => (float) ($payload['summary']['subtotal'] ?? 0),
                'vat_total' => (float) ($payload['summary']['vat_total'] ?? 0),
                'grand_total' => (float) ($payload['summary']['grand_total'] ?? 0),
            ],
        ];
    }

    private function isTextLikeMime(string $mime): bool
    {
        $mime = strtolower(trim($mime));

        if ($mime === '') {
            return false;
        }

        return str_starts_with($mime, 'text/')
            || str_contains($mime, 'json')
            || str_contains($mime, 'xml')
            || str_contains($mime, 'csv');
    }
}
