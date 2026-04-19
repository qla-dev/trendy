<?php

namespace App\Http\Controllers;

use App\Models\WorkOrderOrderItemLink;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class WorkOrderOrderItemLinkController extends OrderItemController
{
    private ?array $workOrderColumnsCache = null;

    public function ordersLinkageWorkOrders(Request $request)
    {
        if (!$this->canAccessOrderLinkage($request->user())) {
            return $this->orderLinkageForbiddenHtmlResponse();
        }

        $validator = Validator::make($request->all(), [
            'order_number' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response(
                '<div class="alert alert-danger mb-0">' . e('Broj narudžbe je obavezan.') . '</div>',
                422
            );
        }

        $orderNumber = trim((string) ($validator->validated()['order_number'] ?? ''));
        $normalizedOrderNumber = $this->normalizeComparableIdentifier($orderNumber);

        if ($normalizedOrderNumber === '') {
            return response(
                '<div class="alert alert-danger mb-0">' . e('Neispravan broj narudžbe.') . '</div>',
                422
            );
        }

        try {
            $links = $this->fetchMappedLinkRows($normalizedOrderNumber);
            $summary = $this->buildOrderSummary($normalizedOrderNumber, $orderNumber);
            $summary['link_count'] = count($links);

            if (empty($links) && empty($summary['found'])) {
                return response(
                    '<div class="alert alert-warning mb-0">' . e('Narudžba nije pronađena.') . '</div>',
                    404
                );
            }

            return view('content.apps.orders.partials.order-work-orders-modal-content', [
                'orderSummary' => $summary,
                'links' => $links,
            ]);
        } catch (Throwable $exception) {
            Log::error('Order work-order item links modal failed.', [
                'connection' => config('database.default'),
                'links_table' => WorkOrderOrderItemLink::qualifiedSourceTableName(),
                'work_orders_table' => $this->qualifiedWorkOrderTableName(),
                'order_number' => $normalizedOrderNumber,
                'message' => $exception->getMessage(),
            ]);

            return response(
                '<div class="alert alert-danger mb-0">' . e('Greška pri učitavanju veza narudžbe.') . '</div>',
                500
            );
        }
    }

    protected function fetchMappedLinkRows(string $normalizedOrderNumber): array
    {
        $linkRows = $this->fetchRawLinkRows($normalizedOrderNumber);

        if (empty($linkRows)) {
            return [];
        }

        $orderItemsByKeyAndPosition = $this->indexOrderItemsByKeyAndPosition(
            $this->fetchRawOrderItemRows($normalizedOrderNumber)
        );
        $workOrderKeys = array_values(array_unique(array_filter(array_map(function (array $row) {
            return $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acKey'], ''));
        }, $linkRows))));
        $workOrdersByKey = $this->fetchWorkOrdersByKeys($workOrderKeys);

        return array_values(array_map(function (array $row) use ($orderItemsByKeyAndPosition, $workOrdersByKey) {
            $workOrderKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acKey'], ''));
            $orderKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acLnkKey'], ''));
            $position = (string) $this->valueTrimmed($row, ['anLnkNo'], '');
            $orderItem = (array) ($orderItemsByKeyAndPosition[$orderKey . '#' . $position] ?? []);
            $workOrder = (array) ($workOrdersByKey[$workOrderKey] ?? []);

            return $this->mapLinkRow($row, $orderItem, $workOrder);
        }, $linkRows));
    }

    protected function fetchRawLinkRows(string $normalizedOrderNumber): array
    {
        $columns = WorkOrderOrderItemLink::sourceColumns();

        if (empty($columns)) {
            return [];
        }

        $selectColumns = $this->existingColumns($columns, [
            'acKey',
            'acKeyView',
            'anNo',
            'acLnkKey',
            'acLnkKeyView',
            'anLnkNo',
            'acType',
            'acTypeA',
            'acTypeB',
            'anFieldNA',
            'anFieldNB',
            'adDate',
            'acValue',
            'adTimeIns',
        ]);
        $query = WorkOrderOrderItemLink::newSourceQuery();
        $found = $this->applyOrderNumberFilter(
            $query,
            $columns,
            $normalizedOrderNumber,
            ['acLnkKey'],
            ['acLnkKeyView']
        );

        if (!$found) {
            return [];
        }

        foreach (['adTimeIns', 'adDate', 'acKey', 'anNo'] as $orderByColumn) {
            if (in_array($orderByColumn, $columns, true)) {
                $query->orderByDesc($orderByColumn);
            }
        }

        return (empty($selectColumns) ? $query->get() : $query->get($selectColumns))
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();
    }

    protected function indexOrderItemsByKeyAndPosition(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $orderKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acKey'], ''));
            $position = (string) $this->valueTrimmed($row, ['anNo', 'anLineNo', 'anItemNo', 'anPosition', 'anPos', 'anPosNo'], '');

            if ($orderKey === '' || $position === '') {
                continue;
            }

            $indexed[$orderKey . '#' . $position] = $row;
        }

        return $indexed;
    }

    protected function fetchWorkOrdersByKeys(array $normalizedWorkOrderKeys): array
    {
        $normalizedWorkOrderKeys = array_values(array_unique(array_filter($normalizedWorkOrderKeys, function ($key) {
            return trim((string) $key) !== '';
        })));

        if (empty($normalizedWorkOrderKeys)) {
            return [];
        }

        $columns = $this->workOrderColumns();
        $keyColumn = $this->firstExistingColumn($columns, ['acKey']);

        if ($keyColumn === null) {
            return [];
        }

        $selectColumns = $this->existingColumns($columns, [
            'acKey',
            'acKeyView',
            'acRefNo1',
            'anNo',
            'acStatusMF',
            'acStatus',
            'status',
            'adDate',
            'adTimeIns',
            'adSchedStartTime',
            'adSchedEndTime',
            'adStartTime',
            'adEndTime',
            'adDeliveryDeadline',
            'adDateOut',
            'anPlanQty',
            'anQty',
            'anQty1',
            'anProducedQty',
            'acUM',
            'acIdent',
            'acName',
            'acDescr',
        ]);
        $query = $this->newWorkOrderTableQuery();
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

    protected function mapLinkRow(array $linkRow, array $orderItem, array $workOrder): array
    {
        $position = (string) $this->valueTrimmed($linkRow, ['anLnkNo', 'anNo'], '');
        $ident = (string) $this->valueTrimmed($orderItem, ['acIdent'], $this->valueTrimmed($workOrder, ['acIdent'], ''));
        $orderedQty = $this->toFloatOrNull($this->value(
            $orderItem,
            ['anQty', 'anQty1', 'anPlanQty'],
            $this->value($workOrder, ['anPlanQty', 'anQty', 'anQty1'], null)
        ));
        $producedQty = $this->toFloatOrNull($this->value($workOrder, ['anProducedQty'], null));

        if ($producedQty === null && $orderedQty !== null) {
            $producedQty = 0.0;
        }

        $remainingQty = null;
        if ($orderedQty !== null) {
            $remainingQty = max($orderedQty - (float) ($producedQty ?? 0.0), 0.0);
        }

        $document = (string) $this->valueTrimmed($linkRow, ['acKey'], '');

        if ($document === '') {
            $document = (string) $this->valueTrimmed($workOrder, ['acKey', 'acRefNo1', 'acKeyView'], '');
        }

        if ($document === '') {
            $document = (string) $this->valueTrimmed($linkRow, ['acKeyView'], '-');
        }

        $document = $this->formatWorkOrderDocumentNumber($document);
        $status = (string) $this->valueTrimmed($workOrder, ['acStatusMF', 'acStatus', 'status'], '-');
        $scheduledStart = $this->value($workOrder, ['adSchedStartTime', 'adStartTime'], null);
        $scheduledEnd = $this->value(
            $workOrder,
            ['adSchedEndTime', 'adEndTime', 'adDeliveryDeadline', 'adDateOut'],
            $scheduledStart
        );
        $article = trim($ident);

        return [
            'dokument' => $document,
            'datum' => $this->displayDate($this->normalizeDate($this->value($workOrder, ['adDate', 'adTimeIns'], $this->value($linkRow, ['adDate', 'adTimeIns'], null)))),
            'pozicija' => $position,
            'artikal' => $article !== '' ? $article : '-',
            'sifra' => $ident,
            'naziv' => (string) $this->valueTrimmed($orderItem, ['acName', 'acDescr'], $this->valueTrimmed($workOrder, ['acName', 'acDescr'], '')),
            'neizradjeno' => $remainingQty,
            'izradjeno' => $producedQty,
            'naruceno' => $orderedQty,
            'poc_ter' => $this->displayDateTime($scheduledStart),
            'rok_izr' => $this->displayDateTime($scheduledEnd),
            'status' => $status,
            'tip' => (string) $this->valueTrimmed($linkRow, ['acType', 'acTypeA', 'acTypeB'], ''),
        ];
    }

    protected function workOrderColumns(): array
    {
        if ($this->workOrderColumnsCache !== null) {
            return $this->workOrderColumnsCache;
        }

        $this->workOrderColumnsCache = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->workOrderSchema())
            ->where('TABLE_NAME', $this->workOrderTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();

        return $this->workOrderColumnsCache;
    }

    protected function newWorkOrderTableQuery(): Builder
    {
        return DB::table($this->qualifiedWorkOrderTableName());
    }

    protected function qualifiedWorkOrderTableName(): string
    {
        return $this->workOrderSchema() . '.' . $this->workOrderTableName();
    }

    protected function workOrderSchema(): string
    {
        return (string) config('workorders.schema', 'dbo');
    }

    protected function workOrderTableName(): string
    {
        return (string) config('workorders.table', 'tHF_WOEx');
    }
}
