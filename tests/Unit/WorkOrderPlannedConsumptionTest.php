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

    public function test_build_planned_consumption_stock_adjustments_keeps_negative_deltas_for_stock_readjustments(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('buildPlannedConsumptionStockAdjustments');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            [
                'item_kind' => 'materials',
                'acIdent' => 'CK2020S235JRC',
                'stock_consumed_qty' => -10,
            ],
        ]);

        $this->assertSame([
            [
                'material_code' => 'CK2020S235JRC',
                'value' => -10.0,
            ],
        ], $result);
    }

    public function test_resolve_released_material_quantity_uses_scanned_consumed_quantity_first(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('resolveReleasedMaterialQuantity');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'stock_consumed_qty' => 9,
            'anQty1' => 7,
            'anQty' => 7,
            'anPlanQty' => 25.0866,
        ]);

        $this->assertSame(9.0, $result);
    }

    public function test_planned_consumption_component_selection_key_prefers_row_uid_when_present(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('plannedConsumptionComponentSelectionKey');
        $method->setAccessible(true);

        $result = $method->invoke($controller, 10, 'CK2020S235JRC', 'fine-adjust-row-abc');

        $this->assertSame('row|fine-adjust-row-abc', $result);
    }

    public function test_find_duplicate_planned_consumption_positions_returns_sorted_unique_duplicates(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('findDuplicatePlannedConsumptionPositions');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            ['anNo' => 5],
            ['anNo' => 3],
            ['anNo' => 5],
            ['anNo' => 3],
            ['anNo' => 8],
        ]);

        $this->assertSame([3, 5], $result);
    }

    public function test_find_duplicate_planned_consumption_positions_ignores_invalid_positions(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('findDuplicatePlannedConsumptionPositions');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            ['anNo' => null],
            ['anNo' => ''],
            ['anNo' => 0],
            ['anNo' => 2.5],
            ['anNo' => 7],
            ['anNo' => 7],
        ]);

        $this->assertSame([7], $result);
    }

    public function test_resolve_work_order_item_insert_no_prefers_available_bom_position(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('resolveWorkOrderItemInsertNo');
        $method->setAccessible(true);

        $result = $method->invoke($controller, 10, 1, []);

        $this->assertSame(10, $result);
    }

    public function test_resolve_work_order_item_insert_no_falls_back_when_bom_position_is_used(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('resolveWorkOrderItemInsertNo');
        $method->setAccessible(true);

        $result = $method->invoke($controller, 10, 1, [
            1 => true,
            10 => true,
        ]);

        $this->assertSame(2, $result);
    }

    public function test_pantheon_basic_sastavnica_copy_parameters_enable_full_pantheon_copy(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('pantheonBasicSastavnicaCopyParameters');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '2660000003564', '3276631', [
            ['anVariant' => 0],
        ], [
            'anVariant' => 0,
        ], 1);

        $this->assertSame([
            '3276631',
            0,
            '2660000003564',
            0,
            1.0,
            'T',
            'T',
            'T',
            'T',
            'P',
            1,
            'T',
            'F',
        ], $result);
    }

    public function test_resolve_work_order_item_quantity_payload_for_manual_save_leaves_plan_quantity_to_pantheon(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('resolveWorkOrderItemQuantityPayloadForSave');
        $method->setAccessible(true);

        $result = $method->invoke($controller, 'manual', 50.0, 0.0);

        $this->assertSame([
            'anQty' => 0.0,
            'anQty1' => 50.0,
        ], $result);
        $this->assertArrayNotHasKey('anPlanQty', $result);
    }

    public function test_resolve_work_order_item_quantity_payload_for_barcode_save_keeps_actual_quantity(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('resolveWorkOrderItemQuantityPayloadForSave');
        $method->setAccessible(true);

        $result = $method->invoke($controller, 'barcode', 50.0, 300.0);

        $this->assertSame([
            'anPlanQty' => 50.0,
            'anQty' => 300.0,
            'anQty1' => 300.0,
        ], $result);
    }

    public function test_resolve_operation_type_for_save_maps_operation_catalog_marker_to_pantheon_task_type(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('resolveOperationTypeForSave');
        $method->setAccessible(true);

        $this->assertSame('D', $method->invoke($controller, 'O', 'OPR'));
        $this->assertSame('D', $method->invoke($controller, '', 'OPR'));
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

    public function test_work_order_item_quantity_prefers_non_zero_an_qty1_over_plan_quantity(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('workOrderItemQuantity');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'anPlanQty' => 4.6,
            'anQty' => 0,
            'anQty1' => 7,
        ]);

        $this->assertSame(7.0, $result);
    }

    public function test_work_order_item_quantity_falls_back_to_plan_quantity_when_actual_quantities_are_zero(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('workOrderItemQuantity');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'anPlanQty' => 4.6,
            'anQty' => 0,
            'anQty1' => 0,
        ]);

        $this->assertSame(4.6, $result);
    }

    public function test_work_order_item_actual_quantity_uses_an_qty1_when_an_qty_is_zero(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('workOrderItemActualQuantity');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'anQty' => 0,
            'anQty1' => 7,
        ]);

        $this->assertSame(7.0, $result);
    }

    public function test_map_item_row_displays_an_qty1_when_plan_quantity_is_stale(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('mapItemRow');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'anQId' => 55663,
            'anNo' => 2,
            'acKey' => '2660000003492',
            'acIdent' => 'ALPL5083',
            'acDescr' => 'Aluminij ploca',
            'anPlanQty' => 4.6,
            'anQty' => 0,
            'anQty1' => 7,
            'acUM' => 'KG',
        ]);

        $this->assertSame(7, $result['kolicina']);
    }

    public function test_work_order_item_note_column_prefers_ac_note_over_ac_field_se(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('workOrderItemNoteColumn');
        $method->setAccessible(true);

        $this->assertSame('acNote', $method->invoke($controller, ['acFieldSE', 'acNote']));
    }

    public function test_work_order_item_display_note_prefers_ac_note_over_ac_field_se(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('workOrderItemDisplayNote');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'acNote' => 'existing note value',
            'acFieldSE' => 'old field value',
        ]);

        $this->assertSame('existing note value', $result);
    }

    public function test_planned_consumption_has_complete_dimensions_requires_all_positive_values(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('plannedConsumptionHasCompleteDimensions');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'dim1' => '20',
            'dim2' => '495',
            'dim3' => '905',
        ]);

        $this->assertTrue($result);
    }

    public function test_planned_consumption_has_complete_dimensions_rejects_missing_values(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('plannedConsumptionHasCompleteDimensions');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'dim1' => '20',
            'dim2' => '495',
            'dim3' => '',
        ]);

        $this->assertFalse($result);
    }

    public function test_work_order_item_statement_adjusts_stock_skips_pending_markers(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('workOrderItemStatementAdjustsStock');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, 'PLANNED_BOM_PENDING'));
        $this->assertFalse($method->invoke($controller, 'PLANNED_RAW_PENDING'));
    }

    public function test_work_order_item_row_should_restore_stock_on_remove_skips_pending_markers(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('workOrderItemRowShouldRestoreStockOnRemove');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, ['acStatement' => 'PLANNED_BOM_PENDING'], true));
        $this->assertFalse($method->invoke($controller, ['acStatement' => 'PLANNED_RAW_PENDING'], true));
    }
}
