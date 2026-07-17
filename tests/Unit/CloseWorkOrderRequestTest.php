<?php

namespace Tests\Unit;

use App\Http\Requests\CloseWorkOrderRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CloseWorkOrderRequestTest extends TestCase
{
    public function test_empty_rows_are_removed_and_manual_operation_ids_are_null(): void
    {
        $request = CloseWorkOrderRequest::create('/', 'POST', [
            'operations' => [
                [
                    'item_qid' => 0,
                    'code' => 'op30',
                    'worker_id' => 69,
                    'time' => '120',
                    'start_time' => '09:30',
                    'end_time' => '11:30',
                ],
                ['item_qid' => 0, 'code' => '', 'worker_id' => null, 'time' => '', 'start_time' => '', 'end_time' => ''],
            ],
            'materials' => [
                ['code' => 'ploplazma', 'quantity' => '2'],
                ['code' => '', 'quantity' => ''],
            ],
        ]);

        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame([[
            'item_qid' => null,
            'code' => 'op30',
            'worker_id' => 69,
            'time' => '120',
            'start_time' => '09:30',
            'end_time' => '11:30',
        ]], $request->input('operations'));
        $this->assertSame([['code' => 'ploplazma', 'quantity' => '2']], $request->input('materials'));
    }
}
