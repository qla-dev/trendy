<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductionPlanController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionPlanDetailsTest extends TestCase
{
    public function test_creator_filter_uses_stored_user_id_without_requiring_notes(): void
    {
        $query = \Mockery::mock();
        $query->shouldReceive('where')->once()->with('wo.anUserIns', 7)->andReturnSelf();
        $method = new \ReflectionMethod(ProductionPlanController::class, 'applyCreatorFilter');
        $method->setAccessible(true);
        $method->invoke(new ProductionPlanController(), $query, ['kreirao' => '7']);
    }

    public function test_empty_creator_filter_preserves_all_orders(): void
    {
        $query = \Mockery::mock();
        $query->shouldNotReceive('where');
        $method = new \ReflectionMethod(ProductionPlanController::class, 'applyCreatorFilter');
        $method->setAccessible(true);
        $method->invoke(new ProductionPlanController(), $query, ['kreirao' => '']);
    }

    public function test_details_are_requested_by_the_exact_work_order_key(): void
    {
        $orders = \Mockery::mock();
        $orders->shouldReceive('where')->once()->with('acKey', 'RN1')->andReturnSelf();
        $orders->shouldReceive('exists')->once()->andReturnTrue();
        DB::shouldReceive('table')->once()->with('dbo.tHF_WOEx')->andReturn($orders);
        $mapper = \Mockery::mock(WorkOrderController::class);
        $mapper->shouldReceive('productionPlanDetails')->once()->with('RN1')->andReturn([
            'operations' => [['pozicija' => '1']],
        ]);
        $this->app->instance(WorkOrderController::class, $mapper);
        $response = (new ProductionPlanController())->operations('RN1');
        $this->assertSame(['operations' => [['pozicija' => '1']]], $response->getData(true)['data']);
    }
}
