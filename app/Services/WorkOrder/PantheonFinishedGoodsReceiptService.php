<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class PantheonFinishedGoodsReceiptService
{
    public function __construct(
        private PantheonDocumentWriter $writer,
        private PantheonFinishedGoodsStockService $stock
    )
    {
    }

    public function create(
        ConnectionInterface $connection,
        array $number,
        array $workOrder,
        array $calculation,
        Carbon $now,
        int $userId,
        string $producedQuantity,
        array $headerContext
    ): array {
        $catalog = $connection->table('dbo.tHE_SetItem')
            ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [trim((string) $workOrder['acIdent'])])
            ->first(['anQId', 'acIdent', 'acName', 'acUM']);

        if ($catalog === null || (int) ($catalog->anQId ?? 0) < 1) {
            throw new RuntimeException('Gotovi proizvod nije pronađen u Pantheon šifrantu.');
        }

        $headerContext['total_value'] = $calculation['totalPrice'];
        $moveQId = $this->writer->insertHeader($connection, $number, $workOrder, $headerContext, $now, $userId);
        $this->writer->linkWorkOrder($connection, $number, $moveQId, $workOrder['acKey'], 'M', $now, $userId);
        $dept = trim((string) ($headerContext['department'] ?? ''));
        $deptQId = (int) ($connection->table('dbo.tHE_Move')->where('acKey', $number['key'])->value('anDeptQId') ?? 1);

        $this->writer->insertItem($connection, [
            'acKey' => $number['key'],
            'anNo' => 1,
            'acIdent' => trim((string) $catalog->acIdent),
            'acName' => trim((string) ($catalog->acName ?? $catalog->acIdent)),
            'anQty' => $producedQuantity,
            'anQtyTemp' => $producedQuantity,
            'acUM' => trim((string) ($catalog->acUM ?? $workOrder['acUM'] ?? 'KO')),
            'anPrice' => $calculation['pricePerUnit'],
            'anRebate' => 0,
            'acVATCode' => 'I0',
            'anVAT' => 0,
            'anStockPrice' => $calculation['pricePerUnit'],
            'anPriceCurrency' => $calculation['pricePerUnit'],
            'acLnkKey' => $workOrder['acKey'],
            'anLnkNo' => 0,
            'anPackQty' => $producedQuantity,
            'acDept' => $dept,
            'adTimeIns' => $now,
            'anUserIns' => $userId,
            'adTimeChg' => $now,
            'anUserChg' => $userId,
            'anWOPrice' => $calculation['pricePerUnit'],
            'anPVValue' => $calculation['totalPrice'],
            'anPVOCForPay' => $calculation['totalPrice'],
            'anPVOCVATBase' => $calculation['totalPrice'],
            'anPVOCValue' => $calculation['totalPrice'],
            'anPVOCStockValue' => $calculation['totalPrice'],
            'anQtyConverted' => $producedQuantity,
            'acUMConverted' => trim((string) ($catalog->acUM ?? $workOrder['acUM'] ?? 'KO')),
            'acDistributeCosts' => 'T',
            'anMoveQId' => $moveQId,
            'anCostDrvQId' => 1,
            'anDeptQId' => $deptQId,
            'anIdentQId' => (int) $catalog->anQId,
        ]);

        $this->stock->receive(
            $connection,
            (string) ($headerContext['receiver'] ?? ''),
            trim((string) $catalog->acIdent),
            $producedQuantity,
            (string) $calculation['totalPrice'],
            (string) $calculation['pricePerUnit'],
            $now,
            $userId
        );

        return [
            'document_key' => $number['key'],
            'document_number' => $number['number'],
            'document_type' => $number['type'],
            'work_order_code' => trim((string) ($workOrder['acKeyView'] ?? $workOrder['acKey'])),
            'item_code' => trim((string) $catalog->acIdent),
            'quantity' => $producedQuantity,
            'price_per_unit' => $calculation['pricePerUnit'],
            'work_order_price' => $calculation['pricePerUnit'],
            'total_price' => $calculation['totalPrice'],
            'operation_cost' => $calculation['operationTotal'],
            'material_cost' => $calculation['materialTotal'],
        ];
    }
}
