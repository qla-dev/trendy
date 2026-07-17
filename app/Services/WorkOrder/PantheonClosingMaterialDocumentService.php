<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * Creates the document-only 6400 material release used by work-order closing.
 * Stock posting remains outside this service; this mirrors the closing modal's
 * existing document-only material behavior while supplying Pantheon links.
 */
class PantheonClosingMaterialDocumentService
{
    public function __construct(private PantheonDocumentWriter $writer)
    {
    }

    public function create(
        ConnectionInterface $connection,
        array $number,
        array $receiptNumber,
        array $workOrder,
        array $materials,
        Carbon $now,
        int $userId,
        array $headerContext
    ): array {
        $total = '0';
        foreach ($materials as $material) {
            $total = bcadd($total, (string) ($material['total'] ?? '0'), WorkOrderClosingCalculator::SCALE);
        }

        $headerContext['total_value'] = $total;
        $moveQId = $this->writer->insertHeader($connection, $number, $workOrder, $headerContext, $now, $userId);
        $this->writer->linkWorkOrder($connection, $number, $moveQId, (string) $workOrder['acKey'], 'P', $now, $userId);
        $dept = trim((string) ($headerContext['department'] ?? ''));
        $deptQId = (int) ($connection->table('dbo.tHE_Move')->where('acKey', $number['key'])->value('anDeptQId') ?? 1);

        foreach ($materials as $index => $material) {
            $lineNo = $index + 1;
            $quantity = (string) $material['quantity'];
            $price = (string) $material['price'];
            $lineTotal = (string) $material['total'];

            $moveItemQId = $this->writer->insertItem($connection, [
                'acKey' => $number['key'],
                'anNo' => $lineNo,
                'acIdent' => $material['code'],
                'acName' => $material['name'],
                'anQty' => $quantity,
                'anQtyTemp' => $quantity,
                'acUM' => $material['unit'],
                'anPrice' => $price,
                'anRebate' => 0,
                'acVATCode' => 'I0',
                'anVAT' => 0,
                'anStockPrice' => $price,
                'anPriceCurrency' => $price,
                'acLnkKey' => $receiptNumber['key'],
                'anLnkNo' => 0,
                'anPackQty' => $quantity,
                'acDept' => $dept,
                'acVATCodeTR' => 'P1',
                'anVATIn' => 17,
                'adTimeIns' => $now,
                'anUserIns' => $userId,
                'adTimeChg' => $now,
                'anUserChg' => $userId,
                'anWOPrice' => $price,
                'anPVForPay' => $lineTotal,
                'anPVVATBase' => $lineTotal,
                'anPVValue' => $lineTotal,
                'anPVOCForPay' => $lineTotal,
                'anPVOCVATBase' => $lineTotal,
                'anPVOCValue' => $lineTotal,
                'anQtyConverted' => $quantity,
                'acUMConverted' => $material['unit'],
                'anMoveQId' => $moveQId,
                'anCostDrvQId' => 1,
                'anDeptQId' => $deptQId,
                'anIdentQId' => $material['ident_qid'],
            ]);

            if ((int) ($material['item_qid'] ?? 0) > 0) {
                $this->writer->linkItem(
                    $connection,
                    $number,
                    $lineNo,
                    $moveItemQId,
                    (int) $material['item_qid'],
                    $now,
                    $userId,
                    'A '
                );

                $connection->table('dbo.tHF_WOExItem')
                    ->where('anQId', (int) $material['item_qid'])
                    ->update([
                        'acIssueFinished' => 'Y',
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
            'item_code' => implode(', ', array_column($materials, 'code')),
            'total_price' => $total,
            'items' => $materials,
        ];
    }
}
