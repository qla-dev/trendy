<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WorkOrderOrderItemLink;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class OrderItemController extends OrderItemMoveLinkController
{
    private ?array $positionTransferWorkOrderOrderItemLinkColumnsCache = null;
    private ?array $positionTransferWorkOrderColumnsCache = null;

    public function ordersLinkagePositions(Request $request)
    {
        if (!$this->canAccessOrderLinkage($request->user())) {
            return $this->orderLinkageForbiddenHtmlResponse();
        }

        $validator = Validator::make($request->all(), [
            'order_number' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response(
                '<div class="alert alert-danger mb-0">' . e('Broj narudzbe je obavezan.') . '</div>',
                422
            );
        }

        $orderNumber = trim((string) ($validator->validated()['order_number'] ?? ''));
        $normalizedOrderNumber = $this->normalizeComparableIdentifier($orderNumber);

        if ($normalizedOrderNumber === '') {
            return response(
                '<div class="alert alert-danger mb-0">' . e('Neispravan broj narudzbe.') . '</div>',
                422
            );
        }

        try {
            $items = $this->fetchOrderItemRows($normalizedOrderNumber);
            $items = $this->addTransferStatusToOrderItems($items, $normalizedOrderNumber);
            $summary = $this->buildOrderSummary($normalizedOrderNumber, $orderNumber);
            $summary['position_count'] = count($items);

            if (empty($items) && empty($summary['found'])) {
                return response(
                    '<div class="alert alert-warning mb-0">' . e('Narudzba nije pronadjena.') . '</div>',
                    404
                );
            }

            return view('content.apps.orders.partials.order-positions-modal-content', [
                'orderSummary' => $summary,
                'items' => $items,
            ]);
        } catch (Throwable $exception) {
            Log::error('Order item positions modal failed.', [
                'connection' => config('database.default'),
                'orders_table' => Order::qualifiedSourceTableName(),
                'order_items_table' => OrderItem::qualifiedSourceTableName(),
                'order_number' => $normalizedOrderNumber,
                'message' => $exception->getMessage(),
            ]);

            return response(
                '<div class="alert alert-danger mb-0">' . e('Greska pri ucitavanju pozicija narudzbe.') . '</div>',
                500
            );
        }
    }

    protected function buildOrderSummary(string $normalizedOrderNumber, string $requestedOrderNumber = ''): array
    {
        $columns = Order::sourceColumns();
        $selectColumns = $this->existingColumns($columns, [
            'acKey',
            'acKeyView',
            'acRefNo1',
            'acConsignee',
            'acReceiver',
            'acPartner',
            'adDate',
            'adDateIns',
            'adDeliveryDeadline',
            'adDateValid',
            'adDateOut',
            'adDateDoc',
        ]);
        $query = Order::newSourceQuery();
        $found = $this->applyOrderNumberFilter(
            $query,
            $columns,
            $normalizedOrderNumber,
            ['acKey'],
            ['acKeyView', 'acRefNo1']
        );

        $row = $found
            ? (array) (empty($selectColumns) ? $query->first() : $query->first($selectColumns))
            : [];

        $displayNumber = $this->valueTrimmed($row, ['acKeyView', 'acRefNo1', 'acKey'], '');
        if ($displayNumber === '') {
            $displayNumber = $requestedOrderNumber !== ''
                ? $requestedOrderNumber
                : $this->formatDisplayOrderNumber($normalizedOrderNumber);
        }

        return [
            'found' => !empty($row),
            'order_number' => (string) $displayNumber,
            'narudzba' => (string) $displayNumber,
            'customer' => (string) $this->valueTrimmed($row, ['acConsignee', 'acReceiver', 'acPartner'], ''),
            'narucitelj' => (string) $this->valueTrimmed($row, ['acConsignee', 'acReceiver', 'acPartner'], ''),
            'date' => $this->displayDate($this->normalizeDate($this->value($row, ['adDate', 'adDateIns'], null))),
            'due_date' => $this->displayDate($this->normalizeDate($this->value($row, ['adDeliveryDeadline', 'adDateValid', 'adDateOut', 'adDateDoc'], null))),
        ];
    }

    protected function fetchOrderItemRows(string $normalizedOrderNumber): array
    {
        return array_values(array_map(function (array $row) {
            return $this->mapOrderItemRow($row);
        }, $this->fetchRawOrderItemRows($normalizedOrderNumber)));
    }

    protected function fetchRawOrderItemRows(string $normalizedOrderNumber): array
    {
        $columns = OrderItem::sourceColumns();

        if (empty($columns)) {
            return [];
        }

        $selectColumns = $this->existingColumns($columns, [
            'acKey',
            'anNo',
            'acIdent',
            'acName',
            'acDescr',
            'anQty',
            'anQtyDispDoc',
            'anQty1',
            'anPlanQty',
            'acUM',
            'anPrice',
            'anRebate',
            'acVATCode',
            'anVAT',
            'acLnkKey',
            'anLnkNo',
            'acNote',
            'anSalePrice',
            'anPackQty',
            'adDeliveryDeadline',
            'adDeliveryDate',
            'adDateOut',
            'adDateValid',
            'acDept',
            'acCostDrv',
            'anVariant',
            'anDimVolume',
            'anDimWeight',
            'anDimWeightBrutto',
            'anRebate1',
            'anRebate2',
            'anRebate3',
            'anPVValue',
            'anPVDiscount',
            'anPVVATBase',
            'anPVVAT',
            'anPVForPay',
            'anRTPrice',
            'anReserved',
            'anQId',
            'anQtyConverted',
            'acUMConverted',
            'acPackNum',
        ]);
        $query = OrderItem::newSourceQuery();
        $found = $this->applyOrderNumberFilter(
            $query,
            $columns,
            $normalizedOrderNumber,
            ['acKey', 'acLnkKey', 'acOrderKey', 'order_key'],
            ['acKeyView', 'acRefNo1', 'acOrderNo', 'order_number']
        );

        if (!$found) {
            return [];
        }

        foreach (['anNo', 'anLineNo', 'anItemNo'] as $orderByColumn) {
            if (in_array($orderByColumn, $columns, true)) {
                $query->orderBy($orderByColumn);
            }
        }

        return (empty($selectColumns) ? $query->get() : $query->get($selectColumns))
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();
    }

    protected function mapOrderItemRow(array $row): array
    {
        $quantity = $this->toFloatOrNull($this->value($row, ['anQty', 'anQty1', 'anPlanQty'], null));
        $dimensionMultiplier = $quantity ?? $this->toFloatOrNull($this->value($row, ['anQtyConverted'], null));
        $unitNetWeight = $this->toFloatOrNull($this->value($row, ['anDimWeight'], null));
        $unitGrossWeight = $this->toFloatOrNull($this->value($row, ['anDimWeightBrutto'], null));
        $unitVolume = $this->toFloatOrNull($this->value($row, ['anDimVolume'], null));
        $deliveryDate = $this->normalizeDate($this->value(
            $row,
            ['adDeliveryDeadline', 'adDeliveryDate', 'adDateOut', 'adDateValid'],
            null
        ));

        return [
            'order_key' => (string) $this->valueTrimmed($row, ['acKey'], ''),
            'pozicija' => (string) $this->valueTrimmed($row, ['anNo', 'anLineNo', 'anItemNo', 'anPosition', 'anPos', 'anPosNo'], ''),
            'sifra' => (string) $this->valueTrimmed($row, ['acIdent'], ''),
            'artikal' => (string) $this->valueTrimmed($row, ['acIdent'], ''),
            'alt' => $this->normalizeNullableNumber($this->value($row, ['anVariant'], null)),
            'naziv' => (string) $this->valueTrimmed($row, ['acName', 'acDescr'], ''),
            'opis' => (string) $this->valueTrimmed($row, ['acName', 'acDescr'], ''),
            'jm' => (string) $this->valueTrimmed($row, ['acUM'], ''),
            'kolicina' => $quantity,
            'cijena' => $this->normalizeNullableNumber($this->value($row, ['anPrice'], null)),
            'r1' => $this->normalizeNullableNumber($this->value($row, ['anRebate1'], null)),
            'r2' => $this->normalizeNullableNumber($this->value($row, ['anRebate2'], null)),
            'sr' => $this->normalizeNullableNumber($this->value($row, ['anRebate3'], null)),
            'popust' => $this->normalizeNullableNumber($this->value($row, ['anRebate'], null)),
            'vrijednost' => $this->normalizeNullableNumber($this->value($row, ['anPVValue'], null)),
            'pdv' => (string) $this->valueTrimmed($row, ['acVATCode'], ''),
            'pdv_stopa' => $this->normalizeNullableNumber($this->value($row, ['anVAT'], null)),
            'za_platiti' => $this->normalizeNullableNumber($this->value($row, ['anPVForPay'], null)),
            'otpremljeno' => $this->normalizeNullableNumber($this->value($row, ['anQtyDispDoc'], null)),
            'paketa' => $this->normalizeNullableNumber($this->value($row, ['anPackQty'], null)),
            'neto_tezina' => $this->multiplyDimension($unitNetWeight, $dimensionMultiplier),
            'bruto_tezina' => $this->multiplyDimension($unitGrossWeight, $dimensionMultiplier),
            'volumen' => $this->multiplyDimension($unitVolume, $dimensionMultiplier),
            'rok_otpreme' => $this->displayDate($deliveryDate),
            'odjel' => (string) $this->valueTrimmed($row, ['acDept'], ''),
            'nos_tr' => (string) $this->valueTrimmed($row, ['acCostDrv'], ''),
            'cijena_s_rabatom' => $this->normalizeNullableNumber($this->value($row, ['anRTPrice', 'anSalePrice', 'anPrice'], null)),
            'order_item_qid' => $this->normalizeNullableNumber($this->value($row, ['anQId'], null)),
        ];
    }

    protected function addTransferStatusToOrderItems(array $items, string $normalizedOrderNumber): array
    {
        if (empty($items)) {
            return $items;
        }

        $statuses = $this->fetchOrderItemTransferStatuses($normalizedOrderNumber);

        if (empty($statuses)) {
            return array_values(array_map(function (array $item) {
                $item['prenos_status'] = '';
                $item['prenos_status_tone'] = 'secondary';

                return $item;
            }, $items));
        }

        return array_values(array_map(function (array $item) use ($statuses) {
            $position = trim((string) ($item['pozicija'] ?? ''));
            $qid = trim((string) ($item['order_item_qid'] ?? ''));
            $status = [];

            if ($qid !== '' && isset($statuses['qid:' . $qid])) {
                $status = $statuses['qid:' . $qid];
            } elseif ($position !== '' && isset($statuses['position:' . $position])) {
                $status = $statuses['position:' . $position];
            }

            $item['prenos_status'] = (string) ($status['label'] ?? '');
            $item['prenos_status_tone'] = (string) ($status['tone'] ?? 'secondary');
            $item['prenos_document'] = (string) ($status['document'] ?? '');

            return $item;
        }, $items));
    }

    protected function fetchOrderItemTransferStatuses(string $normalizedOrderNumber): array
    {
        $linkColumns = $this->positionTransferWorkOrderOrderItemLinkColumns();

        if (empty($linkColumns)) {
            return [];
        }

        $selectColumns = $this->existingColumns($linkColumns, [
            'acKey',
            'acKeyView',
            'anNo',
            'acLnkKey',
            'acLnkKeyView',
            'anLnkNo',
            'acType',
            'acTypeA',
            'acTypeB',
            'acValue',
            'adDate',
            'adTimeIns',
            'anOrderItemQId',
        ]);
        $query = DB::table($this->qualifiedPositionTransferWorkOrderOrderItemLinkTableName());
        $found = $this->applyOrderNumberFilter(
            $query,
            $linkColumns,
            $normalizedOrderNumber,
            ['acLnkKey'],
            ['acLnkKeyView']
        );

        if (!$found) {
            return [];
        }

        foreach (['adTimeIns', 'adDate', 'acKey', 'anNo'] as $orderByColumn) {
            if (in_array($orderByColumn, $linkColumns, true)) {
                $query->orderByDesc($orderByColumn);
            }
        }

        $linkRows = (empty($selectColumns) ? $query->get() : $query->get($selectColumns))
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();

        if (empty($linkRows)) {
            return [];
        }

        $workOrderKeys = array_values(array_unique(array_filter(array_map(function (array $row) {
            return $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acKey'], ''));
        }, $linkRows))));
        $workOrdersByKey = $this->fetchPositionTransferWorkOrdersByKeys($workOrderKeys);
        $producedOrderItemQids = $this->fetchProducedMoveOrderItemQids(array_values(array_unique(array_filter(array_map(function (array $row) {
            return trim((string) $this->valueTrimmed($row, ['anOrderItemQId'], ''));
        }, $linkRows)))));
        $statuses = [];

        foreach ($linkRows as $row) {
            $workOrderKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acKey'], ''));
            $workOrder = (array) ($workOrdersByKey[$workOrderKey] ?? []);
            $position = trim((string) $this->valueTrimmed($row, ['anLnkNo'], ''));
            $qid = trim((string) $this->valueTrimmed($row, ['anOrderItemQId'], ''));
            $document = $this->transferStatusDocument($row, $workOrder);
            $hasProducedMove = $qid !== '' && isset($producedOrderItemQids[$qid]);
            $transferMessage = $this->transferStatusMessageForLink($workOrder, $hasProducedMove, $workOrderKey !== '');
            $rawStatus = (string) $this->valueTrimmed($workOrder, ['acStatusMF', 'acStatus', 'status'], '');
            $label = $this->formatTransferStatusLabel($document, $transferMessage, $rawStatus);
            $tone = $this->transferStatusTone($rawStatus, $this->transferHasProducedQuantity($workOrder) || $hasProducedMove);

            if ($label === '') {
                continue;
            }

            $mapped = [
                'label' => $label,
                'tone' => $tone,
                'document' => $document,
            ];

            if ($qid !== '') {
                $statuses['qid:' . $qid] = $mapped;
            }

            if ($position !== '' && !isset($statuses['position:' . $position])) {
                $statuses['position:' . $position] = $mapped;
            }
        }

        return $statuses;
    }

    protected function positionTransferWorkOrderOrderItemLinkColumns(): array
    {
        if ($this->positionTransferWorkOrderOrderItemLinkColumnsCache !== null) {
            return $this->positionTransferWorkOrderOrderItemLinkColumnsCache;
        }

        $this->positionTransferWorkOrderOrderItemLinkColumnsCache = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->positionTransferWorkOrderOrderItemLinkSchema())
            ->where('TABLE_NAME', $this->positionTransferWorkOrderOrderItemLinkTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();

        return $this->positionTransferWorkOrderOrderItemLinkColumnsCache;
    }

    protected function qualifiedPositionTransferWorkOrderOrderItemLinkTableName(): string
    {
        return $this->positionTransferWorkOrderOrderItemLinkSchema() . '.' . $this->positionTransferWorkOrderOrderItemLinkTableName();
    }

    protected function positionTransferWorkOrderOrderItemLinkSchema(): string
    {
        return (string) config('workorders.schema', 'dbo');
    }

    protected function positionTransferWorkOrderOrderItemLinkTableName(): string
    {
        // Use the base table so we always have anOrderItemQId for MoveItem linkage checks.
        return (string) config('workorders.work_order_order_item_link_insert_table', 'tHF_LinkWOExOrderItem');
    }

    protected function fetchPositionTransferWorkOrdersByKeys(array $normalizedWorkOrderKeys): array
    {
        $normalizedWorkOrderKeys = array_values(array_unique(array_filter($normalizedWorkOrderKeys, function ($key) {
            return trim((string) $key) !== '';
        })));

        if (empty($normalizedWorkOrderKeys)) {
            return [];
        }

        $columns = $this->positionTransferWorkOrderColumns();
        $keyColumn = $this->firstExistingColumn($columns, ['acKey']);

        if ($keyColumn === null) {
            return [];
        }

        $selectColumns = $this->existingColumns($columns, [
            'acKey',
            'acKeyView',
            'acRefNo1',
            'acDocType',
            'acDocTypeView',
            'acStatusMF',
            'acStatus',
            'status',
            'anProducedQty',
        ]);
        $query = DB::table($this->qualifiedPositionTransferWorkOrderTableName());
        $placeholders = implode(', ', array_fill(0, count($normalizedWorkOrderKeys), '?'));
        $query->whereRaw(
            $this->normalizedIdentifierExpression($query, $keyColumn) . " IN ($placeholders)",
            $normalizedWorkOrderKeys
        );

        return (empty($selectColumns) ? $query->get() : $query->get($selectColumns))
            ->map(function ($row) {
                return (array) $row;
            })
            ->keyBy(function (array $row) {
                return $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acKey'], ''));
            })
            ->all();
    }

    protected function positionTransferWorkOrderColumns(): array
    {
        if ($this->positionTransferWorkOrderColumnsCache !== null) {
            return $this->positionTransferWorkOrderColumnsCache;
        }

        $this->positionTransferWorkOrderColumnsCache = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->positionTransferWorkOrderSchema())
            ->where('TABLE_NAME', $this->positionTransferWorkOrderTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();

        return $this->positionTransferWorkOrderColumnsCache;
    }

    protected function qualifiedPositionTransferWorkOrderTableName(): string
    {
        return $this->positionTransferWorkOrderSchema() . '.' . $this->positionTransferWorkOrderTableName();
    }

    protected function positionTransferWorkOrderSchema(): string
    {
        return (string) config('workorders.schema', 'dbo');
    }

    protected function positionTransferWorkOrderTableName(): string
    {
        return (string) config('workorders.table', 'tHF_WOEx');
    }

    protected function transferStatusDocument(array $linkRow, array $workOrder): string
    {
        $document = (string) $this->valueTrimmed($workOrder, ['acKeyView', 'acKey'], '');

        if ($document === '') {
            $document = (string) $this->valueTrimmed($linkRow, ['acKeyView', 'acKey'], '');
        }

        // Some sources append extra tokens (e.g. "26-6000-0000011 6000"). Keep the first identifier.
        $document = trim((string) preg_replace('/\\s+/', ' ', $document));
        $spacePos = strpos($document, ' ');
        if ($spacePos !== false) {
            $document = substr($document, 0, $spacePos);
        }

        return trim($document);
    }

    protected function transferStatusMessageForLink(array $workOrder, bool $hasProducedMove, bool $hasWorkOrderLink): string
    {
        if ($this->transferHasProducedQuantity($workOrder) || $hasProducedMove) {
            return 'Nalog je već djelimično izrađen';
        }

        if ($hasWorkOrderLink || !empty($workOrder)) {
            return 'Promjena naloga';
        }

        return '';
    }

    protected function transferStatusMessage(array $workOrder, bool $hasProducedMove = false): string
    {
        return $this->transferStatusMessageForLink($workOrder, $hasProducedMove, !empty($workOrder));
    }

    protected function formatTransferStatusLabel(string $document, string $message, string $rawStatus): string
    {
        $document = trim($document);
        $message = trim($message);
        $rawStatus = trim($rawStatus);

        if ($document !== '' && $message !== '') {
            return $document . ' ' . $message;
        }

        if ($document !== '') {
            return $document;
        }

        return $rawStatus;
    }

    protected function transferHasProducedQuantity(array $workOrder): bool
    {
        $producedQty = $this->toFloatOrNull($this->value($workOrder, ['anProducedQty'], null));

        return $producedQty !== null && $producedQty > 0;
    }

    protected function transferStatusTone(string $rawStatus, bool $hasProduced = false): string
    {
        if ($hasProduced) {
            return 'danger';
        }

        switch (strtoupper(trim($rawStatus))) {
            case 'O':
            case 'N':
                return 'success';
            case 'P':
            case 'R':
            case 'L':
                return 'warning';
            case 'Z':
            case 'C':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    protected function applyOrderNumberFilter(
        Builder $query,
        array $columns,
        string $normalizedOrderNumber,
        array $keyColumns,
        array $displayColumns
    ): bool {
        $candidates = $this->orderNumberCandidates($normalizedOrderNumber);
        $keyColumns = $this->existingColumns($columns, $keyColumns);
        $displayColumns = $this->existingColumns($columns, $displayColumns);

        if (empty($candidates) || (empty($keyColumns) && empty($displayColumns))) {
            return false;
        }

        $query->where(function (Builder $filterQuery) use ($keyColumns, $displayColumns, $candidates) {
            $hasCondition = false;
            $placeholders = implode(', ', array_fill(0, count($candidates), '?'));

            foreach (array_merge($keyColumns, $displayColumns) as $column) {
                $normalizedExpression = $this->normalizedIdentifierExpression($filterQuery, $column);
                $method = $hasCondition ? 'orWhereRaw' : 'whereRaw';
                $filterQuery->{$method}("$normalizedExpression IN ($placeholders)", $candidates);
                $hasCondition = true;
            }
        });

        return true;
    }

    protected function orderNumberCandidates(string $normalizedOrderNumber): array
    {
        $candidates = [$normalizedOrderNumber];

        if (preg_match('/^\d{12}$/', $normalizedOrderNumber) === 1) {
            $candidates[] = substr($normalizedOrderNumber, 0, 6) . '0' . substr($normalizedOrderNumber, 6);
        }

        if (preg_match('/^\d{13}$/', $normalizedOrderNumber) === 1 && substr($normalizedOrderNumber, 6, 1) === '0') {
            $candidates[] = substr($normalizedOrderNumber, 0, 6) . substr($normalizedOrderNumber, 7);
        }

        return array_values(array_unique(array_filter($candidates, function ($candidate) {
            return trim((string) $candidate) !== '';
        })));
    }

    protected function formatDisplayOrderNumber(string $normalizedOrderNumber): string
    {
        $digits = preg_replace('/\D+/', '', $normalizedOrderNumber);

        if (!is_string($digits) || $digits === '') {
            return $normalizedOrderNumber;
        }

        if (strlen($digits) === 13 && substr($digits, 6, 1) === '0') {
            $digits = substr($digits, 0, 6) . substr($digits, 7);
        }

        if (strlen($digits) === 12) {
            return substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6);
        }

        return $normalizedOrderNumber;
    }

    protected function normalizedIdentifierExpression(Builder $query, string $columnIdentifier): string
    {
        $wrappedColumn = $query->getGrammar()->wrap($columnIdentifier);

        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(CAST($wrappedColumn AS NVARCHAR(128)), ''), '-', ''), ' ', ''), '/', ''), '.', ''), '_', ''))";
    }

    protected function existingColumns(array $columns, array $candidates): array
    {
        return array_values(array_filter($candidates, function ($candidate) use ($columns) {
            return in_array($candidate, $columns, true);
        }));
    }

    protected function firstExistingColumn(array $columns, array $candidates): ?string
    {
        $existing = $this->existingColumns($columns, $candidates);

        return $existing[0] ?? null;
    }

    protected function value(array $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    protected function valueTrimmed(array $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }

    protected function normalizeComparableIdentifier(string $value): string
    {
        $normalized = preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value)));

        return is_string($normalized) ? $normalized : '';
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->format('Y-m-d');
            }

            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable $exception) {
            return null;
        }
    }

    protected function displayDate(?string $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d.m.Y');
        } catch (Throwable $exception) {
            return '';
        }
    }

    protected function displayDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $dateTime = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);

            return $dateTime->format('d.m.Y H:i');
        } catch (Throwable $exception) {
            return '';
        }
    }

    protected function normalizeNumber(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        return $value ?? 0;
    }

    protected function normalizeNullableNumber(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeNumber($value);
    }

    protected function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    protected function multiplyDimension(?float $unitValue, ?float $quantity): ?float
    {
        if ($unitValue === null) {
            return null;
        }

        return $quantity !== null ? $unitValue * $quantity : $unitValue;
    }

    protected function canAccessOrderLinkage(mixed $user = null): bool
    {
        return $this->canDeleteWorkOrders($user);
    }

    protected function canDeleteWorkOrders(mixed $user = null): bool
    {
        if (!is_object($user)) {
            return false;
        }

        if (method_exists($user, 'isAdmin')) {
            return (bool) $user->isAdmin();
        }

        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }

    protected function orderLinkageForbiddenHtmlResponse()
    {
        return response(
            '<div class="alert alert-danger mb-0">' . e('Nemate dozvolu za pristup upravljanju narudzbama.') . '</div>',
            403
        );
    }
}
