<?php

namespace App\Services\Procurement;

use App\Models\User;
use App\Services\WorkOrder\PantheonFinishedGoodsStockService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Uses the existing Pantheon schema; never creates tables or modifies document types. */
class PantheonProcurementGateway implements ProcurementGateway, ProcurementReceiptGateway
{
    private function db(): ConnectionInterface
    {
        return DB::connection(config('services.work_order.target_connection', 'sqlsrv'));
    }

    public function transfer(array $purchase, string $idempotencyKey): array
    {
        $db = $this->db();
        return $db->transaction(function () use ($db, $purchase, $idempotencyKey) {
            $this->lock($db, $idempotencyKey);
            $type = config('procurement.document_type', '020S');
            $this->documentType($db, $type, 'N', 'I');
            $marker = $idempotencyKey;
            if ($existing = $this->existing($db, 'tHE_Order', $type, $marker)) {
                return $this->orderConfirmation($db, $existing, $purchase);
            }
            $user = $this->user($db, (int) ($purchase['erp_actor_id'] ?? $purchase['created_by']));
            $warehouse = config('procurement.warehouse', 'Skladište sirovina');
            $this->subject($db, $purchase['supplier']);
            $this->subject($db, $warehouse);
            $this->currency($purchase);
            $date = $purchase['header']['document_date'] ?? substr($purchase['created_at'], 0, 10);
            $result = $this->procedure($db, "DECLARE @k varchar(13), @s varchar(2), @e varchar(1024);
                EXEC dbo.pHE_OrderCreAll @cPoslDog=?, @cNarocnik=?, @cPrejemnik=?, @cSkladisce=?,
                    @dDatum=?, @nUserId=?, @cOddelek=?, @cKljuc=@k OUTPUT, @p_cStatus=@s OUTPUT, @p_cError=@e OUTPUT;
                SELECT @k AS document_key, @s AS status, @e AS error;",
                [$type, $purchase['supplier'], $purchase['supplier'], $warehouse, $date, $user, $this->department($db, $purchase)]);
            $key = $this->createdKey($result);
            $now = Carbon::now();
            $discount = '0'; $base = '0';
            foreach ($purchase['items'] as $index => $line) {
                $item = $this->item($db, $line);
                $money = $this->moneyFields($line);
                $db->table('dbo.tHE_OrderItem')->insert($item + $money + [
                    'acKey'=>$key, 'anNo'=>$index + 1, 'anOrderQId'=>$db->table('dbo.tHE_Order')->where('acKey',$key)->value('anQId'),
                    'adDeliveryDeadline'=>$line['deadline'], 'adDeliveryDate'=>$line['deadline'],
                    'adTimeIns'=>$now, 'adTimeChg'=>$now, 'anUserIns'=>$user, 'anUserChg'=>$user,
                ]);
                $discount = bcadd($discount, $line['discount'], 2); $base = bcadd($base, $line['base'], 2);
            }
            $db->table('dbo.tHE_Order')->where('acKey',$key)->update([
                'adDeliveryDeadline'=>$purchase['deadline'], 'adDeliveryDate'=>$purchase['deadline'],
                'adDateValid'=>$purchase['header']['valid_until'] ?? $purchase['deadline'],
                'acCurrency'=>$purchase['currency'], 'anFXRate'=>'1',
                'anValue'=>$base, 'anDiscount'=>$discount, 'anVAT'=>$purchase['totals']['vat'],
                'anForPay'=>$purchase['totals']['gross'], 'anCurrValue'=>$purchase['totals']['gross'],
                'acInternalNote'=>$marker, 'acNote'=>$purchase['header']['notes'] ?? '',
            ]);
            return $this->orderConfirmation($db, $db->table('dbo.tHE_Order')->where('acKey',$key)->first(), $purchase);
        });
    }

    public function receive(array $purchase, array $receipt, string $idempotencyKey): array
    {
        $db = $this->db();
        return $db->transaction(function () use ($db, $purchase, $receipt, $idempotencyKey) {
            $this->lock($db, 'enalog-procurement-' . $purchase['id']);
            $type = config('procurement.receipt_document_type', '1005');
            $this->documentType($db, $type, 'F', 'P');
            $marker = $idempotencyKey . ':' . hash('sha256', json_encode([$purchase['id'],$purchase['pantheon_key'],array_intersect_key($receipt,
                array_flip(['submission','reference','warehouse','received_date','accepted','rejected']))]));
            if ($existing = $this->existing($db, 'tHE_Move', $type, $marker)) return $this->confirmation($existing);
            // A changed retry must never create a second receipt with the same submission token.
            if ($db->table('dbo.tHE_Move')->where('acInternalNote','like',$idempotencyKey . ':%')->exists()) {
                throw new RuntimeException('ERP prijem za ovaj token već postoji s drugačijim podacima.');
            }
            $this->currency($purchase);
            $order = $db->table('dbo.tHE_Order')->where('acKey',$purchase['pantheon_key'])->first();
            if (!$order) throw new RuntimeException('ERP narudžbenica nije pronađena.');
            $this->orderConfirmation($db, $order, $purchase);
            $user = $this->user($db, (int) $receipt['user_id']);
            $this->subject($db, $receipt['warehouse']);
            if (!$db->table('dbo.tHE_SetSubj')->where('acSubject',$receipt['warehouse'])->where('acWarehouse','T')->exists()) {
                throw new RuntimeException('Izabrani subjekt nije Pantheon skladište.');
            }
            $positive = array_filter($receipt['accepted'], fn($qty) => bccomp($qty,'0',6)>0);
            if (!$positive) return ['confirmed'=>true,'key'=>null,'number'=>null,'rejected_only'=>true];
            $result = $this->procedure($db, "DECLARE @k varchar(13), @s varchar(2), @e varchar(1024);
                EXEC dbo.pHE_MoveCreAll @cDocType=?, @cIssuer=?, @cReceiver=?, @dDate=?, @nUserId=?,
                    @cDept=?, @cKey=@k OUTPUT, @cStatus=@s OUTPUT, @cError=@e OUTPUT, @p_cDoc1=?, @p_dDateDoc1=?;
                SELECT @k AS document_key, @s AS status, @e AS error;",
                [$type,$purchase['supplier'],$receipt['warehouse'],$receipt['received_date'],$user,$this->department($db,$purchase),$receipt['reference'],$receipt['received_date']]);
            $key = $this->createdKey($result);
            $header = $db->table('dbo.tHE_Move')->where('acKey',$key)->first();
            $now = Carbon::now(); $lines = []; $no = 0;
            foreach ($purchase['items'] as $index => $line) {
                $qty = $receipt['accepted'][$index];
                if (bccomp($qty,'0',6)<=0) continue;
                $orderLine = $db->table('dbo.tHE_OrderItem')->where('acKey',$order->acKey)->where('anNo',$index+1)->lockForUpdate()->first();
                $remaining = bcsub((string)$orderLine->anQty, (string)$orderLine->anQtyDispDoc, 6);
                if (bccomp($qty,$remaining,6)>0) throw new RuntimeException('ERP prijem prelazi preostalu količinu narudžbenice.');
                $calc = app(ProcurementCalculator::class)->line($qty,$line['price'],$line['vatRate'],$line['discountRate']);
                $lines[] = $calc;
                $item = $this->item($db,$line);
                $stockPrice = bcdiv($calc['net'],$qty,12);
                $item['anQty'] = $qty;
                $item['anQtyConverted'] = $qty;
                $money = $this->moneyFields($calc);
                // Inbound VAT uses IncVAT/BeatShare, unlike an order's output-VAT fields.
                foreach (['anPVVATBase','anPVVAT','anPVForPay','anPVOCVAT'] as $field) $money[$field] = '0';
                $db->table('dbo.tHE_MoveItem')->insert($item + $money + [
                    'acKey'=>$key,'anNo'=>++$no,'anMoveQId'=>$header->anQId,
                    'anQtyTemp'=>$qty,'anStockPrice'=>$stockPrice,'acLnkKey'=>$order->acKey,'anLnkNo'=>$index+1,
                    'acVATCodeTR'=>$item['acVATCode'],'anVATIn'=>$line['vatRate'],
                    'anPVOCIncVAT'=>$calc['vat'],'anPVOCBeatShare'=>$calc['vat'],'anPVOCStockValue'=>$calc['net'],
                    'anPVOCIncVATWoExc'=>$calc['vat'],'anPVOCVATBaseWoExc'=>$calc['net'],
                    'anPVOCForPayWoExc'=>$calc['gross'],'anPVOCBeatShareWoExc'=>$calc['vat'],
                    'adTimeIns'=>$now,'adTimeChg'=>$now,'anUserIns'=>$user,'anUserChg'=>$user,
                ]);
                $moveLine = $db->table('dbo.tHE_MoveItem')->where('acKey',$key)->where('anNo',$no)->first();
                $db->table('dbo.tHE_LinkMoveItemOrderItem')->insert([
                    'acKey'=>$key,'anNo'=>$no,'acLnkKey'=>$order->acKey,'anLnkNo'=>$index+1,'acType'=>'2','acTypeA'=>'O ',
                    'anQty'=>$qty,'anOrderItemQId'=>$orderLine->anQId,'anMoveItemQId'=>$moveLine->anQId,
                    'adTimeIns'=>$now,'adTimeChg'=>$now,'anUserIns'=>$user,'anUserChg'=>$user,
                ]);
                $db->table('dbo.tHE_OrderItem')->where('anQId',$orderLine->anQId)->update([
                    'anQtyDispDoc'=>bcadd((string)$orderLine->anQtyDispDoc,$qty,6),'adTimeChg'=>$now,'anUserChg'=>$user,
                ]);
                app(PantheonFinishedGoodsStockService::class)->receive($db,$receipt['warehouse'],$item['acIdent'],$qty,$calc['net'],$stockPrice,$now,$user);
            }
            $totals = app(ProcurementCalculator::class)->totals($lines);
            $db->table('dbo.tHE_Move')->where('acKey',$key)->update([
                'acInternalNote'=>$marker,'acDoc2'=>$order->acKey,'acNote'=>$receipt['notes'],
                'anValue'=>$totals['net'],'anCurrValue'=>$totals['net'],'anVATIn'=>$totals['vat'],'anVAT'=>'0',
                'anForPay'=>$totals['gross'],'anDiscount'=>array_reduce($lines,fn($sum,$l)=>bcadd($sum,$l['discount'],2),'0'),
                'anRebate'=>array_reduce($lines,fn($sum,$l)=>bcadd($sum,$l['discount'],2),'0'),
            ]);
            return $this->confirmation($db->table('dbo.tHE_Move')->where('acKey',$key)->first());
        });
    }

    private function lock(ConnectionInterface $db, string $key): void
    {
        $row = $db->selectOne("DECLARE @r int; EXEC @r=sys.sp_getapplock @Resource=?, @LockMode='Exclusive', @LockOwner='Transaction', @LockTimeout=15000; SELECT @r AS result;",[$key]);
        if (!$row || (int)$row->result<0) throw new RuntimeException('ERP evidencija je zauzeta. Pokušajte ponovo.');
    }

    private function documentType(ConnectionInterface $db, string $type, string $set, string $direction): void
    {
        if (!$db->table('dbo.tPA_SetDocType')->where('acDocType',$type)->where('acSetOf',$set)->where('acType',$direction)->exists()) {
            throw new RuntimeException('Pantheon vrsta dokumenta nije ispravno definisana: '.$type);
        }
    }

    private function subject(ConnectionInterface $db, string $subject): void
    {
        if (!$db->table('dbo.tHE_SetSubj')->where('acSubject',$subject)->exists()) throw new RuntimeException('Pantheon subjekt nije pronađen.');
    }

    private function department(ConnectionInterface $db, array $purchase): string
    {
        $department = trim($purchase['header']['department'] ?? '');
        if ($department !== '') $this->subject($db,$department);
        return $department;
    }

    private function user(ConnectionInterface $db, int $id): int
    {
        $user = User::findOrFail($id);
        $users = $db->table('dbo.tPA_User')->where('acActive','T')->get(['anUserId','acUserId','acTitle']);
        $map = config('workorders.pantheon_user_map',[]);
        $mapped = $map[$id] ?? $map[$user->username] ?? null;
        if ($mapped && $users->contains(fn($row)=>(int)$row->anUserId===(int)$mapped)) return (int)$mapped;
        $normalize = fn($value)=>preg_replace('/[^a-z0-9]/','',\Illuminate\Support\Str::ascii(mb_strtolower(trim((string)$value))));
        // Prefer an exact login match, then an unambiguous full-name match.
        foreach ([[$user->username,'acUserId'],[$user->name,'acTitle']] as [$value,$field]) {
            if ($normalize($value)==='') continue;
            $matches = $users->filter(fn($row)=>$normalize($row->$field)===$normalize($value));
            if ($matches->count()===1) return (int)$matches->first()->anUserId;
        }
        $fallback = (int) config('procurement.fallback_pantheon_user_id',57);
        if (!$users->contains(fn($row)=>(int)$row->anUserId===$fallback)) throw new RuntimeException('Podrazumijevani Pantheon korisnik nije aktivan.');
        return $fallback;
    }

    private function currency(array $purchase): void
    {
        if ($purchase['currency'] !== 'KM' || bccomp((string)($purchase['header']['exchange_rate'] ?? '1'),'1',6)!==0) {
            throw new RuntimeException('ERP prijenos trenutno podržava KM i kurs 1; druga valuta zahtijeva potvrđeno mapiranje.');
        }
    }

    private function item(ConnectionInterface $db, array $line): array
    {
        $catalog = $db->table('dbo.tHE_SetItem')->where('acIdent',$line['source']['material'])->first();
        if (!$catalog || trim($catalog->acUM)!==trim($line['unit'])) throw new RuntimeException('ERP materijal ili jedinica ne odgovaraju nabavci.');
        $tax = $db->table('dbo.tHE_SetTax')->where('acVATCode',$catalog->acVATCode)->first();
        if (!$tax || bccomp((string)$tax->anVAT,$line['vatRate'],6)!==0) throw new RuntimeException('Stopa PDV-a ne odgovara poreznom profilu ERP materijala.');
        return ['acIdent'=>trim($catalog->acIdent),'acName'=>$catalog->acName,'anIdentQId'=>$catalog->anQId,
                'acUM'=>$line['unit'],'acUMConverted'=>$line['unit'],'anQty'=>$line['quantity'],'anQtyConverted'=>$line['quantity'],
            'anPrice'=>$line['price'],'anPriceCurrency'=>$line['price'],'anRebate'=>$line['discountRate'],'anRebate1'=>$line['discountRate'],
            'acVATCode'=>trim($catalog->acVATCode),'anVAT'=>$line['vatRate'],'acNote'=>$line['source']['dimensions'],
        ];
    }

    private function moneyFields(array $line): array
    {
        $fields = [];
        foreach (['anPV','anPVOC'] as $prefix) foreach (['Value'=>'base','Discount'=>'discount','VATBase'=>'net','VAT'=>'vat','ForPay'=>'gross'] as $suffix=>$value) {
            $fields[$prefix.$suffix] = $line[$value];
        }
        return $fields;
    }

    private function existing(ConnectionInterface $db, string $table, string $type, string $marker): ?object
    {
        $rows = $db->table('dbo.'.$table)->where('acDocType',$type)->where('acInternalNote',$marker)->limit(2)->get();
        if ($rows->count()>1) throw new RuntimeException('Pronađeno je više ERP dokumenata s istim ključem.');
        return $rows->first();
    }

    private function orderConfirmation(ConnectionInterface $db, object $order, array $purchase): array
    {
        $lines = $db->table('dbo.tHE_OrderItem')->where('acKey',$order->acKey)->orderBy('anNo')->get();
        if (trim($order->acDocType)!==config('procurement.document_type','020S') || trim($order->acReceiver)!==$purchase['supplier']
            || trim($order->acCurrency)!==$purchase['currency'] || $lines->count()!==count($purchase['items'])
            || substr((string)$order->adDeliveryDeadline,0,10)!==$purchase['deadline']
            || bccomp((string)$order->anForPay,$purchase['totals']['gross'],2)!==0) throw new RuntimeException('ERP dokument je izmijenjen; potrebna je provjera.');
        foreach ($lines as $index=>$line) {
            $expected = $purchase['items'][$index];
            if ((int)$line->anNo!==$index+1 || trim($line->acIdent)!==$expected['source']['material'] || trim($line->acUM)!==$expected['unit']
                || bccomp((string)$line->anQty,$expected['quantity'],6)!==0 || substr((string)$line->adDeliveryDeadline,0,10)!==$expected['deadline']
                || trim((string)$line->acNote)!==trim($expected['source']['dimensions']) || abs((float)$line->anPrice-(float)$expected['price'])>0.000001
                || abs((float)$line->anVAT-(float)$expected['vatRate'])>0.000001 || abs((float)$line->anRebate-(float)$expected['discountRate'])>0.000001) {
                throw new RuntimeException('ERP stavka je izmijenjena; potrebna je provjera.');
            }
        }
        return $this->confirmation($order);
    }

    private function confirmation(object $row): array
    {
        return ['confirmed'=>true,'key'=>trim($row->acKey),'number'=>trim($row->acKeyView)];
    }

    private function createdKey(array $result): string
    {
        if (($result['status'] ?? '')!=='T' || empty($result['document_key'])) throw new RuntimeException('Pantheon nije potvrdio kreiranje dokumenta: '.($result['error'] ?? 'bez odgovora'));
        return trim($result['document_key']);
    }

    private function procedure(ConnectionInterface $db, string $sql, array $bindings): array
    {
        $statement = $db->getPdo()->prepare('SET NOCOUNT ON; '.$sql);
        $statement->execute($bindings);
        $result = null;
        do {
            if ($statement->columnCount()>0) foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if (array_key_exists('document_key',$row)) $result = $row;
            }
        } while ($statement->nextRowset());
        if (!$result) throw new RuntimeException('Pantheon nije vratio potvrdu.');
        return $result;
    }
}
