<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Applies the stock consequence of a 6100 finished-goods receipt.
 *
 * Pantheon-created 6100 documents increase tHE_Stock for the receiver
 * warehouse. eNalog creates the same change explicitly because direct
 * tHE_Move/tHE_MoveItem inserts do not invoke Pantheon's desktop posting flow.
 */
class PantheonFinishedGoodsStockService
{
    public function receive(
        ConnectionInterface $connection,
        string $warehouse,
        string $ident,
        string $quantity,
        string $totalValue,
        string $unitPrice,
        Carbon $now,
        int $userId
    ): void {
        $warehouse = trim($warehouse);
        $ident = trim($ident);

        if ($warehouse === '' || $ident === '') {
            throw new RuntimeException('Skladište ili šifra gotovog proizvoda nisu pronađeni za prijem.');
        }

        if (bccomp($quantity, '0', WorkOrderClosingCalculator::SCALE) <= 0) {
            throw new RuntimeException('Količina prijema mora biti veća od nule.');
        }

        if (bccomp($totalValue, '0', WorkOrderClosingCalculator::SCALE) < 0
            || bccomp($unitPrice, '0', WorkOrderClosingCalculator::SCALE) < 0) {
            throw new RuntimeException('Vrijednost prijema ne može biti negativna.');
        }

        // Keep the stock row locked for the complete enclosing work-order close
        // transaction, so repeated close requests cannot apply the receipt twice.
        $row = $connection->selectOne(
            "SELECT TOP 1 anQId, anStock, anValue
             FROM dbo.tHE_Stock WITH (UPDLOCK, HOLDLOCK)
             WHERE LTRIM(RTRIM(acWarehouse)) = ?
               AND LTRIM(RTRIM(acIdent)) = ?",
            [$warehouse, $ident]
        );

        if ($row === null) {
            $connection->table('dbo.tHE_Stock')->insert([
                'acWarehouse' => $warehouse,
                'acIdent' => $ident,
                'anStock' => $quantity,
                'anValue' => $totalValue,
                'anLastPrice' => $unitPrice,
                'anReserved' => '0',
                'adTimeIns' => $now,
                'adTimeChg' => $now,
                'anUserIns' => $userId,
                'anUserChg' => $userId,
                'anMinStock' => '-1',
                'anOptStock' => '-1',
                'anMaxStock' => '-1',
            ]);

            return;
        }

        $stock = bcadd((string) ($row->anStock ?? '0'), $quantity, WorkOrderClosingCalculator::SCALE);
        $value = bcadd((string) ($row->anValue ?? '0'), $totalValue, WorkOrderClosingCalculator::SCALE);

        $connection->table('dbo.tHE_Stock')
            ->where('anQId', (int) $row->anQId)
            ->update([
                'anStock' => $stock,
                'anValue' => $value,
                'anLastPrice' => $unitPrice,
                'adTimeChg' => $now,
                'anUserChg' => $userId,
            ]);
    }
}
