<?php

namespace Tests\Feature;

use App\Services\Procurement\ProcurementSource;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ProcurementSourceTest extends TestCase
{
    private ProcurementSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse('2026-10-27','Europe/Sarajevo'));
        // Disposable in-memory fixture only. No migrations and no external ERP connection.
        config(['database.connections.procurement_fixture'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''],
            'services.work_order.target_connection'=>'procurement_fixture','workorders.schema'=>'main']);
        $db = DB::connection('procurement_fixture');
        $db->statement('CREATE TABLE tHF_WOEx (acKey TEXT, acKeyView TEXT, acLnkKey TEXT, anLnkNo INTEGER, acStatusMF TEXT, adSchedStartTime TEXT)');
        $db->statement('CREATE TABLE tHF_WOExItem (acKey TEXT, anQId INTEGER, anNo INTEGER, anVariant INTEGER, acIdent TEXT, acDescr TEXT, acNote TEXT, acUM TEXT, acOperationType TEXT, anQty1 TEXT, anQty TEXT, anPlanQty TEXT)');
        $db->statement('CREATE TABLE tHE_SetItem (acIdent TEXT, acName TEXT, acSetOfItem TEXT, anVAT TEXT, acSupplier TEXT, acUM TEXT, acCurrency TEXT, anPriceSupp TEXT, anPrice TEXT, anBuyPrice TEXT)');
        $db->statement('CREATE TABLE tHF_LinkWOExOrderItem (acKey TEXT, acLnkKey TEXT, acLnkKeyView TEXT, anLnkNo INTEGER)');
        $db->statement('CREATE TABLE tHE_SetSubj (acSubject TEXT)');
        $db->table('tHF_WOEx')->insert([
            ['acKey'=>'WO1','acKeyView'=>'RN-1','acLnkKey'=>'fallback','anLnkNo'=>9,'acStatusMF'=>'O','adSchedStartTime'=>'2026-11-03'],
            ['acKey'=>'WO2','acKeyView'=>'RN-2','acLnkKey'=>'fallback','anLnkNo'=>4,'acStatusMF'=>'Z','adSchedStartTime'=>'2026-11-03'],
        ]);
        $db->table('tHE_SetItem')->insert([
            ['acIdent'=>'STEEL','acName'=>'Čelik','acSetOfItem'=>'011','anVAT'=>'0','acSupplier'=>'Supplier'],
            ['acIdent'=>'OP','acName'=>'Operacija','acSetOfItem'=>'OPR','anVAT'=>'17','acSupplier'=>''],
            ['acIdent'=>'MANUAL','acName'=>'Ručno','acSetOfItem'=>'OTHER','anVAT'=>'17','acSupplier'=>'Supplier'],
        ]);
        foreach ([
            ['WO1',101,1,'STEEL','KG','M','2.5','10x20x30'],
            ['WO1',102,2,'STEEL','KOM','M','3','PLANNED_BOM|40x50x60'],
            ['WO1',103,3,'OP','MIN','M','10',''],
            ['WO2',104,1,'STEEL','KG','M','9',''],
            ['WO1',105,4,'MANUAL','KG','M','4',''],
        ] as [$key,$qid,$no,$code,$unit,$type,$qty,$note]) {
            $db->table('tHF_WOExItem')->insert(['acKey'=>$key,'anQId'=>$qid,'anNo'=>$no,'anVariant'=>0,'acIdent'=>$code,'acDescr'=>$code,'acNote'=>$note,'acUM'=>$unit,'acOperationType'=>$type,'anQty1'=>$qty,'anQty'=>'0','anPlanQty'=>'20']);
        }
        $db->table('tHF_LinkWOExOrderItem')->insert([
            ['acKey'=>'WO1','acLnkKey'=>'ORDER1','acLnkKeyView'=>'N-1','anLnkNo'=>2],
            ['acKey'=>'WO1','acLnkKey'=>'ORDER1','acLnkKeyView'=>'N-1','anLnkNo'=>3],
        ]);
        $db->table('tHE_SetSubj')->insert(['acSubject'=>'Supplier']);
        $this->source = new class extends ProcurementSource {
            protected function columns(string $table): array {
                return DB::connection('procurement_fixture')->getSchemaBuilder()->getColumnListing(substr($table, strlen('main.')));
            }
        };
    }

    protected function tearDown(): void
    {
        \Carbon\CarbonImmutable::setTestNow();
        DB::purge('procurement_fixture');
        parent::tearDown();
    }

    public function test_reads_each_bom_material_once_with_all_order_links_and_dimensions(): void
    {
        $rows = $this->source->materials('', 'position', 'asc');
        $this->assertSame(3, $rows->total());
        $this->assertSame('101', $rows[0]['item_qid']);
        $this->assertSame('102', $rows[1]['item_qid']);
        $this->assertNotSame($rows[0]['id'], $rows[1]['id']);
        $this->assertSame('10x20x30', $rows[0]['dimensions']);
        $this->assertSame('40x50x60', $rows[1]['dimensions']);
        $this->assertSame('2.5', $rows[0]['required']);
        $this->assertSame('KOM', $rows[1]['unit']);
        $this->assertSame('0', $rows[0]['vat_rate']);
        $this->assertSame(['2','3'], array_column($rows[0]['links'], 'position'));
        $this->assertSame('WO1', $rows[0]['work_order_key']);
    }

    public function test_filters_sorting_and_selected_items_use_actual_source_rows(): void
    {
        $rows = $this->source->materials('STEEL', 'position', 'desc', 'KG');
        $this->assertSame(1, $rows->total());
        $selected = $this->source->selected([$rows[0]['id']]);
        $this->assertSame('101', array_values($selected)[0]['item_qid']);
        $this->assertSame(['Supplier'], $this->source->suppliers());
        $this->assertTrue($this->source->validSupplier('Supplier'));
        $this->assertFalse($this->source->validSupplier('missing'));
        DB::connection('procurement_fixture')->table('tHF_WOEx')->where('acKey', 'WO1')->update(['acStatusMF'=>'Z']);
        $this->expectException(InvalidArgumentException::class);
        $this->source->selected([$rows[0]['id']]);
    }

    public function test_price_is_suggested_from_catalog_with_currency_unit_and_purchase_price_fallback(): void
    {
        $db=DB::connection('procurement_fixture');
        $catalog=$db->table('tHE_SetItem')->where('acIdent','STEEL');
        $catalog->update(['acUM'=>'KG','acCurrency'=>'KM','anPriceSupp'=>'2.4500000000000002','anPrice'=>'3','anBuyPrice'=>'4']);
        $row=$this->source->materials('STEEL','position','asc','KG')[0];
        $this->assertSame('2.450000',$row['price']);
        $this->assertSame('2.450000',$this->source->selected([$row['id']])[$row['id']]['price']);
        $this->assertSame('', $this->source->materials('STEEL','position','asc','KOM')[0]['price']);
        $catalog->update(['anPriceSupp'=>null]);
        $this->assertSame('3.000000',$this->source->materials('STEEL','position','asc','KG')[0]['price']);
        $catalog->update(['anPrice'=>'0','anBuyPrice'=>'1.88161715319039']);
        $this->assertSame('1.881617',$this->source->materials('STEEL','position','asc','KG')[0]['price']);
        $catalog->update(['acCurrency'=>'EUR']);
        $this->assertSame('',$this->source->materials('STEEL','position','asc','KG')[0]['price']);
        $catalog->update(['acCurrency'=>'KM','anBuyPrice'=>null]);
        $this->assertSame('',$this->source->materials('STEEL','position','asc','KG')[0]['price']);
    }

    public function test_malformed_source_identifier_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->source->selected(['bad-identifier']);
    }

    public function test_requirement_filters_search_dimensions_position_and_partial_quantities(): void
    {
        $rows=$this->source->requirements(['q'=>'40x50x60']);$this->assertSame(1,$rows->total());$this->assertSame('102',$rows[0]['item_qid']);
        $this->assertSame(3,$this->source->requirements(['q'=>'N-1'])->total());
        $this->assertSame('2026-10-27',$rows[0]['required_date']);
        $id=$rows[0]['id'];$progress=[$id=>['ordered'=>'1','received'=>'0','purchases'=>['test'=>'NS-1'],'suppliers'=>['Supplier'],'late'=>false]];
        $partial=$this->source->requirements(['status'=>'part_ordered'],$progress);$this->assertSame(1,$partial->total());$this->assertSame('2.000000',$partial[0]['remaining']);
        $this->assertSame(1,$this->source->requirements(['selected_only'=>1,'selected_ids'=>[$id]],$progress)->total());
        config(['procurement.lead_days'=>8]);$this->assertSame(3,$this->source->requirements(['status'=>'late'],$progress)->total());
    }

    public function test_excess_stock_reservation_notes_are_excluded_from_lists_and_selection(): void
    {
        $row=$this->source->materials('STEEL','position','asc','KG')[0];
        DB::connection('procurement_fixture')->table('tHF_WOExItem')->where('anQId',101)->update(['acNote'=>'Napomena: rezervacija_dodatnih_sirovina — već osigurano']);
        $this->assertSame(0,$this->source->materials('STEEL','position','asc','KG')->total());
        $this->assertSame(2,$this->source->requirements([])->total());
        $this->expectException(InvalidArgumentException::class);
        $this->source->selected([$row['id']]);
    }

    public function test_reservation_marker_underscores_are_literal_not_wildcards(): void
    {
        DB::connection('procurement_fixture')->table('tHF_WOExItem')->where('anQId',101)->update(['acNote'=>'REZERVACIJAXDODATNIHYSIROVINA']);
        $this->assertSame(3,$this->source->requirements([])->total());
    }
    public function test_empty_primary_note_uses_work_order_fallback_and_raw_stock_is_warehouse_specific(): void
    {
        $row=$this->source->map(['acKey'=>'WO1','anNo'=>1,'acIdent'=>'STEEL','acNote'=>' ','acFieldSE'=>'PLANNED_BOM|40x25x40','header_anPlanQty'=>'20']);
        $this->assertSame('40x25x40',$row['dimensions']);$this->assertSame('20',$row['pieces']);
        $db=DB::connection('procurement_fixture');
        $db->statement('CREATE TABLE tHE_Stock (acIdent TEXT, acWarehouse TEXT, anStock TEXT)');
        $db->table('tHE_Stock')->insert([['acIdent'=>'STEEL','acWarehouse'=>'Skladište sirovina','anStock'=>'12.5'],['acIdent'=>'STEEL','acWarehouse'=>'Other','anStock'=>'100']]);
        $this->assertSame(['STEEL'=>'12.5'],$this->source->stock(['STEEL']));
        $catalog=$this->source->catalog('STEEL',true);
        $this->assertCount(1,$catalog);$this->assertTrue($catalog[0]['stock_order']);
        $this->assertSame($catalog[0],$this->source->selected([$catalog[0]['id']])[$catalog[0]['id']]);
    }

    public function test_release_history_uses_linked_bom_rows_and_actual_issue_documents(): void
    {
        $db=DB::connection('procurement_fixture');
        $db->statement('CREATE TABLE tHE_Move (acKey TEXT, adDate TEXT, acDocType TEXT)');
        $db->statement('CREATE TABLE tHE_MoveItem (acKey TEXT, anNo INTEGER, acIdent TEXT, anQty TEXT, acUM TEXT)');
        $db->statement('CREATE TABLE tHF_LinkMoveItemWOExItem (acKey TEXT, anNo INTEGER, anWOExItemQid INTEGER)');
        foreach ([['ISSUE','2026-10-25','6400',101],['OLD','2026-10-01','6400',101],['RECEIPT','2026-10-25','1005',101],['OTHER','2026-10-25','6400',102]] as [$key,$date,$type,$qid]) {
            $db->table('tHE_Move')->insert(['acKey'=>$key,'adDate'=>$date,'acDocType'=>$type]);
            $db->table('tHE_MoveItem')->insert(['acKey'=>$key,'anNo'=>1,'acIdent'=>'STEEL','anQty'=>'2','acUM'=>'KG']);
            $db->table('tHF_LinkMoveItemWOExItem')->insert(['acKey'=>$key,'anNo'=>1,'anWOExItemQid'=>$qid]);
        }
        $source=$this->source->materials('STEEL','position','asc','KG')[0];
        $events=$this->source->releases(['created_at'=>'2026-10-20T12:00:00','items'=>[['source'=>$source]]]);
        $this->assertSame(['ISSUE'],array_column($events,'acKey'));
        $this->assertSame('2',$events[0]['anQty']);
        $source['stock_order']=true;
        $this->assertSame([],$this->source->releases(['created_at'=>'2026-10-20','items'=>[['source'=>$source]]]));
    }

    public function test_creation_date_range_is_independent_of_required_receipt_range(): void
    {
        $db=DB::connection('procurement_fixture');
        $db->statement('ALTER TABLE tHF_WOEx ADD COLUMN adDate TEXT');
        $db->table('tHF_WOEx')->where('acKey','WO1')->update(['adDate'=>'2026-10-20 23:55:00']);
        $this->assertSame(3,$this->source->requirements(['created_from'=>'2026-10-20','created_to'=>'2026-10-20'])->total());
        $this->assertSame(0,$this->source->requirements(['created_from'=>'2026-10-21'])->total());
        $this->assertSame(0,$this->source->requirements(['created_to'=>'2026-10-19'])->total());
        $this->assertSame(3,$this->source->requirements(['created_from'=>'2026-10-20','from'=>'2026-10-27','to'=>'2026-10-27'])->total());
        $this->assertSame(0,$this->source->requirements(['created_from'=>'2026-10-20','from'=>'2026-10-28'])->total());
    }

    public function test_material_page_lengths_preserve_filters_and_all_requirements_for_ordering(): void
    {
        $db=DB::connection('procurement_fixture');
        for($i=0;$i<52;$i++)$db->table('tHF_WOExItem')->insert(['acKey'=>'WO1','anQId'=>1000+$i,'anNo'=>20+$i,'anVariant'=>0,'acIdent'=>'STEEL','acDescr'=>'STEEL','acNote'=>'20x30x40','acUM'=>'KG','acOperationType'=>'M','anQty1'=>'1']);
        foreach([10,25,50,100] as $length){
            $rows=$this->source->requirements(['per_page'=>$length]);
            $this->assertSame($length,$rows->perPage());$this->assertCount(min(55,$length),$rows->items());$this->assertSame(55,$rows->total());
        }
        $first=$this->source->requirements(['per_page'=>10,'page'=>1,'sort'=>'position','direction'=>'asc']);
        $second=$this->source->requirements(['per_page'=>10,'page'=>2,'sort'=>'position','direction'=>'asc']);
        $this->assertSame([],array_values(array_intersect(array_column($first->items(),'id'),array_column($second->items(),'id'))));
        $this->assertSame(54,$this->source->requirements(['per_page'=>25,'unit'=>'KG'])->total());
        $this->assertCount(55,$this->source->requirements(['per_page'=>10,'_all'=>true])->items());
    }

    public function test_supplier_details_use_actual_jib_vat_and_postal_catalog_fields(): void
    {
        $db=DB::connection('procurement_fixture');
        foreach(['acName2','acAddress','acPost','acCountry','acCode','acJIBCode','acRegNo','acPhone'] as $column)$db->statement('ALTER TABLE tHE_SetSubj ADD COLUMN '.$column.' TEXT');
        $db->statement('CREATE TABLE tHE_SetPostCode (acPost TEXT, acName TEXT)');
        $db->table('tHE_SetPostCode')->insert(['acPost'=>'BA-74203','acName'=>'Doboj Jug']);
        $db->table('tHE_SetSubj')->where('acSubject','Supplier')->update(['acAddress'=>'Sarajevska 17','acPost'=>'BA-74203','acCode'=>'218466780006','acJIBCode'=>'4218466780006','acRegNo'=>'Reg-42']);
        $details=$this->source->supplierDetails('Supplier');
        $this->assertSame('4218466780006',$details['jib']);$this->assertSame('218466780006',$details['vat']);
        $this->assertSame('BA-74203 Doboj Jug',$details['postal_line']);$this->assertSame('Reg-42',$details['registration']);
        $this->assertSame([],$this->source->supplierDetails('Unknown'));
    }

}
