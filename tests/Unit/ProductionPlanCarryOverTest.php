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

    public function test_week_filter_has_no_lower_date_limit_on_carry_over_and_preserves_other_filters(): void
    {
        // Compile SQL only: no database access or writes.
        $query = DB::connection('sqlsrv')->table('dbo.tHF_WOEx as wo')->where('wo.acIdent', 'ABC');
        $range = $this->invoke('applyWeekFilter', $query, 2026, 1);
        $this->assertSame('2025-12-29', $range['start']->toDateString());
        $this->assertSame(['ABC', '2025-12-29', '2026-01-04', '2025-12-29', 'F', 'I', 'Z'], $query->getBindings());
        $this->assertStringContainsString(' or ', $query->toSql());
        $this->assertStringContainsString('not in', $query->toSql());
    }

    public function test_excel_includes_cost_driver_numbering_and_preserves_priority_colours(): void
    {
        $row = (object) array_fill_keys(['rn', 'narucitelj', 'prioritet', 'datum', 'narudzba', 'pozicija', 'pocetak', 'kraj', 'proizvod', 'plan_kol', 'izr_kol', 'naziv', 'napomena', 'status_code'], 'test');
        $row->nositelj_troska = 'Plazma & Lakiranje';
        $row->progress = 0;
        $row->priority_row_color = 'yellow';
        $row->is_previous_week_open_order = true;
        $current = clone $row;
        $current->is_previous_week_open_order = false;
        $xml = simplexml_load_string($this->invoke('excelXml', [$row, $current], [], true, true, false));
        $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
        $rows = $xml->xpath('//ss:Table/ss:Row');
        $this->assertSame('Nositelj troška', (string) $rows[1]->Cell[14]->Data);
        $this->assertSame('Plazma & Lakiranje', (string) $rows[2]->Cell[14]->Data);
        $this->assertSame('1', (string) $rows[2]->Cell[0]->Data);
        $this->assertSame('2', (string) $rows[3]->Cell[0]->Data);
        $namespace = 'urn:schemas-microsoft-com:office:spreadsheet';
        $this->assertSame('red', (string) $rows[2]->Cell[0]->attributes($namespace)->StyleID);
        $this->assertSame('yellow', (string) $rows[3]->Cell[0]->attributes($namespace)->StyleID);
    }
}
