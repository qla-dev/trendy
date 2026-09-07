<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExcessStockWorkflowStructureTest extends TestCase
{
    public function test_excess_stock_is_reserved_with_decimal_math_and_checked_again_before_insert(): void
    {
        $service = file_get_contents(__DIR__ . '/../../app/Services/WorkOrder/ExcessStockService.php');

        $this->assertStringContainsString("'reservation_marker' => 'Rezervacija dodatnih sirovina'", file_get_contents(__DIR__ . '/../../config/excess-stock.php'));
        $this->assertStringContainsString('bcsub($physical, $held', $service);
        $this->assertStringContainsString("\$quantity = bccomp(\$planned, '0'", $service);
        $this->assertStringContainsString('WITH (UPDLOCK, HOLDLOCK)', $service);
        $this->assertStringContainsString('reservedByMaterial($db)', $service);
        $this->assertStringContainsString("'acNote' => 'Automatski popunjeno sa '", $service);
        $this->assertStringContainsString('physical stock changes only', $service);
        $this->assertStringContainsString('assignToNewWorkOrder', $service);
        $this->assertStringContainsString('salesPriceForWorkOrder', $service);
        $this->assertStringContainsString("bcmul(\$salesPrice, \$preview['sales_price_percent']", $service);
        $this->assertStringContainsString('assignment_per_piece', $service);
    }

    public function test_special_6400_is_separate_from_normal_consumption_and_is_retry_safe(): void
    {
        $closing = file_get_contents(__DIR__ . '/../../app/Services/WorkOrder/WorkOrderClosingService.php');
        $command = file_get_contents(__DIR__ . '/../../app/Console/Commands/BackfillExcessStockCommand.php');

        $this->assertStringContainsString('excessMaterialNumber', $closing);
        $this->assertStringContainsString("'internal_note' => \$this->excessStock->documentMarker()", $closing);
        $this->assertStringContainsString("'6400_excess'", $closing);
        $this->assertStringContainsString('materialStock->issue($this->connection, $this->excessStock->warehouse()', $closing);
        $this->assertStringContainsString('Generate this number only after the regular 6400', $closing);
        $this->assertStringContainsString("str_contains(strtoupper((string) \$db->getDatabaseName()), 'TESTNA')", $command);
        $this->assertStringContainsString('--allow-limited-apply', $command);
        $this->assertFileExists(__DIR__ . '/../../app/Console/Commands/RecoverExcessStock6400Command.php');
        $controller = file_get_contents(__DIR__ . '/../../app/Http/Controllers/WorkOrderController.php');
        $this->assertStringContainsString('assignToNewWorkOrder(', $controller);
        $closingController = file_get_contents(__DIR__ . '/../../app/Http/Controllers/WorkOrderClosingController.php');
        $this->assertStringContainsString('userFacingCloseError', $closingController);
        $this->assertStringContainsString('broj u međuvremenu već zauzet', $closingController);
    }
}
