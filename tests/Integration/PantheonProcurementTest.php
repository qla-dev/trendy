<?php

namespace Tests\Integration;

use App\Models\User;
use App\Services\Procurement\PantheonProcurementGateway;
use App\Services\Procurement\ProcurementCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Explicit opt-in only. Every write is rolled back on the named test ERP. */
class PantheonProcurementTest extends TestCase
{
    private $db;
    private int $actor;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PROCUREMENT_ERP_TESTS') !== '1') $this->markTestSkipped('Requires explicit ERP test opt-in.');
        $this->db = DB::connection(config('services.work_order.target_connection','sqlsrv'));
        if ($this->db->getDatabaseName() !== 'BA_TRENDY_TESTNA') throw new \RuntimeException('ERP integration tests require BA_TRENDY_TESTNA.');
        $this->actor = (int) User::where('username','admin')->firstOrFail()->id;
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db && $this->db->transactionLevel()>0) $this->db->rollBack(0);
        parent::tearDown();
    }

    private function purchase(): array
    {
        $catalog = $this->db->table('dbo.tHE_SetItem')->where('acIdent','PLO16MNCR5')->first();
        $line = (new ProcurementCalculator())->line('2','2.50','17','10') + [
            'id'=>(string)Str::uuid(),'source'=>['material'=>trim($catalog->acIdent),'dimensions'=>'TEST 20x30x40'],
            'unit'=>trim($catalog->acUM),'deadline'=>date('Y-m-d'),'received'=>'0',
        ];
        return ['id'=>(string)Str::uuid(),'created_by'=>$this->actor,'created_at'=>date('c'),
            'supplier'=>'EURO-METALI','currency'=>'KM','deadline'=>date('Y-m-d'),'header'=>['notes'=>'ROLLBACK INTEGRATION TEST'],
            'items'=>[$line],'totals'=>(new ProcurementCalculator())->totals([$line])];
    }

    public function test_real_order_receipts_stock_and_retry_recovery_are_atomic(): void
    {
        $purchase = $this->purchase(); $gateway = new PantheonProcurementGateway();
        $token = 'enalog-procurement-'.$purchase['id'];
        $confirmation = $gateway->transfer($purchase,$token);
        $this->assertTrue($confirmation['confirmed']);
        $this->assertStringContainsString('-020S-', $confirmation['number']);
        $this->assertSame($confirmation,$gateway->transfer($purchase,$token));
        $this->assertSame(1,$this->db->table('dbo.tHE_Order')->where('acInternalNote',$token)->count());
        $erpOrder=$this->db->table('dbo.tHE_Order')->where('acKey',$confirmation['key'])->first();
        $this->assertSame(57,(int)$erpOrder->anUserIns);
        $this->assertEqualsWithDelta(4.50,(float)$erpOrder->anValue-(float)$erpOrder->anDiscount,0.000001);
        $this->assertEqualsWithDelta(0.77,(float)$erpOrder->anVAT,0.000001);
        $this->assertEqualsWithDelta(5.27,(float)$erpOrder->anForPay,0.000001);
        $purchase['pantheon_key'] = $confirmation['key'];
        $before = $this->db->table('dbo.tHE_Stock')->where('acWarehouse','Skladište sirovina')->where('acIdent','PLO16MNCR5')->first();
        $receipt = ['submission'=>(string)Str::uuid(),'reference'=>'TEST-'.Str::random(8),'warehouse'=>'Skladište sirovina',
            'received_date'=>date('Y-m-d'),'accepted'=>['0.500000'],'rejected'=>['0.250000'],'user_id'=>$this->actor,'notes'=>'ROLLBACK TEST'];
        $receiptKey = 'enalog-receipt-'.$receipt['submission'];
        $received = $gateway->receive($purchase,$receipt,$receiptKey);
        $this->assertStringContainsString('-1005-', $received['number']);
        $this->assertSame($received,$gateway->receive($purchase,$receipt,$receiptKey));
        $changed=$receipt;$changed['accepted']=['0.600000'];
        try {$gateway->receive($purchase,$changed,$receiptKey);$this->fail('Changed retry accepted.');}
        catch (\RuntimeException $expected) {$this->assertSame($received,$gateway->receive($purchase,$receipt,$receiptKey));}
        $stock = $this->db->table('dbo.tHE_Stock')->where('acWarehouse','Skladište sirovina')->where('acIdent','PLO16MNCR5')->first();
        $this->assertEqualsWithDelta((float)$before->anStock+0.5,(float)$stock->anStock,0.000001);
        $this->assertEqualsWithDelta((float)$before->anValue+1.12,(float)$stock->anValue,0.000001);
        $line = $this->db->table('dbo.tHE_MoveItem')->where('acKey',$received['key'])->first();
        $this->assertEqualsWithDelta(0.5,(float)$line->anQty,0.000001);
        $this->assertEqualsWithDelta(0,(float)$line->anPVOCVAT,0.000001);
        $this->assertEqualsWithDelta(0.19,(float)$line->anPVOCIncVAT,0.000001);
        $this->assertEqualsWithDelta(1.31,(float)$line->anPVOCForPay,0.000001);
        $this->assertSame($confirmation['key'],trim($line->acLnkKey));
        $this->assertSame(1,$this->db->table('dbo.tHE_LinkMoveItemOrderItem')->where('acKey',$received['key'])->count());
        $this->assertEqualsWithDelta(0.5,(float)$this->db->table('dbo.tHE_OrderItem')->where('acKey',$confirmation['key'])->value('anQtyDispDoc'),0.000001);
        $receipt['accepted']=['1.500000'];$receipt['submission']=(string)Str::uuid();$receipt['reference'].='-2';
        $second = $gateway->receive($purchase,$receipt,'enalog-receipt-'.$receipt['submission']);
        $this->assertNotSame($received['key'],$second['key']);
        $this->assertEqualsWithDelta(2,(float)$this->db->table('dbo.tHE_OrderItem')->where('acKey',$confirmation['key'])->value('anQtyDispDoc'),0.000001);
        $count = $this->db->table('dbo.tHE_Move')->count();
        try {
            $receipt['submission']=(string)Str::uuid();
            $gateway->receive($purchase,$receipt,'enalog-receipt-'.$receipt['submission']);
            $this->fail('Overdelivery was accepted.');
        } catch (\RuntimeException $expected) {
            $this->assertSame($count,$this->db->table('dbo.tHE_Move')->count());
        }
    }

    public function test_bad_unit_rolls_back_header(): void
    {
        $purchase=$this->purchase();$gateway=new PantheonProcurementGateway();$count=$this->db->table('dbo.tHE_Order')->count();
        $purchase['items'][0]['unit']='KOM';
        try {$gateway->transfer($purchase,'enalog-procurement-'.$purchase['id']);$this->fail('Unit mismatch accepted.');}
        catch(\RuntimeException $expected){$this->assertSame($count,$this->db->table('dbo.tHE_Order')->count());}
    }
}
