<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Procurement\ProcurementCalculator;
use App\Services\Procurement\ProcurementService;
use App\Services\Procurement\ProcurementSource;
use App\Services\Procurement\ProcurementStore;
use App\Support\AiTokenNavbarCounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    private string $directory;
    private ProcurementStore $store;
    private ProcurementService $service;
    private array $sources;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');
        \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse('2026-10-27 12:00:00', 'Europe/Sarajevo'));
        $this->directory = sys_get_temp_dir() . '/trendy-procurement-' . bin2hex(random_bytes(8));
        $this->store = new ProcurementStore($this->directory);
        $this->sources = [
            'source-a'=>['id'=>'source-a', 'work_order'=>'RN-1', 'work_order_key'=>'key-1', 'item_qid'=>'101','item_no'=>'1','variant'=>'0','material'=>'STEEL','name'=>'Čelik','dimensions'=>'20x30x40','required'=>'10','unit'=>'KG','vat_rate'=>'17','supplier'=>'Dobavljač','links'=>[['order_key'=>'order-1','order_number'=>'N-1','position'=>'2']]],
            'source-b'=>['id'=>'source-b', 'work_order'=>'RN-1', 'work_order_key'=>'key-1', 'item_qid'=>'102','item_no'=>'2','variant'=>'0','material'=>'STEEL','name'=>'Čelik','dimensions'=>'50x60x70','required'=>'3','unit'=>'KG','vat_rate'=>'0','supplier'=>'Dobavljač','links'=>[['order_key'=>'order-1','order_number'=>'N-1','position'=>'2']]],
        ];
        $source = $this->mock(ProcurementSource::class);
        $source->shouldReceive('validSupplier')->andReturnUsing(fn ($name) => $name === 'Dobavljač');
        $source->shouldReceive('selected')->andReturnUsing(function ($ids) {
            $rows = [];
            foreach (array_unique($ids) as $id) {
                if (!isset($this->sources[$id])) throw new InvalidArgumentException('Izvor ne postoji.');
                $rows[$id] = $this->sources[$id];
            }
            return $rows;
        });
        $source->shouldReceive('suppliers')->andReturn(['Dobavljač']);
        $source->shouldReceive('warehouses')->andReturn(['Sirovine']);
        $source->shouldReceive('validWarehouse')->andReturnUsing(fn($name)=>$name==='Sirovine');
        $this->app->instance(ProcurementStore::class, $this->store);
        $this->service = new ProcurementService($this->store, $source, new ProcurementCalculator());
        $this->app->instance(ProcurementService::class, $this->service);
        $this->mock(AiTokenNavbarCounter::class)->shouldReceive('currentMonthTotal')->andReturn(0);
        // Any unexpected database use in this suite fails rather than reaching an ERP.
        DB::shouldReceive('connection')->andThrow(new \RuntimeException('Database access is forbidden in procurement workflow tests.'));
    }

    protected function tearDown(): void
    {
        \Carbon\CarbonImmutable::setTestNow();
        foreach (glob($this->directory . '/registry/*') ?: [] as $file) unlink($file);
        if (is_dir($this->directory . '/registry')) rmdir($this->directory . '/registry');
        foreach (glob($this->directory . '/*') ?: [] as $file) unlink($file);
        if (is_dir($this->directory)) rmdir($this->directory);
        parent::tearDown();
    }

    private function payload(bool $multiple = true): array
    {
        $items = [['source_id'=>'source-a','quantity'=>'10','price'=>'2.50','vat_rate'=>'17']];
        if ($multiple) $items[] = ['source_id'=>'source-b','quantity'=>'3','price'=>'1','vat_rate'=>'0'];
        return ['submission'=>(string) Str::uuid(), 'supplier'=>'Dobavljač','deadline'=>'2026-10-26','items'=>$items];
    }

    private function login(string $role = 'admin'): void
    {
        $user = new User(['name'=>'Test','role'=>$role]);
        $user->id = 77;
        $this->actingAs($user);
    }

    public function test_multi_item_creation_recalculates_and_preserves_exact_source_links(): void
    {
        $data = $this->payload();
        $data['totals'] = ['gross'=>'0'];
        $purchase = $this->service->create($data, 77);
        $this->assertCount(2, $purchase['items']);
        $this->assertSame('101', $purchase['items'][0]['source']['item_qid']);
        $this->assertSame('102', $purchase['items'][1]['source']['item_qid']);
        $this->assertSame('2', $purchase['items'][1]['source']['links'][0]['position']);
        $this->assertSame(['quantity'=>'13.000000','net'=>'28.00','vat'=>'4.25','gross'=>'32.25'], $purchase['totals']);
        $this->assertSame($purchase, $this->service->create($data, 77));
        $this->assertCount(1, (new ProcurementStore($this->directory))->all());
    }

    public function test_different_submissions_cannot_duplicate_a_reserved_source(): void
    {
        $this->service->create($this->payload(false), 77);
        $this->expectException(InvalidArgumentException::class);
        $this->service->create($this->payload(false), 88);
    }

    public function test_missing_supplier_source_and_duplicate_lines_do_not_commit(): void
    {
        foreach (['supplier','source','duplicate'] as $case) {
            $data = $this->payload();
            if ($case==='supplier') $data['supplier'] = 'Nepostojeći';
            if ($case==='source') $data['items'][0]['source_id'] = 'missing';
            if ($case==='duplicate') $data['items'][1]['source_id'] = 'source-a';
            try { $this->service->create($data, 77); $this->fail('Invalid purchase accepted.'); }
            catch (InvalidArgumentException $expected) { $this->assertSame([], $this->store->all()); }
        }
    }

    public function test_partial_and_complete_receipts_are_independent_of_sync(): void
    {
        $purchase = $this->service->create($this->payload(), 77);
        $partial = $this->service->receive($purchase['id'], ['4','0'], 'PR-1', 77);
        $this->assertSame('partial', (new ProcurementCalculator())->deliveryStatus($partial));
        $this->assertCount(1, $partial['receipts']);
        $again = $this->service->receive($purchase['id'], ['4','0'], 'PR-1', 77);
        $this->assertCount(1, $again['receipts']);
        $completed = $this->service->receive($purchase['id'], ['10','3'], 'PR-2', 77);
        $this->assertSame('delivered', (new ProcurementCalculator())->deliveryStatus($completed));
        $this->assertSame(0, (new ProcurementCalculator())->deadline($completed)['days']);
        $this->assertSame('blocked', $completed['sync_status']);
    }

    public function test_invalid_receipts_are_atomic_and_do_not_allow_reduction_overreceipt_or_reused_reference(): void
    {
        $purchase = $this->service->create($this->payload(), 77);
        $saved = $this->service->receive($purchase['id'], ['4','1'], 'PR-1', 77);
        foreach ([['5','4'], ['3','1'], ['5','2']] as $quantities) {
            try { $this->service->receive($purchase['id'], $quantities, 'PR-1', 77); $this->fail('Invalid receipt accepted.'); }
            catch (InvalidArgumentException $expected) { $this->assertSame($saved, $this->service->find($purchase['id'])); }
        }
        $this->expectException(InvalidArgumentException::class);
        $this->service->cancel($purchase['id'], 77);
    }

    public function test_cancel_releases_reservation_and_cancelled_purchase_cannot_receive(): void
    {
        $purchase = $this->service->create($this->payload(false), 77);
        $cancelled = $this->service->cancel($purchase['id'], 77);
        $this->assertSame('cancelled', (new ProcurementCalculator())->deliveryStatus($cancelled));
        $this->assertSame([], $this->service->reserved());
        $this->service->create($this->payload(false), 77);
        $this->assertCount(2, $this->service->all());
        $this->expectException(InvalidArgumentException::class);
        $this->service->receive($purchase['id'], ['1'], 'PR-1', 77);
    }

    public function test_blocked_sync_is_logged_retryable_and_never_claims_success_or_creates_erp_documents(): void
    {
        Log::spy();
        $purchase = $this->service->create($this->payload(false), 77);
        $this->service->sync($purchase['id'], 77);
        $retry = $this->service->sync($purchase['id'], 77);
        $this->assertSame('blocked', $retry['sync_status']);
        $this->assertSame(2, $retry['sync_attempts']);
        $this->assertNull($retry['pantheon_key']);
        $this->assertNull($retry['pantheon_number']);
        $this->assertSame('0.000000', $retry['items'][0]['received']);
        $this->assertCount(1, $this->service->all());
        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_failed_mutation_and_corrupt_ledger_preserve_original_file(): void
    {
        $purchase = $this->service->create($this->payload(false), 77);
        try {
            $this->store->update(function (&$rows) { $rows = []; throw new \RuntimeException('Write failed'); });
        } catch (\RuntimeException $expected) { $this->assertSame([$purchase], $this->store->all()); }
        file_put_contents($this->directory . '/ledger.json', '{broken');
        try { $this->service->create($this->payload(false), 77); $this->fail('Corrupt ledger accepted.'); }
        catch (\JsonException $expected) { $this->assertSame('{broken', file_get_contents($this->directory . '/ledger.json')); }
    }

    public function test_http_permissions_validation_creation_and_detail_rendering(): void
    {
        $this->get('/app/procurement')->assertRedirect();
        $this->login('user');
        $this->get('/app/procurement')->assertForbidden();
        $this->post('/app/procurement', $this->payload())->assertForbidden();
        $this->login();
        $this->post('/app/procurement', array_replace($this->payload(), ['deadline'=>'']))->assertSessionHasErrors('deadline');
        $response = $this->post('/app/procurement', $this->payload());
        $purchase = $this->store->all()[0];
        $response->assertRedirect(route('app-procurement-show', $purchase['id']));
        $this->get('/app/procurement/' . $purchase['id'])->assertOk()->assertSee('32,25')->assertSee('20x30x40');
        $this->get('/app/procurement/' . Str::uuid())->assertNotFound();
    }

    public function test_index_search_late_delivery_and_sync_filters(): void
    {
        $this->login();
        $purchase = $this->service->create($this->payload(false), 77);
        $this->get('/app/procurement?q=RN-1&late=1&sync=blocked')->assertOk()->assertSee($purchase['number']);
        $this->get('/app/procurement?q=missing')->assertOk()->assertDontSee($purchase['number']);
        $this->get('/app/procurement?status=delivered')->assertOk()->assertDontSee($purchase['number']);
        $this->service->receive($purchase['id'], ['10'], 'PR-1', 77);
        $this->get('/app/procurement?late=1')->assertOk()->assertDontSee($purchase['number']);
        $this->get('/app/procurement?status=delivered')->assertOk()->assertSee($purchase['number']);
    }

    public function test_creation_form_can_be_reopened_after_backend_validation_error(): void
    {
        $this->login();
        $this->post('/app/procurement/create', ['selected'=>['source-a','source-b']])->assertRedirect(route('app-procurement-create-form'));
        $this->get('/app/procurement/create')->assertOk()->assertSee('20x30x40')->assertSee('50x60x70');
        $this->from('/app/procurement/create')->post('/app/procurement', array_replace($this->payload(), ['supplier'=>'missing']))->assertRedirect('/app/procurement/create')->assertSessionHasErrors('procurement');
        $this->get('/app/procurement/create')->assertOk()->assertSee('missing');
    }

    public function test_custom_role_requires_explicit_procurement_permission(): void
    {
        $roles = $this->mock(\App\Support\CustomRoles::class);
        $roles->shouldReceive('find')->with('buyer')->andReturn(['slugs'=>['app-procurement']]);
        $roles->shouldReceive('find')->with('unrelated')->andReturn(['slugs'=>['app-stock']]);
        $this->login('buyer');
        $this->get('/app/procurement')->assertOk()->assertSee('Nabavke sirovina');
        $this->login('unrelated');
        $this->get('/app/procurement')->assertForbidden();
        $this->post('/app/procurement/unknown/sync')->assertForbidden();
    }

    public function test_stale_selection_returns_to_materials_instead_of_redirecting_back_to_the_form(): void
    {
        $this->login();
        $this->post('/app/procurement/create', ['selected'=>['source-a']]);
        $this->service->create($this->payload(false), 77);
        $this->get('/app/procurement/create')->assertRedirect(route('app-procurement-materials'))->assertSessionHasErrors('selected');
    }

    public function test_partial_procurement_preserves_remaining_requirement_and_blocks_overordering(): void
    {
        $first=$this->payload(false);$first['items'][0]['quantity']='4';
        $this->service->create($first,77);
        $second=$this->payload(false);$second['items'][0]['quantity']='6';
        $this->service->create($second,77);
        $this->assertSame('10.000000',$this->service->progress()['source-a']['ordered']);
        $this->assertCount(2,$this->service->all());
        $this->expectException(InvalidArgumentException::class);
        $this->service->create($this->payload(false),77);
    }

    public function test_compatible_requirements_combine_with_allocations_and_discount(): void
    {
        $this->sources['source-b']['dimensions']='20x30x40';$this->sources['source-b']['vat_rate']='17';$this->sources['source-b']['work_order']='RN-2';
        $data=$this->payload(false);
        $data['items']=[['quantity'=>'9','price'=>'2','vat_rate'=>'17','discount_rate'=>'10','allocations'=>[['source_id'=>'source-a','quantity'=>'6'],['source_id'=>'source-b','quantity'=>'3']]]];
        $purchase=$this->service->create($data,77);
        $this->assertCount(1,$purchase['items']);$this->assertCount(2,$purchase['items'][0]['allocations']);
        $this->assertSame('18.95',$purchase['totals']['gross']);$this->assertSame('1.80',$purchase['items'][0]['discount']);
        $this->assertSame('6.000000',$this->service->progress()['source-a']['ordered']);
    }

    public function test_different_dimensions_and_units_cannot_be_combined(): void
    {
        foreach (['dimensions','unit'] as $field) {
            $this->sources['source-b']['dimensions']=$field==='dimensions'?'different':'20x30x40';
            $this->sources['source-b']['vat_rate']='17';$this->sources['source-b']['unit']=$field==='unit'?'KOM':'KG';
            $data=$this->payload(false);$data['items']=[['quantity'=>'2','price'=>'1','vat_rate'=>'17','allocations'=>[['source_id'=>'source-a','quantity'=>'1'],['source_id'=>'source-b','quantity'=>'1']]]];
            try{$this->service->create($data,77);$this->fail('Incompatible allocation accepted.');}catch(InvalidArgumentException $expected){$this->assertSame([],$this->service->all());}
        }
    }

    public function test_source_unit_is_preserved_and_weight_is_separate(): void
    {
        $this->sources['source-a']['unit']='KOM';$data=$this->payload(false);$data['items'][0]['quantity']='2';$data['items'][0]['weight']='7.5';
        $purchase=$this->service->create($data,77);
        $this->assertSame('KOM',$purchase['items'][0]['unit']);$this->assertSame('2.000000',$purchase['items'][0]['quantity']);$this->assertSame('7.500000',$purchase['items'][0]['weight']);
        $this->assertSame('2.000000',$this->service->progress()['source-a']['ordered']);
    }

    private function receivingPayload(): array
    {
        return ['submission'=>(string)Str::uuid(),'reference'=>'PR-1','warehouse'=>'Sirovine','received_date'=>'2026-10-27','quantities'=>['4'],'rejected'=>['1'],'notes'=>'Pregledano'];
    }

    public function test_receiving_events_are_idempotent_and_keep_accepted_rejected_and_warehouse(): void
    {
        $purchase=$this->service->create($this->payload(false),77);$data=$this->receivingPayload();
        $received=$this->service->receiveEvent($purchase['id'],$data,77);
        $repeat=$this->service->receiveEvent($purchase['id'],$data,77);
        $this->assertCount(1,$repeat['receipts']);$this->assertSame('4.000000',$repeat['items'][0]['received']);
        $this->assertSame('Sirovine',$received['receipts'][0]['warehouse']);$this->assertSame(['1.000000'],$received['receipts'][0]['rejected']);
        $this->assertSame('4.000000',$this->service->progress()['source-a']['received']);
        $data['submission']=(string)Str::uuid();$data['reference']='PR-2';$data['quantities']=['6'];$data['rejected']=['0'];
        $complete=$this->service->receiveEvent($purchase['id'],$data,77);$this->assertSame('delivered',(new ProcurementCalculator())->deliveryStatus($complete));
    }

    public function test_receiving_rejects_unknown_warehouse_overdelivery_and_future_date(): void
    {
        $purchase=$this->service->create($this->payload(false),77);
        foreach (['warehouse','overdelivery','future'] as $case) {
            $data=$this->receivingPayload();if($case==='warehouse')$data['warehouse']='Unknown';if($case==='overdelivery')$data['quantities']=['11'];if($case==='future')$data['received_date']='2026-10-28';
            try{$this->service->receiveEvent($purchase['id'],$data,77);$this->fail('Invalid receiving accepted.');}catch(InvalidArgumentException $expected){$this->assertSame([],$this->service->find($purchase['id'])['receipts']);}
        }
    }

    public function test_qr_lookup_only_opens_receiving_and_labels_have_stable_distinct_references(): void
    {
        $this->login();$purchase=$this->service->create($this->payload(),77);
        $this->get(route('app-procurement-receive-form',['id'=>$purchase['id'],'line'=>$purchase['items'][0]['id']]))->assertOk()->assertSee('Potvrdi stvarni prijem');
        $this->assertSame([],$this->service->find($purchase['id'])['receipts']);
        $this->get(route('app-procurement-labels',['orders'=>[$purchase['id']]]))->assertOk()->assertSee($purchase['items'][0]['id'])->assertSee($purchase['items'][1]['id'])->assertSee('Početno mjesto');
        $this->get(route('app-procurement-receive-form',['id'=>$purchase['id'],'line'=>(string)Str::uuid()]))->assertNotFound();
    }

    public function test_supplier_isolation_projection_delay_and_backend_route_restriction(): void
    {
        $purchase=$this->service->create($this->payload(false),77);
        $otherData=$this->payload(false);$otherData['items'][0]['source_id']='source-b';$otherData['items'][0]['quantity']='3';
        $other=$this->service->create($otherData,77);$this->service->change($other['id'],function(&$row){$row['supplier']='Other';});
        $access=app(\App\Services\Procurement\ProcurementAccess::class);$access->bind(77,'Dobavljač','contact@example.test',1);
        $this->login('supplier');
        $this->get('/supplier/procurement')->assertOk()->assertSee($purchase['number'])->assertDontSee($other['number']);
        $this->get('/supplier/procurement/'.$purchase['id'])->assertOk()->assertDontSee('RN-1')->assertDontSee('key-1')->assertDontSee('2.500000');
        $this->get('/supplier/procurement/'.$other['id'])->assertNotFound();
        $this->get('/app/procurement')->assertForbidden();$this->get('/app/orders')->assertForbidden();$this->get('/app/user/list')->assertForbidden();
        $this->post('/supplier/procurement/'.$purchase['id'].'/event',['action'=>'delay','line_id'=>$purchase['items'][0]['id'],'expected_date'=>'2026-11-01','reason'=>'Odgođena isporuka'])->assertRedirect();
        $saved=$this->service->find($purchase['id']);$this->assertSame('2026-10-26',$saved['deadline']);$this->assertSame('2026-11-01',$saved['items'][0]['expected_date']);
        $this->assertGreaterThan(0,(new ProcurementCalculator())->deadline($saved)['days']);
        $this->actingAs(auth()->user(),'sanctum')->getJson('/api/work-orders')->assertForbidden();
    }

    public function test_supplier_document_upload_validation_and_download_ownership(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');$purchase=$this->service->create($this->payload(false),77);
        app(\App\Services\Procurement\ProcurementAccess::class)->bind(77,'Dobavljač','contact@example.test',1);$this->login('supplier');
        $url='/supplier/procurement/'.$purchase['id'].'/documents';
        $this->post($url,['type'=>'certificate','file'=>\Illuminate\Http\UploadedFile::fake()->create('bad.exe',1,'application/x-msdownload')])->assertSessionHasErrors('file');
        $this->post($url,['type'=>'certificate','file'=>\Illuminate\Http\UploadedFile::fake()->create('atest.pdf',10,'application/pdf')])->assertRedirect();
        $document=$this->service->find($purchase['id'])['documents'][0];
        $this->get($url.'/'.$document['id'])->assertOk();
        $this->service->change($purchase['id'],function(&$row){$row['documents'][0]['shared']=false;});
        $this->get($url.'/'.$document['id'])->assertNotFound();
    }

    public function test_complaint_tracks_receipt_and_terminal_resolution(): void
    {
        $purchase=$this->service->create($this->payload(false),77);$received=$this->service->receiveEvent($purchase['id'],$this->receivingPayload(),77);
        $complaint=$this->service->complaint($purchase['id'],['line_id'=>$purchase['items'][0]['id'],'receipt_id'=>$received['receipts'][0]['id'],'type'=>'quality','description'=>'Loš kvalitet','responsible'=>'Test','accepted'=>'4','rejected'=>'1'],77);
        $id=$complaint['complaints'][0]['id'];$resolved=$this->service->resolveComplaint($purchase['id'],$id,'resolved','Zamijenjen materijal',77);
        $this->assertSame('resolved',$resolved['complaints'][0]['status']);$this->assertNotNull($resolved['complaints'][0]['resolved_at']);
        $this->expectException(InvalidArgumentException::class);$this->service->resolveComplaint($purchase['id'],$id,'processing','Ponovo',77);
    }

    public function test_sync_adapter_retries_same_key_and_records_only_confirmed_success(): void
    {
        $purchase=$this->service->create($this->payload(false),77);
        $adapter=new class implements \App\Services\Procurement\ProcurementGateway { public array $keys=[];public function transfer(array $purchase,string $key):array{$this->keys[]=$key;if(count($this->keys)===1)throw new \RuntimeException('ERP unavailable');return ['confirmed'=>true,'key'=>'TEST-0205','number'=>'TEST-1'];} };
        $service=new ProcurementService($this->store,app(ProcurementSource::class),new ProcurementCalculator(),$adapter);
        $this->assertSame('error',$service->sync($purchase['id'],77)['sync_status']);$synced=$service->sync($purchase['id'],77);
        $this->assertSame('synced',$synced['sync_status']);$this->assertSame('TEST-0205',$synced['pantheon_key']);$this->assertSame($adapter->keys[0],$adapter->keys[1]);
        $service->sync($purchase['id'],77);$this->assertCount(2,$adapter->keys);
        $this->expectException(InvalidArgumentException::class);$service->edit($purchase['id'],$this->payload(false),77);
    }

    public function test_selection_persists_in_session_and_can_be_cleared(): void
    {
        $this->login();$this->postJson('/app/procurement/selection',['add'=>['source-a']])->assertOk()->assertJson(['count'=>1]);
        $this->postJson('/app/procurement/selection',['add'=>['source-b']])->assertOk()->assertJson(['count'=>2]);
        $this->post('/app/procurement/create',['from_session'=>1])->assertRedirect();$this->get('/app/procurement/create')->assertOk()->assertSee('20x30x40')->assertSee('50x60x70');
        $this->postJson('/app/procurement/selection',['clear'=>true])->assertOk()->assertJson(['count'=>0]);
    }

    public function test_material_table_has_separate_position_no_header_checkbox_or_variant_and_full_value_tooltips(): void
    {
        $this->login();$row=$this->sources['source-a']+['required_date'=>'2026-10-27','ordered'=>'0','received'=>'0','remaining'=>'10','missing'=>'10','purchases'=>[],'suppliers'=>[],'late'=>false,'state'=>'not_ordered'];
        app(ProcurementSource::class)->shouldReceive('requirements')->andReturn(new \Illuminate\Pagination\LengthAwarePaginator(collect([$row]),1,40));
        $response=$this->get('/app/procurement/materials');$response->assertOk()->assertSee('Poništi odabir')->assertDontSee('varijanta')->assertDontSee('Radni nalog /');
        $dom=new \DOMDocument();@$dom->loadHTML($response->getContent());$xpath=new \DOMXPath($dom);
        $this->assertSame(0,$xpath->query('//table[contains(@class,"procurement-compact")]/thead//input[@type="checkbox"]')->length);
        $this->assertSame(1,$xpath->query('//th/a[normalize-space(.)="Poz."]')->length);
        $this->assertSame(1,$xpath->query('//th/a[normalize-space(.)="Radni nalog"]')->length);
        $this->assertGreaterThan(0,$xpath->query('//td[@title="20x30x40"]')->length);
        $this->assertSame(1,$xpath->query('//*[@id="procurement-material-filter-panel" and contains(@class,"d-none")]')->length);
        $this->assertSame(1,$xpath->query('//input[@name="q" and @form="procurement-material-filters" and not(ancestor::*[@id="procurement-material-filter-panel"])]')->length);
        $this->assertSame(1,$xpath->query('//button[@data-filter-toggle="procurement-material-filter-panel" and @aria-expanded="false"]')->length);
        $this->assertSame(0,$xpath->query('//div[@class="d-flex gap-1 mb-2 flex-wrap"]/a[normalize-space(.)="Dobavljači i postavke"]')->length);
        $this->assertSame(1,$xpath->query('//*[@id="main-menu-navigation"]//a[normalize-space(.)="Dobavljači i postavke"]')->length);
        $this->assertStringNotContainsString('padding:.55rem',$response->getContent());
        $this->assertSame(0,$xpath->query('//*[contains(@class,"breadcrumb-right")]//button[@data-bs-toggle="dropdown"]')->length);
        $this->assertStringNotContainsString('>Todo<',$response->getContent());
    }

    public function test_all_procurement_templates_compile_as_valid_php(): void
    {
        foreach(glob(resource_path('views/content/apps/procurement/*.blade.php')) as $file){
            $compiled=app('blade.compiler')->compileString(file_get_contents($file));
            try{eval('if(false){?>'.$compiled.'<?php }');$this->assertTrue(true,basename($file));}catch(\ParseError $exception){$this->fail(basename($file).': '.$exception->getMessage());}
        }
    }

    public function test_viewer_cannot_edit_or_receive_and_receiver_cannot_manage(): void
    {
        $roles=$this->mock(\App\Support\CustomRoles::class);$roles->shouldReceive('find')->with('viewer')->andReturn(['slugs'=>['app-procurement']]);$roles->shouldReceive('find')->with('receiver')->andReturn(['slugs'=>['app-procurement-receive']]);
        $purchase=$this->service->create($this->payload(false),77);$this->login('viewer');
        $this->post('/app/procurement',$this->payload(false))->assertForbidden();$this->get('/app/procurement/'.$purchase['id'].'/receive-form')->assertForbidden();
        $this->login('receiver');$this->get('/app/procurement/'.$purchase['id'].'/receive-form')->assertOk();$this->post('/app/procurement/'.$purchase['id'].'/cancel')->assertForbidden();
    }

    public function test_local_edit_preserves_qr_identity_and_requires_authorization_for_overordering(): void
    {
        $data=$this->payload(false);$data['items'][0]['quantity']='4';$purchase=$this->service->create($data,77);
        $data['items'][0]['quantity']='5';$data['items'][0]['price']='3';$edited=$this->service->edit($purchase['id'],$data,77);
        $this->assertSame($purchase['items'][0]['id'],$edited['items'][0]['id']);$this->assertSame('17.55',$edited['totals']['gross']);
        $data=$this->payload(false);$data['items'][0]['quantity']='6';
        try{$this->service->create($data,77);$this->fail('Overordering accepted without authorization.');}catch(InvalidArgumentException $expected){$this->assertCount(1,$this->service->all());}
        $data['_allow_overorder']=true;$data['override_reason']='Sigurnosna zaliha';$over=$this->service->create($data,77);$this->assertSame('Sigurnosna zaliha',$over['override_reason']);
    }

    public function test_supplier_without_binding_is_denied_and_invalid_erp_confirmation_stays_failed(): void
    {
        $this->login('supplier');$this->get('/supplier/procurement')->assertForbidden();$this->login();
        $purchase=$this->service->create($this->payload(false),77);
        $adapter=new class implements \App\Services\Procurement\ProcurementGateway { public function transfer(array $purchase,string $key):array{return ['confirmed'=>false,'key'=>'fake','number'=>'fake'];} };
        $service=new ProcurementService($this->store,app(ProcurementSource::class),new ProcurementCalculator(),$adapter);$result=$service->sync($purchase['id'],77);
        $this->assertSame('error',$result['sync_status']);$this->assertNull($result['pantheon_key']);$this->assertNull($result['pantheon_number']);
    }

    public function test_receiving_http_cannot_bypass_warehouse_and_date_validation(): void
    {
        $this->login();$purchase=$this->service->create($this->payload(false),77);
        $this->post('/app/procurement/'.$purchase['id'].'/receive',['reference'=>'PR-1','received'=>['10']])->assertSessionHasErrors(['submission','warehouse','received_date','quantities']);
        $this->assertSame([],$this->service->find($purchase['id'])['receipts']);
        $this->post('/app/procurement/'.$purchase['id'].'/receive',$this->receivingPayload())->assertRedirect(route('app-procurement-show',$purchase['id']));
        $this->assertCount(1,$this->service->find($purchase['id'])['receipts']);
    }
}
