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
        $db->statement('CREATE TABLE tHE_SetItem (acIdent TEXT, acName TEXT, acSetOfItem TEXT, anVAT TEXT, acSupplier TEXT)');
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
}
