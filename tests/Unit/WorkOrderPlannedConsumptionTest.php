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

    public function test_planned_consumption_component_selection_key_prefers_row_uid_when_present(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('plannedConsumptionComponentSelectionKey');
        $method->setAccessible(true);

        $result = $method->invoke($controller, 10, 'CK2020S235JRC', 'fine-adjust-row-abc');

        $this->assertSame('row|fine-adjust-row-abc', $result);
    }

    public function test_build_removed_planned_consumption_stock_adjustment_returns_reverse_delta_for_materials(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('buildRemovedPlannedConsumptionStockAdjustment');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'acIdent' => 'CK2020S235JRC',
            'acOperationType' => 'M',
            'anPlanQty' => 25,
        ], 'WH1');

        $this->assertSame([
            'material_code' => 'CK2020S235JRC',
            'value' => -25.0,
            'warehouse' => 'WH1',
        ], $result);
    }

    public function test_build_removed_planned_consumption_stock_adjustment_skips_non_material_rows(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('buildRemovedPlannedConsumptionStockAdjustment');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'acIdent' => 'OP-001',
            'acOperationType' => 'O',
            'anPlanQty' => 25,
        ], 'WH1');

        $this->assertNull($result);
    }
}
