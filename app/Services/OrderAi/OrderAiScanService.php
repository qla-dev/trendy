<?php

namespace App\Services\OrderAi;

use App\Jobs\ProcessImportedOrderAiScanJob;
use App\Models\OrderAiScan;
use App\Services\OrderAi\Contracts\OrderAiScanProvider;
use App\Services\OrderAi\Profiles\OrderDocumentProfileDetector;
use App\Services\OrderAi\Support\OrderAiDocumentPreparationService;
use App\Services\OrderAi\Support\OrderAiDocumentMetrics;
use App\Support\Utf8Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class OrderAiScanService
{
    private const TRENDY_DE_PARTY_NAME = 'Trendy Germany GmbH';
    private const GROB_ATTENTION_MARKER = '*********************************** ACHTUNG * *************************************';

    private ?array $orderAiScanColumns = null;
    private array $effectivePageMetaCache = [];

    public function createScan(UploadedFile $file, mixed $user = null): OrderAiScan
    {
        $provider = trim((string) config('ai-order-scan.provider', 'mock')) ?: 'mock';
        $model = trim((string) config('ai-order-scan.model', 'gpt-5'));
        $disk = (string) config('ai-order-scan.storage_disk', 'local');
        $directory = trim((string) config('ai-order-scan.storage_directory', 'order-ai-scans'), '/');
        $originalName = (string) $file->getClientOriginalName();
        $mimeType = (string) ($file->getMimeType() ?: '');
        $fileBytes = $this->readLocalFileBytes((string) $file->getRealPath());
        $documentProfile = $this->detectDocumentProfile($originalName, $mimeType, $fileBytes);
        $requestPrompt = $this->buildRequestPrompt($documentProfile);
        $documentMetrics = app(OrderAiDocumentMetrics::class)->resolveForUpload($file);
        $storedName = Str::uuid()->toString() . '.' . ($file->guessExtension() ?: $file->extension() ?: 'bin');
        $relativePath = $file->storeAs($directory . '/' . Carbon::now()->format('Y/m'), $storedName, $disk);

        if ($relativePath === false) {
            throw new RuntimeException('Upload fajla nije uspio.');
        }

        return $this->createScanRecord(
            relativePath: $relativePath,
            originalName: $originalName,
            mimeType: $mimeType,
            fileSize: $file->getSize(),
            documentMetrics: $documentMetrics,
            documentProfile: $documentProfile,
            requestPrompt: $requestPrompt,
            user: $user
        );
    }

    public function createScanFromBinary(
        string $originalName,
        string $binaryContent,
        ?string $mimeType = null,
        mixed $user = null,
        array $attributes = []
    ): OrderAiScan {
        $disk = (string) config('ai-order-scan.storage_disk', 'local');
        $directory = trim((string) config('ai-order-scan.storage_directory', 'order-ai-scans'), '/');
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $storedName = Str::uuid()->toString() . '.' . ($extension !== '' ? $extension : $this->guessExtensionFromMimeType($mimeType));
        $relativePath = trim($directory . '/' . Carbon::now()->format('Y/m') . '/' . $storedName, '/');
        $stored = Storage::disk($disk)->put($relativePath, $binaryContent);

        if ($stored === false) {
            throw new RuntimeException('Spremanje email privitka nije uspjelo.');
        }

        $resolvedMimeType = trim((string) $mimeType) !== ''
            ? trim((string) $mimeType)
            : $this->guessMimeTypeFromName($originalName);
        $documentProfile = $this->detectDocumentProfile($originalName, $resolvedMimeType, $binaryContent);
        $requestPrompt = $this->buildRequestPrompt($documentProfile);
        $documentMetrics = app(OrderAiDocumentMetrics::class)->resolveForStoredFile(
            $disk,
            $relativePath,
            $resolvedMimeType,
            $originalName
        );

        return $this->createScanRecord(
            relativePath: $relativePath,
            originalName: $originalName,
            mimeType: $resolvedMimeType,
            fileSize: strlen($binaryContent),
            documentMetrics: $documentMetrics,
            documentProfile: $documentProfile,
            requestPrompt: $requestPrompt,
            user: $user,
            attributes: $attributes
        );
    }

    public function dispatchBackgroundProcessing(OrderAiScan $scan): void
    {
        $connection = (string) config('ai-order-scan.inbox.queue_connection', 'database_ai_inbox');
        $queue = (string) config('ai-order-scan.inbox.queue_name', 'ai-inbox');

        ProcessImportedOrderAiScanJob::dispatch((int) $scan->id)
            ->onConnection($connection)
            ->onQueue($queue);

        Log::info('AI inbox scan dispatched to queue.', [
            'scan_id' => (int) $scan->id,
            'connection' => $connection,
            'queue' => $queue,
            'status' => (string) ($scan->status ?? ''),
            'source_origin' => (string) ($scan->source_origin ?? ''),
            'source_email_subject' => (string) ($scan->source_email_subject ?? ''),
        ]);
    }

    public function processUntilReviewed(OrderAiScan $scan, mixed $user = null): OrderAiScan
    {
        $current = $scan->fresh();
        $safety = 0;

        while ($current instanceof OrderAiScan && !$current->isTerminal() && $safety < 5) {
            $current = $this->advance($current, $user);
            $safety++;
        }

        return $current instanceof OrderAiScan ? $current->fresh() : $scan->fresh();
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
                'processing_step' => 'Pokrenuta je AI obrada dokumenta.',
                'progress_current' => 25,
            ])->save();

            return $scan->fresh();
        }

        if ($scan->status === 'extracting') {
            return $this->runExtraction($scan, $user);
        }

        if ($scan->status === 'ready_for_transfer') {
            $attributes = [
                'status' => 'transferring',
                'processing_step' => 'Priprema upisa u bazu.',
                'progress_current' => 82,
            ];

            if ($this->orderAiScanColumnExists('transfer_started_at')) {
                $attributes['transfer_started_at'] = now();
            }

            $scan->forceFill($attributes)->save();

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
        $transferPreview = Utf8Sanitizer::cleanRecursive($scan->pantheon_transfer_payload);
        $payload = $this->overlayTransferPreview($payload, $transferPreview);
        $processingPageMeta = $this->resolveProcessingPageMeta($scan, $payload);

        if (!isset($payload['order']) || !is_array($payload['order'])) {
            $payload['order'] = [];
        }

        if (($processingPageMeta['effective_page_count'] ?? 0) > 0) {
            $payload['order']['effective_page_count'] = (int) $processingPageMeta['effective_page_count'];
        }

        if (($processingPageMeta['page_processing_limit_reason'] ?? '') !== '') {
            $payload['order']['page_processing_limit_reason'] = (string) $processingPageMeta['page_processing_limit_reason'];
        }

        $payload = Utf8Sanitizer::cleanRecursive($payload);
        $warnings = is_array($payload['order']['warnings'] ?? null) ? $payload['order']['warnings'] : [];
        $autoTransfer = $this->shouldAutoTransfer($scan);
        $transferReady = $this->resolveTransferReadyForDisplay($scan, $payload, $transferPreview);
        $elapsed = $this->resolveElapsedMeta($scan);
        $pageCount = max(0, (int) ($scan->page_count ?? data_get($payload, 'order.page_count', 0)));
        $billedTokens = max(0, (int) ($scan->billed_tokens ?? 0));
        $displayErrorMessage = $this->resolveDisplayErrorMessage($scan);

        if ($billedTokens <= 0 && $pageCount > 0) {
            $billedTokens = app(OrderAiDocumentMetrics::class)->calculateBilledTokens($pageCount);
        }

        return [
            'id' => $scan->id,
            'status' => Utf8Sanitizer::clean((string) ($scan->status ?? ''), 40),
            'processing_step' => Utf8Sanitizer::clean((string) ($scan->processing_step ?? ''), 160),
            'current_progress' => (int) $scan->progress_current,
            'max_progress_steps' => (int) $scan->progress_total,
            'credits_spent' => (float) $scan->credits_spent,
            'page_count' => $pageCount,
            'effective_page_count' => (int) ($processingPageMeta['effective_page_count'] ?? 0),
            'page_processing_limit_reason' => (string) ($processingPageMeta['page_processing_limit_reason'] ?? ''),
            'billed_tokens' => $billedTokens,
            'started_at' => $elapsed['started_at'],
            'finished_at' => $elapsed['finished_at'],
            'elapsed_seconds' => $elapsed['seconds'],
            'elapsed_display' => $elapsed['display'],
            'warnings' => $warnings,
            'transfer_ready' => $transferReady,
            'auto_transfer' => $autoTransfer,
            'transfer_preview_available' => !empty($transferPreview),
            'transfer_preview_error' => is_array($transferPreview)
                ? (string) ($transferPreview['preview_error'] ?? '')
                : '',
            'source_origin' => Utf8Sanitizer::clean((string) ($scan->source_origin ?? 'manual'), 40),
            'source_file_name' => Utf8Sanitizer::clean((string) ($scan->source_file_name ?? '')),
            'source_email_subject' => Utf8Sanitizer::clean((string) ($scan->source_email_subject ?? '')),
            'source_email_from' => Utf8Sanitizer::clean((string) ($scan->source_email_from ?? '')),
            'source_email_received_at' => $scan->source_email_received_at?->toIso8601String(),
            'pantheon_order' => [
                'key' => Utf8Sanitizer::clean((string) ($scan->pantheon_order_key ?? '')),
                'view' => Utf8Sanitizer::clean((string) ($scan->pantheon_order_view ?? '')),
                'qid' => $scan->pantheon_order_qid,
            ],
            'result' => $payload,
            'error_message' => $displayErrorMessage,
        ];
    }

    private function runExtraction(OrderAiScan $scan, mixed $user = null): OrderAiScan
    {
        try {
            $provider = $this->resolveProvider($scan);
            $result = $provider->scan($scan);
            $profilePayload = $this->postProcessProfilePayload(
                $scan,
                is_array($result['normalized_payload'] ?? null) ? $result['normalized_payload'] : []
            );
            $normalizedPayload = Utf8Sanitizer::cleanRecursive(
                $this->normalizePayload($profilePayload)
            );
            $pageCount = max(0, (int) data_get($normalizedPayload, 'order.page_count', 0));
            $billedTokens = $pageCount > 0
                ? app(OrderAiDocumentMetrics::class)->calculateBilledTokens($pageCount)
                : 0;
            $transferReady = app(PantheonOrderTransferService::class)->isTransferReady($normalizedPayload);
            $autoTransfer = $transferReady && $this->shouldAutoTransfer($scan) && $provider->supportsLiveTransfer();
            $transferPreview = null;

            if ($transferReady && !$autoTransfer) {
                $transferPreview = $this->buildTransferPreview($scan, $normalizedPayload, $user);
            }

            $attributes = [
                'provider' => Utf8Sanitizer::clean((string) ($result['provider'] ?? $scan->provider), 40),
                'model' => Utf8Sanitizer::clean((string) ($result['model'] ?? $scan->model), 120),
                'provider_task_id' => trim(Utf8Sanitizer::clean((string) ($result['provider_task_id'] ?? ''))) ?: null,
                'raw_provider_response' => Utf8Sanitizer::cleanRecursive($result['raw_response'] ?? null),
                'normalized_payload' => $normalizedPayload,
                'pantheon_transfer_payload' => $transferPreview,
                'credits_spent' => (float) ($result['credits_spent'] ?? 0),
                'processed_at' => now(),
                'status' => $autoTransfer ? 'ready_for_transfer' : 'completed',
                'processing_step' => $this->resolveCompletedProcessingStep($autoTransfer, $transferReady, $transferPreview),
                'progress_current' => $autoTransfer ? 70 : 100,
                'completed_at' => $autoTransfer ? null : now(),
                'error_message' => null,
            ];

            if ($pageCount > 0 && $this->orderAiScanColumnExists('page_count')) {
                $attributes['page_count'] = $pageCount;
            }

            if ($billedTokens > 0 && $this->orderAiScanColumnExists('billed_tokens')) {
                $attributes['billed_tokens'] = $billedTokens;
            }

            $scan->forceFill($attributes)->save();
        } catch (\Throwable $exception) {
            Log::warning('Order AI extraction failed.', [
                'scan_id' => $scan->id,
                'user_id' => $scan->user_id,
                'provider' => $scan->provider,
                'document_profile' => $scan->document_profile,
                'message' => Utf8Sanitizer::cleanExceptionMessage($exception),
            ]);

            $scan->forceFill([
                'status' => 'failed',
                'processing_step' => 'AI analiza nije uspjela.',
                'error_message' => $this->humanizeExtractionFailureReason($exception),
                'completed_at' => now(),
            ])->save();
        }

        return $scan->fresh();
    }

    private function runTransfer(OrderAiScan $scan, mixed $user = null): OrderAiScan
    {
        try {
            $result = Utf8Sanitizer::cleanRecursive(
                app(PantheonOrderTransferService::class)
                    ->createFromNormalizedPayload((array) $scan->normalized_payload, $user)
            );

            $scan->forceFill([
                'status' => 'transferred',
                'processing_step' => 'Narudžba je prebačena u bazu.',
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
                'processing_step' => 'Transfer u bazu nije uspio.',
                'error_message' => $this->humanizeTransferFailureReason($exception),
                'completed_at' => now(),
            ])->save();
        }

        return $scan->fresh();
    }

    private function humanizeExtractionFailureReason(\Throwable $exception): string
    {
        return $this->humanizeExtractionFailureMessage(
            Utf8Sanitizer::cleanExceptionMessage($exception)
        );
    }

    private function humanizeExtractionFailureMessage(string $message): string
    {
        $message = Utf8Sanitizer::clean($message);
        $normalized = Str::lower($message);

        if ($message === '') {
            return 'AI obrada narudzbe je bila neuspjesna.';
        }

        if (str_contains($normalized, 'openrouter') || str_contains($normalized, 'openai')) {
            if (str_contains($normalized, 'json') || str_contains($normalized, 'tekstualni')) {
                return 'AI obrada narudzbe je bila neuspjesna. AI provider nije vratio ispravan odgovor.';
            }

            return 'AI obrada narudzbe je bila neuspjesna. AI provider je prekinuo obradu dokumenta.';
        }

        return 'AI obrada narudzbe je bila neuspjesna. ' . $message;
    }

    private function resolveDisplayErrorMessage(OrderAiScan $scan): string
    {
        $message = Utf8Sanitizer::clean((string) ($scan->error_message ?? ''));

        if ($message === '') {
            return '';
        }

        $normalizedMessage = Str::lower($message);
        $normalizedStep = Str::lower(Utf8Sanitizer::clean((string) ($scan->processing_step ?? '')));
        $looksLikeTransferFailure = str_contains($normalizedStep, 'transfer')
            || str_contains($normalizedStep, 'bazu')
            || str_contains($normalizedMessage, 'pantheon');
        $looksLikeLegacyProviderFailure = str_contains($normalizedMessage, 'openrouter')
            || str_contains($normalizedMessage, 'openai');

        if ($looksLikeTransferFailure) {
            return $this->humanizeTransferFailureMessage($message);
        }

        if (!$looksLikeTransferFailure && $looksLikeLegacyProviderFailure) {
            return $this->humanizeExtractionFailureMessage($message);
        }

        return $message;
    }

    private function humanizeTransferFailureReason(\Throwable $exception): string
    {
        return $this->humanizeTransferFailureMessage(
            Utf8Sanitizer::cleanExceptionMessage($exception)
        );
    }

    private function humanizeTransferFailureMessage(string $message): string
    {
        $message = trim(Utf8Sanitizer::clean($message));

        if ($message === '') {
            return 'Transfer u bazu nije uspio, ali detaljan razlog nije vraćen.';
        }

        $normalized = Str::lower($message);

        if (str_contains($normalized, 'unknown column') && str_contains($normalized, 'transfer_started_at')) {
            return 'Transfer trenutno nije moguć jer bazi nedostaje obavezno polje za praćenje transfera. Potrebno je ažurirati bazu pa pokušati ponovo.';
        }

        if (str_contains($normalized, 'rthe_order_the_setsubj_21') || str_contains($normalized, 'anconsigneeqid')) {
            return 'Pantheon nije prihvatio naručitelja jer nije bio postavljen validan subject za anConsigneeQId.';
        }

        if (str_contains($normalized, 'sqlstate') || str_contains($normalized, 'column not found')) {
            return 'Transfer trenutno nije moguć zbog interne greške pri upisu u bazu. Pokušajte ponovo ili kontaktirajte administratora.';
        }

        return $message;
    }

    private function shouldAutoTransfer(OrderAiScan $scan): bool
    {
        if ((string) ($scan->source_origin ?? 'manual') === 'imap') {
            return false;
        }

        return filter_var(config('ai-order-scan.auto_transfer', false), FILTER_VALIDATE_BOOL)
            && $scan->provider !== 'mock';
    }

    private function buildTransferPreview(OrderAiScan $scan, array $normalizedPayload, mixed $user = null): ?array
    {
        try {
            $preview = app(PantheonOrderTransferService::class)->previewFromNormalizedPayload($normalizedPayload, $user);
            $preview = Utf8Sanitizer::cleanRecursive($preview);

            Log::info('Order AI Pantheon preview prepared.', [
                'scan_id' => $scan->id,
                'user_id' => $scan->user_id,
                'pantheon_order_key' => $preview['pantheon_order_key'] ?? null,
                'pantheon_order_qid' => $preview['pantheon_order_qid'] ?? null,
                'item_count' => $preview['item_count'] ?? 0,
                'preview' => $preview,
            ]);

            return $preview;
        } catch (\Throwable $exception) {
            $sanitizedMessage = Utf8Sanitizer::cleanExceptionMessage($exception);

            Log::warning('Order AI Pantheon preview failed.', [
                'scan_id' => $scan->id,
                'user_id' => $scan->user_id,
                'message' => $sanitizedMessage,
            ]);

            return [
                'preview_error' => $sanitizedMessage,
            ];
        }
    }

    private function resolveCompletedProcessingStep(bool $autoTransfer, bool $transferReady, mixed $transferPreview): string
    {
        if ($autoTransfer) {
            return 'AI obrada je završena. Dokument je spreman za upis u bazu.';
        }

        if (!$transferReady) {
            return 'AI obrada je završena. Pregledaj rezultat i dopuni podatke prije upisa u bazu.';
        }

        if (is_array($transferPreview) && !empty($transferPreview['preview_error'])) {
            return 'AI obrada je završena. Provjera podataka za bazu nije uspjela, ali možeš nastaviti ručno.';
        }

        return 'AI obrada je završena. Narudžba je spremna za upis u bazu.';
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
        $normalizedItems = array_values(array_filter(array_map(function ($item, int $index) {
            if (!is_array($item)) {
                return null;
            }

            $itemMeta = $this->extractScannedItemMetadata(
                (string) ($item['product_name'] ?? ''),
                (string) ($item['note'] ?? ''),
                (string) ($item['drawing_reference'] ?? ''),
                (string) ($item['material_hint'] ?? '')
            );

            return [
                'line_number' => (int) ($item['line_number'] ?? ($index + 1)),
                'product_code' => trim((string) ($item['product_code'] ?? '')),
                'product_name' => $itemMeta['product_name'],
                'drawing_reference' => $itemMeta['drawing_reference'],
                'material_hint' => $itemMeta['material_hint'],
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit' => $this->normalizeScannedUnit(
                    trim((string) ($item['unit'] ?? '')) !== ''
                        ? (string) $item['unit']
                        : (string) config('ai-order-scan.default_unit', 'KO')
                ),
                'delivery_deadline' => trim((string) ($item['delivery_deadline'] ?? '')),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'line_total' => (float) ($item['line_total'] ?? 0),
                'vat_rate' => (float) ($item['vat_rate'] ?? config('ai-order-scan.default_vat_rate', 17)),
                'vat_code' => trim((string) ($item['vat_code'] ?? config('ai-order-scan.default_vat_code', 'P1'))),
                'discount_percent' => (float) ($item['discount_percent'] ?? 0),
                'priority' => trim((string) ($item['priority'] ?? '')),
                'note' => $itemMeta['note'],
            ];
        }, $items, array_keys($items))));
        $supplierName = $this->normalizeSupplierName((string) ($order['supplier_name'] ?? ''));
        $warnings = array_values(array_filter(array_map(function ($warning) {
            return trim((string) $warning);
        }, is_array($order['warnings'] ?? null) ? $order['warnings'] : [])));
        $summary = $this->normalizeSummary($payload, $normalizedItems, $supplierName, $warnings);

        return [
            'order' => [
                'customer_name' => trim((string) ($order['customer_name'] ?? '')),
                'supplier_name' => $supplierName,
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
                'warnings' => $warnings,
            ],
            'items' => $normalizedItems,
            'summary' => $summary,
        ];
    }

    private function normalizeSummary(array $payload, array $items, string $supplierName, array &$warnings): array
    {
        $summarySubtotal = (float) ($payload['summary']['subtotal'] ?? 0);
        $summaryVatTotal = (float) ($payload['summary']['vat_total'] ?? 0);
        $summaryGrandTotal = (float) ($payload['summary']['grand_total'] ?? 0);
        $computedSubtotal = 0.0;
        $computedVatTotal = 0.0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $quantity = max(0, (float) ($item['quantity'] ?? 0));
            $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));
            $lineTotal = max(0, (float) ($item['line_total'] ?? 0));
            $discountPercent = max(0, (float) ($item['discount_percent'] ?? 0));
            $vatRate = max(0, (float) ($item['vat_rate'] ?? 0));
            $discountFactor = max(0, 1 - ($discountPercent / 100));
            $baseValue = $lineTotal > 0 ? $lineTotal : ($quantity * $unitPrice * $discountFactor);

            $computedSubtotal += $baseValue;
            $computedVatTotal += $baseValue * ($vatRate / 100);
        }

        $computedSubtotal = round($computedSubtotal, 4);
        $computedVatTotal = round($computedVatTotal, 4);
        $computedGrandTotal = round($computedSubtotal + $computedVatTotal, 4);
        $subtotalDelta = round($summarySubtotal - $computedSubtotal, 4);

        if (abs($subtotalDelta) >= 0.01) {
            $warnings[] = $this->buildSubtotalMismatchWarning($subtotalDelta, $supplierName);
        }

        if ($summarySubtotal <= 0 && $computedSubtotal > 0) {
            $summarySubtotal = $computedSubtotal;
        }

        if ($summaryVatTotal <= 0 && $computedVatTotal > 0) {
            $summaryVatTotal = $computedVatTotal;
        }

        if ($summaryGrandTotal <= 0) {
            $summaryGrandTotal = round($summarySubtotal + $summaryVatTotal, 4);
        }

        if ($summaryGrandTotal <= 0 && $computedGrandTotal > 0) {
            $summaryGrandTotal = $computedGrandTotal;
        }

        $warnings = array_values(array_unique(array_filter($warnings)));

        return [
            'subtotal' => $summarySubtotal,
            'vat_total' => $summaryVatTotal,
            'grand_total' => $summaryGrandTotal,
        ];
    }

    private function extractScannedItemMetadata(
        string $productName,
        string $note = '',
        string $drawingReference = '',
        string $materialHint = ''
    ): array {
        $nameLines = [];
        $drawingParts = [];
        $noteParts = [];
        $productName = trim($productName);
        $note = trim($note);
        $drawingReference = trim($drawingReference);
        $materialHint = trim($materialHint);

        foreach ($this->splitVisibleTextLines($productName) as $line) {
            if (preg_match('/^werkstoff\s*:/iu', $line) === 1) {
                if ($materialHint === '') {
                    $materialHint = preg_replace('/^werkstoff\s*:\s*/iu', '', $line) ?? $line;
                }

                continue;
            }

            if (preg_match('/^zeichnung\b/iu', $line) === 1) {
                $drawingParts[] = $line;
                continue;
            }

            $nameLines[] = $line;
        }

        foreach ($this->splitVisibleTextLines($drawingReference) as $line) {
            $drawingParts[] = $line;
        }

        foreach ($this->splitVisibleTextLines($note) as $line) {
            if (preg_match('/^werkstoff\s*:/iu', $line) === 1) {
                if ($materialHint === '') {
                    $materialHint = preg_replace('/^werkstoff\s*:\s*/iu', '', $line) ?? $line;
                }

                continue;
            }

            $noteParts[] = $line;
        }

        $drawingReference = implode(' | ', array_values(array_unique(array_filter($drawingParts))));

        if ($drawingReference !== '') {
            $noteParts[] = $drawingReference;
        }

        $resolvedProductName = trim(implode(' ', array_values(array_filter($nameLines))));

        if ($resolvedProductName === '') {
            $resolvedProductName = $productName;
        }

        return [
            'product_name' => $resolvedProductName,
            'drawing_reference' => $drawingReference,
            'material_hint' => trim((string) (preg_replace('/\s+/', ' ', $materialHint) ?? $materialHint)),
            'note' => implode(' | ', array_values(array_unique(array_filter($noteParts)))),
        ];
    }

    private function splitVisibleTextLines(string $value): array
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = preg_split('/\n+/u', $value) ?: [];

        return array_values(array_filter(array_map(function ($line) {
            $line = trim((string) (preg_replace('/\s+/u', ' ', (string) $line) ?? $line));

            return $line;
        }, $lines)));
    }

    private function buildSubtotalMismatchWarning(float $subtotalDelta, string $supplierName): string
    {
        $amount = number_format(abs($subtotalDelta), 2, '.', '');

        if ($subtotalDelta > 0 && stripos($supplierName, 'grob') !== false) {
            return 'Skenirani Nettowert/Gesamtbetrag je veci od zbira stavki za ' . $amount
                . '. Provjeri GROB page-break continuation redove poput Ruesten/Termin abs. i Nettopreis, jer vjerovatno pripadaju prethodnoj stavci.';
        }

        if ($subtotalDelta > 0) {
            return 'Skenirani dokumentni iznos je veci od zbira stavki za ' . $amount
                . '. Moguc je continuation red na prelomu stranice koji pripada prethodnoj stavci.';
        }

        return 'Skenirani dokumentni iznos se razlikuje od zbira stavki za ' . $amount . '. Provjeri ekstraktovane cijene i totale.';
    }

    private function overlayTransferPreview(array $payload, mixed $transferPreview): array
    {
        if (!is_array($transferPreview)) {
            return $payload;
        }

        $prepared = is_array($transferPreview['payload'] ?? null) ? $transferPreview['payload'] : [];

        if (empty($prepared)) {
            return $payload;
        }

        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        $items = is_array($payload['items'] ?? null) ? array_values($payload['items']) : [];
        $preparedItems = is_array($prepared['items'] ?? null) ? array_values($prepared['items']) : [];

        $payload['order'] = array_merge($order, [
            'customer_name' => trim((string) ($prepared['customer_name'] ?? ($order['customer_name'] ?? ''))),
            'supplier_name' => $this->normalizeSupplierName((string) ($prepared['supplier_name'] ?? ($order['supplier_name'] ?? ''))),
            'receiver_name' => trim((string) ($prepared['receiver_name'] ?? ($order['receiver_name'] ?? ''))),
            'contact_name' => trim((string) ($prepared['contact_name'] ?? ($order['contact_name'] ?? ''))),
            'external_document_number' => trim((string) ($prepared['external_document_number'] ?? ($order['external_document_number'] ?? ''))),
            'document_type' => trim((string) ($prepared['document_type'] ?? ($order['document_type'] ?? ''))),
            'currency' => trim((string) ($prepared['currency'] ?? ($order['currency'] ?? ''))),
            'delivery_deadline' => trim((string) ($prepared['delivery_deadline'] ?? ($order['delivery_deadline'] ?? ''))),
            'note' => trim((string) ($prepared['note'] ?? ($order['note'] ?? ''))),
            'way_of_sale' => trim((string) ($prepared['way_of_sale'] ?? ($order['way_of_sale'] ?? ''))),
            'warnings' => array_values(array_unique(array_filter(
                is_array($prepared['warnings'] ?? null)
                    ? array_map('strval', $prepared['warnings'])
                    : (is_array($order['warnings'] ?? null) ? array_map('strval', $order['warnings']) : [])
            ))),
        ]);

        foreach ($preparedItems as $index => $preparedItem) {
            $existingItem = is_array($items[$index] ?? null) ? $items[$index] : [];

            $items[$index] = array_merge($existingItem, [
                'line_number' => (int) ($preparedItem['line_number'] ?? ($existingItem['line_number'] ?? ($index + 1))),
                'product_code' => trim((string) ($preparedItem['product_code'] ?? ($existingItem['product_code'] ?? ''))),
                'product_name' => trim((string) ($preparedItem['product_name'] ?? ($existingItem['product_name'] ?? ''))),
                'drawing_reference' => trim((string) ($preparedItem['drawing_reference'] ?? ($existingItem['drawing_reference'] ?? ''))),
                'material_hint' => trim((string) ($preparedItem['material_hint'] ?? ($existingItem['material_hint'] ?? ''))),
                'quantity' => (float) ($preparedItem['quantity'] ?? ($existingItem['quantity'] ?? 0)),
                'unit' => $this->normalizeScannedUnit((string) ($preparedItem['unit'] ?? ($existingItem['unit'] ?? ''))),
                'delivery_deadline' => trim((string) ($preparedItem['delivery_deadline'] ?? ($existingItem['delivery_deadline'] ?? ''))),
                'unit_price' => (float) ($existingItem['unit_price'] ?? ($preparedItem['unit_price'] ?? 0)),
                'line_total' => (float) ($existingItem['line_total'] ?? ($preparedItem['line_total'] ?? 0)),
                'vat_rate' => (float) ($preparedItem['vat_rate'] ?? ($existingItem['vat_rate'] ?? 0)),
                'vat_code' => trim((string) ($preparedItem['vat_code'] ?? ($existingItem['vat_code'] ?? ''))),
                'discount_percent' => (float) ($preparedItem['discount_percent'] ?? ($existingItem['discount_percent'] ?? 0)),
                'priority' => trim((string) ($preparedItem['priority'] ?? ($existingItem['priority'] ?? ''))),
                'note' => trim((string) ($preparedItem['note'] ?? ($existingItem['note'] ?? ''))),
                'primary_classification' => trim((string) ($preparedItem['primary_classification'] ?? ($existingItem['primary_classification'] ?? ''))),
                'catalog_item_exists' => (bool) ($preparedItem['catalog_item_exists'] ?? ($existingItem['catalog_item_exists'] ?? false)),
                'catalog_item_missing' => (bool) ($preparedItem['catalog_item_missing'] ?? ($existingItem['catalog_item_missing'] ?? false)),
                'catalog_item_auto_create' => (bool) ($preparedItem['catalog_item_auto_create'] ?? ($existingItem['catalog_item_auto_create'] ?? false)),
                'catalog_item_created' => (bool) ($preparedItem['catalog_item_created'] ?? ($existingItem['catalog_item_created'] ?? false)),
                'catalog_item_status' => trim((string) ($preparedItem['catalog_item_status'] ?? ($existingItem['catalog_item_status'] ?? ''))),
                'catalog_item_notice' => trim((string) ($preparedItem['catalog_item_notice'] ?? ($existingItem['catalog_item_notice'] ?? ''))),
            ]);
        }

        $payload['items'] = $items;
        $payload['summary'] = array_merge($summary, [
            'subtotal' => (float) ($prepared['subtotal'] ?? ($summary['subtotal'] ?? 0)),
            'vat_total' => (float) ($prepared['vat_total'] ?? ($summary['vat_total'] ?? 0)),
            'grand_total' => (float) ($prepared['grand_total'] ?? ($summary['grand_total'] ?? 0)),
        ]);

        return $payload;
    }

    private function normalizeSupplierName(string $value): string
    {
        $value = Utf8Sanitizer::clean($value);
        $value = trim((string) (preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $value)) ?? $value));

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+GMBH\s*&\s*CO\.\s*KG.*$/i', '', $value) ?? $value;
        $value = preg_replace('/\s+GMBH.*$/i', '', $value) ?? $value;
        $value = preg_replace('/\s+D\.O\.O\..*$/i', '', $value) ?? $value;
        return trim((string) $value, " \t\n\r\0\x0B,.-");
    }

    private function orderAiScanColumnExists(string $column): bool
    {
        if ($this->orderAiScanColumns === null) {
            $this->orderAiScanColumns = [];

            foreach (['page_count', 'billed_tokens', 'document_profile', 'transfer_started_at'] as $candidate) {
                $this->orderAiScanColumns[$candidate] = Schema::connection('mysql')->hasColumn('order_ai_scans', $candidate);
            }
        }

        return (bool) ($this->orderAiScanColumns[$column] ?? false);
    }

    private function createScanRecord(
        string $relativePath,
        string $originalName,
        string $mimeType,
        int $fileSize,
        array $documentMetrics,
        string $documentProfile,
        string $requestPrompt,
        mixed $user = null,
        array $attributes = []
    ): OrderAiScan {
        $provider = trim(Utf8Sanitizer::clean((string) config('ai-order-scan.provider', 'mock'), 40)) ?: 'mock';
        $model = trim(Utf8Sanitizer::clean((string) config('ai-order-scan.model', 'gpt-5'), 120));
        $documentProfile = $this->normalizeDocumentProfileKey($documentProfile);
        $requestPrompt = trim(Utf8Sanitizer::clean($requestPrompt));

        $baseAttributes = [
            'user_id' => is_object($user) ? ($user->id ?? null) : null,
            'provider' => $provider,
            'model' => $model !== '' ? $model : null,
            'status' => 'uploaded',
            'processing_step' => 'Fajl je uspješno učitan.',
            'progress_current' => 10,
            'progress_total' => 100,
            'source_file_name' => Utf8Sanitizer::clean($originalName, 255),
            'source_file_path' => $relativePath,
            'source_mime_type' => Utf8Sanitizer::clean($mimeType, 150),
            'source_file_size' => $fileSize,
            'source_origin' => 'manual',
            'request_prompt' => $requestPrompt !== '' ? $requestPrompt : $this->buildRequestPrompt($documentProfile),
        ];

        if ($this->orderAiScanColumnExists('page_count')) {
            $baseAttributes['page_count'] = $documentMetrics['page_count'];
        }

        if ($this->orderAiScanColumnExists('billed_tokens')) {
            $baseAttributes['billed_tokens'] = $documentMetrics['billed_tokens'];
        }

        $payload = array_merge($baseAttributes, Utf8Sanitizer::cleanRecursive($attributes));

        if ($this->orderAiScanColumnExists('document_profile')) {
            $payload['document_profile'] = $documentProfile;
        }

        $payload['request_prompt'] = $baseAttributes['request_prompt'];

        return OrderAiScan::query()->create($payload);
    }

    private function resolveTransferReadyForDisplay(OrderAiScan $scan, array $payload, mixed $transferPreview): bool
    {
        $status = trim((string) ($scan->status ?? ''));

        if (in_array($status, ['ready_for_transfer', 'transferring', 'transferred'], true)) {
            return true;
        }

        if ($status === 'completed') {
            if (is_array($transferPreview)) {
                return true;
            }

            return $this->looksTransferReady($payload);
        }

        return false;
    }

    private function looksTransferReady(array $payload): bool
    {
        $customerName = trim((string) data_get($payload, 'order.customer_name', ''));
        $items = data_get($payload, 'items', []);

        return $customerName !== ''
            && is_array($items)
            && !empty($items);
    }

    private function resolveElapsedMeta(OrderAiScan $scan): array
    {
        $status = trim((string) ($scan->status ?? ''));
        $createdAt = $this->normalizeScanTimestamp($scan->created_at);
        $processedAt = $this->normalizeScanTimestamp($scan->processed_at);
        $completedAt = $this->normalizeScanTimestamp($scan->completed_at);
        $transferStartedAt = $this->normalizeScanTimestamp($scan->transfer_started_at);
        $transferredAt = $this->normalizeScanTimestamp($scan->transferred_at);
        $startedAt = $transferStartedAt ?? $createdAt;
        $finishedAt = $transferredAt
            ?? ($transferStartedAt instanceof Carbon ? $completedAt : null)
            ?? $processedAt
            ?? ($status === 'failed' ? $completedAt : null);
        $extractionEndAt = $processedAt
            ?? $transferStartedAt
            ?? (($status === 'uploaded' || $status === 'extracting') ? now() : $completedAt);
        $extractionSeconds = $createdAt instanceof Carbon && $extractionEndAt instanceof Carbon
            ? max(0, $createdAt->diffInSeconds($extractionEndAt))
            : 0;
        $transferEndAt = $transferredAt
            ?? (($transferStartedAt instanceof Carbon && in_array($status, ['transferring', 'failed'], true)) ? ($completedAt ?? now()) : null);
        $transferSeconds = $transferStartedAt instanceof Carbon && $transferEndAt instanceof Carbon
            ? max(0, $transferStartedAt->diffInSeconds($transferEndAt))
            : 0;
        $seconds = $extractionSeconds + $transferSeconds;

        return [
            'started_at' => $startedAt?->toIso8601String(),
            'finished_at' => $finishedAt?->toIso8601String(),
            'seconds' => $seconds,
            'display' => $this->formatElapsedSeconds($seconds),
        ];
    }

    private function normalizeScanTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function formatElapsedSeconds(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }

        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $remainingSeconds);
        }

        return sprintf('%ds', $remainingSeconds);
    }

    private function guessExtensionFromMimeType(?string $mimeType): string
    {
        if (stripos((string) $mimeType, 'pdf') !== false) {
            return 'pdf';
        }

        return 'bin';
    }

    private function guessMimeTypeFromName(string $originalName): string
    {
        return strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'pdf'
            ? 'application/pdf'
            : 'application/octet-stream';
    }

    private function detectDocumentProfile(string $originalName, ?string $mimeType, string $bytes): string
    {
        return $this->normalizeDocumentProfileKey(
            app(OrderDocumentProfileDetector::class)->detect($originalName, $mimeType, $bytes)
        );
    }

    private function buildRequestPrompt(string $documentProfile): string
    {
        $profileKey = $this->normalizeDocumentProfileKey($documentProfile);
        $basePrompt = trim((string) config('ai-order-scan.prompt_base', config('ai-order-scan.prompt', '')));
        $profileRules = trim((string) data_get(config('ai-order-scan.profiles', []), $profileKey . '.prompt_rules', ''));

        return trim(implode(PHP_EOL, array_filter([
            $basePrompt,
            $profileRules,
        ])));
    }

    private function normalizeDocumentProfileKey(string $documentProfile): string
    {
        $documentProfile = trim($documentProfile);
        $profiles = config('ai-order-scan.profiles', []);

        if ($documentProfile !== '' && array_key_exists($documentProfile, $profiles)) {
            return $documentProfile;
        }

        $defaultProfile = trim((string) config('ai-order-scan.default_profile', 'grob'));

        if ($defaultProfile !== '' && array_key_exists($defaultProfile, $profiles)) {
            return $defaultProfile;
        }

        return array_key_first($profiles) ?: 'grob';
    }

    private function resolveDocumentProfileKey(OrderAiScan $scan): string
    {
        return $this->normalizeDocumentProfileKey((string) ($scan->document_profile ?? ''));
    }

    private function postProcessProfilePayload(OrderAiScan $scan, array $payload): array
    {
        $profileKey = $this->resolveDocumentProfileKey($scan);

        if (!in_array($profileKey, ['trendy_de', 'grob'], true)) {
            return $payload;
        }

        $bytes = $this->readStoredFileBytes($scan);
        $preparedDocument = $this->prepareDocumentContext($scan, $profileKey, $bytes);

        $context = [
            'file_name' => (string) ($scan->source_file_name ?? ''),
            'mime_type' => (string) ($scan->source_mime_type ?? ''),
            'searchable_text' => (string) ($preparedDocument['searchable_text'] ?? ''),
            'raw_content' => $bytes,
            'processed_pages' => is_array($preparedDocument['processed_pages'] ?? null)
                ? $preparedDocument['processed_pages']
                : [],
            'source_page_count' => (int) ($preparedDocument['source_page_count'] ?? 0),
            'effective_page_count' => (int) ($preparedDocument['effective_page_count'] ?? 0),
            'page_processing_limit_reason' => (string) ($preparedDocument['page_processing_limit_reason'] ?? ''),
        ];

        if ($profileKey === 'trendy_de') {
            return $this->postProcessTrendyDePayload($payload, $context);
        }

        return $this->postProcessGrobPayload($payload, $context);
    }

    private function prepareDocumentContext(OrderAiScan $scan, ?string $profileKey = null, ?string $bytes = null): array
    {
        $resolvedBytes = is_string($bytes) ? $bytes : $this->readStoredFileBytes($scan);

        return app(OrderAiDocumentPreparationService::class)->prepareDocument(
            $profileKey !== null ? $profileKey : $this->resolveDocumentProfileKey($scan),
            (string) ($scan->source_file_name ?? ''),
            (string) ($scan->source_mime_type ?? ''),
            $resolvedBytes
        );
    }

    private function readLocalFileBytes(string $path): string
    {
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $bytes = @file_get_contents($path);

        return is_string($bytes) ? $bytes : '';
    }

    private function readStoredFileBytes(OrderAiScan $scan): string
    {
        $disk = (string) config('ai-order-scan.storage_disk', 'local');
        $path = trim((string) ($scan->source_file_path ?? ''));

        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            return '';
        }

        return (string) Storage::disk($disk)->get($path);
    }

    private function postProcessTrendyDePayload(array $payload, array $context = []): array
    {
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $searchableText = (string) ($context['searchable_text'] ?? '');
        $fileName = (string) ($context['file_name'] ?? '');
        $leftSupplierBlock = $this->extractProfileSectionText(
            $searchableText,
            '/Lieferant\s*:/i',
            [
                '/Anlieferadresse\s*:/i',
                '/Datum\b/i',
                '/Bestellung\b/i',
            ]
        );
        $documentNumber = trim((string) ($order['external_document_number'] ?? ''));

        if ($documentNumber === '') {
            $documentNumber = $this->extractTrendyDeDocumentNumber($searchableText, $fileName);
        }

        $deliveryDeadline = trim((string) ($order['delivery_deadline'] ?? ''));

        if ($deliveryDeadline === '') {
            $deliveryDeadline = $this->extractProfileFieldValue($searchableText, 'Liefertermin');
        }

        $contactName = trim((string) ($order['contact_name'] ?? ''));

        if ($contactName === '') {
            $contactName = $this->extractProfileFieldValue($searchableText, 'Person responsible');
        }

        $receiverName = trim((string) ($order['receiver_name'] ?? ''));

        if ($receiverName === '') {
            $receiverName = $this->extractProfileFieldValue($searchableText, 'Anlieferadresse');
        }

        $noteParts = [];

        foreach ([
            trim((string) ($order['note'] ?? '')),
            $leftSupplierBlock,
        ] as $notePart) {
            if ($notePart === '') {
                continue;
            }

            $noteParts[$notePart] = $notePart;
        }

        $order['customer_name'] = self::TRENDY_DE_PARTY_NAME;
        $order['supplier_name'] = self::TRENDY_DE_PARTY_NAME;
        $order['external_document_number'] = $documentNumber;
        $order['delivery_deadline'] = $deliveryDeadline;
        $order['contact_name'] = $contactName;
        $order['receiver_name'] = $receiverName !== '' ? $receiverName : self::TRENDY_DE_PARTY_NAME;
        $order['note'] = implode(' | ', array_values($noteParts));

        $payload['order'] = $order;
        $payload['items'] = $this->postProcessTrendyDeItems(
            is_array($payload['items'] ?? null) ? $payload['items'] : []
        );

        return $payload;
    }

    private function postProcessTrendyDeItems(array $items): array
    {
        return array_values(array_map(function ($item) {
            if (!is_array($item)) {
                return $item;
            }

            $contentLines = [];
            $deliveryDeadline = trim((string) ($item['delivery_deadline'] ?? ''));
            $pendingDeliveryLabel = false;

            foreach ($this->splitVisibleTextLines(
                trim((string) ($item['product_name'] ?? '')) . "\n" . trim((string) ($item['note'] ?? ''))
            ) as $line) {
                $lineDeliveryDeadline = $this->extractVisibleDateFromLine($line);

                if (preg_match('/\bliefertermin\b/i', $line) === 1) {
                    if ($deliveryDeadline === '' && $lineDeliveryDeadline !== '') {
                        $deliveryDeadline = $lineDeliveryDeadline;
                    }

                    $pendingDeliveryLabel = $lineDeliveryDeadline === '';
                    continue;
                }

                if ($pendingDeliveryLabel && $deliveryDeadline === '') {
                    $deliveryDeadline = $lineDeliveryDeadline;

                    if ($deliveryDeadline !== '') {
                        $pendingDeliveryLabel = false;
                        continue;
                    }
                }

                $pendingDeliveryLabel = false;
                $contentLines[] = $line;
            }

            $contentLines = array_values(array_filter($contentLines));
            $productName = trim((string) ($contentLines[0] ?? $item['product_name'] ?? ''));
            $noteLines = array_slice($contentLines, 1);

            $item['product_name'] = $productName;
            $item['note'] = implode(' | ', array_values(array_unique(array_filter($noteLines))));
            $item['delivery_deadline'] = $deliveryDeadline;
            $item['unit'] = $this->normalizeScannedUnit((string) ($item['unit'] ?? ''));

            return $item;
        }, $items));
    }

    private function postProcessGrobPayload(array $payload, array $context = []): array
    {
        $searchableText = trim((string) ($context['searchable_text'] ?? ''));
        $processedPages = is_array($context['processed_pages'] ?? null) ? $context['processed_pages'] : [];
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];

        if ($searchableText === '') {
            $payload['order'] = $order;
            return $payload;
        }

        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        $parsedItems = $processedPages !== []
            ? $this->parseGrobItemsFromPages($processedPages)
            : $this->parseGrobItemsFromText($searchableText);
        $documentSubtotal = $this->extractLastGermanAmountAfterLabels($searchableText, ['Nettowert', 'Gesamtbetrag']);
        $effectivePageCount = max(0, (int) ($context['effective_page_count'] ?? 0));
        $sourcePageCount = max(0, (int) ($context['source_page_count'] ?? 0), (int) ($order['page_count'] ?? 0));
        $pageLimitReason = trim((string) ($context['page_processing_limit_reason'] ?? ''));

        if ($effectivePageCount <= 0) {
            $effectivePageCount = $this->estimateGrobEffectivePageCount(
                (string) ($context['raw_content'] ?? ''),
                $searchableText,
                $sourcePageCount
            );
        }

        if (!empty($parsedItems) && is_array($payload['items'] ?? null)) {
            $payload['items'] = $this->mergeGrobParsedItemsIntoPayload($payload['items'], $parsedItems);
        }

        if ($documentSubtotal > 0) {
            $summary['subtotal'] = $documentSubtotal;

            if ((float) ($summary['grand_total'] ?? 0) <= 0) {
                $summary['grand_total'] = $documentSubtotal;
            }
        }

        if ($effectivePageCount > 0) {
            $order['effective_page_count'] = $effectivePageCount;
            $order['page_count'] = $effectivePageCount;
        }

        if ($pageLimitReason !== '') {
            $order['page_processing_limit_reason'] = 'GROB obrada stavki je ograničena do ACHTUNG reda.';
        }

        $payload['order'] = $order;
        if ($pageLimitReason !== '') {
            $payload['order']['page_processing_limit_reason'] = $pageLimitReason;
        } elseif ($effectivePageCount > 0 && $sourcePageCount > 0 && $effectivePageCount < $sourcePageCount) {
            $payload['order']['page_processing_limit_reason'] = OrderAiDocumentPreparationService::GROB_PAGE_LIMIT_REASON;
        }

        $payload['summary'] = $summary;

        return $payload;
    }

    private function mergeGrobParsedItemsIntoPayload(array $items, array $parsedItems): array
    {
        $remaining = array_values($parsedItems);

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $lineNumber = (int) ($item['line_number'] ?? 0);
            $productCode = trim((string) ($item['product_code'] ?? ''));
            $matchIndex = null;

            foreach ($remaining as $candidateIndex => $parsedItem) {
                $candidateLineNumber = (int) ($parsedItem['line_number'] ?? 0);
                $candidateCode = trim((string) ($parsedItem['product_code'] ?? ''));

                if ($lineNumber > 0 && $candidateLineNumber === $lineNumber) {
                    $matchIndex = $candidateIndex;
                    break;
                }

                if ($productCode !== '' && $candidateCode !== '' && strcasecmp($candidateCode, $productCode) === 0) {
                    $matchIndex = $candidateIndex;
                    break;
                }
            }

            if ($matchIndex === null) {
                continue;
            }

            $parsedItem = $remaining[$matchIndex];
            unset($remaining[$matchIndex]);
            $mergedItem = $item;

            $mergedItem['unit_price'] = (float) ($parsedItem['unit_price'] ?? 0);
            $mergedItem['line_total'] = (float) ($parsedItem['line_total'] ?? 0);

            if ((float) ($parsedItem['quantity'] ?? 0) > 0) {
                $mergedItem['quantity'] = (float) $parsedItem['quantity'];
            }

            if (trim((string) ($parsedItem['unit'] ?? '')) !== '') {
                $mergedItem['unit'] = $this->normalizeScannedUnit((string) $parsedItem['unit']);
            }

            if (trim((string) ($parsedItem['product_name'] ?? '')) !== '') {
                $mergedItem['product_name'] = trim((string) $parsedItem['product_name']);
            }

            foreach (['drawing_reference', 'material_hint', 'delivery_deadline'] as $field) {
                if (trim((string) ($parsedItem[$field] ?? '')) !== '') {
                    $mergedItem[$field] = trim((string) $parsedItem[$field]);
                }
            }

            if (trim((string) ($parsedItem['note'] ?? '')) !== '') {
                $mergedItem['note'] = $this->appendItemNote(
                    (string) ($mergedItem['note'] ?? ''),
                    (string) $parsedItem['note']
                );
            }

            if (!empty($parsedItem['warnings']) && is_array($parsedItem['warnings'])) {
                $mergedItem['note'] = $this->appendItemNote(
                    (string) ($mergedItem['note'] ?? ''),
                    implode(' | ', array_values(array_unique(array_filter($parsedItem['warnings']))))
                );
            }

            $items[$index] = $mergedItem;
        }

        return $items;
    }

    private function parseGrobItemsFromPages(array $pages): array
    {
        $items = [];
        $currentItem = null;

        foreach ($pages as $page) {
            foreach ($this->prepareGrobPageLinesForParsing($page, is_array($currentItem)) as $line) {
                $newItem = $this->createGrobParsedItemFromLine($line);

                if ($newItem !== null) {
                    if (is_array($currentItem)) {
                        $items[] = $this->finalizeGrobParsedItem($currentItem);
                    }

                    $currentItem = $newItem;
                    continue;
                }

                if (!is_array($currentItem) || $this->isGrobNonItemNoiseLine($line)) {
                    continue;
                }

                if ($currentItem['product_code'] === '' && preg_match('/^[A-Z0-9][A-Z0-9.\-\/]{2,}$/iu', $line) === 1 && !$this->isGrobKeywordLine($line)) {
                    $currentItem['product_code'] = trim($line);
                    continue;
                }

                if (preg_match('/^zeichnung\b/iu', $line) === 1) {
                    $currentItem['product_name_capture_complete'] = true;
                    $currentItem['drawing_reference'] = trim(implode(' | ', array_filter([
                        $currentItem['drawing_reference'],
                        $line,
                    ])));
                    continue;
                }

                if (preg_match('/^werkstoff\s*:\s*(.+)$/iu', $line, $matches) === 1) {
                    $currentItem['product_name_capture_complete'] = true;
                    $currentItem['material_hint'] = trim((string) ($matches[1] ?? ''));
                    continue;
                }

                if (preg_match('/^(?:kontierung\s*:|ref\.\s*des\.)/iu', $line) === 1) {
                    $currentItem['product_name_capture_complete'] = true;
                    $this->appendGrobItemNoteLine($currentItem, $line);
                    continue;
                }

                if (preg_match('/^preiseinheit\b.*?\b([A-Z]{1,5})\b/iu', $line, $matches) === 1) {
                    $currentItem['product_name_capture_complete'] = true;
                    $currentItem['unit'] = trim((string) ($matches[1] ?? ''));
                    continue;
                }

                if (preg_match('/^bruttopreis\b/iu', $line) === 1) {
                    $currentItem['product_name_capture_complete'] = true;
                    continue;
                }

                if (preg_match('/^nettopreis\b/iu', $line) === 1) {
                    $currentItem['product_name_capture_complete'] = true;
                    $this->populateGrobItemFromNettoLine($currentItem, $line);
                    continue;
                }

                if (preg_match('/^wert\b.*?(-?\d[\d.,]*)/iu', $line, $matches) === 1) {
                    $currentItem['product_name_capture_complete'] = true;
                    $currentItem['line_total'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));
                    $currentItem['line_total_found'] = $currentItem['line_total'] > 0;
                    continue;
                }

                if (preg_match('/^lieferdatum\b/iu', $line) === 1) {
                    $currentItem['product_name_capture_complete'] = true;
                    $currentItem['delivery_deadline'] = $this->extractVisibleDateFromLine($line);

                    if ((float) ($currentItem['quantity'] ?? 0) <= 0 && preg_match('/(-?\d{1,3}(?:\.\d{3})*,\d+|-?\d+(?:,\d+)?)\s+([A-Z]{1,5})\b/u', $line, $matches) === 1) {
                        $currentItem['quantity'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));

                        if (trim((string) ($currentItem['unit'] ?? '')) === '') {
                            $currentItem['unit'] = trim((string) ($matches[2] ?? ''));
                        }
                    }

                    continue;
                }

                if ($this->isGrobKeywordLine($line)) {
                    $currentItem['product_name_capture_complete'] = true;
                    continue;
                }

                if (!($currentItem['product_name_capture_complete'] ?? false)) {
                    $currentItem['product_name_lines'][] = $line;
                    continue;
                }

                $this->appendGrobItemNoteLine($currentItem, $line);
            }
        }

        if (is_array($currentItem)) {
            $items[] = $this->finalizeGrobParsedItem($currentItem);
        }

        return array_values(array_filter($items, function ($item) {
            return is_array($item)
                && (((int) ($item['line_number'] ?? 0)) > 0 || trim((string) ($item['product_code'] ?? '')) !== '');
        }));
    }

    private function parseGrobItemsFromText(string $searchableText): array
    {
        $items = [];
        $currentItem = null;

        foreach ($this->splitVisibleTextLines($searchableText) as $line) {
            $newItem = $this->createGrobParsedItemFromLine($line);

            if ($newItem !== null) {
                if (is_array($currentItem)) {
                    $items[] = $this->finalizeGrobParsedItem($currentItem);
                }

                $currentItem = $newItem;
                continue;
            }

            if (!is_array($currentItem)) {
                continue;
            }

            if ($currentItem['product_code'] === '' && preg_match('/^[A-Z0-9][A-Z0-9.\-\/]{2,}$/iu', $line) === 1 && !$this->isGrobKeywordLine($line)) {
                $currentItem['product_code'] = trim($line);
                continue;
            }

            if (preg_match('/^zeichnung\b/iu', $line) === 1) {
                $currentItem['product_name_capture_complete'] = true;
                $currentItem['drawing_reference'] = trim(implode(' | ', array_filter([
                    $currentItem['drawing_reference'],
                    $line,
                ])));
                continue;
            }

            if (preg_match('/^werkstoff\s*:\s*(.+)$/iu', $line, $matches) === 1) {
                $currentItem['product_name_capture_complete'] = true;
                $currentItem['material_hint'] = trim((string) ($matches[1] ?? ''));
                continue;
            }

            if (preg_match('/^(?:kontierung\s*:|ref\.\s*des\.)/iu', $line) === 1) {
                $currentItem['product_name_capture_complete'] = true;
                $this->appendGrobItemNoteLine($currentItem, $line);
                continue;
            }

            if (preg_match('/^preiseinheit\b.*?\b([A-Z]{1,5})\b/iu', $line, $matches) === 1) {
                $currentItem['product_name_capture_complete'] = true;
                $currentItem['unit'] = trim((string) ($matches[1] ?? ''));
                continue;
            }

            if (preg_match('/^bruttopreis\b/iu', $line) === 1) {
                $currentItem['product_name_capture_complete'] = true;
                continue;
            }

            if (preg_match('/^nettopreis\b/iu', $line) === 1) {
                $currentItem['product_name_capture_complete'] = true;
                $this->populateGrobItemFromNettoLine($currentItem, $line);
                continue;
            }

            if (preg_match('/^wert\b.*?(-?\d[\d.,]*)/iu', $line, $matches) === 1) {
                $currentItem['product_name_capture_complete'] = true;
                $currentItem['line_total'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));
                $currentItem['line_total_found'] = $currentItem['line_total'] > 0;
                continue;
            }

            if (preg_match('/^lieferdatum\b/iu', $line) === 1) {
                $currentItem['product_name_capture_complete'] = true;
                $currentItem['delivery_deadline'] = $this->extractVisibleDateFromLine($line);

                if ((float) ($currentItem['quantity'] ?? 0) <= 0 && preg_match('/(-?\d{1,3}(?:\.\d{3})*,\d+|-?\d+(?:,\d+)?)\s+([A-Z]{1,5})\b/u', $line, $matches) === 1) {
                    $currentItem['quantity'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));

                    if (trim((string) ($currentItem['unit'] ?? '')) === '') {
                        $currentItem['unit'] = trim((string) ($matches[2] ?? ''));
                    }
                }

                continue;
            }

            if ($this->isGrobKeywordLine($line)) {
                $currentItem['product_name_capture_complete'] = true;
                continue;
            }

            if (!($currentItem['product_name_capture_complete'] ?? false)) {
                $currentItem['product_name_lines'][] = $line;
                continue;
            }

            $this->appendGrobItemNoteLine($currentItem, $line);
        }

        if (is_array($currentItem)) {
            $items[] = $this->finalizeGrobParsedItem($currentItem);
        }

        return array_values(array_filter($items, function ($item) {
            return is_array($item)
                && (((int) ($item['line_number'] ?? 0)) > 0 || trim((string) ($item['product_code'] ?? '')) !== '');
        }));
    }

    private function finalizeGrobParsedItem(array $item): array
    {
        $item['product_name'] = trim(implode(' ', array_values(array_filter($item['product_name_lines'] ?? []))));
        $item['note'] = implode(' | ', array_values(array_unique(array_filter($item['note_lines'] ?? []))));
        unset($item['product_name_lines']);
        unset($item['note_lines']);
        unset($item['product_name_capture_complete']);

        if (!($item['netto_price_found'] ?? false)) {
            $item['unit_price'] = 0.0;
            $item['line_total'] = ($item['line_total_found'] ?? false) ? (float) ($item['line_total'] ?? 0) : 0.0;
            $item['warnings'][] = 'NettoPreis nije pronadjen u GROB dokumentu.';
        } elseif (!($item['line_total_found'] ?? false)) {
            $item['line_total'] = 0.0;
            $item['warnings'][] = 'Wert za GROB stavku nije pronadjen uz NettoPreis.';
        }

        if (trim((string) ($item['unit'] ?? '')) !== '') {
            $item['unit'] = $this->normalizeScannedUnit((string) $item['unit']);
        }

        return $item;
    }

    private function createGrobParsedItemFromLine(string $line): ?array
    {
        if (preg_match('/^(\d{1,4})\s+([A-Z0-9.\-\/]+)\s+(-?\d{1,3}(?:\.\d{3})*,\d+|-?\d+(?:,\d+)?)\s+([A-Z]{1,5})\s*(.*)$/iu', $line, $matches) === 1) {
            return $this->initializeGrobParsedItem(
                (int) ($matches[1] ?? 0),
                trim((string) ($matches[2] ?? '')),
                $this->parseGermanNumber((string) ($matches[3] ?? '')),
                trim((string) ($matches[4] ?? '')),
                trim((string) ($matches[5] ?? ''))
            );
        }

        if (preg_match('/^pos(?:ition)?\.?\s*([0-9]+)\b(?:\s+([A-Z0-9.\-\/]+))?/iu', $line, $matches) === 1) {
            return $this->initializeGrobParsedItem(
                (int) ($matches[1] ?? 0),
                trim((string) ($matches[2] ?? '')),
                0.0,
                '',
                ''
            );
        }

        return null;
    }

    private function initializeGrobParsedItem(int $lineNumber, string $productCode, float $quantity, string $unit, string $productName): array
    {
        return [
            'line_number' => $lineNumber,
            'product_code' => $productCode,
            'quantity' => $quantity,
            'unit' => $unit,
            'product_name_lines' => $productName !== '' ? [$productName] : [],
            'note_lines' => [],
            'product_name_capture_complete' => false,
            'drawing_reference' => '',
            'material_hint' => '',
            'delivery_deadline' => '',
            'unit_price' => 0.0,
            'line_total' => 0.0,
            'line_total_found' => false,
            'netto_price_found' => false,
            'warnings' => [],
        ];
    }

    private function populateGrobItemFromNettoLine(array &$item, string $line): void
    {
        $amounts = $this->extractGermanAmounts($line);

        if ($amounts !== []) {
            $item['unit_price'] = $amounts[0];
            $item['netto_price_found'] = $item['unit_price'] > 0;

            if (count($amounts) > 1) {
                $item['line_total'] = (float) end($amounts);
                $item['line_total_found'] = $item['line_total'] > 0;
            }
        }

        if (trim((string) ($item['unit'] ?? '')) === '' && preg_match('/^nettopreis\b.*?eur\s+([A-Z]{1,5})\b/iu', $line, $matches) === 1) {
            $item['unit'] = trim((string) ($matches[1] ?? ''));
        }
    }

    private function prepareGrobPageLinesForParsing(array $page, bool $hasOpenItem): array
    {
        $lines = array_values(array_filter(array_map(function ($line) {
            return trim((string) $line);
        }, is_array($page['lines'] ?? null) ? $page['lines'] : $this->splitVisibleTextLines((string) ($page['text'] ?? '')))));

        if ($lines === []) {
            return [];
        }

        $startIndex = 0;

        foreach ($lines as $index => $line) {
            if ($hasOpenItem) {
                if (!$this->isGrobNonItemNoiseLine($line)) {
                    $startIndex = $index;
                    break;
                }

                continue;
            }

            if ($this->createGrobParsedItemFromLine($line) !== null) {
                $startIndex = $index;
                break;
            }
        }

        $lines = array_slice($lines, $startIndex);

        while (!empty($lines) && $this->isGrobNonItemNoiseLine((string) end($lines))) {
            array_pop($lines);
        }

        return array_values($lines);
    }

    private function isGrobNonItemNoiseLine(string $line): bool
    {
        if (preg_match('/^w.+hrung\b/iu', $line) === 1) {
            return true;
        }

        if (preg_match('/^\d{4,6}\s+[A-ZÄÖÜ][A-ZÄÖÜ\s.\-]+$/u', $line) === 1) {
            return true;
        }

        return preg_match(
            '/^(?:grob-werke(?:\s+gmbh\s*&\s*co\.\s*kg)?|gmbh\s*&\s*co\.\s*kg|bestellung|bestell-nr\.:|trendy d\.o\.o\.|mehmeda spahe\b|bosnien-herz\.?|seite\s+\d+\s+von\s+\d+|pos\b|beschreibung\b|menge\b|mengeneinheit\b|w[äa]hrung\b|preis\b|_{10,}|\*{20,}|liefer\.-nr\.|kunden-nr\.|zahlungsbed\.|sachb\.\/tel\.|ekg:|bitte weisen sie|lieferant\b|grob-identnr\.:|material:|banf:)/iu',
            $line
        ) === 1;
    }

    private function appendItemNote(string $existingNote, string $extraNote): string
    {
        $notes = [];

        foreach ([trim($existingNote), trim($extraNote)] as $note) {
            if ($note === '') {
                continue;
            }

            $notes[$note] = $note;
        }

        return implode(' | ', array_values($notes));
    }

    private function extractGermanAmounts(string $value): array
    {
        if (preg_match_all('/-?\d{1,3}(?:\.\d{3})*,\d{2}|-?\d+(?:,\d{2})/u', $value, $matches) < 1) {
            return [];
        }

        return array_values(array_filter(array_map(function ($amount) {
            return $this->parseGermanNumber((string) $amount);
        }, $matches[0] ?? []), function ($amount) {
            return $amount > 0;
        }));
    }

    private function isGrobKeywordLine(string $line): bool
    {
        return preg_match(
            '/^(?:bruttopreis|nettopreis|wert|preiseinheit|preis|pro|lieferdatum|r.+\/termin abs\.?|vertrag\b|beschichtung\b|gesamtbetrag|nettowert|seite\b|summe\b|mwst\b|achtung\b|attention\b)/iu',
            $line
        ) === 1;
    }

    private function appendGrobItemNoteLine(array &$item, string $line): void
    {
        $trimmedLine = trim($line);

        if ($trimmedLine === '' || $this->isGrobNonItemNoiseLine($trimmedLine)) {
            return;
        }

        $item['note_lines'][] = $trimmedLine;
    }

    private function truncateGrobSearchableText(string $searchableText): string
    {
        $normalized = trim($searchableText);

        if ($normalized === '') {
            return '';
        }

        $markerOffset = mb_stripos($normalized, self::GROB_ATTENTION_MARKER);

        if ($markerOffset === false) {
            return $normalized;
        }

        return trim(mb_substr($normalized, 0, $markerOffset));
    }

    private function extractLastGermanAmountAfterLabels(string $value, array $labels): float
    {
        $amount = 0.0;

        foreach ($labels as $label) {
            $pattern = '/' . preg_quote($label, '/') . '[^0-9-]{0,40}(-?\d{1,3}(?:\.\d{3})*,\d{2}|-?\d+(?:,\d{2})?)/iu';

            if (preg_match_all($pattern, $value, $matches) < 1 || empty($matches[1])) {
                continue;
            }

            $lastMatch = (string) end($matches[1]);
            $parsed = $this->parseGermanNumber($lastMatch);

            if ($parsed > 0) {
                $amount = $parsed;
            }
        }

        return $amount;
    }

    private function extractVisibleDateFromLine(string $value): string
    {
        if (preg_match('/(\d{1,2}\.\s*\d{1,2}\.\s*\d{2,4}\.?)/u', $value, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    private function parseGermanNumber(string $value): float
    {
        $value = trim($value);

        if ($value === '') {
            return 0.0;
        }

        $normalized = preg_replace('/[^\d,.\-]/u', '', $value) ?? $value;
        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (substr_count($normalized, '.') > 1) {
            $normalized = str_replace('.', '', $normalized);
        }

        return round((float) $normalized, 4);
    }

    private function normalizeScannedUnit(string $unit): string
    {
        $unit = strtoupper(trim($unit));

        if ($unit === '') {
            return '';
        }

        return match ($unit) {
            'ST', 'STK', 'STUECK', 'STUCK', 'STU', 'PCS', 'PIECE' => strtoupper((string) config('ai-order-scan.default_unit', 'KO')),
            default => $unit,
        };
    }

    private function resolveProcessingPageMeta(OrderAiScan $scan, array $payload): array
    {
        $effectivePageCount = max(0, (int) data_get($payload, 'order.effective_page_count', 0));
        $totalPageCount = max(0, (int) ($scan->page_count ?? data_get($payload, 'order.page_count', 0)));
        $reason = trim((string) data_get($payload, 'order.page_processing_limit_reason', ''));

        if ($effectivePageCount <= 0 && $this->resolveDocumentProfileKey($scan) === 'grob' && $totalPageCount > 0) {
            $cacheKey = implode('|', [
                (int) ($scan->id ?? 0),
                trim((string) ($scan->source_file_path ?? '')),
                $totalPageCount,
            ]);

            if (!array_key_exists($cacheKey, $this->effectivePageMetaCache)) {
                $preparedDocument = $this->prepareDocumentContext($scan, 'grob');

                $this->effectivePageMetaCache[$cacheKey] = [
                    'effective_page_count' => max(
                        0,
                        (int) ($preparedDocument['effective_page_count'] ?? 0)
                    ),
                    'page_processing_limit_reason' => trim((string) ($preparedDocument['page_processing_limit_reason'] ?? '')),
                ];
            }

            $cachedMeta = is_array($this->effectivePageMetaCache[$cacheKey] ?? null)
                ? $this->effectivePageMetaCache[$cacheKey]
                : ['effective_page_count' => (int) ($this->effectivePageMetaCache[$cacheKey] ?? 0)];

            $effectivePageCount = max(0, (int) ($cachedMeta['effective_page_count'] ?? 0));

            if ($reason === '') {
                $reason = trim((string) ($cachedMeta['page_processing_limit_reason'] ?? ''));
            }
        }

        if ($effectivePageCount <= 0) {
            $effectivePageCount = $totalPageCount;
        }

        if (
            $reason === ''
            && $effectivePageCount > 0
            && $totalPageCount > 0
            && $effectivePageCount < $totalPageCount
        ) {
            $reason = OrderAiDocumentPreparationService::GROB_PAGE_LIMIT_REASON;
        }

        return [
            'effective_page_count' => $effectivePageCount,
            'page_processing_limit_reason' => $reason,
        ];
    }

    private function estimateGrobEffectivePageCount(string $rawContent, string $searchableText, int $totalPageCount): int
    {
        if ($totalPageCount <= 1) {
            return max(0, $totalPageCount);
        }

        $markerOffsetText = mb_stripos($searchableText, self::GROB_ATTENTION_MARKER);
        $markerOffsetBytes = stripos($rawContent, self::GROB_ATTENTION_MARKER);

        if ($markerOffsetText === false && $markerOffsetBytes === false) {
            return $totalPageCount;
        }

        $pageIndicator = $this->extractLastVisiblePageIndicatorBeforeMarker($searchableText, $markerOffsetText, $totalPageCount);

        if ($pageIndicator > 0) {
            return $pageIndicator;
        }

        if ($markerOffsetBytes !== false) {
            $pageObjectCount = preg_match_all('/\/Type\s*\/Page\b/i', substr($rawContent, 0, $markerOffsetBytes), $matches);

            if ($pageObjectCount >= 1 && $pageObjectCount <= $totalPageCount) {
                return (int) $pageObjectCount;
            }
        }

        if ($markerOffsetText !== false) {
            $fullLength = max(1, mb_strlen($searchableText));
            $estimatedPage = (int) ceil((($markerOffsetText + 1) / $fullLength) * $totalPageCount);

            return max(1, min($totalPageCount, $estimatedPage));
        }

        return $totalPageCount;
    }

    private function extractLastVisiblePageIndicatorBeforeMarker(string $searchableText, int|false $markerOffset, int $totalPageCount): int
    {
        if ($markerOffset === false) {
            return 0;
        }

        $contextLength = 16000;
        $startOffset = max(0, $markerOffset - $contextLength);
        $beforeMarker = mb_substr($searchableText, $startOffset, $markerOffset - $startOffset);
        $patterns = [
            '/(?:Seite|Page|Stranica)\s*[:#]?\s*(\d{1,3})\s*(?:\/|von|of)\s*(\d{1,3})/iu',
            '/(?:Seite|Page|Stranica)\s*[:#]?\s*(\d{1,3})\b/iu',
            '/\b(\d{1,3})\s*\/\s*' . preg_quote((string) $totalPageCount, '/') . '\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $beforeMarker, $matches, PREG_SET_ORDER) < 1) {
                continue;
            }

            for ($index = count($matches) - 1; $index >= 0; $index--) {
                $pageCandidate = (int) ($matches[$index][1] ?? 0);

                if ($pageCandidate >= 1 && $pageCandidate <= $totalPageCount) {
                    return $pageCandidate;
                }
            }
        }

        return 0;
    }

    private function extractTrendyDeDocumentNumber(string $searchableText, string $fileName): string
    {
        foreach ([$searchableText, $fileName] as $source) {
            if (preg_match('/Bestellung[\s_:-]*([0-9]{2}-[0-9]{3}-[0-9]+)/i', $source, $matches) === 1) {
                return trim((string) ($matches[1] ?? ''));
            }
        }

        return '';
    }

    private function extractProfileFieldValue(string $searchableText, string $fieldLabel): string
    {
        $pattern = '/' . preg_quote($fieldLabel, '/') . '\s*:?\s*([^\r\n]+)/i';

        if (preg_match($pattern, $searchableText, $matches) === 1) {
            return $this->normalizeProfileWhitespace((string) ($matches[1] ?? ''));
        }

        return '';
    }

    private function extractProfileSectionText(string $searchableText, string $startPattern, array $endPatterns): string
    {
        if (preg_match($startPattern, $searchableText, $startMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }

        $startOffset = (int) ($startMatch[0][1] ?? 0);
        $section = substr($searchableText, $startOffset);
        $endOffset = strlen($section);

        foreach ($endPatterns as $pattern) {
            if (preg_match($pattern, $section, $endMatch, PREG_OFFSET_CAPTURE) === 1) {
                $candidateOffset = (int) ($endMatch[0][1] ?? $endOffset);

                if ($candidateOffset > 0 && $candidateOffset < $endOffset) {
                    $endOffset = $candidateOffset;
                }
            }
        }

        $section = substr($section, 0, $endOffset);
        $section = preg_replace($startPattern, '', $section, 1) ?? $section;

        return $this->normalizeProfileWhitespace($section);
    }

    private function normalizeProfileWhitespace(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = preg_split('/\n+/u', $value) ?: [];
        $lines = array_values(array_filter(array_map(function ($line) {
            $line = preg_replace('/\s+/', ' ', trim((string) $line)) ?? trim((string) $line);

            return trim((string) $line);
        }, $lines)));

        return implode(' | ', $lines);
    }
}
