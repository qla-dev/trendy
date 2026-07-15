<?php

namespace Tests\Feature;

use App\Services\WorkOrder\PantheonWorkerSearchService;
use App\Services\WorkOrder\WorkOrderClosingService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class WorkOrderClosingPantheonTest extends TestCase
{
    private const WORK_ORDER_KEY = '2660000003059';
    private const OPERATION_ITEM_QID = 53467;
    private const WORKER_QID = 69;

    private ConnectionInterface $pantheon;

    protected function setUp(): void
    {
        parent::setUp();
        $connectionName = (string) config('services.work_order.target_connection');
        $database = (string) config('database.connections.' . $connectionName . '.database');

        if (strtoupper($database) !== 'BA_TRENDY_TESTNA') {
            $this->markTestSkipped('Pantheon closing integration tests are hard-locked to BA_TRENDY_TESTNA.');
        }

        $this->pantheon = DB::connection($connectionName);

        if ($this->closingDocumentCount() > 0 || $this->pantheon->table('dbo.tHF_WOEx')->where('acKey', self::WORK_ORDER_KEY)->value('acStatusMF') === 'Z') {
            $this->markTestSkipped('The reference work order is no longer open and document-free.');
        }
    }

    public function test_partial_case_insensitive_worker_search_for_le(): void
    {
        $results = app(PantheonWorkerSearchService::class)->search('le', 20);

        $this->assertNotEmpty($results);
        $this->assertContains('Lejla Krnjić', array_column($results, 'text'));
        $this->assertLessThanOrEqual(20, count($results));
    }

    public function test_real_schema_close_is_atomic_and_repeated_submission_is_idempotent(): void
    {
        $this->pantheon->beginTransaction();

        try {
            $workOrder = $this->pantheon->table('dbo.tHF_WOEx')
                ->where('acKey', self::WORK_ORDER_KEY)
                ->first(['acIdent']);
            $warehouse = (string) config('work_order_closing.receipt_warehouse', 'Veleprodajno skladište');
            $stockBefore = $this->pantheon->table('dbo.tHE_Stock')
                ->where('acWarehouse', $warehouse)
                ->where('acIdent', trim((string) $workOrder->acIdent))
                ->first(['anStock', 'anValue']);

            $service = app(WorkOrderClosingService::class);
            $first = $service->close(self::WORK_ORDER_KEY, $this->payload(), 1);
            $second = $service->close(self::WORK_ORDER_KEY, $this->payload(), 1);

            $this->assertFalse($first['already_closed']);
            $this->assertTrue($second['already_closed']);
            $this->assertSame(['6600', '6100'], array_column($first['documents'], 'document_type'));
            $this->assertSame(2, $this->pantheon->table('dbo.tHE_Move')->whereIn('acKey', array_column($first['documents'], 'document_key'))->count());
            $this->assertSame('Z', trim((string) $this->pantheon->table('dbo.tHF_WOEx')->where('acKey', self::WORK_ORDER_KEY)->value('acStatusMF')));

            $receipt = $first['documents'][1];
            $stockAfter = $this->pantheon->table('dbo.tHE_Stock')
                ->where('acWarehouse', $warehouse)
                ->where('acIdent', trim((string) $workOrder->acIdent))
                ->first(['anStock', 'anValue', 'anLastPrice']);
            $this->assertNotNull($stockAfter);
            $this->assertSame(
                bcadd((string) ($stockBefore->anStock ?? '0'), (string) $receipt['quantity'], 6),
                number_format((float) $stockAfter->anStock, 6, '.', '')
            );
            $this->assertEqualsWithDelta(
                (float) ($stockBefore->anValue ?? 0) + (float) $receipt['total_price'],
                (float) $stockAfter->anValue,
                0.000001
            );
            $this->assertEqualsWithDelta((float) $receipt['price_per_unit'], (float) $stockAfter->anLastPrice, 0.000001);
        } finally {
            while ($this->pantheon->transactionLevel() > 0) {
                $this->pantheon->rollBack();
            }
        }

        $this->assertSame(0, $this->closingDocumentCount());
        $this->assertSame('O', trim((string) $this->pantheon->table('dbo.tHF_WOEx')->where('acKey', self::WORK_ORDER_KEY)->value('acStatusMF')));
    }

    public function test_receipt_failure_rolls_back_operation_document_and_status(): void
    {
        $originalWarehouse = config('work_order_closing.receipt_warehouse');
        config(['work_order_closing.receipt_warehouse' => '__INVALID_TEST_WAREHOUSE__']);

        try {
            app(WorkOrderClosingService::class)->close(self::WORK_ORDER_KEY, $this->payload(), 1);
            $this->fail('Expected the receipt subject validation to fail.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('__INVALID_TEST_WAREHOUSE__', $exception->getMessage());
        } finally {
            config(['work_order_closing.receipt_warehouse' => $originalWarehouse]);
        }

        $this->assertSame(0, $this->closingDocumentCount());
        $this->assertSame('O', trim((string) $this->pantheon->table('dbo.tHF_WOEx')->where('acKey', self::WORK_ORDER_KEY)->value('acStatusMF')));
    }

    public function test_incomplete_operation_input_marks_the_order_partially_closed_without_documents(): void
    {
        $this->pantheon->beginTransaction();

        try {
            $result = app(WorkOrderClosingService::class)->close(self::WORK_ORDER_KEY, [[
                'item_qid' => self::OPERATION_ITEM_QID,
                'worker_id' => null,
                'time' => null,
                'start_time' => '',
                'end_time' => '',
            ]], 1);

            $workOrder = $this->pantheon->table('dbo.tHF_WOEx')
                ->where('acKey', self::WORK_ORDER_KEY)
                ->first(['acStatus', 'acStatusMF']);
            $this->assertTrue($result['partial']);
            $this->assertSame('djelomično zaključen', $result['status']);
            $this->assertSame('N', trim((string) $workOrder->acStatus));
            $this->assertSame('R', trim((string) $workOrder->acStatusMF));
            $this->assertSame(0, $this->closingDocumentCount());
        } finally {
            while ($this->pantheon->transactionLevel() > 0) {
                $this->pantheon->rollBack();
            }
        }
    }

    public function test_copied_operation_rows_are_aggregated_to_one_document_time(): void
    {
        $this->pantheon->beginTransaction();

        try {
            $producedQuantity = (string) $this->pantheon->table('dbo.tHF_WOEx')
                ->where('acKey', self::WORK_ORDER_KEY)
                ->value('anPlanQty');
            $result = app(WorkOrderClosingService::class)->close(self::WORK_ORDER_KEY, [
                [
                    'item_qid' => self::OPERATION_ITEM_QID,
                    'worker_id' => self::WORKER_QID,
                    'time' => '999', // The service must use the time range instead.
                    'start_time' => '09:00',
                    'end_time' => '09:15',
                ],
                [
                    'item_qid' => self::OPERATION_ITEM_QID,
                    'worker_id' => self::WORKER_QID,
                    'time' => '999',
                    'start_time' => '11:00',
                    'end_time' => '11:20',
                ],
            ], 1);

            $operationDocument = $result['documents'][0];
            $documentItems = $this->pantheon->table('dbo.tHE_MoveItem')
                ->where('acKey', $operationDocument['document_key'])
                ->get(['anQty']);
            $workerEntries = $this->pantheon->table('dbo.tHF_WOExItemWork')
                ->where('acLnkKey', $operationDocument['document_key'])
                ->get(['anTime', 'anTn']);

            $this->assertCount(1, $documentItems);
            $this->assertSame(
                bcmul('35', $producedQuantity, 6),
                number_format((float) $documentItems->first()->anQty, 6, '.', '')
            );
            $this->assertCount(2, $workerEntries);
            $this->assertSame(['15.000000', '20.000000'], $workerEntries->pluck('anTn')->sort()->values()->all());
        } finally {
            while ($this->pantheon->transactionLevel() > 0) {
                $this->pantheon->rollBack();
            }
        }

        $this->assertSame(0, $this->closingDocumentCount());
    }

    public function test_break_time_is_normalized_before_operation_document_creation(): void
    {
        $this->pantheon->beginTransaction();

        try {
            $producedQuantity = (string) $this->pantheon->table('dbo.tHF_WOEx')
                ->where('acKey', self::WORK_ORDER_KEY)
                ->value('anPlanQty');
            $result = app(WorkOrderClosingService::class)->close(self::WORK_ORDER_KEY, [[
                'item_qid' => self::OPERATION_ITEM_QID,
                'worker_id' => self::WORKER_QID,
                'time' => '999',
                'start_time' => '09:00',
                'end_time' => '10:15',
            ]], 1);

            $operationDocument = $result['documents'][0];
            $item = $this->pantheon->table('dbo.tHE_MoveItem')
                ->where('acKey', $operationDocument['document_key'])
                ->first(['anQty']);
            $workerEntry = $this->pantheon->table('dbo.tHF_WOExItemWork')
                ->where('acLnkKey', $operationDocument['document_key'])
                ->first(['anTn', 'adBeginTime', 'adEndTime']);

            $this->assertSame(bcmul('60', $producedQuantity, 6), number_format((float) $item->anQty, 6, '.', ''));
            $this->assertSame('60.000000', (string) $workerEntry->anTn);
            $this->assertStringContainsString('10:00:00', (string) $workerEntry->adEndTime);
        } finally {
            while ($this->pantheon->transactionLevel() > 0) {
                $this->pantheon->rollBack();
            }
        }
    }

    private function payload(): array
    {
        return [[
            'item_qid' => self::OPERATION_ITEM_QID,
            'worker_id' => self::WORKER_QID,
            'time' => '30.25',
        ]];
    }

    private function closingDocumentCount(): int
    {
        return $this->pantheon->table('dbo.tHE_Move as m')
            ->join('dbo.tHF_LinkMoveWOEx as l', 'l.acKey', '=', 'm.acKey')
            ->where('l.acLnkKey', self::WORK_ORDER_KEY)
            ->whereIn('m.acDocType', ['6100', '6600'])
            ->count();
    }
}
