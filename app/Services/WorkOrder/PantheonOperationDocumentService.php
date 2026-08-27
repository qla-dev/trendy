<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class PantheonOperationDocumentService
{
    public function __construct(
        private PantheonDocumentWriter $writer,
        private WorkOrderClosingCalculator $calculator
    ) {
    }

    public function create(
        ConnectionInterface $connection,
        array $number,
        array $receiptNumber,
        array $workOrder,
        array $operations,
        Carbon $now,
        int $userId,
        string $producedQuantity,
        array $headerContext
    ): array {
        $prepared = [];
        $total = '0';

        foreach ($operations as $operation) {
            $catalog = $connection->table('dbo.tHE_SetItem')
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$operation['code']])
                ->first(['anQId', 'acIdent', 'acName', 'acUM', 'anPrStPrice']);

            if ($catalog === null || (int) ($catalog->anQId ?? 0) < 1) {
                throw new RuntimeException('Pantheon operacija nije pronađena: ' . $operation['code']);
            }

            $price = $this->calculator->normalizeNonNegative($catalog->anPrStPrice ?? null, 'Cijena operacije');
            $calculation = $this->calculator->operation($operation['minutes_per_unit'], $price, $producedQuantity);
            $total = $this->calculator->add($total, $calculation['totalCost']);
            $prepared[] = array_merge($operation, $calculation, [
                'ident_qid' => (int) $catalog->anQId,
                'name' => trim((string) ($catalog->acName ?? $operation['code'])),
            ]);
        }

        $headerContext['total_value'] = $total;
        $moveQId = $this->writer->insertHeader($connection, $number, $workOrder, $headerContext, $now, $userId);
        $this->writer->linkWorkOrder($connection, $number, $moveQId, $workOrder['acKey'], 'P', $now, $userId);
        $dept = trim((string) ($headerContext['department'] ?? ''));
        $deptQId = (int) ($connection->table('dbo.tHE_Move')->where('acKey', $number['key'])->value('anDeptQId') ?? 1);

        $workEntryNo = 0;
        foreach ($prepared as $index => $operation) {
            $lineNo = $index + 1;
            $moveItemQId = $this->writer->insertItem($connection, [
                'acKey' => $number['key'],
                'anNo' => $lineNo,
                'acIdent' => $operation['code'],
                'acName' => $operation['name'],
                'anQty' => $operation['consumedMinutes'],
                'anQtyTemp' => $operation['consumedMinutes'],
                'acUM' => 'RDS',
                'anPrice' => $operation['pricePerMinute'],
                'anRebate' => 0,
                'acVATCode' => 'I0',
                'anVAT' => 0,
                'anStockPrice' => $operation['pricePerMinute'],
                'anPriceCurrency' => $operation['pricePerMinute'],
                'acLnkKey' => $receiptNumber['key'],
                'anLnkNo' => 0,
                'anPackQty' => $operation['consumedMinutes'],
                'acDept' => $dept,
                'acVATCodeTR' => 'P1',
                'anVATIn' => 17,
                'adTimeIns' => $now,
                'anUserIns' => $userId,
                'adTimeChg' => $now,
                'anUserChg' => $userId,
                'anWOPrice' => $operation['pricePerMinute'],
                'anPVForPay' => $operation['totalCost'],
                'anPVVATBase' => $operation['totalCost'],
                'anPVValue' => $operation['totalCost'],
                'anPVOCForPay' => $operation['totalCost'],
                'anPVOCVATBase' => $operation['totalCost'],
                'anPVOCValue' => $operation['totalCost'],
                'anQtyConverted' => $operation['consumedMinutes'],
                'acUMConverted' => 'MIN',
                'anMoveQId' => $moveQId,
                'anCostDrvQId' => 1,
                'anDeptQId' => $deptQId,
                'anIdentQId' => $operation['ident_qid'],
            ]);

            // A manually entered closing operation has no BOM item to link.
            if ((int) ($operation['item_qid'] ?? 0) > 0) {
                $this->writer->linkItem(
                    $connection,
                    $number,
                    $lineNo,
                    $moveItemQId,
                    (int) $operation['item_qid'],
                    $now,
                    $userId
                );
            }

            $workerEntries = $operation['worker_entries'] ?? [[
                'worker' => $operation['worker'] ?? [],
                'minutes_per_unit' => $operation['minutes_per_unit'],
                'downtime' => $operation['downtime'] ?? '0.000000',
                'start_time' => $operation['start_time'] ?? '',
                'end_time' => $operation['end_time'] ?? '',
            ]];

            foreach ($workerEntries as $workerEntry) {
                // Worker-detail records require a real tHF_WOExItem foreign key.
                // Manual closing rows intentionally have none, so their code,
                // duration and cost live on the 6600 document line only.
                if ((int) ($operation['item_qid'] ?? 0) < 1) {
                    continue;
                }

                $workEntryNo++;
                // The 6600 line carries total operation minutes and remains
                // quantity-scaled. On Pantheon's individual worker-time row,
                // anTn is the per-piece duration, while anTime is the
                // corresponding total duration for the closed quantity.
                $workerMinutesPerPiece = (string) ($workerEntry['minutes_per_unit'] ?? '0');
                $workerTotalMinutes = $this->calculator->multiply($workerMinutesPerPiece, $producedQuantity);

                $connection->table('dbo.tHF_WOExItemWork')->insert([
                    'acWorker' => (string) ($workerEntry['worker']['worker'] ?? ''),
                    'acIdent' => $operation['code'],
                    'anQty' => 0,
                    'anPlanQty' => 0,
                    'anTime' => $workerTotalMinutes,
                    'adDate' => $now->copy()->startOfDay(),
                    'adTimeChg' => $now,
                    'anUserChg' => $userId,
                    'adTimeIns' => $now,
                    'anUserIns' => $userId,
                    'anVariant' => 0,
                    'anScrapPcs' => 0,
                    // Pantheon's dedicated downtime column. Keep the gross
                    // interval in begin/end and persist the entered hold-up
                    // separately while document quantities use net minutes.
                    'anHoldUp' => (string) ($workerEntry['downtime'] ?? '0.000000'),
                    'acHoldUpType' => '0',
                    'adBeginTime' => $this->workTimeDate($now, $workerEntry['start_time'] ?? null),
                    'adEndTime' => $this->workTimeDate($now, $workerEntry['end_time'] ?? null),
                    'acET' => '',
                    'acWorkTimeType' => '3',
                    'anRatifyPcs' => 0,
                    'anRatifyTime' => 0,
                    'anSubNo' => $workEntryNo,
                    'acNote' => '',
                    'acParentWorker' => '',
                    'anTn' => $workerMinutesPerPiece,
                    'anTpf' => 0,
                    'anPrice' => 0,
                    'acLnkKey' => $number['key'],
                    'anLnkNo' => $lineNo,
                    'acResursID' => '',
                    'anMoveItemQId' => $moveItemQId,
                    'anWorkID' => $workEntryNo,
                    'anWOExItemQid' => (int) $operation['item_qid'],
                ]);
            }

            if ((int) ($operation['item_qid'] ?? 0) > 0) {
                $connection->table('dbo.tHF_WOExItem')
                    ->where('anQId', (int) $operation['item_qid'])
                    ->update(['acIssueFinished' => 'Y', 'adTimeChg' => $now, 'anUserChg' => $userId]);

                // Pantheon exposes the related Operacije branch through the
                // operation resource as well as through the document links.
                // Retain a BOM-planned duration when it exists, so Pantheon
                // can show a genuine partial-completion percentage.
                $resource = $connection->table('dbo.tHF_WOExItemResources')
                    ->where('anWOExItemQId', (int) $operation['item_qid'])
                    ->first(['anPlanQty']);
                $plannedMinutesPerUnit = is_numeric((string) ($resource->anPlanQty ?? null))
                    ? (float) $resource->anPlanQty
                    : 0.0;
                // Resource Tn belongs to this operation only. It is the
                // operation's combined worker minutes per produced piece,
                // not a sum of the other operations on the work order.
                $completedMinutesPerUnit = (float) $operation['minutesPerUnit'];
                $executionPercent = $plannedMinutesPerUnit > 0
                    ? min(100.0, ($completedMinutesPerUnit / $plannedMinutesPerUnit) * 100)
                    : 100.0;

                $connection->table('dbo.tHF_WOExItemResources')
                    ->where('anWOExItemQId', (int) $operation['item_qid'])
                    ->update([
                        'anQty' => $operation['minutesPerUnit'],
                        'anPlanQty' => $plannedMinutesPerUnit > 0
                            ? $plannedMinutesPerUnit
                            : $operation['minutesPerUnit'],
                        'anQty1' => $operation['minutesPerUnit'],
                        'acIssueFinished' => $executionPercent >= 100 ? 'Y' : 'N',
                        'anExecutionPerc' => $executionPercent,
                        'adTimeChg' => $now,
                        'anUserChg' => $userId,
                    ]);
            }
        }

        return [
            'document_key' => $number['key'],
            'document_number' => $number['number'],
            'document_type' => $number['type'],
            'work_order_code' => trim((string) ($workOrder['acKeyView'] ?? $workOrder['acKey'])),
            'item_code' => implode(', ', array_values(array_unique(array_column($prepared, 'code')))),
            'quantity' => $producedQuantity,
            'price_per_unit' => $this->calculator->divide($total, $producedQuantity),
            'operation_cost' => $total,
            'items' => $prepared,
        ];
    }

    private function workTimeDate(Carbon $date, mixed $time): Carbon
    {
        $time = trim((string) $time);
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $matches) !== 1) {
            return $date->copy()->startOfDay();
        }

        return $date->copy()->startOfDay()->setTime((int) $matches[1], (int) $matches[2]);
    }
}
