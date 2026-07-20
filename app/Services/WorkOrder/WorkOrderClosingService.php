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
        private PantheonMaterialStockService $materialStock,
        private PantheonMaterialPreparationService $materialPreparation,
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

            if (isset($existing['6400'])) {
                throw new RuntimeException('Dokument 6400 za radni nalog već postoji. Materijal nije ponovo razdužen.');
            }

            if (isset($existing['6600']) && (isset($existing['6100']) || isset($existing['7100']))) {
                return $this->alreadyClosedResult($workOrder, $existing);
            }

            if ($existing !== []) {
                throw new RuntimeException('Radni nalog ima nepotpun postojeći skup završnih dokumenata. Nije kreiran novi dokument.');
            }

            if (strtoupper(trim((string) ($workOrder['acStatusMF'] ?? ''))) === 'Z') {
                throw new RuntimeException('Radni nalog je već zatvoren.');
            }

            $producedQuantity = $this->calculator->normalizeNonNegative($workOrder['anPlanQty'] ?? null, 'Planirana količina');
            if (bccomp($producedQuantity, '0', WorkOrderClosingCalculator::SCALE) <= 0) {
                throw new RuntimeException('Planirana količina mora biti veća od nule.');
            }

            $preparedOperations = $this->prepareOperations((string) $workOrder['acKey'], $submittedOperations);
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
            $materials = $this->preparedMaterialsFromTransfer((string) $workOrder['acKey']);
            $submittedCloseMaterials = $this->prepareMaterials(array_values(array_filter($submittedMaterials, fn ($material) => (bool) ($material['is_new'] ?? false))));
            $transferredCodes = array_fill_keys(array_map(fn ($material) => strtolower((string) $material['code']), $materials), true);
            $newCloseMaterials = array_values(array_filter($submittedCloseMaterials, fn ($material) => !isset($transferredCodes[strtolower((string) $material['code'])])));
            if ($newCloseMaterials !== []) {
                $this->materialPreparation->append($this->connection, $workOrder, $newCloseMaterials, $now, $userId);
                $materials = array_merge($materials, $newCloseMaterials);
            }

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
            $materialTotal = $this->preparedMaterialCostTotal($materials);
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
                    'issuer' => (string) config('work_order_closing.work_in_progress_warehouse', config('work_order_closing.operation_warehouse', 'RN skladište')),
                    'receiver_stock' => 'N',
                    'issuer_stock' => 'Y',
                    'person3' => $consignee,
                    'way_of_sale' => 'I',
                    'department' => $department,
                ]
            );
            if ($materialResult !== null) {
                $warehouse = trim((string) config('work_order_closing.work_in_progress_warehouse', config('work_order_closing.operation_warehouse', '')));
                if ($warehouse === '') {
                    throw new RuntimeException('Konfiguracija međuskladišta nije postavljena.');
                }
                $this->materialStock->issue($this->connection, $warehouse, $materials, $now, $userId);
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

            Log::info('Work order closed through eNalog.', [
                'work_order_key' => $workOrder['acKey'],
                'material_document' => $materialResult['document_number'] ?? null,
                'operation_document' => $operationResult['document_number'],
                'receipt_documents' => array_column($receiptResults, 'document_number'),
                'created_work_order_items' => $closingItems['created_items'],
                'user_id' => $userId,
            ]);

            return [
                'already_closed' => false,
                'status' => 'zaključen',
                'work_order_key' => $workOrder['acKey'],
                'work_order_number' => $this->formatNumber((string) ($workOrder['acKeyView'] ?? $workOrder['acKey'])),
                'message' => 'kreirani dokumenti ' . implode(' i ', array_filter([
                    $materialResult['document_number'] ?? null,
                    $operationResult['document_number'],
                    ...array_column($receiptResults, 'document_number'),
                ])),
                'documents' => array_values(array_filter([$materialResult, $operationResult, ...$receiptResults])),
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

    private function prepareOperations(string $workOrderKey, array $submitted): array
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

                $timing = $this->operationTiming($input);
                $minutesPerUnit = $this->calculator->add($minutesPerUnit, $timing['minutes']);
                $workerEntries[] = [
                    'worker' => $worker,
                    'minutes_per_unit' => $timing['minutes'],
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
            $timing = $this->operationTiming($input);
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
        $time = trim((string) ($input['time'] ?? ''));
        $start = trim((string) ($input['start_time'] ?? ''));
        $end = trim((string) ($input['end_time'] ?? ''));

        return $workerId > 0 && ($time !== '' || ($start !== '' && $end !== ''));
    }

    private function isEmptyOperationInput(array $input): bool
    {
        // The code is prefilled for existing WO positions and can be selected
        // in a manual row. A code alone is not work performed, therefore it
        // must be treated as an empty placeholder instead of an incomplete
        // operation that blocks the rest of the closing request.
        return (int) ($input['worker_id'] ?? 0) < 1
            && trim((string) ($input['time'] ?? '')) === ''
            && trim((string) ($input['start_time'] ?? '')) === ''
            && trim((string) ($input['end_time'] ?? '')) === '';
    }

    private function operationTiming(array $input): array
    {
        $start = trim((string) ($input['start_time'] ?? ''));
        $end = trim((string) ($input['end_time'] ?? ''));

        if ($start === '' && $end === '') {
            $minutes = $this->calculator->normalizeNonNegative($input['time'] ?? null, 'Vrijeme operacije');

            return [
                'minutes' => $minutes,
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

        // Calculate server-side instead of trusting the browser's computed value.
        return [
            'minutes' => (string) (($endMinutes - $startMinutes) - $this->breakOverlapMinutes($startMinutes, $endMinutes)),
            'start_time' => $this->formatClockMinutes($startMinutes),
            'end_time' => $this->formatClockMinutes($endMinutes),
        ];
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

        if ($prepared === [] || bccomp($total, $producedQuantity, WorkOrderClosingCalculator::SCALE) !== 0) {
            throw new RuntimeException('Ukupna količina prijema mora biti jednaka planiranoj količini radnog naloga.');
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

    private function prepareMaterials(array $submitted): array
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

            $catalog = $this->connection->table('dbo.tHE_SetItem')
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$code])
                ->first(['anQId', 'acIdent', 'acName', 'acUM', 'anPrStPrice']);
            if ($catalog === null || (int) ($catalog->anQId ?? 0) < 1) {
                throw new RuntimeException('Pantheon material was not found: ' . $code);
            }

            $normalizedQuantity = $this->calculator->normalizeNonNegative(
                str_replace(',', '.', $quantity),
                'Material quantity'
            );
            $price = $this->calculator->normalizeNonNegative($catalog->anPrStPrice ?? null, 'Material price');
            $unit = strtoupper(substr(trim((string) ($catalog->acUM ?? 'KOM')), 0, 3));
            if ($unit === '') {
                $unit = 'KOM';
            }

            $prepared[] = [
                'item_qid' => 0,
                'position' => 0,
                'code' => trim((string) $catalog->acIdent),
                'name' => trim((string) ($catalog->acName ?? $catalog->acIdent)),
                'unit' => $unit,
                'quantity' => $normalizedQuantity,
                'price' => $price,
                'total' => $this->calculator->operation($normalizedQuantity, $price, '1')['totalCost'],
                'ident_qid' => (int) $catalog->anQId,
            ];
        }

        return $prepared;
    }

    private function preparedMaterialCostTotal(array $materials): string
    {
        $total = '0';
        foreach ($materials as $material) {
            $total = $this->calculator->add($total, (string) ($material['total'] ?? '0'));
        }

        return $total;
    }

    /** 6400 is always based on the quantities actually moved by 2005. */
    private function preparedMaterialsFromTransfer(string $workOrderKey): array
    {
        $transfer = $this->connection->table('dbo.tHE_Move as m')
            ->join('dbo.tHF_LinkMoveWOEx as l', 'l.acKey', '=', 'm.acKey')
            ->where('l.acLnkKey', $workOrderKey)->where('m.acDocType', '2005')
            ->orderByDesc('m.acKey')->first(['m.acKey']);
        if ($transfer === null) throw new RuntimeException('Dokument 2005 za pripremu materijala nije pronađen.');
        $items = $this->connection->table('dbo.tHE_MoveItem')->where('acKey', $transfer->acKey)->orderBy('anNo')->get();
        if ($items->isEmpty()) throw new RuntimeException('Dokument 2005 nema materijalne stavke.');
        return $items->map(function ($row) use ($workOrderKey) {
            $itemQid = (int) ($this->connection->table('dbo.tHF_LinkMoveItemWOExItem')
                ->where('acKey', $row->acKey)->where('anNo', $row->anNo)->value('anWOExItemQid') ?? 0);
            if ($itemQid < 1) {
                $itemQid = (int) ($this->connection->table('dbo.tHF_WOExItem')
                    ->where('acKey', $workOrderKey)->whereRaw("LTRIM(RTRIM(acIdent)) = ?", [trim((string) $row->acIdent)])
                    ->orderBy('anNo')->value('anQId') ?? 0);
            }
            return [
            'item_qid'=>$itemQid, 'position'=>(int) $row->anNo, 'code'=>trim((string) $row->acIdent),
            'name'=>trim((string) ($row->acName ?? $row->acIdent)), 'unit'=>trim((string) ($row->acUM ?? 'KOM')),
            'quantity'=>(string) $row->anQty, 'price'=>(string) ($row->anPrice ?? 0),
            'total'=>bcmul((string) $row->anQty, (string) ($row->anPrice ?? 0), WorkOrderClosingCalculator::SCALE),
            'ident_qid'=>(int) ($row->anIdentQId ?? 0),
        ];
        })->all();
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
