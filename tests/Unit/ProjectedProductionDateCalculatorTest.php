<?php

namespace Tests\Unit;

use App\Services\WorkOrder\ProjectedProductionDateCalculator;
use Tests\TestCase;

class ProjectedProductionDateCalculatorTest extends TestCase
{
    private ProjectedProductionDateCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ProjectedProductionDateCalculator();
    }

    public function test_empty_protection_uses_two_weeks(): void
    {
        $this->assertSame('2026-04-07', $this->calculator->calculate('2026-04-21'));
    }

    public function test_regular_protection_uses_three_weeks(): void
    {
        $this->assertSame('2026-03-31', $this->calculator->calculate('2026-04-21', 'Lakiranje', 2));
    }

    public function test_plasma_lacquering_uses_four_weeks(): void
    {
        $this->assertSame('2026-03-24', $this->calculator->calculate('2026-04-21', 'plazma+lakiranje', 37));
    }

    public function test_plasma_nitriding_uses_four_weeks(): void
    {
        $this->assertSame('2026-03-24', $this->calculator->calculate('2026-04-21', 'Plazmanitriranje', 5));
    }

    public function test_protection_name_comparison_ignores_case_and_outer_whitespace(): void
    {
        $this->assertSame('2026-03-24', $this->calculator->calculate('2026-04-21', '  pLaZmAnItRiRaNjE  '));
    }

    public function test_date_calculation_crosses_previous_month(): void
    {
        $this->assertSame('2026-03-31', $this->calculator->calculate('2026-04-21', 'Lakiranje', 2));
    }

    public function test_date_calculation_crosses_previous_year(): void
    {
        $this->assertSame('2025-12-22', $this->calculator->calculate('2026-01-05'));
    }

    public function test_date_calculation_handles_leap_year(): void
    {
        $this->assertSame('2024-02-02', $this->calculator->calculate('2024-03-01', 'plazma+lakiranje', 37));
    }

    public function test_date_only_values_do_not_shift_through_timezone_conversion(): void
    {
        $this->assertSame('2026-04-21', $this->calculator->dateOnly('2026-04-21T23:00:00.000Z')->format('Y-m-d'));
        $this->assertSame('21/04/2026', $this->calculator->formatDisplay('2026-04-21'));
    }
}
