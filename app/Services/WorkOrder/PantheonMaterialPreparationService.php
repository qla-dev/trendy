<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class PantheonMaterialPreparationService
{
    public function __construct(private PantheonDocumentNumberGenerator $numbers, private PantheonDocumentWriter $writer, private PantheonMaterialStockService $stock) {}
    public function prepare(ConnectionInterface $db, array $workOrder, array $items, Carbon $now, int $userId): array
    {
        $raw = trim((string) config('work_order_closing.raw_material_warehouse', 'Skladište sirovina'));
        $wip = trim((string) config('work_order_closing.work_in_progress_warehouse', ''));
        if ($raw === '' || $wip === '') throw new RuntimeException('Konfiguracija skladišta sirovina ili međuskladišta nije postavljena.');
        if ($items === []) throw new RuntimeException('Radni nalog nema materijale za pripremu.');
        $wo = (string) $workOrder['acKey'];
        if ($db->table('dbo.tHE_Move as m')->join('dbo.tHF_LinkMoveWOEx as l','l.acKey','=','m.acKey')->where('l.acLnkKey',$wo)->where('m.acDocType','2005')->exists()) throw new RuntimeException('Dokument 2005 za radni nalog već postoji.');
        $number=$this->numbers->next($db,'2005',$now); $total='0'; foreach($items as $item) $total=bcadd($total,(string)$item['total'],WorkOrderClosingCalculator::SCALE);
        $qid=$this->writer->insertHeader($db,$number,$workOrder,['receiver'=>$wip,'issuer'=>$raw,'receiver_stock'=>'Y','issuer_stock'=>'Y','person3'=>$wip,'way_of_sale'=>'P','total_value'=>$total],$now,$userId);
        $this->writer->linkWorkOrder($db,$number,$qid,$wo,'P',$now,$userId);
        foreach($items as $i=>$item) {
            $line = $i + 1;
            $itemQid = $this->writer->insertItem($db,['acKey'=>$number['key'],'anNo'=>$line,'acIdent'=>$item['code'],'acName'=>$item['name'],'anQty'=>$item['quantity'],'anQtyTemp'=>$item['quantity'],'acUM'=>$item['unit'],'anPrice'=>$item['price'],'anStockPrice'=>$item['price'],'anPriceCurrency'=>$item['price'],'anRebate'=>0,'acVATCode'=>'I0','anVAT'=>0,'anMoveQId'=>$qid,'anIdentQId'=>$item['ident_qid'],'adTimeIns'=>$now,'anUserIns'=>$userId,'adTimeChg'=>$now,'anUserChg'=>$userId]);
            if ((int) ($item['item_qid'] ?? 0) > 0) $this->writer->linkItem($db, $number, $line, $itemQid, (int) $item['item_qid'], $now, $userId, 'A ');
        }
        $this->stock->transfer($db,$raw,$wip,$items,$now,$userId);
        return ['document_key'=>$number['key'],'document_number'=>$number['number'],'document_type'=>'2005','items'=>$items];
    }
}
