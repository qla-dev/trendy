<?php

namespace App\Services\OrderAi;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PantheonOrderTransferService
{
    public function previewFromNormalizedPayload(array $normalizedPayload, mixed $user = null): array
    {
        $prepared = $this->prepareTransferData($normalizedPayload);

        return $this->buildTransferPayload($prepared, $user);
    }

    public function createFromNormalizedPayload(array $normalizedPayload, mixed $user = null): array
    {
        try {
            return DB::connection('sqlsrv')->transaction(function () use ($normalizedPayload, $user) {
            $prepared = $this->prepareTransferData($normalizedPayload);
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
            throw new RuntimeException('Nije moguÄ‡e pronaÄ‡i template narudÅ¾be za Pantheon.');
        }

        if (empty($itemTemplate)) {
            throw new RuntimeException('Nije moguÄ‡e pronaÄ‡i template stavke narudÅ¾be za Pantheon.');
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
        ];
    }

    private function prepareTransferData(array $normalizedPayload, bool $strict = true): array
    {
        $order = is_array($normalizedPayload['order'] ?? null) ? $normalizedPayload['order'] : [];
        $rawItems = is_array($normalizedPayload['items'] ?? null) ? $normalizedPayload['items'] : [];
        $warnings = is_array($order['warnings'] ?? null) ? array_map('strval', $order['warnings']) : [];
        $preparedItems = [];

        foreach (array_values($rawItems) as $index => $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $productCode = trim((string) ($rawItem['product_code'] ?? ''));
            $productName = trim((string) ($rawItem['product_name'] ?? ''));
            $quantity = round(max(0, (float) ($rawItem['quantity'] ?? 0)), 6);

            if ($quantity <= 0) {
                continue;
            }

            if ($strict && $productCode === '') {
                throw new RuntimeException('Svaka stavka mora imati šifru artikla za transfer u Pantheon.');
            }

            if ($productCode === '' && $productName === '') {
                continue;
            }

            $unitPrice = round(max(0, (float) ($rawItem['unit_price'] ?? 0)), 4);
            $discount = round(max(0, (float) ($rawItem['discount_percent'] ?? 0)), 4);
            $vatRate = round(max(0, (float) ($rawItem['vat_rate'] ?? config('ai-order-scan.default_vat_rate', 17))), 4);
            $baseValue = round($quantity * $unitPrice * (1 - ($discount / 100)), 4);
            $vatValue = round($baseValue * ($vatRate / 100), 4);
            $grandTotal = round($baseValue + $vatValue, 4);

            $preparedItems[] = [
                'line_number' => (int) ($rawItem['line_number'] ?? ($index + 1)),
                'product_code' => $productCode,
                'product_name' => $productName,
                'quantity' => $quantity,
                'unit' => trim((string) ($rawItem['unit'] ?? config('ai-order-scan.default_unit', 'KO'))) ?: (string) config('ai-order-scan.default_unit', 'KO'),
                'unit_price' => $unitPrice,
                'vat_rate' => $vatRate,
                'vat_code' => trim((string) ($rawItem['vat_code'] ?? config('ai-order-scan.default_vat_code', 'P1'))) ?: (string) config('ai-order-scan.default_vat_code', 'P1'),
                'discount_percent' => $discount,
                'priority' => trim((string) ($rawItem['priority'] ?? '')),
                'note' => trim((string) ($rawItem['note'] ?? '')),
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

        if ($strict && $customerName === '') {
            throw new RuntimeException('Naziv kupca je obavezan za kreiranje narudžbe.');
        }

        return [
            'customer_name' => $customerName,
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

        $payload['acKey'] = $this->fitString('acKey', $numberContext['raw_key'], $stringLengths);
        $payload['acKeyView'] = $this->fitString('acKeyView', $numberContext['display_key'], $stringLengths);
        $payload['acDocType'] = $this->fitString('acDocType', $numberContext['doc_type'], $stringLengths);
        $payload['acRefNo1'] = $this->fitString('acRefNo1', (string) config('ai-order-scan.default_ref_no', '99'), $stringLengths);
        $payload['adDate'] = $now->copy()->startOfDay();
        $payload['adDeliveryDeadline'] = $deliveryDeadline->copy()->startOfDay();
        $payload['adDateValid'] = $now->copy()->addDays($validDays)->startOfDay();
        $payload['anDaysForValid'] = $validDays;
        $payload['acStatus'] = '1';
        $payload['acConsignee'] = $this->fitString('acConsignee', $prepared['customer_name'], $stringLengths);
        $payload['acReceiver'] = $this->fitString('acReceiver', $prepared['receiver_name'], $stringLengths);
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

        foreach (['anConsigneeQId', 'anReceiverQId', 'anBuyerCostCenterIdDef', 'anBuyerIdDef'] as $zeroColumn) {
            if (in_array($zeroColumn, $columns, true)) {
                $payload[$zeroColumn] = 0;
            }
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

        $resolvedUnit = $this->resolvePantheonItemUnit($item, $template, $stringLengths);

        $payload['acKey'] = $this->fitString('acKey', (string) ($headerPayload['acKey'] ?? ''), $stringLengths);
        $payload['anNo'] = $lineNumber;
        $payload['acIdent'] = $this->fitString('acIdent', $item['product_code'], $stringLengths);
        $payload['acName'] = $this->fitString('acName', $item['product_name'], $stringLengths);
        $payload['anQty'] = $item['quantity'];
        $payload['anQtyDispDoc'] = 0;
        $payload['acUM'] = $this->fitString('acUM', $resolvedUnit, $stringLengths);
        $payload['anPrice'] = $item['unit_price'];
        $payload['anRebate'] = $item['discount_percent'];
        $payload['acVATCode'] = $this->fitString('acVATCode', $item['vat_code'], $stringLengths);
        $payload['anVAT'] = $item['vat_rate'];
        $payload['acLnkKey'] = '';
        $payload['anLnkNo'] = 0;
        $payload['acNote'] = $this->fitString('acNote', $item['note'], $stringLengths);
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
        $payload['adDeliveryDeadline'] = $headerPayload['adDeliveryDeadline'] ?? null;
        $payload['adDeliveryDate'] = $headerPayload['adDeliveryDeadline'] ?? null;

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
        $productUnit = $this->resolveRecentProductUnit($productCode, $stringLengths);
        $templateUnit = $this->normalizeUnitCode((string) ($template['acUM'] ?? ($template['acUMConverted'] ?? '')), $stringLengths);
        $fallbackUnit = $this->normalizeUnitCode((string) config('ai-order-scan.default_unit', 'KO'), $stringLengths);

        if ($sourceUnit !== '' && $this->isValidPantheonUnit($sourceUnit)) {
            return $sourceUnit;
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
