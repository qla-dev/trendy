<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Creates the minimum Pantheon WO positions required for a closing entered
 * against an otherwise empty work order.  The positions provide the foreign
 * keys that Pantheon uses to connect 6400/6600 document lines back to the WO.
 */
class PantheonClosingWorkOrderItemService
{
    /**
     * @return array{operations: array, materials: array, created_items: array}
     */
    public function createForEmptyWorkOrder(
        ConnectionInterface $connection,
        array $workOrder,
        array $operations,
        array $materials,
        Carbon $now,
        int $userId
    ): array {
        $workOrderKey = trim((string) ($workOrder['acKey'] ?? ''));
        if ($workOrderKey === '') {
            throw new RuntimeException('KljuÄ radnog naloga nije pronaÄ‘en za kreiranje pozicija.');
        }

        $workOrderHasItems = $connection->table('dbo.tHF_WOExItem')
            ->where('acKey', $workOrderKey)
            ->exists();
        $position = $workOrderHasItems
            ? (int) ($connection->table('dbo.tHF_WOExItem')->where('acKey', $workOrderKey)->max('anNo') ?? 0) + 1
            : 1;
        $createdItems = [];
        if (!$workOrderHasItems) {
            foreach ($operations as $index => $operation) {
                if ((int) ($operation['item_qid'] ?? 0) > 0) {
                    continue;
                }

                $qid = $this->insertItem(
                    $connection,
                    $workOrderKey,
                    $position,
                    (string) ($operation['code'] ?? ''),
                    (string) ($operation['name'] ?? ''),
                    'RDS',
                    'D',
                    '0',
                    $now,
                    $userId
                );

                $operations[$index]['item_qid'] = $qid;
                $operations[$index]['position'] = $position;
                $this->ensureOperationResourceRow($connection, $qid, $workOrderKey, $position, $now, $userId);
                $createdItems[] = [
                    'qid' => $qid,
                    'position' => $position,
                    'code' => $operations[$index]['code'],
                    'kind' => 'operation',
                ];
                $position++;
            }
        }

        // A material added on the closing tab has no WO-item link. Add only
        // that missing material position, even when the WO already has other
        // (for example operation) positions, so both 2005 and 6400 can link
        // to the same Pantheon row.
        foreach ($materials as $index => $material) {
            if ((int) ($material['item_qid'] ?? 0) > 0) {
                continue;
            }

            $qid = $this->insertItem(
                $connection,
                $workOrderKey,
                $position,
                (string) ($material['code'] ?? ''),
                (string) ($material['name'] ?? ''),
                (string) ($material['unit'] ?? ''),
                ' ',
                (string) ($material['quantity'] ?? '0'),
                $now,
                $userId
            );

            $materials[$index]['item_qid'] = $qid;
            $materials[$index]['position'] = $position;
            $createdItems[] = [
                'qid' => $qid,
                'position' => $position,
                'code' => $materials[$index]['code'],
                'kind' => 'material',
            ];
            $position++;
        }

        return [
            'operations' => $operations,
            'materials' => $materials,
            'created_items' => $createdItems,
        ];
    }

    private function insertItem(
        ConnectionInterface $connection,
        string $workOrderKey,
        int $position,
        string $code,
        string $name,
        string $unit,
        string $operationType,
        string $quantity,
        Carbon $now,
        int $userId
    ): int {
        $code = trim($code);
        if ($code === '') {
            throw new RuntimeException('Å ifra ruÄno unesene WO pozicije je obavezna.');
        }

        $unit = strtoupper(substr(trim($unit), 0, 3));
        if ($unit === '') {
            $unit = $operationType === 'D' ? 'RDS' : 'KOM';
        }

        $quantity = $operationType === 'D' ? '0' : $quantity;
        $connection->table('dbo.tHF_WOExItem')->insert([
            'acKey' => $workOrderKey,
            'anNo' => $position,
            'anVariant' => 0,
            'acIdent' => substr($code, 0, 16),
            'acUM' => $unit,
            'acUM3' => '',
            'acUMTime' => 'H',
            'anPlanQty' => $quantity,
            'anQtySE' => 0,
            'anQty' => $quantity,
            'anPrice' => 0,
            'anRebate' => 0,
            'anFieldNAx' => 0,
            'anWasteQty' => 0,
            'anWasteQtySE' => 0,
            'anPlanWasteQty' => 0,
            'anVariantSubLvl' => 253,
            'anQty1' => $quantity,
            'anQty3' => 0,
            'anBatch' => 1,
            'anQtyBase' => 0,
            'acQtyFormula' => '',
            'anQtyBase3' => 0,
            'acDescr' => substr(trim($name !== '' ? $name : $code), 0, 80),
            'acOperationType' => $operationType,
            'acDelayType' => 'Z',
            'anDelayQty' => 0,
            'acRuleID' => '',
            'acScrapIdent' => '',
            'anScrapPrc' => 0,
            'anPlanScrapQty' => 0,
            'anScrapQty' => 0,
            'anWastePrc' => 0,
            'acIssueFinished' => 'N',
            'acAutoPrepType' => 'F',
            'acFieldSA' => '',
            'acFieldSB' => '',
            'acFieldSC' => '',
            'acFieldSD' => '',
            'anFieldNA' => 0,
            'anFieldNB' => 0,
            'anFieldNC' => 0,
            'acNote' => 'eNalog.app closing entry',
            'adTimeIns' => $now,
            'adTimeChg' => $now,
            'anUserChg' => $userId,
            'anUserIns' => $userId,
            'anActive' => 1,
            'acFieldSE' => '',
            'anPrcValue' => 0,
            'acGroupResursID' => '',
            'acFixedResource' => 'F',
            'acFixedTime' => 'F',
            'acTaskState' => 'O',
            'acOrigin' => 'N',
            'anFixedScrapQty' => 0,
            'anFixedWasteQty' => 0,
            'anNoSubMat' => 0,
            'anIssuePerc' => 100,
        ]);

        $qid = $connection->table('dbo.tHF_WOExItem')
            ->where('acKey', $workOrderKey)
            ->where('anNo', $position)
            ->value('anQId');

        if (!is_numeric((string) $qid) || (int) $qid < 1) {
            throw new RuntimeException('Pantheon QId WO pozicije nije kreiran.');
        }

        return (int) $qid;
    }

    /**
     * Pantheon treats an operation position as schedulable only when it also
     * has a resource row. Without it the 6600 line and its links exist, but
     * the related-documents UI can omit the Operacije branch.
     */
    public function ensureOperationResourceRow(
        ConnectionInterface $connection,
        int $itemQid,
        string $workOrderKey,
        int $position,
        Carbon $now,
        int $userId
    ): void {
        if ($itemQid < 1 || $connection->table('dbo.tHF_WOExItemResources')->where('anWOExItemQId', $itemQid)->exists()) {
            return;
        }

        $connection->table('dbo.tHF_WOExItemResources')->insert([
            'acResursID' => '',
            'anQty' => 0,
            'acNote' => '',
            'anUserIns' => $userId,
            'adTimeIns' => $now,
            'anUserChg' => $userId,
            'adTimeChg' => $now,
            'acResType' => '',
            'anPlanQty' => 0,
            'anShift' => 0,
            'anPlanArea' => 0,
            'anArea' => 0,
            'anQty1' => 0,
            'anQty2' => 0,
            'anBatch' => 1,
            'anNoOfWorkers' => 1,
            'anPriority' => 0,
            'anWOExItemQId' => $itemQid,
            'acETAdditive' => '',
            'acIncomeGrp' => '',
            'acQtyFormula' => '',
            'acIssueFinished' => 'N',
            'anExecutionPerc' => 0,
            'acSubContractor' => '',
        ]);
    }
}
