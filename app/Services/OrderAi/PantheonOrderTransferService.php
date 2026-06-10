<?php

namespace App\Services\OrderAi;

use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Utf8Sanitizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PantheonOrderTransferService
{
    private array $catalogMaterialByCodeCache = [];
    private array $catalogMaterialByNameCache = [];
    private array $createdCatalogItems = [];

    public function previewFromNormalizedPayload(array $normalizedPayload, mixed $user = null): array
    {
        $this->createdCatalogItems = [];
        $prepared = $this->prepareTransferData($normalizedPayload, false, false, $user);

        return $this->buildTransferPayload($prepared, $user);
    }

    public function createFromNormalizedPayload(array $normalizedPayload, mixed $user = null): array
    {
        $this->createdCatalogItems = [];

        try {
            return DB::connection('sqlsrv')->transaction(function () use ($normalizedPayload, $user) {
            $prepared = $this->prepareTransferData($normalizedPayload, true, true, $user);
            $numberContext = $this->generateNextOrderNumber($prepared['document_type']);
            $headerTemplate = $this->resolveHeaderTemplate($prepared['document_type']);
            $itemTemplate = $this->resolveItemTemplate($headerTemplate['acKey'] ?? null);

            if (empty($headerTemplate)) {
                throw new RuntimeException('Nije moguće pronaći template narudžbe za Pantheon.');
            }

            if (empty($itemTemplate)) {
                throw new RuntimeException('Nije moguće pronaći template stavke narudžbe za Pantheon.');
            }

            $headerQid = $this->nextIntegerValue(Order::newSourceQuery(), Order::sourceColumns(), Order::sourceNonInsertableColumns(), 'anQId');
            $itemQid = $this->nextIntegerValue(OrderItem::newSourceQuery(), OrderItem::sourceColumns(), OrderItem::sourceNonInsertableColumns(), 'anQId');

            $headerPayload = $this->buildHeaderPayload(
                $headerTemplate,
                $prepared,
                $numberContext,
                $headerQid,
                $user
            );

            Order::newSourceQuery()->insert($headerPayload);

            $itemPayloads = [];
            foreach ($prepared['items'] as $index => $item) {
                $itemPayloads[] = $this->buildItemPayload(
                    $itemTemplate,
                    $item,
                    $headerPayload,
                    $headerQid,
                    $itemQid !== null ? ($itemQid + $index) : null,
                    $index + 1,
                    $user
                );
            }

            if (!empty($itemPayloads)) {
                OrderItem::newSourceQuery()->insert($itemPayloads);
            }

                Log::info('Order AI Pantheon transfer prepared.', [
                    'pantheon_order_key' => $headerPayload['acKey'] ?? null,
                    'pantheon_order_view' => $headerPayload['acKeyView'] ?? ($numberContext['display_key'] ?? null),
                    'header_payload' => $headerPayload,
                    'item_payloads' => $itemPayloads,
                ]);

                $result = [
                'payload' => $prepared,
                'pantheon_order_key' => (string) ($headerPayload['acKey'] ?? ''),
                'pantheon_order_view' => (string) ($headerPayload['acKeyView'] ?? ($numberContext['display_key'] ?? '')),
                'pantheon_order_qid' => $headerPayload['anQId'] ?? null,
                'item_count' => count($itemPayloads),
                'header_payload' => $headerPayload,
                'item_payloads' => $itemPayloads,
                'created_catalog_items' => $this->createdCatalogItems,
                'created_catalog_item_count' => count($this->createdCatalogItems),
                ];

                Log::info('Order AI Pantheon transfer completed.', [
                    'pantheon_order_key' => $result['pantheon_order_key'] ?? null,
                    'pantheon_order_qid' => $result['pantheon_order_qid'] ?? null,
                    'item_count' => $result['item_count'] ?? 0,
                    'header_payload' => $result['header_payload'] ?? [],
                    'item_payloads' => $result['item_payloads'] ?? [],
                ]);

                return $result;
            }, 3);
        } catch (\Throwable $exception) {
            Log::error('Order AI Pantheon transfer failed.', [
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function isTransferReady(array $normalizedPayload): bool
    {
        $this->createdCatalogItems = [];
        $prepared = $this->prepareTransferData($normalizedPayload, false);

        if (trim((string) ($prepared['customer_name'] ?? '')) === '') {
            return false;
        }

        return !empty($prepared['items']);
    }

    private function buildTransferPayload(array $prepared, mixed $user = null): array
    {
        $numberContext = $this->generateNextOrderNumber($prepared['document_type']);
        $headerTemplate = $this->resolveHeaderTemplate($prepared['document_type']);
        $itemTemplate = $this->resolveItemTemplate($headerTemplate['acKey'] ?? null);

        if (empty($headerTemplate)) {
            throw new RuntimeException('Nije moguće pronaći template narudžbe za Pantheon.');
        }

        if (empty($itemTemplate)) {
            throw new RuntimeException('Nije moguće pronaći template stavke narudžbe za Pantheon.');
        }

        $headerQid = $this->nextIntegerValue(Order::newSourceQuery(), Order::sourceColumns(), Order::sourceNonInsertableColumns(), 'anQId');
        $itemQid = $this->nextIntegerValue(OrderItem::newSourceQuery(), OrderItem::sourceColumns(), OrderItem::sourceNonInsertableColumns(), 'anQId');

        $headerPayload = $this->buildHeaderPayload(
            $headerTemplate,
            $prepared,
            $numberContext,
            $headerQid,
            $user
        );

        $itemPayloads = [];
        foreach ($prepared['items'] as $index => $item) {
            $itemPayloads[] = $this->buildItemPayload(
                $itemTemplate,
                $item,
                $headerPayload,
                $headerQid,
                $itemQid !== null ? ($itemQid + $index) : null,
                $index + 1,
                $user
            );
        }

        return [
            'payload' => $prepared,
            'pantheon_order_key' => (string) ($headerPayload['acKey'] ?? ''),
            'pantheon_order_view' => (string) ($headerPayload['acKeyView'] ?? ($numberContext['display_key'] ?? '')),
            'pantheon_order_qid' => $headerPayload['anQId'] ?? null,
            'item_count' => count($itemPayloads),
            'header_payload' => $headerPayload,
            'item_payloads' => $itemPayloads,
            'created_catalog_items' => $this->createdCatalogItems,
            'created_catalog_item_count' => count($this->createdCatalogItems),
        ];
    }

    private function prepareTransferData(
        array $normalizedPayload,
        bool $strict = true,
        bool $autoCreateMissingCatalogItems = false,
        mixed $user = null
    ): array
    {
        $order = is_array($normalizedPayload['order'] ?? null) ? $normalizedPayload['order'] : [];
        $rawItems = is_array($normalizedPayload['items'] ?? null) ? $normalizedPayload['items'] : [];
        $warnings = is_array($order['warnings'] ?? null) ? array_map('strval', $order['warnings']) : [];
        $preparedItems = [];
        $productCodeUsage = [];

        foreach (array_values($rawItems) as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $itemMeta = $this->extractTransferItemMetadata($rawItem);
            $productCode = $this->normalizeCatalogLookupValue((string) ($rawItem['product_code'] ?? ''));
            $productName = $this->normalizeCatalogLookupValue((string) ($itemMeta['product_name'] ?? ''));

            if ($productCode === '') {
                continue;
            }

            if (!array_key_exists($productCode, $productCodeUsage)) {
                $productCodeUsage[$productCode] = [
                    'count' => 0,
                    'names' => [],
                ];
            }

            $productCodeUsage[$productCode]['count']++;

            if ($productName !== '') {
                $productCodeUsage[$productCode]['names'][$productName] = true;
            }
        }

        foreach (array_values($rawItems) as $index => $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $itemMeta = $this->extractTransferItemMetadata($rawItem);
            $productCode = trim((string) ($rawItem['product_code'] ?? ''));
            $productName = $this->normalizePantheonText((string) ($itemMeta['product_name'] ?? ''));
            $sourceProductCode = $productCode;
            $sourceProductName = $productName;
            $itemNote = $this->normalizePantheonText((string) ($itemMeta['note'] ?? ''));
            $drawingReference = $this->normalizePantheonText((string) ($itemMeta['drawing_reference'] ?? ''));
            $materialHint = $this->normalizePantheonText((string) ($itemMeta['material_hint'] ?? ''));
            $primaryClassification = $this->resolvePrimaryClassification($materialHint, $order);
            $quantity = round(max(0, (float) ($rawItem['quantity'] ?? 0)), 6);
            $itemDeliveryDeadline = trim((string) ($rawItem['delivery_deadline'] ?? ''));

            if ($quantity <= 0) {
                continue;
            }

            if ($productCode === '' && $productName === '') {
                continue;
            }

            $normalizedSourceCode = $this->normalizeCatalogLookupValue($productCode);
            $codeUsage = $normalizedSourceCode !== '' ? ($productCodeUsage[$normalizedSourceCode] ?? null) : null;
            $shouldIgnoreRepeatedSourceCode = is_array($codeUsage)
                && (int) ($codeUsage['count'] ?? 0) > 1
                && count((array) ($codeUsage['names'] ?? [])) > 1;
            $resolvedCatalogMaterial = [];

            if ($normalizedSourceCode !== '' && !$shouldIgnoreRepeatedSourceCode) {
                $resolvedCatalogMaterial = $this->resolveCatalogMaterialByCode($productCode);
            } elseif ($productName !== '') {
                $resolvedCatalogMaterial = $this->resolveCatalogMaterialByName($productName);
            }
            $resolvedProductCode = trim((string) ($resolvedCatalogMaterial['material_code'] ?? ''));
            $resolvedProductName = trim((string) ($resolvedCatalogMaterial['material_name'] ?? ''));
            $resolvedProductUnit = trim((string) ($resolvedCatalogMaterial['material_um'] ?? ''));
            $resolvedProductQid = $this->positiveIntegerOrNull($resolvedCatalogMaterial['material_qid'] ?? null);
            $catalogItemCreated = false;

            if ($resolvedProductCode !== '') {
                $productCode = $resolvedProductCode;
            }

            if ($resolvedProductName !== '') {
                $productName = $resolvedProductName;
            }

            if ($sourceProductName !== '') {
                $productName = $sourceProductName;
            }

            if ($strict && $productCode === '') {
                throw new RuntimeException('Svaka stavka mora imati šifru artikla za transfer u Pantheon.');
            }

            if ($resolvedProductQid === null && $strict && $autoCreateMissingCatalogItems) {
                $createdCatalogMaterial = $this->createMissingCatalogMaterial(
                    $productCode,
                    $productName !== '' ? $productName : ($sourceProductName !== '' ? $sourceProductName : $productCode),
                    $primaryClassification,
                    $user
                );

                if (!empty($createdCatalogMaterial)) {
                    $resolvedCatalogMaterial = $createdCatalogMaterial;
                    $resolvedProductCode = trim((string) ($resolvedCatalogMaterial['material_code'] ?? $productCode));
                    $resolvedProductName = trim((string) ($resolvedCatalogMaterial['material_name'] ?? $productName));
                    $resolvedProductUnit = trim((string) ($resolvedCatalogMaterial['material_um'] ?? config('ai-order-scan.default_unit', 'KO')));
                    $resolvedProductQid = $this->positiveIntegerOrNull($resolvedCatalogMaterial['material_qid'] ?? null);
                    $catalogItemCreated = $resolvedProductQid !== null;

                    if ($resolvedProductCode !== '') {
                        $productCode = $resolvedProductCode;
                    }

                    if ($resolvedProductName !== '' && $sourceProductName === '') {
                        $productName = $resolvedProductName;
                    }
                }
            }

            $catalogItemMissing = $resolvedProductQid === null;

            if ($strict && $catalogItemMissing) {
                throw new RuntimeException(sprintf(
                    'Pantheon nije pronašao katalog artikal za šifru %s.',
                    $productCode !== '' ? $productCode : ($sourceProductCode !== '' ? $sourceProductCode : '[bez sifre]')
                ));
            }

            $unitPrice = round(max(0, (float) ($rawItem['unit_price'] ?? 0)), 4);
            $lineTotal = round(max(0, (float) ($rawItem['line_total'] ?? 0)), 4);
            $discount = round(max(0, (float) ($rawItem['discount_percent'] ?? 0)), 4);
            $vatRate = round(max(0, (float) ($rawItem['vat_rate'] ?? config('ai-order-scan.default_vat_rate', 17))), 4);
            $discountFactor = max(0, 1 - ($discount / 100));
            $computedBaseValue = round($quantity * $unitPrice * $discountFactor, 4);

            if ($lineTotal > 0) {
                $baseValue = $lineTotal;

                if ($quantity > 0 && $discountFactor > 0) {
                    $unitPrice = round($baseValue / ($quantity * $discountFactor), 4);
                }
            } else {
                $baseValue = $computedBaseValue;
                $lineTotal = $baseValue;
            }

            $vatValue = round($baseValue * ($vatRate / 100), 4);
            $grandTotal = round($baseValue + $vatValue, 4);
            $catalogItemNotice = $this->buildCatalogItemNotice(
                $productCode !== '' ? $productCode : $sourceProductCode,
                $catalogItemCreated ? 'created' : ($catalogItemMissing ? 'missing' : 'matched')
            );

            if ($catalogItemNotice !== '') {
                $warnings[] = $catalogItemNotice;
            }

            $preparedItems[] = [
                'line_number' => (int) ($rawItem['line_number'] ?? ($index + 1)),
                'product_code' => $productCode,
                'product_name' => $productName !== '' ? $productName : $productCode,
                'drawing_reference' => $drawingReference,
                'material_hint' => $materialHint,
                'quantity' => $quantity,
                'unit' => trim((string) ($rawItem['unit'] ?? config('ai-order-scan.default_unit', 'KO'))) ?: (string) config('ai-order-scan.default_unit', 'KO'),
                'delivery_deadline' => $itemDeliveryDeadline,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'vat_rate' => $vatRate,
                'vat_code' => trim((string) ($rawItem['vat_code'] ?? config('ai-order-scan.default_vat_code', 'P1'))) ?: (string) config('ai-order-scan.default_vat_code', 'P1'),
                'discount_percent' => $discount,
                'priority' => trim((string) ($rawItem['priority'] ?? '')),
                'note' => $itemNote,
                'primary_classification' => $primaryClassification,
                'catalog_item_exists' => !$catalogItemMissing,
                'catalog_item_missing' => $catalogItemMissing,
                'catalog_item_auto_create' => $catalogItemMissing || $catalogItemCreated,
                'catalog_item_created' => $catalogItemCreated,
                'catalog_item_status' => $catalogItemCreated ? 'created' : ($catalogItemMissing ? 'missing' : 'matched'),
                'catalog_item_notice' => $catalogItemNotice,
                'catalog_unit_hint' => $resolvedProductUnit,
                'product_qid' => $resolvedProductQid,
                'source_product_code' => $sourceProductCode,
                'source_product_name' => $sourceProductName,
                'base_value' => $baseValue,
                'vat_value' => $vatValue,
                'grand_total' => $grandTotal,
            ];
        }

        if ($strict && empty($preparedItems)) {
            throw new RuntimeException('Nema nijedne stavke spremne za transfer u Pantheon.');
        }

        $subtotal = round(array_sum(array_column($preparedItems, 'base_value')), 4);
        $vatTotal = round(array_sum(array_column($preparedItems, 'vat_value')), 4);
        $grandTotal = round(array_sum(array_column($preparedItems, 'grand_total')), 4);
        $customerName = trim((string) ($order['customer_name'] ?? ''));
        $supplierName = trim((string) ($order['supplier_name'] ?? ''));

        if ($strict && $customerName === '') {
            throw new RuntimeException('Naziv kupca je obavezan za kreiranje narudžbe.');
        }

        return [
            'customer_name' => $customerName,
            'supplier_name' => $supplierName,
            'receiver_name' => trim((string) ($order['receiver_name'] ?? $customerName)) ?: $customerName,
            'contact_name' => trim((string) ($order['contact_name'] ?? '')),
            'external_document_number' => trim((string) ($order['external_document_number'] ?? '')),
            'document_type' => $this->resolvePantheonDocType((string) ($order['document_type'] ?? '')),
            'currency' => trim((string) ($order['currency'] ?? config('ai-order-scan.default_currency', 'KM'))) ?: (string) config('ai-order-scan.default_currency', 'KM'),
            'delivery_deadline' => trim((string) ($order['delivery_deadline'] ?? '')),
            'note' => trim((string) ($order['note'] ?? '')),
            'way_of_sale' => trim((string) ($order['way_of_sale'] ?? config('ai-order-scan.default_way_of_sale', 'D'))) ?: (string) config('ai-order-scan.default_way_of_sale', 'D'),
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'grand_total' => $grandTotal,
            'items' => $preparedItems,
        ];
    }

    private function extractTransferItemMetadata(array $rawItem): array
    {
        $nameLines = [];
        $drawingParts = [];
        $noteParts = [];
        $productName = trim((string) ($rawItem['product_name'] ?? ''));
        $note = trim((string) ($rawItem['note'] ?? ''));
        $drawingReference = trim((string) ($rawItem['drawing_reference'] ?? ''));
        $materialHint = trim((string) ($rawItem['material_hint'] ?? ''));

        foreach ($this->splitTransferTextLines($productName) as $line) {
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

        foreach ($this->splitTransferTextLines($drawingReference) as $line) {
            $drawingParts[] = $line;
        }

        foreach ($this->splitTransferTextLines($note) as $line) {
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

    private function splitTransferTextLines(string $value): array
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = preg_split('/\n+/u', $value) ?: [];

        return array_values(array_filter(array_map(function ($line) {
            $line = trim((string) (preg_replace('/\s+/u', ' ', (string) $line) ?? $line));

            return $line;
        }, $lines)));
    }

    private function resolvePrimaryClassification(string $materialHint, array $order = []): string
    {
        $materialHint = trim($materialHint);

        if ($this->isTrendyGermanyOrder($order)) {
            return '';
        }

        if ($materialHint !== '' && preg_match('/^al/i', $materialHint) === 1) {
            return 'ALUMINIJUM';
        }

        return 'ČELIK';
    }

    private function isTrendyGermanyOrder(array $order): bool
    {
        foreach (['customer_name', 'supplier_name'] as $field) {
            $value = trim((string) ($order[$field] ?? ''));

            if ($value !== '' && stripos($value, 'trendy germany') !== false) {
                return true;
            }
        }

        return false;
    }

    private function buildCatalogItemNotice(string $productCode, string $status): string
    {
        $productCode = trim($productCode);

        if ($productCode === '') {
            return '';
        }

        if ($status === 'missing') {
            return sprintf('Artikal %s nije pronađen u bazi i biće automatski kreiran pri transferu.', $productCode);
        }

        if ($status === 'created') {
            return sprintf('Artikal %s nije postojao u bazi i automatski je kreiran tokom transfera.', $productCode);
        }

        return '';
    }

    private function createMissingCatalogMaterial(
        string $productCode,
        string $productName,
        string $primaryClassification,
        mixed $user = null
    ): array {
        $productCode = trim($productCode);
        $productName = trim($productName);

        if ($productCode === '') {
            return [];
        }

        $userId = is_object($user) ? (int) ($user->id ?? 0) : 0;
        $ensureResult = Product::ensureCatalogProduct([
            'product_code' => $productCode,
            'product_name' => $productName !== '' ? $productName : $productCode,
            'product_um' => (string) config('ai-order-scan.default_unit', 'KO'),
            'product_set' => '120',
            'product_classification' => $primaryClassification,
        ], $userId);

        $catalogRow = is_array($ensureResult['row'] ?? null)
            ? (array) ($ensureResult['row'] ?? [])
            : [];
        $candidate = $this->normalizeCatalogMaterialCandidate($catalogRow);

        if (!empty($candidate)) {
            $this->catalogMaterialByCodeCache[$this->normalizeCatalogLookupValue($productCode)] = $candidate;

            if ($productName !== '') {
                $this->catalogMaterialByNameCache[$this->normalizeCatalogLookupValue($productName)] = $candidate;
            }
        }

        if ((bool) ($ensureResult['created'] ?? false)) {
            $this->createdCatalogItems[] = [
                'product_code' => $productCode,
                'product_name' => $productName !== '' ? $productName : $productCode,
                'unit' => (string) config('ai-order-scan.default_unit', 'KO'),
                'set' => '120',
                'primary_classification' => $primaryClassification,
            ];
        }

        return $candidate;
    }

    private function resolvePantheonDocType(string $candidate): string
    {
        $fallback = strtoupper(trim((string) config('ai-order-scan.default_doc_type', '0200'))) ?: '0200';
        $candidate = strtoupper(trim($candidate));

        if ($candidate === '') {
            return $fallback;
        }

        $maxLength = (int) (Order::sourceStringLengths()['acDocType'] ?? 4);

        if ($maxLength > 0 && strlen($candidate) > $maxLength) {
            Log::info('Order AI doc type fallback applied because extracted document type is not a Pantheon code.', [
                'source_document_type' => $candidate,
                'pantheon_document_type' => $fallback,
            ]);

            return $fallback;
        }

        if (!empty($this->resolveHeaderTemplate($candidate))) {
            return $candidate;
        }

        Log::info('Order AI doc type fallback applied because Pantheon template was not found.', [
            'source_document_type' => $candidate,
            'pantheon_document_type' => $fallback,
        ]);

        return $fallback;
    }

    private function generateNextOrderNumber(string $docType): array
    {
        $docType = strtoupper(trim($docType));
        $yearPrefix = Carbon::now()->format('y');
        $rawPrefix = $yearPrefix . $docType;
        $stringLength = Order::sourceStringLengths()['acKey'] ?? null;
        $sequenceLength = is_int($stringLength) && $stringLength > strlen($rawPrefix)
            ? $stringLength - strlen($rawPrefix)
            : 7;

        $lastKey = (string) (Order::newSourceQuery()
            ->where('acDocType', $docType)
            ->where('acKey', 'like', $rawPrefix . '%')
            ->orderByDesc('acKey')
            ->value('acKey') ?? '');

        $lastSequence = 0;
        if ($lastKey !== '' && str_starts_with($lastKey, $rawPrefix)) {
            $suffix = substr($lastKey, strlen($rawPrefix));
            $lastSequence = (int) ltrim((string) $suffix, '0');
        }

        $nextSequence = $lastSequence + 1;
        $rawKey = $rawPrefix . str_pad((string) $nextSequence, $sequenceLength, '0', STR_PAD_LEFT);

        return [
            'doc_type' => $docType,
            'raw_key' => $rawKey,
            'display_key' => $yearPrefix . '-' . $docType . '-' . str_pad((string) $nextSequence, $sequenceLength, '0', STR_PAD_LEFT),
        ];
    }

    private function resolveHeaderTemplate(string $docType): array
    {
        $query = Order::newSourceQuery();

        $row = $query
            ->where('acDocType', $docType)
            ->orderByDesc('anQId')
            ->first();

        if ($row === null) {
            $row = Order::newSourceQuery()->orderByDesc('anQId')->first();
        }

        return $row ? (array) $row : [];
    }

    private function resolveItemTemplate(?string $templateOrderKey): array
    {
        $query = OrderItem::newSourceQuery();

        if ($templateOrderKey !== null && trim($templateOrderKey) !== '') {
            $row = $query
                ->where('acKey', trim($templateOrderKey))
                ->orderByDesc('anNo')
                ->first();

            if ($row !== null) {
                return (array) $row;
            }
        }

        $row = OrderItem::newSourceQuery()->orderByDesc('anQId')->first();

        return $row ? (array) $row : [];
    }

    private function buildHeaderPayload(
        array $template,
        array $prepared,
        array $numberContext,
        ?int $nextQid,
        mixed $user = null
    ): array {
        $columns = Order::sourceColumns();
        $insertableColumns = Order::sourceInsertableColumns();
        $stringLengths = Order::sourceStringLengths();
        $payload = [];
        $excludedCopyColumns = [
            'acKey',
            'acKeyView',
            'acDocType',
            'acRefNo1',
            'acRefNo2',
            'adDate',
            'adDeliveryDeadline',
            'adDateValid',
            'anValue',
            'anDiscount',
            'anVAT',
            'anForPay',
            'anQId',
            'adTimeIns',
            'adTimeChg',
            'anUserIns',
            'anUserChg',
            'acConsignee',
            'acReceiver',
            'acContactPrsn',
            'acContactPrsn3',
            'acCurrency',
            'acWayOfSale',
            'acWarehouse',
            'acDoc1',
            'acNote',
            'acInternalNote',
            'acAddress',
            'acPost',
            'anConsigneeQId',
            'anReceiverQId',
            'anBuyerCostCenterIdDef',
            'anBuyerIdDef',
            'acBuyerCostCenterId',
            'acBuyerId',
        ];

        foreach ($insertableColumns as $column) {
            if (!array_key_exists($column, $template) || in_array($column, $excludedCopyColumns, true)) {
                continue;
            }

            $payload[$column] = $template[$column];
        }

        $now = Carbon::now();
        $validDays = max(0, (int) config('ai-order-scan.default_valid_days', 5));
        $deliveryDeadline = $this->parseDateOrFallback($prepared['delivery_deadline'] ?? '', $now->copy()->addDays($validDays));
        $valueBeforeDiscount = $prepared['subtotal'];
        $vatTotal = $prepared['vat_total'];
        $grandTotal = $prepared['grand_total'];
        $userId = is_object($user) ? (int) ($user->id ?? 0) : 0;
        $transferPartyName = trim((string) ($prepared['supplier_name'] ?? '')) !== ''
            ? trim((string) $prepared['supplier_name'])
            : trim((string) ($prepared['customer_name'] ?? ''));
        $consigneeQId = $this->resolveSubjectQId(
            $transferPartyName,
            $this->positiveIntegerOrNull($template['anConsigneeQId'] ?? null)
        );
        $receiverQId = $this->resolveSubjectQId(
            $transferPartyName !== '' ? $transferPartyName : (string) ($prepared['receiver_name'] ?? ''),
            $this->positiveIntegerOrNull($template['anReceiverQId'] ?? null)
        );

        $payload['acKey'] = $this->fitString('acKey', $numberContext['raw_key'], $stringLengths);
        $payload['acKeyView'] = $this->fitString('acKeyView', $numberContext['display_key'], $stringLengths);
        $payload['acDocType'] = $this->fitString('acDocType', $numberContext['doc_type'], $stringLengths);
        $payload['acRefNo1'] = $this->fitString('acRefNo1', (string) config('ai-order-scan.default_ref_no', '99'), $stringLengths);
        $payload['adDate'] = $now->copy()->startOfDay();
        $payload['adDeliveryDeadline'] = $deliveryDeadline->copy()->startOfDay();
        $payload['adDateValid'] = $now->copy()->addDays($validDays)->startOfDay();
        $payload['anDaysForValid'] = $validDays;
        $payload['acStatus'] = '1';
        $payload['acConsignee'] = $this->fitString('acConsignee', $transferPartyName, $stringLengths);
        $payload['acReceiver'] = $this->fitString('acReceiver', $transferPartyName !== '' ? $transferPartyName : $prepared['receiver_name'], $stringLengths);
        $payload['acContactPrsn'] = $this->fitString('acContactPrsn', $prepared['contact_name'], $stringLengths);
        $payload['acContactPrsn3'] = $this->fitString('acContactPrsn3', $prepared['contact_name'], $stringLengths);
        $payload['acCurrency'] = $this->fitString('acCurrency', $prepared['currency'], $stringLengths);
        $payload['acWayOfSale'] = $this->fitString('acWayOfSale', $prepared['way_of_sale'], $stringLengths);
        $payload['acWarehouse'] = $this->fitString('acWarehouse', (string) config('ai-order-scan.default_warehouse', ''), $stringLengths);
        $payload['acDoc1'] = $this->fitString('acDoc1', $prepared['external_document_number'], $stringLengths);
        $payload['anValue'] = $valueBeforeDiscount;
        $payload['anDiscount'] = 0;
        $payload['anVAT'] = $vatTotal;
        $payload['anForPay'] = $grandTotal;
        $payload['anCurrValue'] = $grandTotal;
        $payload['acNote'] = $this->fitString('acNote', $this->buildHeaderNote($prepared), $stringLengths);
        $payload['acInternalNote'] = $this->fitString('acInternalNote', $this->buildInternalNote($prepared), $stringLengths);
        $payload['adTimeIns'] = $now;
        $payload['adTimeChg'] = $now;

        if ($userId > 0) {
            $payload['anUserIns'] = $userId;
            $payload['anUserChg'] = $userId;
        }

        if (in_array('anQId', $columns, true) && $nextQid !== null) {
            $payload['anQId'] = $nextQid;
        }

        foreach (['anBuyerCostCenterIdDef', 'anBuyerIdDef'] as $zeroColumn) {
            if (in_array($zeroColumn, $columns, true)) {
                $payload[$zeroColumn] = 0;
            }
        }

        if (in_array('anConsigneeQId', $columns, true) && $consigneeQId !== null) {
            $payload['anConsigneeQId'] = $consigneeQId;
        }

        if (in_array('anReceiverQId', $columns, true) && $receiverQId !== null) {
            $payload['anReceiverQId'] = $receiverQId;
        }

        foreach (['acBuyerCostCenterId', 'acBuyerId', 'acAddress', 'acPost'] as $blankColumn) {
            if (in_array($blankColumn, $columns, true)) {
                $payload[$blankColumn] = '';
            }
        }

        return $this->trimPayloadToInsertableColumns($payload, $insertableColumns);
    }

    private function buildItemPayload(
        array $template,
        array $item,
        array $headerPayload,
        ?int $headerQid,
        ?int $itemQid,
        int $lineNumber,
        mixed $user = null
    ): array {
        $columns = OrderItem::sourceColumns();
        $insertableColumns = OrderItem::sourceInsertableColumns();
        $stringLengths = OrderItem::sourceStringLengths();
        $payload = [];
        $excludedCopyColumns = [
            'acKey',
            'anNo',
            'acIdent',
            'acName',
            'anIdentQId',
            'anQty',
            'anQtyDispDoc',
            'acUM',
            'anPrice',
            'anRebate',
            'acVATCode',
            'anVAT',
            'acLnkKey',
            'anLnkNo',
            'acNote',
            'adTimeIns',
            'anUserIns',
            'adTimeChg',
            'anUserChg',
            'anPVValue',
            'anPVDiscount',
            'anPVExcise',
            'anPVVATBase',
            'anPVVAT',
            'anPVForPay',
            'anPVOCValue',
            'anPVOCDiscount',
            'anPVOCExcise',
            'anPVOCVATBase',
            'anPVOCVAT',
            'anPVOCForPay',
            'anQId',
            'anQtyConverted',
            'acUMConverted',
            'anOrderQId',
            'acPriority',
            'adDeliveryDeadline',
            'adDeliveryDate',
        ];

        foreach ($insertableColumns as $column) {
            if (!array_key_exists($column, $template) || in_array($column, $excludedCopyColumns, true)) {
                continue;
            }

            $payload[$column] = $template[$column];
        }

        $now = Carbon::now();
        $userId = is_object($user) ? (int) ($user->id ?? 0) : 0;
        $headerDeliveryDeadline = $headerPayload['adDeliveryDeadline'] instanceof Carbon
            ? $headerPayload['adDeliveryDeadline']->copy()->startOfDay()
            : $now->copy()->startOfDay();
        $itemDeliveryDeadline = $this->parseDateOrFallback(
            (string) ($item['delivery_deadline'] ?? ''),
            $headerDeliveryDeadline
        )->copy()->startOfDay();

        $resolvedUnit = $this->resolvePantheonItemUnit($item, $template, $stringLengths);
        $resolvedItemQid = $this->positiveIntegerOrNull($item['product_qid'] ?? null);
        $fullItemName = $this->normalizePantheonText((string) ($item['product_name'] ?? ''));
        $fittedItemName = $this->fitString('acName', $fullItemName, $stringLengths);
        $overflowName = '';

        if ($fullItemName !== '' && $fittedItemName !== '' && $fittedItemName !== $fullItemName) {
            $overflowName = trim((string) Str::of($fullItemName)->substr(Str::length($fittedItemName))->toString());
        }

        $resolvedItemNote = $this->mergePantheonTextParts([
            $overflowName,
            (string) ($item['note'] ?? ''),
        ]);

        $payload['acKey'] = $this->fitString('acKey', (string) ($headerPayload['acKey'] ?? ''), $stringLengths);
        $payload['anNo'] = $lineNumber;
        $payload['acIdent'] = $this->fitString('acIdent', $item['product_code'], $stringLengths);
        $payload['acName'] = $fittedItemName !== '' ? $fittedItemName : $this->fitString('acName', $item['product_name'], $stringLengths);
        $payload['anQty'] = $item['quantity'];
        $payload['anQtyDispDoc'] = 0;
        $payload['acUM'] = $this->fitString('acUM', $resolvedUnit, $stringLengths);
        $payload['anPrice'] = $item['unit_price'];
        $payload['anRebate'] = $item['discount_percent'];
        $payload['acVATCode'] = $this->fitString('acVATCode', $item['vat_code'], $stringLengths);
        $payload['anVAT'] = $item['vat_rate'];
        $payload['acLnkKey'] = '';
        $payload['anLnkNo'] = 0;
        $payload['acNote'] = $this->fitString('acNote', $resolvedItemNote, $stringLengths);
        $payload['adTimeIns'] = $now;
        $payload['adTimeChg'] = $now;
        $payload['anPVValue'] = $item['base_value'];
        $payload['anPVDiscount'] = 0;
        $payload['anPVExcise'] = 0;
        $payload['anPVVATBase'] = $item['base_value'];
        $payload['anPVVAT'] = $item['vat_value'];
        $payload['anPVForPay'] = $item['grand_total'];
        $payload['anPVOCValue'] = $item['base_value'];
        $payload['anPVOCDiscount'] = 0;
        $payload['anPVOCExcise'] = 0;
        $payload['anPVOCVATBase'] = $item['base_value'];
        $payload['anPVOCVAT'] = $item['vat_value'];
        $payload['anPVOCForPay'] = $item['grand_total'];
        $payload['anQtyConverted'] = $item['quantity'];
        $payload['acUMConverted'] = $this->fitString('acUMConverted', $resolvedUnit, $stringLengths);
        $payload['adDeliveryDeadline'] = $itemDeliveryDeadline;
        $payload['adDeliveryDate'] = $itemDeliveryDeadline;

        if ($userId > 0) {
            $payload['anUserIns'] = $userId;
            $payload['anUserChg'] = $userId;
        }

        if (in_array('anPriceCurrency', $columns, true)) {
            $payload['anPriceCurrency'] = $item['unit_price'];
        }
        if (in_array('anQId', $columns, true) && $itemQid !== null) {
            $payload['anQId'] = $itemQid;
        }
        if (in_array('anIdentQId', $columns, true) && $resolvedItemQid !== null) {
            $payload['anIdentQId'] = $resolvedItemQid;
        }
        if (in_array('anOrderQId', $columns, true) && $headerQid !== null) {
            $payload['anOrderQId'] = $headerQid;
        }
        if (in_array('acPriority', $columns, true)) {
            $payload['acPriority'] = $this->fitString('acPriority', $item['priority'], $stringLengths);
        }

        return $this->trimPayloadToInsertableColumns($payload, $insertableColumns);
    }

    private function buildHeaderNote(array $prepared): string
    {
        $parts = [];

        if (trim((string) ($prepared['external_document_number'] ?? '')) !== '') {
            $parts[] = 'Izvorni broj: ' . trim((string) $prepared['external_document_number']);
        }

        if (trim((string) ($prepared['note'] ?? '')) !== '') {
            $parts[] = trim((string) $prepared['note']);
        }

        return implode(' | ', $parts);
    }

    private function buildInternalNote(array $prepared): string
    {
        $warnings = array_values(array_filter(array_map(function ($warning) {
            return trim((string) $warning);
        }, $prepared['warnings'] ?? [])));

        $parts = ['Kreirano iz AI skena narudžbe preko eNalog.app'];

        if (!empty($warnings)) {
            $parts[] = 'AI napomene: ' . implode('; ', $warnings);
        }

        return implode(' | ', $parts);
    }

    private function parseDateOrFallback(string $value, Carbon $fallback): Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return $fallback;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return $fallback;
        }
    }

    private function nextIntegerValue($query, array $columns, array $nonInsertableColumns, string $column): ?int
    {
        if (!in_array($column, $columns, true) || in_array($column, $nonInsertableColumns, true)) {
            return null;
        }

        return ((int) ($query->max($column) ?? 0)) + 1;
    }

    private function resolvePantheonItemUnit(array $item, array $template, array $stringLengths): string
    {
        $sourceUnit = $this->normalizeUnitCode((string) ($item['unit'] ?? ''), $stringLengths);
        $productCode = trim((string) ($item['product_code'] ?? ''));
        $catalogUnit = $this->normalizeUnitCode((string) ($item['catalog_unit_hint'] ?? ''), $stringLengths);
        $productUnit = $this->resolveRecentProductUnit($productCode, $stringLengths);
        $templateUnit = $this->normalizeUnitCode((string) ($template['acUM'] ?? ($template['acUMConverted'] ?? '')), $stringLengths);
        $fallbackUnit = $this->normalizeUnitCode((string) config('ai-order-scan.default_unit', 'KO'), $stringLengths);

        if ($sourceUnit !== '' && $this->isValidPantheonUnit($sourceUnit)) {
            return $sourceUnit;
        }

        if ($catalogUnit !== '' && $this->isValidPantheonUnit($catalogUnit)) {
            Log::info('Order AI item unit fallback applied from catalog material.', [
                'product_code' => $productCode,
                'source_unit' => $sourceUnit,
                'pantheon_unit' => $catalogUnit,
            ]);

            return $catalogUnit;
        }

        if ($productUnit !== '') {
            Log::info('Order AI item unit fallback applied from existing Pantheon item.', [
                'product_code' => $productCode,
                'source_unit' => $sourceUnit,
                'pantheon_unit' => $productUnit,
            ]);

            return $productUnit;
        }

        if ($templateUnit !== '' && $this->isValidPantheonUnit($templateUnit)) {
            Log::info('Order AI item unit fallback applied from Pantheon template.', [
                'product_code' => $productCode,
                'source_unit' => $sourceUnit,
                'pantheon_unit' => $templateUnit,
            ]);

            return $templateUnit;
        }

        if ($fallbackUnit !== '' && $this->isValidPantheonUnit($fallbackUnit)) {
            Log::info('Order AI item unit fallback applied from config default.', [
                'product_code' => $productCode,
                'source_unit' => $sourceUnit,
                'pantheon_unit' => $fallbackUnit,
            ]);

            return $fallbackUnit;
        }

        return 'KO';
    }

    private function resolveRecentProductUnit(string $productCode, array $stringLengths): string
    {
        $productCode = trim($productCode);

        if ($productCode === '') {
            return '';
        }

        $unit = (string) (OrderItem::newSourceQuery()
            ->where('acIdent', $productCode)
            ->whereRaw("LTRIM(RTRIM(ISNULL(acUM, ''))) <> ''")
            ->orderByDesc('anQId')
            ->value('acUM') ?? '');

        $unit = $this->normalizeUnitCode($unit, $stringLengths);

        return $this->isValidPantheonUnit($unit) ? $unit : '';
    }

    private function isValidPantheonUnit(string $unit): bool
    {
        $unit = strtoupper(trim($unit));

        if ($unit === '') {
            return false;
        }

        static $knownUnits = null;

        if ($knownUnits === null) {
            $knownUnits = DB::connection('sqlsrv')
                ->table(Order::sourceSchema() . '.tHE_SetUM')
                ->select('acUM')
                ->get()
                ->map(function ($row) {
                    return strtoupper(trim((string) ($row->acUM ?? '')));
                })
                ->filter()
                ->values()
                ->all();
        }

        return in_array($unit, $knownUnits, true);
    }

    private function normalizeUnitCode(string $unit, array $stringLengths): string
    {
        $unit = strtoupper(trim($unit));

        if ($unit === '') {
            return '';
        }

        $aliases = [
            'ST' => (string) config('ai-order-scan.default_unit', 'KO'),
            'STK' => (string) config('ai-order-scan.default_unit', 'KO'),
            'STUECK' => (string) config('ai-order-scan.default_unit', 'KO'),
            'STUCK' => (string) config('ai-order-scan.default_unit', 'KO'),
            'STU' => (string) config('ai-order-scan.default_unit', 'KO'),
            'PCS' => (string) config('ai-order-scan.default_unit', 'KO'),
            'PIECE' => (string) config('ai-order-scan.default_unit', 'KO'),
        ];

        if (array_key_exists($unit, $aliases)) {
            $unit = strtoupper(trim((string) $aliases[$unit]));
        }

        $maxLength = $stringLengths['acUM'] ?? null;

        if (is_int($maxLength) && $maxLength > 0) {
            $unit = substr($unit, 0, $maxLength);
        }

        return $unit;
    }

    private function resolveSubjectQId(string $subject, ?int $fallbackQId = null): ?int
    {
        $subject = trim($subject);

        if ($subject !== '') {
            foreach ($this->subjectLookupCandidates($subject) as $candidate) {
                $resolvedQId = $this->lookupSubjectQId($candidate, false);

                if ($resolvedQId !== null) {
                    return $resolvedQId;
                }
            }

            foreach ($this->subjectLookupCandidates($subject) as $candidate) {
                $resolvedQId = $this->lookupSubjectQId($candidate, true);

                if ($resolvedQId !== null) {
                    return $resolvedQId;
                }
            }
        }

        return $fallbackQId;
    }

    private function subjectLookupCandidates(string $subject): array
    {
        $subject = trim((string) preg_replace('/\s+/', ' ', $subject));

        if ($subject === '') {
            return [];
        }

        $candidates = [$subject];

        foreach ((array) config('ai-order-scan.profiles', []) as $profileConfig) {
            $aliases = is_array($profileConfig['subject_aliases'] ?? null)
                ? $profileConfig['subject_aliases']
                : [];

            foreach ($aliases as $alias) {
                $alias = trim((string) $alias);

                if ($alias === '') {
                    continue;
                }

                $normalizedSubject = $this->normalizeCatalogLookupValue($subject);
                $normalizedAlias = $this->normalizeCatalogLookupValue($alias);

                if ($normalizedSubject === '' || $normalizedAlias === '') {
                    continue;
                }

                if (str_contains($normalizedSubject, $normalizedAlias) || str_contains($normalizedAlias, $normalizedSubject)) {
                    $candidates[] = $alias;
                }
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $candidates))));
    }

    private function lookupSubjectQId(string $subject, bool $prefixMatch = false): ?int
    {
        $subject = trim($subject);

        if ($subject === '') {
            return null;
        }

        try {
            $query = DB::connection('sqlsrv')
                ->table(Order::sourceSchema() . '.tHE_SetSubj')
                ->select('anQId')
                ->whereRaw("LTRIM(RTRIM(ISNULL(acSubject, ''))) <> ''");

            if ($prefixMatch) {
                $query->whereRaw("LTRIM(RTRIM(ISNULL(acSubject, ''))) like ?", [$subject . '%']);
            } else {
                $query->whereRaw("LTRIM(RTRIM(ISNULL(acSubject, ''))) = ?", [$subject]);
            }

            $value = $query->orderBy('anQId')->value('anQId');

            return $this->positiveIntegerOrNull($value);
        } catch (\Throwable $exception) {
            Log::warning('Order AI subject lookup failed.', [
                'subject' => $subject,
                'prefix_match' => $prefixMatch,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function positiveIntegerOrNull(mixed $value): ?int
    {
        if (is_numeric((string) $value)) {
            $integer = (int) $value;

            return $integer > 0 ? $integer : null;
        }

        return null;
    }

    private function resolveCatalogMaterial(string $productCode, string $productName): array
    {
        $productCode = trim(Utf8Sanitizer::clean($productCode, 120));
        $productName = trim(Utf8Sanitizer::clean($productName));

        $codeCandidate = $this->resolveCatalogMaterialByCode($productCode);
        $nameCandidate = $this->resolveCatalogMaterialByName($productName);

        if (!empty($codeCandidate)) {
            $codeSimilarity = $productName !== ''
                ? $this->catalogMaterialSimilarityScore($productName, (string) ($codeCandidate['material_name'] ?? ''))
                : 0.94;
            $exactCodeMatch = $this->normalizeCatalogLookupValue($productCode) !== ''
                && in_array(
                    $this->normalizeCatalogLookupValue($productCode),
                    [
                        $this->normalizeCatalogLookupValue((string) ($codeCandidate['material_code'] ?? '')),
                        $this->normalizeCatalogLookupValue((string) ($codeCandidate['material_code_alt'] ?? '')),
                    ],
                    true
                );

            $codeCandidate['_match_score'] = $productName === ''
                ? ($exactCodeMatch ? 0.94 : max(0.58, $codeSimilarity))
                : max($codeSimilarity, $exactCodeMatch ? 0.42 : 0.34);
        }

        if (!empty($nameCandidate)) {
            $nameCandidate['_match_score'] = max(
                (float) ($nameCandidate['_match_score'] ?? 0),
                $this->catalogMaterialSimilarityScore($productName, (string) ($nameCandidate['material_name'] ?? ''))
            );
        }

        if (!empty($nameCandidate) && empty($codeCandidate)) {
            return $nameCandidate;
        }

        if (!empty($codeCandidate) && empty($nameCandidate)) {
            return $codeCandidate;
        }

        if (empty($codeCandidate) && empty($nameCandidate)) {
            return [];
        }

        $codeScore = (float) ($codeCandidate['_match_score'] ?? 0);
        $nameScore = (float) ($nameCandidate['_match_score'] ?? 0);

        if ($nameScore >= 0.72 && ($codeScore < 0.45 || $nameScore > ($codeScore + 0.08))) {
            return $nameCandidate;
        }

        return $codeScore >= $nameScore ? $codeCandidate : $nameCandidate;
    }

    private function resolveCatalogMaterialByCode(string $productCode): array
    {
        $cacheKey = $this->normalizeCatalogLookupValue($productCode);

        if ($cacheKey === '') {
            return [];
        }

        if (array_key_exists($cacheKey, $this->catalogMaterialByCodeCache)) {
            return $this->catalogMaterialByCodeCache[$cacheKey];
        }

        $candidate = Material::scannerFindByBarcode($productCode);

        return $this->catalogMaterialByCodeCache[$cacheKey] = $candidate !== null
            ? $this->normalizeCatalogMaterialCandidate($candidate)
            : [];
    }

    private function resolveCatalogMaterialByName(string $productName): array
    {
        $productName = Utf8Sanitizer::clean($productName);
        $cacheKey = $this->normalizeCatalogLookupValue($productName);

        if ($cacheKey === '') {
            return [];
        }

        if (array_key_exists($cacheKey, $this->catalogMaterialByNameCache)) {
            return $this->catalogMaterialByNameCache[$cacheKey];
        }

        $bestCandidate = [];
        $bestScore = 0.0;

        foreach ($this->buildCatalogMaterialSearchTerms($productName) as $searchTerm) {
            $rows = Material::scannerList($searchTerm, 25);

            foreach ($rows as $row) {
                $candidate = $this->normalizeCatalogMaterialCandidate($row);

                if (empty($candidate)) {
                    continue;
                }

                $score = $this->catalogMaterialSimilarityScore($productName, (string) ($candidate['material_name'] ?? ''));

                if ($score <= $bestScore) {
                    continue;
                }

                $candidate['_match_score'] = $score;
                $bestCandidate = $candidate;
                $bestScore = $score;
            }

            if ($bestScore >= 0.98) {
                break;
            }
        }

        if ($bestScore < 0.52) {
            $bestCandidate = [];
        }

        if (!empty($bestCandidate)) {
            $bestCandidate = $this->enrichCatalogMaterialCandidate($bestCandidate);
        }

        $this->catalogMaterialByNameCache[$cacheKey] = $bestCandidate;

        return $bestCandidate;
    }

    private function normalizeCatalogMaterialCandidate(array $candidate): array
    {
        $materialCode = trim((string) ($candidate['material_code'] ?? $candidate['acIdentChild'] ?? $candidate['acIdent'] ?? ''));
        $materialName = trim((string) ($candidate['material_name'] ?? $candidate['acDescr'] ?? $candidate['acName'] ?? ''));
        $materialUnit = trim((string) ($candidate['material_um'] ?? $candidate['acUM'] ?? ''));
        $materialQid = $this->positiveIntegerOrNull($candidate['material_qid'] ?? $candidate['anQId'] ?? null);

        if ($materialCode === '' && $materialName === '') {
            return [];
        }

        return [
            'material_code' => $materialCode,
            'material_name' => $materialName !== '' ? $materialName : $materialCode,
            'material_um' => strtoupper(substr($materialUnit, 0, 3)),
            'material_qid' => $materialQid,
            'material_code_alt' => trim((string) ($candidate['material_code_alt'] ?? $candidate['acCode'] ?? '')),
            'material_set' => trim((string) ($candidate['material_set'] ?? $candidate['acSetOfItem'] ?? '')),
            'material_supplier' => trim((string) ($candidate['material_supplier'] ?? $candidate['acSupplier'] ?? '')),
            'material_classification' => trim((string) ($candidate['material_classification'] ?? $candidate['acClassif'] ?? '')),
        ];
    }

    private function enrichCatalogMaterialCandidate(array $candidate): array
    {
        $materialCode = trim((string) ($candidate['material_code'] ?? ''));

        if ($materialCode === '') {
            return $candidate;
        }

        $resolvedByCode = $this->resolveCatalogMaterialByCode($materialCode);

        if (empty($resolvedByCode)) {
            return $candidate;
        }

        $merged = array_merge($resolvedByCode, $candidate);
        $merged['material_qid'] = $this->positiveIntegerOrNull($candidate['material_qid'] ?? null)
            ?? $this->positiveIntegerOrNull($resolvedByCode['material_qid'] ?? null);
        $merged['_match_score'] = max(
            (float) ($candidate['_match_score'] ?? 0),
            (float) ($resolvedByCode['_match_score'] ?? 0)
        );

        return $merged;
    }

    private function buildCatalogMaterialSearchTerms(string $productName): array
    {
        $productName = Utf8Sanitizer::clean($productName);
        $productName = trim((string) (preg_replace('/\s+/', ' ', $productName) ?? $productName));

        if ($productName === '') {
            return [];
        }

        $signature = $this->extractCatalogMaterialSignature($productName);
        $terms = [$productName];

        if ($signature !== '') {
            $terms[] = $signature;
            $terms[] = str_replace('-', '/', $signature);
            $terms[] = str_replace('/', '-', $signature);
        }

        $tokens = preg_split('/\s+/', mb_strtoupper($productName, 'UTF-8')) ?: [];
        $filteredTokens = array_values(array_filter($tokens, function ($token) {
            $token = trim((string) $token);

            if ($token === '' || mb_strlen($token, 'UTF-8') < 3) {
                return false;
            }

            return !in_array($token, [
                'ZEICHNUNG',
                'MIT',
                'REVISIONSSTAND',
                'WERKSTOFF',
                'BESCHICHTUNG',
                'GALVANISCH',
            ], true);
        }));

        if (!empty($filteredTokens)) {
            $terms[] = implode(' ', array_slice($filteredTokens, 0, 6));
            $terms[] = implode(' ', array_slice($filteredTokens, 0, 3));
        }

        $normalizedTerms = [];

        foreach ($terms as $term) {
            $term = trim(Utf8Sanitizer::clean($term));

            if ($term === '') {
                continue;
            }

            if (mb_strlen($term, 'UTF-8') > 120) {
                $term = mb_substr($term, 0, 120, 'UTF-8');
            }

            $normalizedTerms[$term] = $term;
        }

        return array_values($normalizedTerms);
    }

    private function catalogMaterialSimilarityScore(string $sourceName, string $candidateName): float
    {
        $sourceName = trim($sourceName);
        $candidateName = trim($candidateName);

        if ($sourceName === '' || $candidateName === '') {
            return 0.0;
        }

        $normalizedSource = $this->normalizeCatalogLookupValue($sourceName);
        $normalizedCandidate = $this->normalizeCatalogLookupValue($candidateName);

        if ($normalizedSource === '' || $normalizedCandidate === '') {
            return 0.0;
        }

        if ($normalizedSource === $normalizedCandidate) {
            return 1.0;
        }

        $textSimilarity = 0.0;
        similar_text($normalizedSource, $normalizedCandidate, $textSimilarityPercent);
        $textSimilarity = max(0.0, min(1.0, ((float) $textSimilarityPercent) / 100));

        $sourceSignature = $this->extractCatalogMaterialSignature($sourceName);
        $candidateSignature = $this->extractCatalogMaterialSignature($candidateName);
        $signatureScore = 0.0;

        if ($sourceSignature !== '' && $candidateSignature !== '') {
            if ($sourceSignature === $candidateSignature) {
                $signatureScore = 1.0;
            } elseif (str_contains($this->normalizeCatalogLookupValue($candidateSignature), $this->normalizeCatalogLookupValue($sourceSignature))) {
                $signatureScore = 0.88;
            } elseif (str_contains($this->normalizeCatalogLookupValue($sourceSignature), $this->normalizeCatalogLookupValue($candidateSignature))) {
                $signatureScore = 0.82;
            }
        }

        $sourceTokens = array_values(array_unique(array_filter(
            preg_split('/[^A-Z0-9]+/', strtoupper($sourceName)) ?: [],
            fn ($token) => strlen((string) $token) >= 3
        )));
        $candidateTokens = array_values(array_unique(array_filter(
            preg_split('/[^A-Z0-9]+/', strtoupper($candidateName)) ?: [],
            fn ($token) => strlen((string) $token) >= 3
        )));
        $commonTokens = array_intersect($sourceTokens, $candidateTokens);
        $tokenScore = !empty($sourceTokens)
            ? count($commonTokens) / count($sourceTokens)
            : 0.0;

        return round(min(1.0, ($textSimilarity * 0.42) + ($signatureScore * 0.38) + ($tokenScore * 0.2)), 4);
    }

    private function extractCatalogMaterialSignature(string $value): string
    {
        $value = strtoupper(trim($value));

        if ($value === '') {
            return '';
        }

        if (preg_match('/[A-Z0-9]+(?:[\/-][A-Z0-9]+){2,}/', $value, $matches) === 1) {
            return $this->normalizeCatalogLookupValue((string) ($matches[0] ?? ''));
        }

        return '';
    }

    private function normalizeCatalogLookupValue(string $value): string
    {
        $value = Utf8Sanitizer::clean($value);

        return preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value))) ?? '';
    }

    private function normalizePantheonText(string $value): string
    {
        $value = Utf8Sanitizer::clean($value);

        return trim((string) (preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $value)) ?? $value));
    }

    private function mergePantheonTextParts(array $parts): string
    {
        $merged = [];
        $seen = [];

        foreach ($parts as $part) {
            $normalizedPart = $this->normalizePantheonText((string) $part);

            if ($normalizedPart === '') {
                continue;
            }

            $lookupKey = $this->normalizeCatalogLookupValue($normalizedPart);

            if ($lookupKey !== '' && isset($seen[$lookupKey])) {
                continue;
            }

            $merged[] = $normalizedPart;

            if ($lookupKey !== '') {
                $seen[$lookupKey] = true;
            }
        }

        return implode(' | ', $merged);
    }

    private function trimPayloadToInsertableColumns(array $payload, array $insertableColumns): array
    {
        return array_filter($payload, function ($value, $column) use ($insertableColumns) {
            if (!in_array($column, $insertableColumns, true)) {
                return false;
            }

            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function fitString(string $column, string $value, array $stringLengths): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $maxLength = $stringLengths[$column] ?? null;

        if (!is_int($maxLength) || $maxLength <= 0) {
            return $value;
        }

        return Str::of($value)->substr(0, $maxLength)->toString();
    }
}
