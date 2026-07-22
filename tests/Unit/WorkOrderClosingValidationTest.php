<?php

namespace Tests\Unit;

use App\Http\Requests\CloseWorkOrderRequest;
use Illuminate\Support\Facades\Validator;
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
}
