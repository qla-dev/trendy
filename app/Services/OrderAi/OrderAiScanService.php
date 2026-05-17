<?php

namespace App\Services\OrderAi;

use App\Models\OrderAiScan;
use App\Services\OrderAi\Contracts\OrderAiScanProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class OrderAiScanService
{
    public function createScan(UploadedFile $file, mixed $user = null): OrderAiScan
    {
        $provider = trim((string) config('ai-order-scan.provider', 'mock')) ?: 'mock';
        $model = trim((string) config('ai-order-scan.model', 'gpt-5'));
        $disk = (string) config('ai-order-scan.storage_disk', 'local');
        $directory = trim((string) config('ai-order-scan.storage_directory', 'order-ai-scans'), '/');
        $storedName = Str::uuid()->toString() . '.' . ($file->guessExtension() ?: $file->extension() ?: 'bin');
        $relativePath = $file->storeAs($directory . '/' . Carbon::now()->format('Y/m'), $storedName, $disk);

        if ($relativePath === false) {
            throw new RuntimeException('Upload fajla nije uspio.');
        }

        return OrderAiScan::query()->create([
            'user_id' => is_object($user) ? ($user->id ?? null) : null,
            'provider' => $provider,
            'model' => $model !== '' ? $model : null,
            'status' => 'uploaded',
            'processing_step' => 'Fajl je uspješno učitan.',
            'progress_current' => 10,
            'progress_total' => 100,
            'source_file_name' => $file->getClientOriginalName(),
            'source_file_path' => $relativePath,
            'source_mime_type' => $file->getMimeType(),
            'source_file_size' => $file->getSize(),
            'request_prompt' => (string) config('ai-order-scan.prompt'),
        ]);
    }

    public function advance(OrderAiScan $scan, mixed $user = null): OrderAiScan
    {
        $scan->refresh();

        if (in_array($scan->status, ['failed', 'transferred', 'completed'], true)) {
            return $scan;
        }

        if ($scan->status === 'uploaded') {
            $scan->forceFill([
                'status' => 'extracting',
                'processing_step' => 'Pokrenuta je AI analiza dokumenta.',
                'progress_current' => 25,
            ])->save();

            return $scan->fresh();
        }

        if ($scan->status === 'extracting') {
            return $this->runExtraction($scan);
        }

        if ($scan->status === 'ready_for_transfer') {
            $scan->forceFill([
                'status' => 'transferring',
                'processing_step' => 'Priprema transfera prema Pantheonu.',
                'progress_current' => 82,
            ])->save();

            return $scan->fresh();
        }

        if ($scan->status === 'transferring') {
            return $this->runTransfer($scan, $user);
        }

        return $scan;
    }

    public function buildStatusPayload(OrderAiScan $scan): array
    {
        $payload = is_array($scan->normalized_payload) ? $scan->normalized_payload : [];
        $warnings = is_array($payload['order']['warnings'] ?? null) ? $payload['order']['warnings'] : [];
        $transferService = app(PantheonOrderTransferService::class);
        $transferReady = $transferService->isTransferReady($payload);
        $autoTransfer = $this->shouldAutoTransfer($scan);

        return [
            'id' => $scan->id,
            'status' => $scan->status,
            'processing_step' => $scan->processing_step,
            'current_progress' => (int) $scan->progress_current,
            'max_progress_steps' => (int) $scan->progress_total,
            'credits_spent' => (float) $scan->credits_spent,
            'warnings' => $warnings,
            'transfer_ready' => $transferReady,
            'auto_transfer' => $autoTransfer,
            'pantheon_order' => [
                'key' => (string) ($scan->pantheon_order_key ?? ''),
                'view' => (string) ($scan->pantheon_order_view ?? ''),
                'qid' => $scan->pantheon_order_qid,
            ],
            'result' => $payload,
            'error_message' => $scan->error_message,
        ];
    }

    private function runExtraction(OrderAiScan $scan): OrderAiScan
    {
        try {
            $provider = $this->resolveProvider($scan);
            $result = $provider->scan($scan);
            $normalizedPayload = $this->normalizePayload($result['normalized_payload'] ?? []);
            $transferReady = app(PantheonOrderTransferService::class)->isTransferReady($normalizedPayload);
            $autoTransfer = $transferReady && $this->shouldAutoTransfer($scan) && $provider->supportsLiveTransfer();

            $scan->forceFill([
                'provider' => (string) ($result['provider'] ?? $scan->provider),
                'model' => (string) ($result['model'] ?? $scan->model),
                'provider_task_id' => trim((string) ($result['provider_task_id'] ?? '')) ?: null,
                'raw_provider_response' => $result['raw_response'] ?? null,
                'normalized_payload' => $normalizedPayload,
                'credits_spent' => (float) ($result['credits_spent'] ?? 0),
                'processed_at' => now(),
                'status' => $autoTransfer ? 'ready_for_transfer' : 'completed',
                'processing_step' => $autoTransfer
                    ? 'AI analiza završena. Dokument je spreman za transfer.'
                    : 'AI analiza završena. Automatski transfer je preskočen.',
                'progress_current' => $autoTransfer ? 70 : 100,
                'completed_at' => $autoTransfer ? null : now(),
                'error_message' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $scan->forceFill([
                'status' => 'failed',
                'processing_step' => 'AI analiza nije uspjela.',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();
        }

        return $scan->fresh();
    }

    private function runTransfer(OrderAiScan $scan, mixed $user = null): OrderAiScan
    {
        try {
            $result = app(PantheonOrderTransferService::class)
                ->createFromNormalizedPayload((array) $scan->normalized_payload, $user);

            $scan->forceFill([
                'status' => 'transferred',
                'processing_step' => 'Narudžba je prebačena u Pantheon.',
                'progress_current' => 100,
                'pantheon_transfer_payload' => $result,
                'pantheon_order_key' => $result['pantheon_order_key'] ?? null,
                'pantheon_order_view' => $result['pantheon_order_view'] ?? null,
                'pantheon_order_qid' => $result['pantheon_order_qid'] ?? null,
                'transferred_at' => now(),
                'completed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $scan->forceFill([
                'status' => 'failed',
                'processing_step' => 'Transfer prema Pantheonu nije uspio.',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();
        }

        return $scan->fresh();
    }

    private function shouldAutoTransfer(OrderAiScan $scan): bool
    {
        return filter_var(config('ai-order-scan.auto_transfer', true), FILTER_VALIDATE_BOOL)
            && $scan->provider !== 'mock';
    }

    private function resolveProvider(OrderAiScan $scan): OrderAiScanProvider
    {
        $configuredProvider = trim((string) ($scan->provider ?: config('ai-order-scan.provider', 'mock')));

        if ($configuredProvider === 'openrouter') {
            return app(OpenRouterOrderAiScanProvider::class);
        }

        if ($configuredProvider === 'openai') {
            return app(OpenAiOrderScanProvider::class);
        }

        return app(MockOrderAiScanProvider::class);
    }

    private function normalizePayload(array $payload): array
    {
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        return [
            'order' => [
                'customer_name' => trim((string) ($order['customer_name'] ?? '')),
                'receiver_name' => trim((string) ($order['receiver_name'] ?? ($order['customer_name'] ?? ''))),
                'contact_name' => trim((string) ($order['contact_name'] ?? '')),
                'external_document_number' => trim((string) ($order['external_document_number'] ?? '')),
                'document_type' => trim((string) ($order['document_type'] ?? '')),
                'currency' => trim((string) ($order['currency'] ?? config('ai-order-scan.default_currency', 'KM'))),
                'delivery_deadline' => trim((string) ($order['delivery_deadline'] ?? '')),
                'note' => trim((string) ($order['note'] ?? '')),
                'way_of_sale' => trim((string) ($order['way_of_sale'] ?? config('ai-order-scan.default_way_of_sale', 'D'))),
                'confidence' => (float) ($order['confidence'] ?? 0),
                'warnings' => array_values(array_filter(array_map(function ($warning) {
                    return trim((string) $warning);
                }, is_array($order['warnings'] ?? null) ? $order['warnings'] : []))),
            ],
            'items' => array_values(array_filter(array_map(function ($item, int $index) {
                if (!is_array($item)) {
                    return null;
                }

                return [
                    'line_number' => (int) ($item['line_number'] ?? ($index + 1)),
                    'product_code' => trim((string) ($item['product_code'] ?? '')),
                    'product_name' => trim((string) ($item['product_name'] ?? '')),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'unit' => trim((string) ($item['unit'] ?? 'KO')),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'vat_rate' => (float) ($item['vat_rate'] ?? config('ai-order-scan.default_vat_rate', 17)),
                    'vat_code' => trim((string) ($item['vat_code'] ?? config('ai-order-scan.default_vat_code', 'P1'))),
                    'discount_percent' => (float) ($item['discount_percent'] ?? 0),
                    'priority' => trim((string) ($item['priority'] ?? '')),
                    'note' => trim((string) ($item['note'] ?? '')),
                ];
            }, $items, array_keys($items)))),
            'summary' => [
                'subtotal' => (float) ($payload['summary']['subtotal'] ?? 0),
                'vat_total' => (float) ($payload['summary']['vat_total'] ?? 0),
                'grand_total' => (float) ($payload['summary']['grand_total'] ?? 0),
            ],
        ];
    }
}
