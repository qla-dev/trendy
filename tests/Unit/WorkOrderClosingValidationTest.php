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

    /** @dataProvider invalidTimes */
    public function test_invalid_negative_and_empty_time_are_rejected(mixed $time): void
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
        return [[null], [''], ['-1'], ['abc']];
    }

    public function test_worker_is_required_and_copied_operation_rows_are_allowed(): void
    {
        $missingWorker = Validator::make(['operations' => [
            ['item_qid' => 10, 'worker_id' => null, 'time' => '1'],
            ['item_qid' => 10, 'worker_id' => 20, 'time' => '1'],
        ]], (new CloseWorkOrderRequest())->rules());

        $this->assertTrue($missingWorker->fails());
        $this->assertArrayHasKey('operations.0.worker_id', $missingWorker->errors()->toArray());

        $copiedRows = Validator::make(['operations' => [
            ['item_qid' => 10, 'worker_id' => 20, 'time' => '1'],
            ['item_qid' => 10, 'worker_id' => 21, 'time' => '2'],
        ]], (new CloseWorkOrderRequest())->rules());

        $this->assertFalse($copiedRows->fails());
    }
}
