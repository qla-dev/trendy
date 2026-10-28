<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductionPlanController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProductionPlanCarryOverTest extends TestCase
{
    public function test_cost_driver_edit_uses_exact_catalogue_code(): void
    {
        $catalogue = \Mockery::mock();
        $catalogue->shouldReceive('where')->once()->with('acCostDrv', 'Lakiranje')->andReturnSelf();
        $catalogue->shouldReceive('value')->once()->with('acCostDrv')->andReturn(' Lakiranje');
        $orders = \Mockery::mock();
        $orders->shouldReceive('where')->once()->with('acKey', 'RN1')->andReturnSelf();
        $orders->shouldReceive('update')->once()->withArgs(function ($values) {
            return $values['acCostDrv'] === ' Lakiranje' && $values['anUserChg'] === 7;
        })->andReturn(1);
        DB::shouldReceive('table')->once()->with('dbo.tHE_CostDrv')->andReturn($catalogue);
        DB::shouldReceive('table')->once()->with('dbo.tHF_WOEx')->andReturn($orders);
        $request = Request::create('/', 'POST', ['field' => 'nositelj_troska', 'value' => 'Lakiranje']);
        $request->setUserResolver(fn () => (object) ['role' => 'admin', 'id' => 7]);
        $this->assertSame(200, (new ProductionPlanController())->updateField($request, 'RN1')->getStatusCode());
    }

    public function test_cost_driver_edit_rejects_unknown_catalogue_entries(): void
    {
        $catalogue = \Mockery::mock();
        $catalogue->shouldReceive('where')->once()->with('acCostDrv', 'Invalid')->andReturnSelf();
        $catalogue->shouldReceive('value')->once()->with('acCostDrv')->andReturnNull();
        DB::shouldReceive('table')->once()->with('dbo.tHE_CostDrv')->andReturn($catalogue);
        $request = Request::create('/', 'POST', ['field' => 'nositelj_troska', 'value' => 'Invalid']);
        $request->setUserResolver(fn () => (object) ['role' => 'admin', 'id' => 7]);
        $this->assertSame(422, (new ProductionPlanController())->updateField($request, 'RN1')->getStatusCode());
    }

    private function invoke(string $method, ...$arguments)
    {
        $controller = new ProductionPlanController();
        $reflection = new \ReflectionMethod($controller, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($controller, ...$arguments);
    }

    public function test_all_earlier_open_orders_are_carry_over_but_closed_and_current_orders_are_not(): void
    {
        $start = Carbon::parse('2026-08-24'); // ISO week 35
        foreach (['2026-08-23', '2026-06-15', '2025-12-01'] as $date) {
            $this->assertTrue($this->invoke('isEarlierWeekOpenOrder', (object) ['pocetak' => $date, 'status_code' => 'O'], $start));
            foreach (['F', 'I', 'Z', ' f '] as $status) {
                $this->assertFalse($this->invoke('isEarlierWeekOpenOrder', (object) ['pocetak' => $date, 'status_code' => $status], $start));
            }
        }
        foreach (['2026-08-24', '2026-08-30', '2026-08-31', null] as $date) {
            $this->assertFalse($this->invoke('isEarlierWeekOpenOrder', (object) ['pocetak' => $date, 'status_code' => 'O'], $start));
        }
    }

    public function test_week_filter_limits_carry_over_to_the_selected_calendar_year(): void
    {
        // Compile SQL only: no database access or writes.
        $query = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo')->where('wo.acIdent', 'ABC');
        $range = $this->invoke('applyWeekFilter', $query, 2026, 1);
        $this->assertSame('2025-12-29', $range['start']->toDateString());
        $this->assertSame(['ABC', '2025-12-29', '2026-01-04', '2026-01-01', '2025-12-29', 'F', 'I', 'Z'], $query->getBindings());
        $this->assertStringContainsString(' or ', $query->toSql());
        $this->assertStringContainsString('not in', $query->toSql());
    }

    public function test_default_view_uses_current_business_week_and_selected_week_keeps_its_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00','Europe/Sarajevo'));
        try {
            $start=$this->invoke('carryOverStart',null);
            $this->assertSame('2026-10-26 00:00:00',$start->format('Y-m-d H:i:s'));
            $this->assertTrue($this->invoke('isEarlierWeekOpenOrder',(object)['pocetak'=>'2026-10-25','status_code'=>'O'],$start));
            $this->assertFalse($this->invoke('isEarlierWeekOpenOrder',(object)['pocetak'=>'2026-10-26','status_code'=>'O'],$start));
            $this->assertFalse($this->invoke('isEarlierWeekOpenOrder',(object)['pocetak'=>'2026-10-25','status_code'=>'Z'],$start));
            $selected=Carbon::parse('2026-09-07');
            $this->assertSame('2026-09-07',$this->invoke('carryOverStart',['start'=>$selected])->toDateString());
            $this->assertSame('2026-09-07',$selected->toDateString());
        } finally { Carbon::setTestNow(); }
    }

    public function test_default_sort_puts_unfinished_prior_weeks_first_before_pagination(): void
    {
        $query=DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applyCarryOverOrdering',$query,Carbon::parse('2026-10-26'),'wo.adSchedStartTime','desc');
        $query->orderBy('wo.adSchedStartTime','desc')->offset(0)->limit(25);
        $sql=$query->toSql();
        $this->assertSame(['2026-10-26'],$query->getBindings());
        $this->assertStringContainsString("COALESCE(UPPER(LTRIM(RTRIM(wo.acStatusMF))), '') NOT IN ('F', 'I', 'Z')",$sql);
        $this->assertLessThan(strpos($sql,'[wo].[adSchedStartTime] desc'),strpos($sql,'CASE WHEN'));

        $custom=DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applyCarryOverOrdering',$custom,Carbon::parse('2026-10-26'),'wo.acKeyView','asc');
        $this->assertSame([],$custom->getBindings());
        $this->assertStringNotContainsString('CASE WHEN',$custom->toSql());
    }

    /** @dataProvider deliveryDateFilterKeys */
    public function test_each_delivery_date_filter_uses_delivery_deadline_for_red_rows(string $key,string $customer,bool $from): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00','Europe/Sarajevo'));
        try {
            $filters=[$key=>$from?'2026-10-05':'2026-10-31'];
            $start=Carbon::parse('2026-10-05');
            $row=(object)['narucitelj'=>$customer,'pocetak'=>'2026-10-20','datum_isporuke'=>$from?'2026-10-04':'2026-10-27','status_code'=>'O'];
            $this->assertTrue($this->invoke('isLateOrder',$row,$start,$filters));
            $row->datum_isporuke=$from?'2026-10-05':'2026-10-28';
            $this->assertFalse($this->invoke('isLateOrder',$row,$start,$filters));
            $row->pocetak='2026-09-01';$row->datum_isporuke=null;
            $this->assertFalse($this->invoke('isLateOrder',$row,$start,$filters));
            $row->datum_isporuke='2026-09-01';
            foreach(['F','I','Z'] as $closed){$row->status_code=$closed;$this->assertFalse($this->invoke('isLateOrder',$row,$start,$filters));}
        } finally { Carbon::setTestNow(); }
    }

    public function deliveryDateFilterKeys(): array
    {
        return [['isporuka_od','OTHER',true],['isporuka_do','OTHER',false],['trendy_germany_date_from','Trendy Germany GmbH-3',true],['trendy_germany_date_to','Trendy Germany',false],['grob_date_from','OTHER',true],['grob_date_to','GROB-WERKE',false]];
    }

    public function test_customer_deadline_boundaries_are_independent_and_keep_week_rule_for_unfiltered_group(): void
    {
        $start=Carbon::parse('2026-10-05');
        $filters=['trendy_germany_date_from'=>'2026-10-15','grob_date_from'=>'2026-10-01'];
        $row=(object)['narucitelj'=>'Trendy Germany','pocetak'=>'2026-10-20','datum_isporuke'=>'2026-10-10','status_code'=>'O'];
        $this->assertTrue($this->invoke('isLateOrder',$row,$start,$filters));
        $row->narucitelj='OTHER';$this->assertFalse($this->invoke('isLateOrder',$row,$start,$filters));
        $row->pocetak='2026-09-20';$this->assertTrue($this->invoke('isLateOrder',$row,$start,['trendy_germany_date_from'=>'2026-10-15']));
        $row->pocetak='2026-10-20';$this->assertFalse($this->invoke('isLateOrder',$row,$start,[]));
    }

    public function test_delivery_range_includes_only_unfinished_earlier_deadlines_and_keeps_customer_conditions_grouped(): void
    {
        $query=DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo')->where('wo.acConsignee','OTHER');
        $this->invoke('applyDeliveryRange',$query,'order_item.adDeliveryDeadline','2026-10-05','2026-10-11');
        $this->assertSame(['OTHER','2026-10-05','2026-10-11','2026-10-05'],$query->getBindings());
        $sql=$query->toSql();
        $this->assertStringContainsString(' or ',$sql);
        $this->assertStringContainsString("COALESCE(UPPER(LTRIM(RTRIM(wo.acStatusMF))), '') NOT IN ('F', 'I', 'Z')",$sql);
        $this->assertStringContainsString('[wo].[acConsignee] = ? and (',$sql);
    }

    public function test_delivery_filtered_late_rows_sort_first_even_when_another_column_is_selected(): void
    {
        $query=DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applyCarryOverOrdering',$query,Carbon::parse('2026-10-05'),'wo.acKeyView','asc',['trendy_germany_date_from'=>'2026-10-15','grob_date_from'=>'2026-10-01']);
        $query->orderBy('wo.acKeyView','asc');
        $sql=$query->toSql();
        $this->assertSame(['2026-10-15','2026-10-01'],$query->getBindings());
        $this->assertLessThan(strpos($sql,'[wo].[acKeyView] asc'),strpos($sql,'CASE WHEN'));
        $this->assertStringContainsString("LIKE 'TRENDY GERMANY%'",$sql);
        $this->assertStringContainsString("NOT LIKE 'TRENDY GERMANY%'",$sql);
    }

    public function test_expired_delivery_date_is_red_for_every_status_and_priority_without_changing_carryover(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00','Europe/Sarajevo'));
        try {
            $start=Carbon::parse('2026-10-12');
            foreach(['O','E','P','R','F','I','Z','C',null] as $status) foreach(['teal','yellow','orange','green','purple','grey'] as $priority) {
                $row=(object)['status_code'=>$status,'priority_row_color'=>$priority,'pocetak'=>'2026-10-12','datum_isporuke'=>'2026-10-16','narucitelj'=>'OTHER'];
                $this->assertFalse($this->invoke('isLateOrder',$row,$start,[]));
                $this->assertTrue($this->invoke('isLateForDisplay',$row,$start,[]));
            }
        } finally { Carbon::setTestNow(); }
    }

    public function test_today_and_future_delivery_dates_keep_priority_unless_already_carryover(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00','Europe/Sarajevo'));
        try {
            $start=Carbon::parse('2026-10-26');
            foreach(['2026-10-28','2026-11-05',null] as $deadline) {
                $row=(object)['status_code'=>'O','pocetak'=>'2026-10-28','datum_isporuke'=>$deadline];
                $this->assertFalse($this->invoke('isLateForDisplay',$row,$start,[]));
            }
            $row=(object)['status_code'=>'Z','pocetak'=>'2026-10-06','datum_isporuke'=>'2026-11-05'];
            $this->assertTrue($this->invoke('isLateForDisplay',$row,$start,[]));
        } finally { Carbon::setTestNow(); }
    }

    public function test_default_plan_excludes_closed_orders_and_selected_status_can_show_them(): void
    {
        $query = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applyStatusFilter', $query, []);
        $this->assertStringContainsString('not in', $query->toSql());
        $this->assertSame(['F', 'I', 'Z'], $query->getBindings());

        $closed = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applyStatusFilter', $closed, ['status_rn' => 'Z']);
        $this->assertStringNotContainsString('not in', $closed->toSql());
        $this->assertSame(['Z'], $closed->getBindings());

        $all = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applyStatusFilter', $all, ['status_rn' => '__all__']);
        $this->assertSame([], $all->getBindings());
    }

    public function test_customer_date_ranges_are_grouped_and_keep_other_filters(): void
    {
        $cases = [
            'GROB only' => [['grob_date_from' => '2026-09-01', 'grob_date_to' => '2026-09-15'], ['GROB%', '2026-09-01', '2026-09-15', '2026-09-01', 'TRENDY GERMANY%']],
            'Germany only' => [['trendy_germany_date_from' => '2026-09-10', 'trendy_germany_date_to' => '2026-09-30'], ['GROB%', 'TRENDY GERMANY%', '2026-09-10', '2026-09-30', '2026-09-10']],
            'both' => [['grob_date_from' => '2026-09-01', 'grob_date_to' => '2026-09-15', 'trendy_germany_date_from' => '2026-09-10', 'trendy_germany_date_to' => '2026-09-30'], ['GROB%', '2026-09-01', '2026-09-15', '2026-09-01', 'TRENDY GERMANY%', '2026-09-10', '2026-09-30', '2026-09-10']],
            'from only' => [['grob_date_from' => '2026-09-01'], ['GROB%', '2026-09-01', '2026-09-01', 'TRENDY GERMANY%']],
            'to only' => [['trendy_germany_date_to' => '2026-09-30'], ['GROB%', 'TRENDY GERMANY%', '2026-09-30']],
        ];

        foreach ($cases as $name => [$filters, $expected]) {
            $query = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo')->where('wo.acIdent', 'ABC');
            $this->invoke('applyCustomerDateFilters', $query, $filters);
            $this->invoke('applyStatusFilter', $query, ['status_rn' => 'O']);
            $this->assertSame(array_merge(['ABC'], $expected, ['O']), $query->getBindings(), $name);
            $this->assertSame(2, substr_count($query->toSql(), 'UPPER(LTRIM(RTRIM(ISNULL(wo.acConsignee, wo.acReceiver)))) LIKE ?'), $name);
            $this->assertStringContainsString(' or ', $query->toSql(), $name);
            $this->assertStringContainsString('wo].[acIdent', $query->toSql(), $name);
            $this->assertStringNotContainsString('adSchedStartTime', $query->toSql(), $name);
            $this->assertSame(count(array_intersect_key($filters, array_flip(['grob_date_from', 'grob_date_to', 'trendy_germany_date_from', 'trendy_germany_date_to']))) + count(array_intersect_key($filters,array_flip(['grob_date_from','trendy_germany_date_from']))), substr_count($query->toSql(), 'CAST(COALESCE(order_item.adDeliveryDeadline, order_item.adDeliveryDate, sales_order.adDeliveryDeadline, sales_order.adDeliveryDate) AS date)'), $name);
        }

        $query = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo')->where('wo.acIdent', 'ABC');
        $this->invoke('applyCustomerDateFilters', $query, []);
        $this->assertSame(['ABC'], $query->getBindings());
        $this->assertStringNotContainsString('acConsignee', $query->toSql());
    }

    public function test_customer_selections_split_germany_and_exclude_it_from_other_customers(): void
    {
        $query = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applyCustomerDateFilters', $query, [
            'trendy_germany_customers' => ['germany_gmbh'],
            'grob_customers' => ['GROB-WERKE', 'OTHER CUSTOMER'],
            'grob_date_from' => '2026-10-01',
        ]);
        $this->assertSame(['TRENDY GERMANY%', 'GROB-WERKE', 'OTHER CUSTOMER', '2026-10-01', '2026-10-01', 'TRENDY GERMANY%', '%GMBH%'], $query->getBindings());
        $this->assertStringContainsString(' NOT LIKE ?', $query->toSql());
        $this->assertStringContainsString(' in (?, ?)', $query->toSql());

        $none = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applyCustomerDateFilters', $none, [
            'trendy_germany_customers' => '__none__', 'grob_customers' => '__none__',
        ]);
        $this->assertSame(2, substr_count($none->toSql(), '1 = 0'));

        $germany = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applyCustomerDateFilters', $germany, [
            'trendy_germany_customers' => ['germany'], 'grob_customers' => '__none__',
        ]);
        $this->assertSame(['TRENDY GERMANY%', '%GMBH%'], $germany->getBindings());
        $this->assertStringContainsString(' NOT LIKE ?', $germany->toSql());
    }

    public function test_priority_selection_filter_supports_multiple_values_and_select_none(): void
    {
        $query = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applySelectionFilters', $query, [
            'prioritet' => ['1', '5'],
        ]);
        $this->assertSame(['1', '5'], $query->getBindings());
        $this->assertStringContainsString('[wo].[anPriority] in', $query->toSql());

        $none = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applySelectionFilters', $none, ['prioritet' => '__none__']);
        $this->assertStringContainsString('1 = 0', $none->toSql());

        $all = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo');
        $this->invoke('applySelectionFilters', $all, ['prioritet' => '']);
        $this->assertSame([], $all->getBindings());
    }

    public function test_excel_includes_cost_driver_without_numbering_and_preserves_priority_colours(): void
    {
        $row = (object) array_fill_keys(['rn', 'narucitelj', 'prioritet', 'datum', 'narudzba', 'broj_narudzbe_kupca', 'pozicija', 'pocetak', 'kraj', 'datum_isporuke', 'proizvod', 'plan_kol', 'izr_kol', 'naziv', 'napomena', 'status_code'], 'test');
        $row->pocetak = '2026-09-24';
        $row->kraj = '2026-09-24';
        $row->datum_isporuke = '2026-10-15';
        $row->nositelj_troska = 'Plazma & Lakiranje';
        $row->progress = 0;
        $row->priority_row_color = 'yellow';
        $row->is_previous_week_open_order = true;
        $current = clone $row;
        $current->is_previous_week_open_order = false;
        $overdue = clone $current;
        $overdue->priority_row_color = 'teal';
        $overdue->status_code = 'Z';
        $overdue->is_late = true;
        $xml = simplexml_load_string($this->invoke('excelXml', [$row, $current, $overdue], [], true, true, false));
        $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
        $rows = $xml->xpath('//ss:Table/ss:Row');
        $this->assertSame('Kraj termin', (string) $rows[1]->Cell[9]->Data);
        $this->assertSame('Datum isporuke', (string) $rows[1]->Cell[10]->Data);
        $this->assertSame('24.09.2026', (string) $rows[2]->Cell[9]->Data);
        $this->assertSame('15.10.2026', (string) $rows[2]->Cell[10]->Data);
        $this->assertSame('Nositelj troška', (string) $rows[1]->Cell[15]->Data);
        $this->assertSame('Plazma & Lakiranje', (string) $rows[2]->Cell[15]->Data);
        $this->assertSame('Napredak', (string) $rows[1]->Cell[0]->Data);
        $this->assertSame('Šifra-crtež', (string) $rows[1]->Cell[18]->Data);
        $namespace = 'urn:schemas-microsoft-com:office:spreadsheet';
        $this->assertSame('red', (string) $rows[2]->Cell[0]->attributes($namespace)->StyleID);
        $this->assertSame('yellow', (string) $rows[3]->Cell[0]->attributes($namespace)->StyleID);
        $this->assertSame('red', (string) $rows[4]->Cell[0]->attributes($namespace)->StyleID);
    }
}
