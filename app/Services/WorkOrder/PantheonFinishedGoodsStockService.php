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

        // SQL Server FLOAT values can be hydrated by PHP in exponential form,
        // for example 2.8421709430404007E-14. BCMath accepts only ordinary
        // decimal strings, so normalise existing stock values before adding
        // the newly received quantity/value.
        $stock = bcadd($this->existingDecimal($row->anStock ?? null, 'anStock'), $quantity, WorkOrderClosingCalculator::SCALE);
        $value = bcadd($this->existingDecimal($row->anValue ?? null, 'anValue'), $totalValue, WorkOrderClosingCalculator::SCALE);

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

    private function existingDecimal(mixed $value, string $column): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '0.000000';
        }

        $decimal = trim((string) $value);
        if (preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $decimal) === 1) {
            return bcadd($decimal, '0', WorkOrderClosingCalculator::SCALE);
        }

        if (is_numeric($decimal)) {
            return number_format((float) $decimal, WorkOrderClosingCalculator::SCALE, '.', '');
        }

        throw new RuntimeException("PostojeÄ‡a vrijednost zalihe {$column} nije broj.");
    }
}
