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

    public function test_closing_creates_the_expected_pantheon_work_order_link_values(): void
    {
        $this->pantheon->beginTransaction();

        try {
            $result = app(WorkOrderClosingService::class)->close(self::WORK_ORDER_KEY, $this->payload(), 1);
            $documentKeys = array_column($result['documents'], 'document_key');
            $links = $this->pantheon->table('dbo.tHF_LinkMoveWOEx as l')
                ->join('dbo.tHE_Move as m', 'm.acKey', '=', 'l.acKey')
                ->where('l.acLnkKey', self::WORK_ORDER_KEY)
                ->whereIn('l.acKey', $documentKeys)
                ->orderBy('m.acDocType')
                ->get(['m.acDocType', 'l.acKey', 'l.acLnkKey', 'l.acType', 'l.anMoveQId'])
                ->keyBy('acDocType');

            $expected = [
                '6100' => 'M',
                '6600' => 'P',
            ];

            foreach ($expected as $documentType => $linkType) {
                $link = $links->get($documentType);
                $this->assertNotNull($link, "Missing {$documentType} header link for the newly closed WO.");
                $this->assertSame(self::WORK_ORDER_KEY, trim((string) $link->acLnkKey));
                $this->assertSame($linkType, trim((string) $link->acType));
                $this->assertGreaterThan(0, (int) $link->anMoveQId);
            }
        } finally {
            while ($this->pantheon->transactionLevel() > 0) {
                $this->pantheon->rollBack();
            }
        }

        $this->assertSame(0, $this->closingDocumentCount());
    }

    public function test_post_cutoff_work_order_without_2005_transfer_can_be_closed(): void
    {
        $originalCutoff = config('work_order_closing.work_order_2005_flow_start_date');
        config(['work_order_closing.work_order_2005_flow_start_date' => '2000-01-01 00:00:00']);
        $this->pantheon->beginTransaction();

        try {
            $result = app(WorkOrderClosingService::class)->close(self::WORK_ORDER_KEY, $this->payload(), 1);
            $this->assertFalse($result['already_closed']);
            $this->assertSame(['6600', '6100'], array_column($result['documents'], 'document_type'));
        } finally {
            config(['work_order_closing.work_order_2005_flow_start_date' => $originalCutoff]);
            while ($this->pantheon->transactionLevel() > 0) {
                $this->pantheon->rollBack();
            }
        }
    }

    public function test_closing_splits_finished_goods_receipts_between_vp_and_scrap_warehouses(): void
    {
        $this->pantheon->beginTransaction();

        try {
            $plannedQuantity = (string) $this->pantheon->table('dbo.tHF_WOEx')
                ->where('acKey', self::WORK_ORDER_KEY)
                ->value('anPlanQty');
            $vpQuantity = bcdiv($plannedQuantity, '2', 6);
            $scrapQuantity = bcsub($plannedQuantity, $vpQuantity, 6);

            $result = app(WorkOrderClosingService::class)->close(
                self::WORK_ORDER_KEY,
                $this->payload(),
                1,
                '',
                [],
                [
                    ['target' => 'vp', 'quantity' => $vpQuantity],
                    ['target' => 'scrap', 'quantity' => $scrapQuantity],
                ]
            );

            $documents = collect($result['documents'])->keyBy('document_type');
            $this->assertArrayHasKey('6100', $documents);
            $this->assertArrayHasKey('7100', $documents);
            $this->assertSame($vpQuantity, $documents['6100']['quantity']);
            $this->assertSame($scrapQuantity, $documents['7100']['quantity']);
            $this->assertTrue($this->pantheon->table('dbo.tHF_LinkMoveWOEx')
                ->where('acKey', $documents['7100']['document_key'])
                ->where('acLnkKey', self::WORK_ORDER_KEY)
                ->where('acType', 'M')
                ->exists());
        } finally {
            while ($this->pantheon->transactionLevel() > 0) {
                $this->pantheon->rollBack();
            }
        }
    }

    public function test_manual_document_only_operation_creates_closing_documents_without_a_bom_item_link(): void
    {
        $this->pantheon->beginTransaction();

        try {
            $result = app(WorkOrderClosingService::class)->close(self::WORK_ORDER_KEY, [[
                'item_qid' => null,
                'code' => 'OP30',
                'worker_id' => self::WORKER_QID,
                'time' => '120',
                'start_time' => '',
                'end_time' => '',
            ]], 1, '', [[
                'code' => 'PLOPLAZMA',
                'quantity' => '2',
            ]]);

            $operationDocument = collect($result['documents'])->firstWhere('document_type', '6600');
            $this->assertNotNull($operationDocument);
            $this->assertSame('OP30', $this->pantheon->table('dbo.tHE_MoveItem')
                ->where('acKey', $operationDocument['document_key'])
                ->value('acIdent'));
            $this->assertTrue(
                $this->pantheon->table('dbo.vHE_ViewDocWOEx')
                    ->where('acKey', self::WORK_ORDER_KEY)
                    ->where('acWhereKey', $operationDocument['document_key'])
                    ->exists(),
                'The manual-operation document must be present in Pantheon\'s work-order related-documents view.'
            );
            $this->assertSame(0, $this->pantheon->table('dbo.tHF_LinkMoveItemWOExItem')
                ->where('acKey', $operationDocument['document_key'])
                ->count());
            $this->assertSame(0, $this->pantheon->table('dbo.tHF_WOExItemWork')
                ->where('acLnkKey', $operationDocument['document_key'])
                ->count());
        } finally {
            while ($this->pantheon->transactionLevel() > 0) {
                $this->pantheon->rollBack();
            }
        }

        $this->assertSame(0, $this->closingDocumentCount());
    }

    public function test_empty_work_order_closing_creates_positions_and_links_all_manual_document_items(): void
    {
        $emptyWorkOrderKey = $this->emptyWorkOrderKey();
        $this->assertNotNull($emptyWorkOrderKey, 'An open, document-free empty work order is required for this integration test.');
        $itemsBefore = $this->pantheon->table('dbo.tHF_WOExItem')->where('acKey', $emptyWorkOrderKey)->count();
        $originalCutoff = config('work_order_closing.work_order_2005_flow_start_date');
        config(['work_order_closing.work_order_2005_flow_start_date' => '2000-01-01 00:00:00']);

        $this->pantheon->beginTransaction();

        try {
            $result = app(WorkOrderClosingService::class)->close($emptyWorkOrderKey, [[
                // Simulates a stale browser item id. An empty WO must treat
                // the coded operation as manual input and create its own QId.
                'item_qid' => 987654321,
                'code' => 'OP30',
                'worker_id' => self::WORKER_QID,
                'time' => '120',
                'start_time' => '',
                'end_time' => '',
            ], [
                // A manually selected code without any work data is a modal
                // placeholder. It must not create a second WO position or
                // make the completed OP30 row a partial close.
                'item_qid' => null,
                'code' => 'OP10',
                'worker_id' => null,
                'time' => '',
                'start_time' => '',
                'end_time' => '',
            ]], 1, '', [[
                'code' => 'PLOPLAZMA',
                'quantity' => '2',
            ]]);

            $documents = collect($result['documents'])->keyBy('document_type');
            $this->assertSame(['2005', '6400', '6600', '6100'], array_column($result['documents'], 'document_type'));

            $workOrderItems = $this->pantheon->table('dbo.tHF_WOExItem')
                ->where('acKey', $emptyWorkOrderKey)
                ->orderBy('anNo')
                ->get(['anQId', 'anNo', 'acIdent', 'acOperationType', 'acIssueFinished'])
                ->keyBy('acIdent');
            $operationItem = $workOrderItems->get('OP30');
            $materialItem = $workOrderItems->get('PLOPLAZMA');

            $this->assertCount(2, $workOrderItems);
            $this->assertSame(1, (int) $operationItem->anNo);
            $this->assertSame('D', trim((string) $operationItem->acOperationType));
            $this->assertSame(2, (int) $materialItem->anNo);
            $this->assertSame('', trim((string) $materialItem->acOperationType));
            $this->assertSame('Y', trim((string) $operationItem->acIssueFinished));
            $this->assertSame('Y', trim((string) $materialItem->acIssueFinished));
            $this->assertTrue($this->pantheon->table('dbo.tHF_WOExItemResources')
                ->where('anWOExItemQId', (int) $operationItem->anQId)
                ->exists(), 'A manually created operation WO item must have its Pantheon resource row.');
            $this->assertSame('Y', trim((string) $this->pantheon->table('dbo.tHF_WOExItemResources')
                ->where('anWOExItemQId', (int) $operationItem->anQId)
                ->value('acIssueFinished')));

            $operationMoveItem = $this->pantheon->table('dbo.tHE_MoveItem')
                ->where('acKey', $documents['6600']['document_key'])
                ->where('acIdent', 'OP30')
                ->first(['anQId', 'acLnkKey']);
            $materialMoveItem = $this->pantheon->table('dbo.tHE_MoveItem')
                ->where('acKey', $documents['6400']['document_key'])
                ->where('acIdent', 'PLOPLAZMA')
                ->first(['anQId', 'acLnkKey']);
            $preparationMoveItem = $this->pantheon->table('dbo.tHE_MoveItem')
                ->where('acKey', $documents['2005']['document_key'])
                ->where('acIdent', 'PLOPLAZMA')
                ->first(['anQId']);

            $this->assertNotNull($operationMoveItem);
            $this->assertNotNull($materialMoveItem);
            $this->assertNotNull($preparationMoveItem);
            $this->assertSame($documents['6100']['document_key'], trim((string) $operationMoveItem->acLnkKey));
            $this->assertSame($documents['6100']['document_key'], trim((string) $materialMoveItem->acLnkKey));

            $operationLink = $this->pantheon->table('dbo.tHF_LinkMoveItemWOExItem')
                ->where('anMoveItemQId', (int) $operationMoveItem->anQId)
                ->first(['acType', 'acTypeA', 'anWOExItemQid']);
            $materialLink = $this->pantheon->table('dbo.tHF_LinkMoveItemWOExItem')
                ->where('anMoveItemQId', (int) $materialMoveItem->anQId)
                ->first(['acType', 'acTypeA', 'anWOExItemQid']);
            $preparationLink = $this->pantheon->table('dbo.tHF_LinkMoveItemWOExItem')
                ->where('anMoveItemQId', (int) $preparationMoveItem->anQId)
                ->first(['acType', 'acTypeA', 'anWOExItemQid']);

            $this->assertSame('PP', trim((string) $operationLink->acType));
            $this->assertSame((int) $operationItem->anQId, (int) $operationLink->anWOExItemQid);
            $this->assertSame('PP', trim((string) $materialLink->acType));
            $this->assertSame('A', trim((string) $materialLink->acTypeA));
            $this->assertSame((int) $materialItem->anQId, (int) $materialLink->anWOExItemQid);
            $this->assertSame('PP', trim((string) $preparationLink->acType));
            $this->assertSame('A', trim((string) $preparationLink->acTypeA));
            $this->assertSame((int) $materialItem->anQId, (int) $preparationLink->anWOExItemQid);

            $workerRecord = $this->pantheon->table('dbo.tHF_WOExItemWork')
                ->where('anWOExItemQid', (int) $operationItem->anQId)
                ->where('anMoveItemQId', (int) $operationMoveItem->anQId)
                ->first(['acIdent', 'anTime']);
            $this->assertNotNull($workerRecord);
            $this->assertSame('OP30', trim((string) $workerRecord->acIdent));
            $this->assertSame('240.000000', number_format((float) $workerRecord->anTime, 6, '.', ''));

            foreach (['2005' => 'P', '6400' => 'P', '6600' => 'P', '6100' => 'M'] as $documentType => $linkType) {
                $document = $documents[$documentType];
                $this->assertTrue($this->pantheon->table('dbo.tHF_LinkMoveWOEx')
                    ->where('acKey', $document['document_key'])
                    ->where('acLnkKey', $emptyWorkOrderKey)
                    ->where('acType', $linkType)
                    ->exists());
                $this->assertTrue($this->pantheon->table('dbo.vHE_ViewDocWOEx')
                    ->where('acKey', $emptyWorkOrderKey)
                    ->where('acWhereKey', $document['document_key'])
                    ->exists());
            }
        } finally {
            config(['work_order_closing.work_order_2005_flow_start_date' => $originalCutoff]);
            while ($this->pantheon->transactionLevel() > 0) {
                $this->pantheon->rollBack();
            }
        }

        $this->assertSame($itemsBefore, $this->pantheon->table('dbo.tHF_WOExItem')->where('acKey', $emptyWorkOrderKey)->count());
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

    public function test_break_time_overlap_is_excluded_from_operation_duration(): void
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
                'start_time' => '10:14',
                'end_time' => '11:00',
            ]], 1);

            $operationDocument = $result['documents'][0];
            $item = $this->pantheon->table('dbo.tHE_MoveItem')
                ->where('acKey', $operationDocument['document_key'])
                ->first(['anQty']);
            $workerEntry = $this->pantheon->table('dbo.tHF_WOExItemWork')
                ->where('acLnkKey', $operationDocument['document_key'])
                ->first(['anTn', 'adBeginTime', 'adEndTime']);

            $this->assertSame(bcmul('30', $producedQuantity, 6), number_format((float) $item->anQty, 6, '.', ''));
            $this->assertSame('30.000000', (string) $workerEntry->anTn);
            $this->assertStringContainsString('10:14:00', (string) $workerEntry->adBeginTime);
            $this->assertStringContainsString('11:00:00', (string) $workerEntry->adEndTime);
        } finally {
            while ($this->pantheon->transactionLevel() > 0) {
                $this->pantheon->rollBack();
            }
        }
    }

    private function emptyWorkOrderKey(): ?string
    {
        $row = $this->pantheon->table('dbo.tHF_WOEx as wo')
            ->join('dbo.tHE_SetItem as product', 'product.acIdent', '=', 'wo.acIdent')
            ->where('wo.acStatusMF', 'O')
            ->where('wo.anPlanQty', '>', 0)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('dbo.tHF_WOExItem as wi')
                    ->whereColumn('wi.acKey', 'wo.acKey');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('dbo.tHF_LinkMoveWOEx as l')
                    ->join('dbo.tHE_Move as m', 'm.acKey', '=', 'l.acKey')
                    ->whereColumn('l.acLnkKey', 'wo.acKey')
                    ->whereIn('m.acDocType', ['6100', '6600']);
            })
            ->orderByDesc('wo.acKey')
            ->value('wo.acKey');

        return is_string($row) && trim($row) !== '' ? trim($row) : null;
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
