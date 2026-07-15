<?php

namespace Tests\Unit;

use App\Services\WorkOrder\WorkOrderClosingCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WorkOrderClosingCalculatorTest extends TestCase
{
    private WorkOrderClosingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new WorkOrderClosingCalculator();
    }

    public function test_zero_and_decimal_values_are_preserved(): void
    {
        $this->assertSame('0.000000', $this->calculator->normalizeNonNegative('0'));
        $this->assertSame('12.345678', $this->calculator->normalizeNonNegative('12,3456789'));
    }

    /** @dataProvider invalidValues */
    public function test_empty_invalid_and_negative_values_are_rejected(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->normalizeNonNegative($value, 'Vrijeme');
    }

    public function invalidValues(): array
    {
        return [[null], [''], ['  '], ['-0.01'], ['abc'], ['1e3']];
    }

    public function test_operation_cost_uses_minutes_per_unit_times_quantity_times_minute_price(): void
    {
        $result = $this->calculator->operation('30.250000', '1.510000', '2.000000');

        $this->assertSame('60.500000', $result['consumedMinutes']);
        $this->assertSame('45.677500', $result['costPerUnit']);
        $this->assertSame('91.355000', $result['totalCost']);
    }

    public function test_receipt_separates_material_operation_unit_and_total_costs(): void
    {
        $result = $this->calculator->receipt('30.700000', '90.600000', '2.000000');

        $this->assertSame('15.350000', $result['materialCostPerUnit']);
        $this->assertSame('45.300000', $result['operationCostPerUnit']);
        $this->assertSame('60.650000', $result['pricePerUnit']);
        $this->assertSame('121.300000', $result['totalPrice']);
    }
}
