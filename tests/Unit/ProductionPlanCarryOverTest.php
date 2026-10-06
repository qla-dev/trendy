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
            'GROB only' => [['grob_date_from' => '2026-09-01', 'grob_date_to' => '2026-09-15'], ['GROB%', '2026-09-01', '2026-09-15', 'TRENDY GERMANY%']],
            'Germany only' => [['trendy_germany_date_from' => '2026-09-10', 'trendy_germany_date_to' => '2026-09-30'], ['GROB%', 'TRENDY GERMANY%', '2026-09-10', '2026-09-30']],
            'both' => [['grob_date_from' => '2026-09-01', 'grob_date_to' => '2026-09-15', 'trendy_germany_date_from' => '2026-09-10', 'trendy_germany_date_to' => '2026-09-30'], ['GROB%', '2026-09-01', '2026-09-15', 'TRENDY GERMANY%', '2026-09-10', '2026-09-30']],
            'from only' => [['grob_date_from' => '2026-09-01'], ['GROB%', '2026-09-01', 'TRENDY GERMANY%']],
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
            $this->assertSame(count(array_intersect_key($filters, array_flip(['grob_date_from', 'grob_date_to', 'trendy_germany_date_from', 'trendy_germany_date_to']))), substr_count($query->toSql(), 'CAST(COALESCE(order_item.adDeliveryDeadline, order_item.adDeliveryDate, sales_order.adDeliveryDeadline, sales_order.adDeliveryDate) AS date)'), $name);
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
        $this->assertSame(['TRENDY GERMANY%', 'GROB-WERKE', 'OTHER CUSTOMER', '2026-10-01', 'TRENDY GERMANY%', '%GMBH%'], $query->getBindings());
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
        $xml = simplexml_load_string($this->invoke('excelXml', [$row, $current], [], true, true, false));
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
    }
}
