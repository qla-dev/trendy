<?php

namespace Tests\Unit;

use App\Services\WorkOrder\WorkOrderClosingCalculator;
use PHPUnit\Framework\TestCase;

class WorkOrderClosingCalculatorTest extends TestCase
{
    public function test_it_normalizes_sql_server_scientific_decimal_notation_for_bcmath(): void
    {
        $this->assertSame('0.095000', WorkOrderClosingCalculator::decimal('9.5E-2'));
        $this->assertSame('1.955249', WorkOrderClosingCalculator::decimal('1.955249'));
    }
}
