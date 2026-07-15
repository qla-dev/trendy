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

            $this->writer->linkItem(
                $connection,
                $number,
                $lineNo,
                $moveItemQId,
                (int) $operation['item_qid'],
                $now,
                $userId
            );

            $workerEntries = $operation['worker_entries'] ?? [[
                'worker' => $operation['worker'] ?? [],
                'minutes_per_unit' => $operation['minutes_per_unit'],
                'start_time' => $operation['start_time'] ?? '',
                'end_time' => $operation['end_time'] ?? '',
            ]];

            foreach ($workerEntries as $workerEntry) {
                $workEntryNo++;
                $workerMinutes = $this->calculator->operation(
                    (string) ($workerEntry['minutes_per_unit'] ?? '0'),
                    $operation['pricePerMinute'],
                    $producedQuantity
                )['consumedMinutes'];

                $connection->table('dbo.tHF_WOExItemWork')->insert([
                    'acWorker' => (string) ($workerEntry['worker']['worker'] ?? ''),
                    'acIdent' => $operation['code'],
                    'anQty' => 0,
                    'anPlanQty' => 0,
                    'anTime' => $workerMinutes,
                    'adDate' => $now->copy()->startOfDay(),
                    'adTimeChg' => $now,
                    'anUserChg' => $userId,
                    'adTimeIns' => $now,
                    'anUserIns' => $userId,
                    'anVariant' => 0,
                    'anScrapPcs' => 0,
                    'anHoldUp' => 0,
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
                    'anTn' => (string) ($workerEntry['minutes_per_unit'] ?? '0'),
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

            $connection->table('dbo.tHF_WOExItem')
                ->where('anQId', (int) $operation['item_qid'])
                ->update(['acIssueFinished' => 'Y', 'adTimeChg' => $now, 'anUserChg' => $userId]);
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
