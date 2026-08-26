<?php

namespace Tests\Unit;

use App\Http\Requests\CloseWorkOrderRequest;
use App\Services\WorkOrder\WorkOrderClosingCalculator;
use App\Services\WorkOrder\WorkOrderClosingService;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class WorkOrderClosingValidationTest extends TestCase
{
    public function test_valid_decimal_time_and_explicit_zero_are_accepted(): void
    {
        $rules = (new CloseWorkOrderRequest())->rules();

        foreach (['0', '12.3456', '12,3456'] as $time) {
            $validator = Validator::make(['operations' => [[
                'item_qid' => 10,
                'worker_id' => 20,
                'time' => $time,
            ]]], $rules);
            $this->assertFalse($validator->fails(), $time);
        }
    }

    public function test_duration_and_per_unit_duration_allow_up_to_four_decimals(): void
    {
        $rules = (new CloseWorkOrderRequest())->rules();
        $valid = Validator::make(['operations' => [[
            'item_qid' => 10,
            'worker_id' => 20,
            'duration' => '80.1234',
            'time' => '40.0617',
        ]]], $rules);
        $invalid = Validator::make(['operations' => [[
            'item_qid' => 10,
            'worker_id' => 20,
            'duration' => '80.12345',
            'time' => '40.0617',
        ]]], $rules);

        $this->assertFalse($valid->fails());
        $this->assertTrue($invalid->fails());
    }

    public function test_optional_downtime_accepts_non_negative_minutes(): void
    {
        $validator = Validator::make(['operations' => [[
            'item_qid' => 10,
            'worker_id' => 20,
            'time' => '60',
            'downtime' => '30,5',
        ]]], (new CloseWorkOrderRequest())->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_negative_or_invalid_downtime_is_rejected(): void
    {
        foreach (['-1', 'abc'] as $downtime) {
            $validator = Validator::make(['operations' => [[
                'item_qid' => 10,
                'worker_id' => 20,
                'time' => '60',
                'downtime' => $downtime,
            ]]], (new CloseWorkOrderRequest())->rules());

            $this->assertTrue($validator->fails(), $downtime);
        }
    }

    /** @dataProvider invalidTimes */
    public function test_invalid_nonempty_time_is_rejected(mixed $time): void
    {
        $validator = Validator::make(['operations' => [[
            'item_qid' => 10,
            'worker_id' => 20,
            'time' => $time,
        ]]], (new CloseWorkOrderRequest())->rules());

        $this->assertTrue($validator->fails());
    }

    public function invalidTimes(): array
    {
        return [['-1'], ['abc']];
    }

    public function test_incomplete_rows_and_copied_operation_rows_are_allowed(): void
    {
        $partialRows = Validator::make(['operations' => [
            ['item_qid' => 10, 'worker_id' => null, 'time' => '1'],
            ['item_qid' => 10, 'worker_id' => null, 'time' => null],
        ]], (new CloseWorkOrderRequest())->rules());

        $this->assertFalse($partialRows->fails());

        $copiedRows = Validator::make(['operations' => [
            ['item_qid' => 10, 'worker_id' => 20, 'time' => '1'],
            ['item_qid' => 10, 'worker_id' => 21, 'time' => '2'],
        ]], (new CloseWorkOrderRequest())->rules());

        $this->assertFalse($copiedRows->fails());
    }

    public function test_zero_material_quantity_blocks_closing_instead_of_being_silently_skipped(): void
    {
        $service = $this->closingServiceWithoutDependencies();
        $method = (new ReflectionClass($service))->getMethod('prepareMaterials');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Količina materijala mora biti veća od nule.');
        $method->invoke($service, [['code' => 'ANY-MATERIAL', 'quantity' => '0']], '2');
    }

    public function test_closing_material_quantity_is_scaled_by_the_work_order_piece_count(): void
    {
        $service = $this->closingServiceWithoutDependencies();
        $method = (new ReflectionClass($service))->getMethod('materialTotalQuantity');
        $method->setAccessible(true);

        $this->assertSame('10.000000', $method->invoke($service, '5.000000', '2.000000'));
    }

    public function test_total_duration_is_converted_to_minutes_per_unit_server_side(): void
    {
        $service = $this->closingServiceWithoutDependencies();
        $method = (new ReflectionClass($service))->getMethod('operationTiming');
        $method->setAccessible(true);

        $timing = $method->invoke($service, ['duration' => '80', 'time' => ''], '2');

        $this->assertSame('40.000000', $timing['minutes']);
    }

    public function test_scrap_can_make_total_receipt_quantity_higher_than_the_work_order_plan(): void
    {
        $service = $this->closingServiceWithoutDependencies();
        $method = (new ReflectionClass($service))->getMethod('prepareReceipts');
        $method->setAccessible(true);

        $receipts = $method->invoke($service, [
            ['target' => 'vp', 'quantity' => '2'],
            ['target' => 'scrap', 'quantity' => '1'],
        ], '2');

        $this->assertSame('2.000000', $receipts[0]['quantity']);
        $this->assertSame('1.000000', $receipts[1]['quantity']);
    }

    public function test_completion_percentage_is_partial_capped_and_defaults_to_complete_without_a_bom_baseline(): void
    {
        $service = $this->closingServiceWithoutDependencies();
        $method = (new ReflectionClass($service))->getMethod('completionPercent');
        $method->setAccessible(true);

        $this->assertSame(50.0, $method->invoke($service, 1, 2, true));
        $this->assertSame(100.0, $method->invoke($service, 3, 2, true));
        $this->assertSame(100.0, $method->invoke($service, 0, 0, true));
    }

    private function closingServiceWithoutDependencies(): WorkOrderClosingService
    {
        $reflection = new ReflectionClass(WorkOrderClosingService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $calculator = $reflection->getProperty('calculator');
        $calculator->setAccessible(true);
        $calculator->setValue($service, new WorkOrderClosingCalculator());

        return $service;
    }
}
