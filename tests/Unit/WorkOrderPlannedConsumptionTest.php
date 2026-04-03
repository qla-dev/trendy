<?php

namespace Tests\Unit;

use App\Http\Controllers\WorkOrderController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class WorkOrderPlannedConsumptionTest extends TestCase
{
    public function test_build_planned_consumption_stock_adjustments_uses_explicit_consumed_quantity_only(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('buildPlannedConsumptionStockAdjustments');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [[
            'item_kind' => 'materials',
            'acIdent' => 'CK2020S235JRC',
            'stock_consumed_qty' => 25,
            'anPlanQty' => 125,
        ]]);

        $this->assertSame([
            [
                'material_code' => 'CK2020S235JRC',
                'value' => 25.0,
            ],
        ], $result);
    }

    public function test_build_planned_consumption_stock_adjustments_skips_rows_without_consumed_quantity(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('buildPlannedConsumptionStockAdjustments');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [[
            'item_kind' => 'materials',
            'acIdent' => 'CK2020S235JRC',
            'anPlanQty' => 25,
        ]]);

        $this->assertSame([], $result);
    }

    public function test_build_planned_consumption_stock_adjustments_aggregates_duplicate_material_rows(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('buildPlannedConsumptionStockAdjustments');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            [
                'item_kind' => 'materials',
                'acIdent' => 'CK2020S235JRC',
                'stock_consumed_qty' => 10,
            ],
            [
                'item_kind' => 'materials',
                'acIdent' => 'CK2020S235JRC',
                'stock_consumed_qty' => 15,
            ],
        ]);

        $this->assertSame([
            [
                'material_code' => 'CK2020S235JRC',
                'value' => 25.0,
            ],
        ], $result);
    }
}
