<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class PantheonMaterialStockService
{
    public function transfer(ConnectionInterface $db, string $from, string $to, array $items, Carbon $now, int $userId): void { foreach ($items as $item) { $this->change($db, $from, $item['code'], '-' . $item['quantity'], $now, $userId, false); $this->change($db, $to, $item['code'], $item['quantity'], $now, $userId, true); } }
    public function issue(ConnectionInterface $db, string $warehouse, array $items, Carbon $now, int $userId): void { foreach ($items as $item) $this->change($db, $warehouse, $item['code'], '-' . $item['quantity'], $now, $userId, false); }
    private function change(ConnectionInterface $db, string $warehouse, string $ident, string $delta, Carbon $now, int $userId, bool $allowInsert): void
    {
        $row = $db->selectOne("SELECT TOP 1 anQId, anStock FROM dbo.tHE_Stock WITH (UPDLOCK, HOLDLOCK) WHERE LTRIM(RTRIM(acWarehouse)) = ? AND LTRIM(RTRIM(acIdent)) = ?", [$warehouse, $ident]);
        if ($row === null) { if (!$allowInsert) throw new RuntimeException('Nedostaje zaliha materijala ' . $ident . ' na skladištu ' . $warehouse . '.'); $db->table('dbo.tHE_Stock')->insert(['acWarehouse'=>$warehouse,'acIdent'=>$ident,'anStock'=>$delta,'anValue'=>0,'anLastPrice'=>0,'anReserved'=>0,'adTimeIns'=>$now,'adTimeChg'=>$now,'anUserIns'=>$userId,'anUserChg'=>$userId,'anMinStock'=>-1,'anOptStock'=>-1,'anMaxStock'=>-1]); return; }
        $next = bcadd(is_numeric((string) $row->anStock) ? (string) $row->anStock : '0', $delta, WorkOrderClosingCalculator::SCALE);
        if (bccomp($next, '0', WorkOrderClosingCalculator::SCALE) < 0) throw new RuntimeException('Nedovoljna zaliha materijala ' . $ident . ' na skladištu ' . $warehouse . '.');
        $db->table('dbo.tHE_Stock')->where('anQId', (int) $row->anQId)->update(['anStock'=>$next,'adTimeChg'=>$now,'anUserChg'=>$userId]);
    }
}
