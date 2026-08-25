<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WorkOrderClosingService
{
    private ConnectionInterface $connection;

    public function __construct(
        private PantheonDocumentNumberGenerator $numbers,
        private PantheonClosingWorkOrderItemService $closingWorkOrderItems,
        private PantheonClosingMaterialDocumentService $materialDocuments,
        private PantheonOperationDocumentService $operationDocuments,
        private PantheonFinishedGoodsReceiptService $receiptDocuments,
        private PantheonMaterialPreparationService $materialPreparation,
        private PantheonMaterialStockService $materialStock,
        private PantheonWorkerSearchService $workers,
        private WorkOrderClosingCalculator $calculator
    ) {
        $name = trim((string) config('services.work_order.target_connection', 'work_order_target'));
        $this->connection = DB::connection($name !== '' ? $name : 'work_order_target');
    }

    public function close(string $locator, array $submittedOperations, int $userId, string $userName = '', array $submittedMaterials = [], ?array $submittedReceipts = null): array
    {
        $now = Carbon::now();

        return $this->connection->transaction(function () use ($locator, $submittedOperations, $submittedMaterials, $submittedReceipts, $userId, $userName, $now) {
            $workOrder = $this->lockWorkOrder($locator);
            $existing = $this->existingClosingDocuments((string) $workOrder['acKey']);

            if (isset($existing['6600']) && (isset($existing['6100']) || isset($existing['7100']))) {
                return $this->alreadyClosedResult($workOrder, $existing);
            }

            $blockingExisting = $existing;
            unset($blockingExisting['6400']);
            if ($blockingExisting !== []) {
                throw new RuntimeException('Radni nalog ima nepotpun postojeći skup završnih dokumenata. Nije kreiran novi dokument.');
            }

            if (strtoupper(trim((string) ($workOrder['acStatusMF'] ?? ''))) === 'Z') {
                throw new RuntimeException('Radni nalog je već zatvoren.');
            }

            $producedQuantity = $this->calculator->normalizeNonNegative($workOrder['anPlanQty'] ?? null, 'Planirana količina');
            if (bccomp($producedQuantity, '0', WorkOrderClosingCalculator::SCALE) <= 0) {
                throw new RuntimeException('Planirana količina mora biti veća od nule.');
            }

            $preparedOperations = $this->prepareOperations((string) $workOrder['acKey'], $submittedOperations, $producedQuantity);
            /* if (!$preparedOperations['complete']) {
                $this->markPartiallyClosed($workOrder, $now, $userId);

                return [
                    'already_closed' => false,
                    'partial' => true,
                    'status' => 'djelomično zaključen',
                    'work_order_key' => $workOrder['acKey'],
                    'work_order_number' => $this->formatNumber((string) ($workOrder['acKeyView'] ?? $workOrder['acKey'])),
                    'message' => 'radni nalog je djelomično zaključen',
                    'documents' => [],
                ];
            } */

            $operations = $preparedOperations['operations'];
            $materialFlow = $this->resolveMaterialFlow($workOrder, $submittedMaterials, $producedQuantity);
            $materials = $materialFlow['materials'];
            $sourceWarehouse = $materialFlow['source_warehouse'];
            $destinationWarehouse = (string) config('work_order_closing.receipt_warehouse', 'Veleprodajno skladište');

            // An empty work order has no item QIds for Pantheon document-line
            // links. Create closing positions first, then use their QIds for
            // both material and operation document links.
            $closingItems = $this->closingWorkOrderItems->createForEmptyWorkOrder(
                $this->connection,
                $workOrder,
                $operations,
                $materials,
                $now,
                $userId
            );
            $operations = $closingItems['operations'];
            $materials = $closingItems['materials'];
            $preparationResult = $materialFlow['uses_2005_flow']
                ? $this->prepareCloseTimeMaterials($workOrder, $materials, $now, $userId)
                : null;
            $related2005 = $materialFlow['uses_2005_flow']
                ? ($preparationResult ?? $this->materialPreparationDocument((string) $workOrder['acKey']))
                : null;
            $materialFallbackNotice = null;

            if ($materialFlow['uses_2005_flow'] && $materials !== [] && !$this->materialStock->canIssue($this->connection, $sourceWarehouse, $materials)) {
                // A WO with neither 2005 nor 6400 was never moved into WIP.
                // Preserve the legacy direct issue only for that unprocessed
                // case; never use it after an existing 6400 release.
                if ($related2005 === null && !isset($existing['6400'])) {
                    $sourceWarehouse = trim((string) config('work_order_closing.raw_material_warehouse', 'SkladiĹˇte sirovina'));
                    if ($sourceWarehouse === '') {
                        throw new RuntimeException('Konfiguracija skladiĹˇta sirovina nije postavljena.');
                    }
                    $materialFlow['name'] = 'legacy_skladiste_sirovina_to_veleprodajno_without_2005_fallback';
                    $materialFallbackNotice = 'Dokument 2005 nije pronađen. Materijal je razdužen direktno iz skladišta sirovina u veleprodajno skladište.';

                    Log::warning('Work order closing is using the raw-material fallback because no 2005 exists.', [
                        'work_order_id' => $workOrder['acKey'],
                        'source_warehouse' => $sourceWarehouse,
                        'destination_warehouse' => $destinationWarehouse,
                        'document_2005_id' => null,
                        'existing_document_6400' => false,
                    ]);
                }
            }

            Log::info('Work order material flow selected.', [
                'work_order_id' => $workOrder['acKey'],
                'work_order_created_at' => $materialFlow['created_at']->toDateTimeString(),
                'work_order_priority' => $materialFlow['priority'],
                'configured_cutoff_date' => $materialFlow['cutoff']->toDateTimeString(),
                'selected_flow' => $materialFlow['name'],
                'source_warehouse' => $sourceWarehouse,
                'destination_warehouse' => $destinationWarehouse,
                'document_2005_id' => $preparationResult['document_key'] ?? $related2005->acKey ?? $materialFlow['document_2005_id'],
            ]);
            $materialTotal = $this->calculator->add(
                $this->materialCostTotal((string) $workOrder['acKey']),
                $this->preparedMaterialCostTotal($materials)
            );
            $operationTotal = '0';

            foreach ($operations as $operation) {
                $operationTotal = $this->calculator->add(
                    $operationTotal,
                    $this->calculator->operation(
                        $operation['minutes_per_unit'],
                        $operation['price_per_minute'],
                        $producedQuantity
                    )['totalCost']
                );
            }

            $receiptCalculation = $this->calculator->receipt($materialTotal, $operationTotal, $producedQuantity);
            $receipts = $this->prepareReceipts($submittedReceipts, $producedQuantity);
            $materialNumber = $materials === []
                ? null
                : $this->numbers->next($this->connection, '6400', $now);
            $operationNumber = $this->numbers->next($this->connection, (string) config('work_order_closing.operation_document_type', '6600'), $now);
            $receiptNumbers = array_map(function (array $receipt) use ($now) {
                $type = $receipt['target'] === 'scrap'
                    ? (string) config('work_order_closing.scrap_receipt_document_type', '7100')
                    : (string) config('work_order_closing.receipt_document_type', '6100');

                return $this->numbers->next($this->connection, $type, $now);
            }, $receipts);
            $primaryReceiptNumber = $receiptNumbers[0];
            $department = $this->resolveDepartment($workOrder, $userName);
            $consignee = trim((string) ($workOrder['acReceiver'] ?: $workOrder['acConsignee'] ?? ''));

            if ($consignee === '') {
                throw new RuntimeException('Pantheon primalac radnog naloga nije pronađen.');
            }

            $materialResult = $materialNumber === null ? null : $this->materialDocuments->create(
                $this->connection,
                $materialNumber,
                $primaryReceiptNumber,
                $workOrder,
                $materials,
                $now,
                $userId,
                [
                    'receiver' => $consignee,
                    'issuer' => $sourceWarehouse,
                    'receiver_stock' => 'N',
                    'issuer_stock' => 'Y',
                    'person3' => $consignee,
                    'way_of_sale' => 'I',
                    'department' => $department,
                ]
            );
            if ($materialResult !== null) {
                if ($sourceWarehouse === '') {
                    throw new RuntimeException('Konfiguracija međuskladišta nije postavljena.');
                }
                $this->materialStock->issue($this->connection, $sourceWarehouse, $materials, $now, $userId);
            }

            $operationResult = $this->operationDocuments->create(
                $this->connection,
                $operationNumber,
                $primaryReceiptNumber,
                $workOrder,
                $operations,
                $now,
                $userId,
                $producedQuantity,
                [
                    'receiver' => $consignee,
                    'issuer' => (string) config('work_order_closing.operation_warehouse', 'RN skladište'),
                    'receiver_stock' => 'N',
                    'issuer_stock' => 'Y',
                    'person3' => $consignee,
                    'way_of_sale' => 'I',
                    'department' => $department,
                ]
            );

            $receiptResults = [];
            foreach ($receipts as $index => $receipt) {
                $quantity = $receipt['quantity'];
                $receiptResults[] = $this->receiptDocuments->create(
                    $this->connection,
                    $receiptNumbers[$index],
                    $workOrder,
                    $this->receiptCalculationForQuantity($receiptCalculation, $quantity),
                    $now,
                    $userId,
                    $quantity,
                    [
                        'receiver' => $receipt['target'] === 'scrap'
                            ? (string) config('work_order_closing.scrap_receipt_warehouse', 'Skladište škarta')
                            : (string) config('work_order_closing.receipt_warehouse', 'Veleprodajno skladište'),
                        'issuer' => $consignee,
                        'receiver_stock' => 'Y',
                        'issuer_stock' => 'N',
                        'person3' => $consignee,
                        'way_of_sale' => 'U',
                        'department' => $department,
                    ]
                );
            }

            $this->markClosed($workOrder, $producedQuantity, $now, $userId);
            $this->syncClosedWorkOrderCompletion($workOrder, $now, $userId);

            Log::info('Work order closed through eNalog.', [
                'work_order_key' => $workOrder['acKey'],
                'material_document' => $materialResult['document_number'] ?? null,
                'operation_document' => $operationResult['document_number'],
                'receipt_documents' => array_column($receiptResults, 'document_number'),
                'created_work_order_items' => $closingItems['created_items'],
                'user_id' => $userId,
            ]);

            $createdDocumentsMessage = 'kreirani dokumenti ' . implode(' i ', array_filter([
                ($preparationResult['created'] ?? false) ? $preparationResult['document_number'] : null,
                $materialResult['document_number'] ?? null,
                $operationResult['document_number'],
                ...array_column($receiptResults, 'document_number'),
            ]));

            return [
                'already_closed' => false,
                'status' => 'zaključen',
                'work_order_key' => $workOrder['acKey'],
                'work_order_number' => $this->formatNumber((string) ($workOrder['acKeyView'] ?? $workOrder['acKey'])),
                'message' => implode(' ', array_filter([$materialFallbackNotice, $createdDocumentsMessage])),
                'notices' => $materialFallbackNotice === null ? [] : [$materialFallbackNotice],
                'documents' => array_values(array_filter([
                    ($preparationResult['created'] ?? false) ? $preparationResult : null,
                    $materialResult,
                    $operationResult,
                    ...$receiptResults,
                ])),
                'costs' => $receiptCalculation,
            ];
        }, 3);
    }

    private function lockWorkOrder(string $locator): array
    {
        $normalized = preg_replace('/\D+/', '', trim($locator));
        $normalized = is_string($normalized) ? $normalized : '';
        $row = $this->connection->selectOne(
            "SELECT TOP 1 * FROM dbo.tHF_WOEx WITH (UPDLOCK, HOLDLOCK)
             WHERE LTRIM(RTRIM(acKey)) = ?
                OR LTRIM(RTRIM(acKeyView)) = ?
                OR REPLACE(REPLACE(LTRIM(RTRIM(acKeyView)), '-', ''), ' ', '') = ?",
            [trim($locator), trim($locator), $normalized]
        );

        if ($row === null) {
            throw new RuntimeException('Radni nalog nije pronađen.');
        }

        return (array) $row;
    }

    private function prepareOperations(string $workOrderKey, array $submitted, string $producedQuantity): array
    {
        $rows = $this->connection->table('dbo.tHF_WOExItem as wi')
            ->leftJoin('dbo.tHE_SetItem as si', 'si.acIdent', '=', 'wi.acIdent')
            ->where('wi.acKey', $workOrderKey)
            ->where(function ($query) {
                $query->whereIn('wi.acOperationType', ['D', 'O'])
                    ->orWhereRaw("LTRIM(RTRIM(ISNULL(si.acSetOfItem, ''))) = 'OPR'");
            })
            ->orderBy('wi.anNo')
            ->get([
                'wi.anQId', 'wi.anNo', 'wi.acIdent', 'wi.acDescr', 'wi.acIssueFinished',
                'si.anQId as ident_qid', 'si.anPrStPrice',
            ]);

        $workOrderHasItems = $this->connection->table('dbo.tHF_WOExItem')
            ->where('acKey', $workOrderKey)
            ->exists();
        $submittedByItem = [];
        $manualInputs = [];
        foreach ($submitted as $operation) {
            $qid = (int) ($operation['item_qid'] ?? 0);
            if ($qid > 0) {
                // A closing modal can retain an old client-side item id. For
                // an entirely empty WO there is no valid item to match, so a
                // coded row is manual input and receives a fresh WO item QId
                // later in the same close transaction.
                if (!$workOrderHasItems && trim((string) ($operation['code'] ?? '')) !== '') {
                    Log::warning('Ignoring stale operation item QId on empty work order closing.', [
                        'work_order_key' => $workOrderKey,
                        'submitted_item_qid' => $qid,
                        'operation_code' => strtoupper(trim((string) $operation['code'])),
                    ]);
                    $operation['item_qid'] = null;
                    $manualInputs[] = $operation;
                    continue;
                }

                $submittedByItem[$qid][] = $operation;
            } elseif (!$this->isEmptyOperationInput($operation)) {
                $manualInputs[] = $operation;
            }
        }

        $rowsByItemQId = $rows->keyBy(fn ($row) => (int) $row->anQId);
        if (array_diff(array_keys($submittedByItem), array_keys($rowsByItemQId->all())) !== []) {
            throw new RuntimeException('Zahtjev sadrži operaciju koja ne pripada radnom nalogu.');
        }

        $prepared = [];
        $complete = true;
        foreach ($rows as $row) {
            $itemQId = (int) $row->anQId;
            $inputs = $submittedByItem[$itemQId] ?? [];
            $operationCode = strtoupper(trim((string) ($inputs[0]['code'] ?? $row->acIdent)));
            if ($inputs === []) {
                if ($operationCode !== 'OP30') {
                    $complete = false;
                }
                continue;
            }

            $price = null;
            $minutesPerUnit = '0';
            $workerEntries = [];

            foreach ($inputs as $input) {
                // OP30 is optional only when an individual row is completely
                // empty. A partially entered OP30 row still needs both a
                // worker and duration before final documents can be created.
                if ($operationCode === 'OP30' && $this->isEmptyOperationInput($input)) {
                    continue;
                }

                if (!$this->hasCompleteOperationInput($input)) {
                    $complete = false;
                    continue;
                }

                if ($price === null) {
                    $catalog = $this->connection->table('dbo.tHE_SetItem')
                        ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$operationCode])
                        ->first(['anPrStPrice']);
                    if ($catalog === null) {
                        throw new RuntimeException('Pantheon operacija nije pronađena: ' . $operationCode);
                    }
                    $price = $this->calculator->normalizeNonNegative($catalog->anPrStPrice ?? null, 'Cijena operacije');
                }

                $worker = $this->workers->findActive((int) ($input['worker_id'] ?? 0));
                if ($worker === null) {
                    throw new RuntimeException('Odabrani radnik ne postoji ili nije aktivan u Pantheonu.');
                }

                $timing = $this->operationTiming($input, $producedQuantity);
                $minutesPerUnit = $this->calculator->add($minutesPerUnit, $timing['minutes']);
                $workerEntries[] = [
                    'worker' => $worker,
                    'minutes_per_unit' => $timing['minutes'],
                    'downtime' => $timing['downtime'],
                    'start_time' => $timing['start_time'],
                    'end_time' => $timing['end_time'],
                ];
            }

            if ($workerEntries === []) {
                continue;
            }

            // Multiple copied rows are one Pantheon operation. Their entered
            // times are summed before document and operation-cost creation.
            $prepared[] = [
                'item_qid' => $itemQId,
                'position' => (int) $row->anNo,
                'code' => $operationCode,
                'name' => trim((string) ($row->acDescr ?? $operationCode)),
                'minutes_per_unit' => $minutesPerUnit,
                'price_per_minute' => $price,
                'worker_entries' => $workerEntries,
            ];
        }

        foreach ($manualInputs as $input) {
            if (!$this->hasCompleteOperationInput($input)) {
                $complete = false;
                continue;
            }

            $code = strtoupper(trim((string) ($input['code'] ?? '')));
            if ($code === '') {
                throw new RuntimeException('Šifra ručno unesene operacije je obavezna.');
            }

            $catalog = $this->connection->table('dbo.tHE_SetItem')
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$code])
                ->first(['acIdent', 'acName', 'anPrStPrice']);
            if ($catalog === null) {
                throw new RuntimeException('Pantheon operacija nije pronađena: ' . $code);
            }

            $worker = $this->workers->findActive((int) ($input['worker_id'] ?? 0));
            if ($worker === null) {
                throw new RuntimeException('Odabrani radnik ne postoji ili nije aktivan u Pantheonu.');
            }
            $timing = $this->operationTiming($input, $producedQuantity);
            $prepared[] = [
                'item_qid' => 0,
                'position' => 0,
                'code' => trim((string) $catalog->acIdent),
                'name' => trim((string) ($catalog->acName ?? $code)),
                'minutes_per_unit' => $timing['minutes'],
                'price_per_minute' => $this->calculator->normalizeNonNegative($catalog->anPrStPrice ?? null, 'Cijena operacije'),
                'worker_entries' => [[
                    'worker' => $worker,
                    'minutes_per_unit' => $timing['minutes'],
                    'downtime' => $timing['downtime'],
                    'start_time' => $timing['start_time'],
                    'end_time' => $timing['end_time'],
                ]],
            ];
        }

        if ($rows->isEmpty() && $prepared === []) {
            $complete = false;
        }

        return ['complete' => $complete, 'operations' => $prepared];
    }

    private function hasCompleteOperationInput(array $input): bool
    {
        $workerId = (int) ($input['worker_id'] ?? 0);
        $duration = trim((string) ($input['duration'] ?? ''));
        $time = trim((string) ($input['time'] ?? ''));
        $start = trim((string) ($input['start_time'] ?? ''));
        $end = trim((string) ($input['end_time'] ?? ''));

        return $workerId > 0 && ($duration !== '' || $time !== '' || ($start !== '' && $end !== ''));
    }

    private function isEmptyOperationInput(array $input): bool
    {
        // The code is prefilled for existing WO positions and can be selected
        // in a manual row. A code alone is not work performed, therefore it
        // must be treated as an empty placeholder instead of an incomplete
        // operation that blocks the rest of the closing request.
        return (int) ($input['worker_id'] ?? 0) < 1
            && trim((string) ($input['duration'] ?? '')) === ''
            && trim((string) ($input['time'] ?? '')) === ''
            && trim((string) ($input['start_time'] ?? '')) === ''
            && trim((string) ($input['end_time'] ?? '')) === '';
    }

    private function operationTiming(array $input, string $producedQuantity): array
    {
        $start = trim((string) ($input['start_time'] ?? ''));
        $end = trim((string) ($input['end_time'] ?? ''));
        $downtime = trim((string) ($input['downtime'] ?? '')) === ''
            ? '0.000000'
            : $this->calculator->normalizeNonNegative($input['downtime'], 'Zastoj');

        if ($start === '' && $end === '') {
            $totalDuration = trim((string) ($input['duration'] ?? ''));
            $minutes = $totalDuration !== ''
                ? $this->calculator->divide(
                    $this->calculator->normalizeNonNegative($totalDuration, 'Trajanje'),
                    $producedQuantity
                )
                : $this->calculator->normalizeNonNegative($input['time'] ?? null, 'Trajanje (min/jedinici)');

            return [
                'minutes' => $minutes,
                'downtime' => $downtime,
                'start_time' => '',
                'end_time' => '',
            ];
        }

        if ($start === '' || $end === '') {
            throw new RuntimeException('Početno i završno vrijeme moraju biti uneseni zajedno.');
        }

        $startMinutes = $this->clockMinutes($start);
        $endMinutes = $this->clockMinutes($end);
        if ($startMinutes === null || $endMinutes === null) {
            throw new RuntimeException('Vrijeme operacije mora biti u formatu HH:MM.');
        }

        if ($endMinutes < $startMinutes) {
            throw new RuntimeException('Završno vrijeme ne može biti prije početnog vremena.');
        }

        // Start/end is the row's total elapsed labour time. Convert it to the
        // per-piece duration required by Pantheon's quantity-scaled 6600 line.
        return [
            'minutes' => $this->calculator->divide($this->netOperationMinutes(
                (string) (($endMinutes - $startMinutes) - $this->breakOverlapMinutes($startMinutes, $endMinutes)),
                $downtime
            ), $producedQuantity),
            'downtime' => $downtime,
            'start_time' => $this->formatClockMinutes($startMinutes),
            'end_time' => $this->formatClockMinutes($endMinutes),
        ];
    }

    private function netOperationMinutes(string $grossMinutes, string $downtime): string
    {
        $netMinutes = bcsub($grossMinutes, $downtime, WorkOrderClosingCalculator::SCALE);

        if (bccomp($netMinutes, '0', WorkOrderClosingCalculator::SCALE) < 0) {
            throw new RuntimeException('Zastoj ne može biti veći od trajanja operacije.');
        }

        return $netMinutes;
    }

    private function clockMinutes(string $value): ?int
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    private function breakOverlapMinutes(int $operationStart, int $operationEnd): int
    {
        $overlap = 0;

        foreach ([[600, 630], [720, 735], [885, 915], [1080, 1110], [1200, 1215], [1365, 1380]] as [$start, $end]) {
            $overlap += max(0, min($operationEnd, $end) - max($operationStart, $start));
        }

        return $overlap;
    }

    private function formatClockMinutes(int $minutes): string
    {
        return str_pad((string) intdiv($minutes, 60), 2, '0', STR_PAD_LEFT)
            . ':' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    private function materialCostTotal(string $workOrderKey): string
    {
        $value = $this->connection->table('dbo.tHE_Move as m')
            ->join('dbo.tHF_LinkMoveWOEx as l', 'l.acKey', '=', 'm.acKey')
            ->where('l.acLnkKey', $workOrderKey)
            ->where('m.acDocType', '6400')
            ->sum('m.anValue');

        return $this->calculator->normalizeNonNegative($value ?? 0, 'Trošak materijala');
    }

    private function submittedMaterialCostTotal(array $submitted): string
    {
        $total = '0';
        foreach ($submitted as $material) {
            $code = strtoupper(trim((string) ($material['code'] ?? '')));
            $quantity = trim((string) ($material['quantity'] ?? ''));
            if ($code === '' && $quantity === '') {
                continue;
            }
            if ($code === '' || $quantity === '') {
                throw new RuntimeException('Šifra i količina materijala moraju biti unesene zajedno.');
            }
            $catalog = $this->connection->table('dbo.tHE_SetItem')
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$code])
                ->first(['anPrStPrice']);
            if ($catalog === null) {
                throw new RuntimeException('Pantheon materijal nije pronađen: ' . $code);
            }
            $total = $this->calculator->add($total, $this->calculator->operation(
                $this->calculator->normalizeNonNegative(str_replace(',', '.', $quantity), 'Količina materijala'),
                $this->calculator->normalizeNonNegative($catalog->anPrStPrice ?? null, 'Cijena materijala'),
                '1'
            )['totalCost']);
        }

        return $total;
    }

    private function prepareReceipts(?array $submitted, string $producedQuantity): array
    {
        // The modal always supplies this. Keeping a default here preserves older
        // internal callers while the HTTP request requires an explicit tab value.
        $submitted ??= [['target' => 'vp', 'quantity' => $producedQuantity]];
        $prepared = [];
        $total = '0';

        foreach ($submitted as $receipt) {
            $target = trim((string) ($receipt['target'] ?? ''));
            $quantity = trim((string) ($receipt['quantity'] ?? ''));
            if ($target === '' && $quantity === '') {
                continue;
            }
            if (!in_array($target, ['vp', 'scrap'], true) || $quantity === '') {
                throw new RuntimeException('Prijem mora imati odredište i količinu.');
            }

            $normalizedQuantity = $this->calculator->normalizeNonNegative($quantity, 'Količina prijema');
            if (bccomp($normalizedQuantity, '0', WorkOrderClosingCalculator::SCALE) <= 0) {
                throw new RuntimeException('Količina prijema mora biti veća od nule.');
            }
            if (isset($prepared[$target])) {
                throw new RuntimeException('Za svako skladište može se unijeti samo jedan prijem.');
            }

            $prepared[$target] = ['target' => $target, 'quantity' => $normalizedQuantity];
            $total = $this->calculator->add($total, $normalizedQuantity);
        }

        if (
            $prepared === []
            || bccomp($total, $producedQuantity, WorkOrderClosingCalculator::SCALE) < 0
            || (
                !isset($prepared['scrap'])
                && bccomp($total, $producedQuantity, WorkOrderClosingCalculator::SCALE) > 0
            )
        ) {
            throw new RuntimeException('Ukupna količina prijema mora biti jednaka planiranoj količini, osim kada je dodat škart.');
        }

        // Keep VP first so the 6400 and 6600 cross-reference uses it whenever
        // both receipts are present.
        $ordered = [];
        foreach (['vp', 'scrap'] as $target) {
            if (isset($prepared[$target])) {
                $ordered[] = $prepared[$target];
            }
        }

        return $ordered;
    }

    private function receiptCalculationForQuantity(array $calculation, string $quantity): array
    {
        $calculation['totalPrice'] = $this->calculator->multiply((string) $calculation['pricePerUnit'], $quantity);
        $calculation['materialTotal'] = $this->calculator->multiply((string) $calculation['materialCostPerUnit'], $quantity);
        $calculation['operationTotal'] = $this->calculator->multiply((string) $calculation['operationCostPerUnit'], $quantity);

        return $calculation;
    }

    /**
     * Closing-material quantities are entered per finished piece. Convert them
     * to the total quantity required by this work order before creating any
     * material documents or changing warehouse stock.
     */
    private function prepareMaterials(array $submitted, string $producedQuantity): array
    {
        $prepared = [];

        foreach ($submitted as $material) {
            $code = strtoupper(trim((string) ($material['code'] ?? '')));
            $quantity = trim((string) ($material['quantity'] ?? ''));
            if ($code === '' && $quantity === '') {
                continue;
            }
            if ($code === '' || $quantity === '') {
                throw new RuntimeException('Material code and quantity must be entered together.');
            }

            $normalizedQuantity = $this->calculator->normalizeNonNegative(
                str_replace(',', '.', $quantity),
                'Material quantity'
            );
            if (bccomp($normalizedQuantity, '0', WorkOrderClosingCalculator::SCALE) <= 0) {
                throw new RuntimeException('Količina materijala mora biti veća od nule.');
            }

            // Match established 6400 material-consumption documents: use the
            // issuing raw-material warehouse's last price, which Pantheon
            // stores on tHE_Stock.anLastPrice and uses as the RN unit price.
            $rawMaterialWarehouse = trim((string) config('work_order_closing.raw_material_warehouse', 'Skladište sirovina'));
            $catalog = $this->connection->table('dbo.tHE_SetItem as i')
                ->leftJoin('dbo.tHE_Stock as s', function ($join) use ($rawMaterialWarehouse) {
                    $join->whereRaw("LTRIM(RTRIM(ISNULL(s.acIdent, ''))) = LTRIM(RTRIM(ISNULL(i.acIdent, ''))) ")
                        ->whereRaw("LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) = ?", [$rawMaterialWarehouse]);
                })
                ->whereRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) = ?", [$code])
                ->first([
                    'i.anQId', 'i.acIdent', 'i.acName', 'i.acUM',
                    's.anLastPrice as warehouse_last_price',
                ]);
            if ($catalog === null || (int) ($catalog->anQId ?? 0) < 1) {
                throw new RuntimeException('Pantheon material was not found: ' . $code);
            }
            $price = $this->calculator->normalizeNonNegative($catalog->warehouse_last_price ?? 0, 'Material price');
            $unit = strtoupper(substr(trim((string) ($catalog->acUM ?? 'KOM')), 0, 3));
            if ($unit === '') {
                $unit = 'KOM';
            }

            $totalQuantity = $this->materialTotalQuantity($normalizedQuantity, $producedQuantity);

            $prepared[] = [
                'item_qid' => (int) ($material['item_qid'] ?? 0),
                'requires_close_time_preparation' => (int) ($material['item_qid'] ?? 0) < 1,
                'position' => 0,
                'code' => trim((string) $catalog->acIdent),
                'name' => trim((string) ($catalog->acName ?? $catalog->acIdent)),
                'unit' => $unit,
                'quantity' => $totalQuantity,
                'price' => $price,
                'total' => $this->calculator->operation($totalQuantity, $price, '1')['totalCost'],
                'ident_qid' => (int) $catalog->anQId,
            ];
        }

        return $prepared;
    }

    private function materialTotalQuantity(string $perPieceQuantity, string $producedQuantity): string
    {
        return $this->calculator->multiply($perPieceQuantity, $producedQuantity);
    }

    /**
     * Materials entered only on the closing tab have not yet left the raw
     * material warehouse. Create (or extend) their 2005 transfer before the
     * closing 6400 releases the same linked WO material from WIP.
     */
    private function prepareCloseTimeMaterials(array $workOrder, array $materials, Carbon $now, int $userId): ?array
    {
        $items = array_values(array_filter($materials, fn (array $material) => (bool) ($material['requires_close_time_preparation'] ?? false)));
        if ($items === []) {
            return null;
        }

        $existing = $this->materialPreparationDocument((string) $workOrder['acKey']);

        if ($existing === null) {
            return $this->materialPreparation->prepare($this->connection, $workOrder, $items, $now, $userId) + ['created' => true];
        }

        return $this->materialPreparation->append($this->connection, $workOrder, $items, $now, $userId) + ['created' => false];
    }

    private function materialPreparationDocument(string $workOrderKey): ?object
    {
        return $this->connection->table('dbo.tHE_Move as m')
            ->join('dbo.tHF_LinkMoveWOEx as l', 'l.acKey', '=', 'm.acKey')
            ->where('l.acLnkKey', $workOrderKey)
            ->where('m.acDocType', '2005')
            ->orderByDesc('m.acKey')
            ->first(['m.acKey', 'm.acKeyView']);
    }

    private function preparedMaterialCostTotal(array $materials): string
    {
        $total = '0';
        foreach ($materials as $material) {
            $total = $this->calculator->add($total, (string) ($material['total'] ?? '0'));
        }

        return $total;
    }

    private function resolveMaterialFlow(array $workOrder, array $submittedMaterials, string $producedQuantity): array
    {
        $createdAt = $this->workOrderCreatedAt($workOrder);
        $cutoff = Carbon::parse((string) config('work_order_closing.work_order_2005_flow_start_date', '2026-07-21 00:00:00'));
        $priority = $this->workOrderPriority($workOrder);
        $submitted = $this->prepareMaterials($submittedMaterials, $producedQuantity);
        // A date change must not reissue raw stock for a WO that was already
        // prepared by 2005 under an earlier configuration.
        $uses2005Flow = $createdAt->gte($cutoff)
            || $this->materialPreparationDocument((string) $workOrder['acKey']) !== null;
        $legacyItemQids = !$uses2005Flow
            ? $this->legacyMaterialItemQids((string) $workOrder['acKey'])
            : [];
        $materials = array_values(array_filter($submitted, function (array $material) use ($legacyItemQids) {
            return !isset($legacyItemQids[(int) ($material['item_qid'] ?? 0)]);
        }));
        $hasLegacyRows = $legacyItemQids !== [];

        return [
            'materials' => $materials,
            'source_warehouse' => $uses2005Flow
                ? trim((string) config('work_order_closing.work_in_progress_warehouse', config('work_order_closing.operation_warehouse', '')))
                : trim((string) config('work_order_closing.raw_material_warehouse', 'SkladiĹˇte sirovina')),
            'name' => $uses2005Flow
                ? 'current_proizvodnja_u_toku_to_veleprodajno'
                : ($hasLegacyRows
                    ? 'legacy_6400_scan_and_skladiste_sirovina_to_veleprodajno'
                    : 'legacy_skladiste_sirovina_to_veleprodajno'),
            'created_at' => $createdAt,
            'cutoff' => $cutoff,
            'document_2005_id' => null,
            'uses_2005_flow' => $uses2005Flow,
            'priority' => $priority,
        ];
    }

    private function legacyMaterialItemQids(string $workOrderKey): array
    {
        $rows = $this->connection->table('dbo.tHF_WOExItem as wi')
            ->leftJoin('dbo.tHE_SetItem as si', 'si.acIdent', '=', 'wi.acIdent')
            ->join('dbo.tHF_LinkMoveItemWOExItem as li', 'li.anWOExItemQid', '=', 'wi.anQId')
            ->join('dbo.tHE_Move as m', 'm.acKey', '=', 'li.acKey')
            ->where('wi.acKey', $workOrderKey)
            ->where('m.acDocType', '6400')
            ->where(function ($query) {
                $query->whereNull('wi.acOperationType')
                    ->orWhereNotIn('wi.acOperationType', ['D', 'O']);
            })
            ->whereRaw("LTRIM(RTRIM(ISNULL(si.acSetOfItem, ''))) <> 'OPR'")
            ->pluck('wi.anQId');

        return array_fill_keys(array_map(fn ($qid) => (int) $qid, $rows->all()), true);
    }

    private function workOrderCreatedAt(array $workOrder): Carbon
    {
        foreach (['created_at', 'adDateIns', 'adTimeIns', 'adDate'] as $field) {
            $value = $workOrder[$field] ?? null;
            if (trim((string) $value) !== '') {
                return Carbon::parse((string) $value);
            }
        }

        throw new RuntimeException('Radni nalog nema datum kreiranja potreban za odabir toka materijala.');
    }

    private function workOrderPriority(array $workOrder): int
    {
        foreach (['anPriority', 'acPriority', 'priority'] as $field) {
            $value = trim((string) ($workOrder[$field] ?? ''));
            if (preg_match('/^\d+/', $value, $matches) === 1) {
                return (int) $matches[0];
            }
        }

        return 0;
    }


    private function existingClosingDocuments(string $workOrderKey): array
    {
        return $this->connection->table('dbo.tHE_Move as m')
            ->join('dbo.tHF_LinkMoveWOEx as l', 'l.acKey', '=', 'm.acKey')
            ->where('l.acLnkKey', $workOrderKey)
            ->whereIn('m.acDocType', ['6100', '6400', '6600', '7100'])
            ->orderByDesc('m.acKey')
            ->get(['m.acKey', 'm.acKeyView', 'm.acDocType', 'm.anValue'])
            ->mapWithKeys(fn ($row) => [trim((string) $row->acDocType) => (array) $row])
            ->all();
    }

    private function alreadyClosedResult(array $workOrder, array $existing): array
    {
        $operations = $existing['6600'];
        $receipt = $existing['6100'] ?? $existing['7100'];
        $operationNumber = $this->formatNumber((string) ($operations['acKeyView'] ?? $operations['acKey']));
        $receiptNumber = $this->formatNumber((string) ($receipt['acKeyView'] ?? $receipt['acKey']));
        $quantity = $this->calculator->normalizeNonNegative($workOrder['anPlanQty'] ?? 0, 'Planirana količina');
        $operationCost = $this->calculator->normalizeNonNegative($operations['anValue'] ?? 0, 'Trošak operacija');
        $receiptTotal = $this->calculator->normalizeNonNegative($receipt['anValue'] ?? 0, 'Vrijednost prijema');
        $materialCost = bccomp($receiptTotal, $operationCost, WorkOrderClosingCalculator::SCALE) >= 0
            ? bcsub($receiptTotal, $operationCost, WorkOrderClosingCalculator::SCALE)
            : '0.000000';
        $pricePerUnit = bccomp($quantity, '0', WorkOrderClosingCalculator::SCALE) > 0
            ? $this->calculator->divide($receiptTotal, $quantity)
            : '0.000000';

        return [
            'already_closed' => true,
            'status' => 'zaključen',
            'work_order_key' => $workOrder['acKey'],
            'work_order_number' => $this->formatNumber((string) ($workOrder['acKeyView'] ?? $workOrder['acKey'])),
            'message' => 'kreirani dokumenti ' . $operationNumber . ' i ' . $receiptNumber,
            'documents' => [
                ['document_key' => $operations['acKey'], 'document_number' => $operationNumber, 'document_type' => '6600', 'work_order_code' => $workOrder['acKeyView'] ?? $workOrder['acKey'], 'quantity' => $quantity, 'operation_cost' => $operationCost],
                ['document_key' => $receipt['acKey'], 'document_number' => $receiptNumber, 'document_type' => $receipt['acDocType'] ?? '6100', 'work_order_code' => $workOrder['acKeyView'] ?? $workOrder['acKey'], 'item_code' => $workOrder['acIdent'] ?? '', 'quantity' => $quantity, 'price_per_unit' => $pricePerUnit, 'total_price' => $receiptTotal, 'operation_cost' => $operationCost, 'material_cost' => $materialCost],
            ],
        ];
    }

    private function markClosed(array $workOrder, string $quantity, Carbon $now, int $userId): void
    {
        $updated = $this->connection->table('dbo.tHF_WOEx')
            ->where('acKey', $workOrder['acKey'])
            ->update([
                'acStatus' => 'I',
                'acStatusMF' => 'Z',
                'anProducedQty' => $quantity,
                'acReceiveFinished' => 'Y',
                'adWOFinishDate' => $now,
                'adTimeChg' => $now,
                'anUserChg' => $userId,
            ]);

        if ($updated < 1) {
            throw new RuntimeException('Status radnog naloga nije ažuriran.');
        }
    }

    /**
     * Synchronize the fields used by Pantheon's Realizacija vs. Plan display.
     * Linked 6400/6600 lines are measured against their planned WO item
     * quantity. If Pantheon has no BOM baseline, a closed WO with final
     * documents is treated as fully completed.
     */
    private function syncClosedWorkOrderCompletion(array $workOrder, Carbon $now, int $userId): void
    {
        $workOrderKey = (string) $workOrder['acKey'];
        $materialDoneByItem = $this->connection->table('dbo.tHF_LinkMoveItemWOExItem as li')
            ->join('dbo.tHE_MoveItem as mi', function ($join) {
                $join->on('mi.acKey', '=', 'li.acKey')
                    ->on('mi.anNo', '=', 'li.anNo');
            })
            ->join('dbo.tHE_Move as m', 'm.acKey', '=', 'mi.acKey')
            ->join('dbo.tHF_LinkMoveWOEx as l', 'l.acKey', '=', 'm.acKey')
            ->where('l.acLnkKey', $workOrderKey)
            ->where('m.acDocType', '6400')
            ->groupBy('li.anWOExItemQid')
            ->selectRaw('li.anWOExItemQid, SUM(mi.anQty) as material_quantity')
            ->pluck('material_quantity', 'li.anWOExItemQid')
            ->all();

        $items = $this->connection->table('dbo.tHF_WOExItem as wi')
            ->leftJoin('dbo.tHE_SetItem as si', 'si.acIdent', '=', 'wi.acIdent')
            ->where('wi.acKey', $workOrderKey)
            ->orderBy('wi.anNo')
            ->get(['wi.anQId', 'wi.anNo', 'wi.acOperationType', 'wi.anPlanQty', 'si.acSetOfItem']);

        foreach ($items as $item) {
            $itemQid = (int) $item->anQId;
            $isOperation = in_array(strtoupper(trim((string) $item->acOperationType)), ['D', 'O'], true)
                || strtoupper(trim((string) $item->acSetOfItem)) === 'OPR';

            if ($isOperation) {
                $this->closingWorkOrderItems->ensureOperationResourceRow(
                    $this->connection,
                    $itemQid,
                    $workOrderKey,
                    (int) $item->anNo,
                    $now,
                    $userId
                );
                $resource = $this->connection->table('dbo.tHF_WOExItemResources')
                    ->where('anWOExItemQId', $itemQid)
                    ->first(['anQty', 'anPlanQty']);
                $percent = $this->completionPercent($resource->anQty ?? 0, $resource->anPlanQty ?? 0, true);

                $this->connection->table('dbo.tHF_WOExItemResources')
                    ->where('anWOExItemQId', $itemQid)
                    ->update([
                        'anExecutionPerc' => $percent,
                        'acIssueFinished' => $percent >= 100 ? 'Y' : 'N',
                        'adTimeChg' => $now,
                        'anUserChg' => $userId,
                    ]);
                $this->connection->table('dbo.tHF_WOExItem')
                    ->where('anQId', $itemQid)
                    ->update([
                        'acIssueFinished' => $percent >= 100 ? 'Y' : 'N',
                        'adTimeChg' => $now,
                        'anUserChg' => $userId,
                    ]);

                continue;
            }

            $actual = $materialDoneByItem[$itemQid] ?? 0;
            $percent = $this->completionPercent($actual, $item->anPlanQty ?? 0, true);
            $this->connection->table('dbo.tHF_WOExItem')
                ->where('anQId', $itemQid)
                ->update([
                    'anQty' => $actual,
                    'anQty1' => $actual,
                    'anIssuePerc' => $percent,
                    'acIssueFinished' => $percent >= 100 ? 'Y' : 'N',
                    'adTimeChg' => $now,
                    'anUserChg' => $userId,
                ]);
        }
    }

    private function completionPercent(mixed $actual, mixed $planned, bool $closedWithDocuments): float
    {
        $actual = is_numeric((string) $actual) ? max(0.0, (float) $actual) : 0.0;
        $planned = is_numeric((string) $planned) ? max(0.0, (float) $planned) : 0.0;

        if ($planned <= 0) {
            return $closedWithDocuments ? 100.0 : 0.0;
        }

        return min(100.0, ($actual / $planned) * 100);
    }

    private function markPartiallyClosed(array $workOrder, Carbon $now, int $userId): void
    {
        $updated = $this->connection->table('dbo.tHF_WOEx')
            ->where('acKey', $workOrder['acKey'])
            ->update([
                // Confirmed from Pantheon records: partial = N/R, full = I/Z.
                'acStatus' => 'N',
                'acStatusMF' => 'R',
                'adTimeChg' => $now,
                'anUserChg' => $userId,
            ]);

        if ($updated < 1) {
            throw new RuntimeException('Status radnog naloga nije ažuriran.');
        }
    }

    private function resolveDepartment(array $workOrder, string $userName): string
    {
        foreach ([
            config('work_order_closing.department'),
            $workOrder['acDept'] ?? '',
            $userName,
        ] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && $this->connection->table('dbo.tHE_SetSubj')->whereRaw("LTRIM(RTRIM(ISNULL(acSubject, ''))) = ?", [$candidate])->exists()) {
                return $candidate;
            }
        }

        return '';
    }

    private function formatNumber(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        return is_string($digits) && strlen($digits) >= 12
            ? substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, -6)
            : trim($value);
    }
}
