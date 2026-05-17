<?php

namespace App\Services\OrderAi;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PantheonOrderTransferService
{
    public function createFromNormalizedPayload(array $normalizedPayload, mixed $user = null): array
    {
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

            return [
                'payload' => $prepared,
                'pantheon_order_key' => (string) ($headerPayload['acKey'] ?? ''),
                'pantheon_order_view' => (string) ($headerPayload['acKeyView'] ?? ''),
                'pantheon_order_qid' => $headerPayload['anQId'] ?? null,
                'item_count' => count($itemPayloads),
                'header_payload' => $headerPayload,
                'item_payloads' => $itemPayloads,
            ];
        }, 3);
    }

    public function isTransferReady(array $normalizedPayload): bool
    {
        $prepared = $this->prepareTransferData($normalizedPayload, false);

        if (trim((string) ($prepared['customer_name'] ?? '')) === '') {
            return false;
        }

        return !empty($prepared['items']);
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
                'unit' => trim((string) ($rawItem['unit'] ?? 'KO')) ?: 'KO',
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
            'document_type' => trim((string) ($order['document_type'] ?? config('ai-order-scan.default_doc_type', '0200'))) ?: (string) config('ai-order-scan.default_doc_type', '0200'),
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

        $payload['acKey'] = $this->fitString('acKey', (string) ($headerPayload['acKey'] ?? ''), $stringLengths);
        $payload['anNo'] = $lineNumber;
        $payload['acIdent'] = $this->fitString('acIdent', $item['product_code'], $stringLengths);
        $payload['acName'] = $this->fitString('acName', $item['product_name'], $stringLengths);
        $payload['anQty'] = $item['quantity'];
        $payload['anQtyDispDoc'] = 0;
        $payload['acUM'] = $this->fitString('acUM', $item['unit'], $stringLengths);
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
        $payload['acUMConverted'] = $this->fitString('acUMConverted', $item['unit'], $stringLengths);
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
