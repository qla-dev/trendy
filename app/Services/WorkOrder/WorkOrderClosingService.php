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
        private PantheonOperationDocumentService $operationDocuments,
        private PantheonFinishedGoodsReceiptService $receiptDocuments,
        private PantheonWorkerSearchService $workers,
        private WorkOrderClosingCalculator $calculator
    ) {
        $name = trim((string) config('services.work_order.target_connection', 'work_order_target'));
        $this->connection = DB::connection($name !== '' ? $name : 'work_order_target');
    }

    public function close(string $locator, array $submittedOperations, int $userId, string $userName = ''): array
    {
        $now = Carbon::now();

        return $this->connection->transaction(function () use ($locator, $submittedOperations, $userId, $userName, $now) {
            $workOrder = $this->lockWorkOrder($locator);
            $existing = $this->existingClosingDocuments((string) $workOrder['acKey']);

            if (isset($existing['6100'], $existing['6600'])) {
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

            $operations = $this->prepareOperations((string) $workOrder['acKey'], $submittedOperations);
            $materialTotal = $this->materialCostTotal((string) $workOrder['acKey']);
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
            $operationNumber = $this->numbers->next($this->connection, (string) config('work_order_closing.operation_document_type', '6600'), $now);
            $receiptNumber = $this->numbers->next($this->connection, (string) config('work_order_closing.receipt_document_type', '6100'), $now);
            $department = $this->resolveDepartment($workOrder, $userName);
            $consignee = trim((string) ($workOrder['acReceiver'] ?: $workOrder['acConsignee'] ?? ''));

            if ($consignee === '') {
                throw new RuntimeException('Pantheon primalac radnog naloga nije pronađen.');
            }

            $operationResult = $this->operationDocuments->create(
                $this->connection,
                $operationNumber,
                $receiptNumber,
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

            $receiptResult = $this->receiptDocuments->create(
                $this->connection,
                $receiptNumber,
                $workOrder,
                $receiptCalculation,
                $now,
                $userId,
                $producedQuantity,
                [
                    'receiver' => (string) config('work_order_closing.receipt_warehouse', 'Veleprodajno skladište'),
                    'issuer' => $consignee,
                    'receiver_stock' => 'Y',
                    'issuer_stock' => 'N',
                    'person3' => $consignee,
                    'way_of_sale' => 'U',
                    'department' => $department,
                ]
            );

            $this->markClosed($workOrder, $producedQuantity, $now, $userId);

            Log::info('Work order closed through eNalog.', [
                'work_order_key' => $workOrder['acKey'],
                'operation_document' => $operationResult['document_number'],
                'receipt_document' => $receiptResult['document_number'],
                'user_id' => $userId,
            ]);

            return [
                'already_closed' => false,
                'status' => 'završen',
                'work_order_key' => $workOrder['acKey'],
                'work_order_number' => $this->formatNumber((string) ($workOrder['acKeyView'] ?? $workOrder['acKey'])),
                'message' => 'kreirani dokumenti ' . $operationResult['document_number'] . ' i ' . $receiptResult['document_number'],
                'documents' => [$operationResult, $receiptResult],
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

        if ($rows->isEmpty()) {
            throw new RuntimeException('Radni nalog nema operacije za zatvaranje.');
        }

        $submittedByItem = [];
        foreach ($submitted as $operation) {
            $qid = (int) ($operation['item_qid'] ?? 0);
            if ($qid > 0) {
                $submittedByItem[$qid][] = $operation;
            }
        }

        $prepared = [];
        foreach ($rows as $row) {
            $itemQId = (int) $row->anQId;
            $inputs = $submittedByItem[$itemQId] ?? [];
            if ($inputs === []) {
                throw new RuntimeException('Sve operacije moraju imati odabranog radnika i vrijeme.');
            }

            $price = $this->calculator->normalizeNonNegative($row->anPrStPrice ?? null, 'Cijena operacije');
            $minutesPerUnit = '0';
            $workerEntries = [];

            foreach ($inputs as $input) {
                $worker = $this->workers->findActive((int) ($input['worker_id'] ?? 0));
                if ($worker === null) {
                    throw new RuntimeException('Odabrani radnik ne postoji ili nije aktivan u Pantheonu.');
                }

                $minutes = $this->operationMinutes($input);
                $minutesPerUnit = $this->calculator->add($minutesPerUnit, $minutes);
                $workerEntries[] = [
                    'worker' => $worker,
                    'minutes_per_unit' => $minutes,
                    'start_time' => trim((string) ($input['start_time'] ?? '')),
                    'end_time' => trim((string) ($input['end_time'] ?? '')),
                ];
            }

            // Multiple copied rows are one Pantheon operation. Their entered
            // times are summed before document and operation-cost creation.
            $prepared[] = [
                'item_qid' => $itemQId,
                'position' => (int) $row->anNo,
                'code' => trim((string) $row->acIdent),
                'name' => trim((string) ($row->acDescr ?? $row->acIdent)),
                'minutes_per_unit' => $minutesPerUnit,
                'price_per_minute' => $price,
                'worker_entries' => $workerEntries,
            ];
        }

        if (count($submittedByItem) !== count($prepared)) {
            throw new RuntimeException('Zahtjev sadrži operaciju koja ne pripada radnom nalogu.');
        }

        return $prepared;
    }

    private function operationMinutes(array $input): string
    {
        $start = trim((string) ($input['start_time'] ?? ''));
        $end = trim((string) ($input['end_time'] ?? ''));

        if ($start === '' && $end === '') {
            return $this->calculator->normalizeNonNegative($input['time'] ?? null, 'Vrijeme operacije');
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
        return (string) ($endMinutes - $startMinutes);
    }

    private function clockMinutes(string $value): ?int
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
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

    private function existingClosingDocuments(string $workOrderKey): array
    {
        return $this->connection->table('dbo.tHE_Move as m')
            ->join('dbo.tHF_LinkMoveWOEx as l', 'l.acKey', '=', 'm.acKey')
            ->where('l.acLnkKey', $workOrderKey)
            ->whereIn('m.acDocType', ['6100', '6600'])
            ->orderByDesc('m.acKey')
            ->get(['m.acKey', 'm.acKeyView', 'm.acDocType', 'm.anValue'])
            ->mapWithKeys(fn ($row) => [trim((string) $row->acDocType) => (array) $row])
            ->all();
    }

    private function alreadyClosedResult(array $workOrder, array $existing): array
    {
        $operations = $existing['6600'];
        $receipt = $existing['6100'];
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
            'status' => 'završen',
            'work_order_key' => $workOrder['acKey'],
            'work_order_number' => $this->formatNumber((string) ($workOrder['acKeyView'] ?? $workOrder['acKey'])),
            'message' => 'kreirani dokumenti ' . $operationNumber . ' i ' . $receiptNumber,
            'documents' => [
                ['document_key' => $operations['acKey'], 'document_number' => $operationNumber, 'document_type' => '6600', 'work_order_code' => $workOrder['acKeyView'] ?? $workOrder['acKey'], 'quantity' => $quantity, 'operation_cost' => $operationCost],
                ['document_key' => $receipt['acKey'], 'document_number' => $receiptNumber, 'document_type' => '6100', 'work_order_code' => $workOrder['acKeyView'] ?? $workOrder['acKey'], 'item_code' => $workOrder['acIdent'] ?? '', 'quantity' => $quantity, 'price_per_unit' => $pricePerUnit, 'total_price' => $receiptTotal, 'operation_cost' => $operationCost, 'material_cost' => $materialCost],
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
