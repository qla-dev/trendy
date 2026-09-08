<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Isolated clean-up workflow for the temporary excess-material warehouse.
 * Reservations live on flagged open WO item rows; physical stock changes only
 * when WorkOrderClosingService creates the dedicated 6400 document.
 */
class ExcessStockService
{
    public function connection(?string $name = null): ConnectionInterface
    {
        $name = trim((string) ($name ?: config('services.work_order.target_connection', 'work_order_target')));

        return DB::connection($name !== '' ? $name : 'work_order_target');
    }

    public function warehouse(): string { return trim((string) config('excess-stock.warehouse')); }
    public function marker(): string { return (string) config('excess-stock.reservation_marker'); }
    public function documentMarker(): string { return (string) config('excess-stock.document_marker'); }

    /** Older rows retain their machine-readable markers after the note became a sentence. */
    public function markers(): array {
        return array_values(array_unique([
            $this->marker(),
            'Automatski popunjeno sa ' . mb_strtolower($this->warehouse()),
            'Automatski popunjeno sa skladišta dodatnih sirovina',
            'REZERVACIJA_DODATNIH_SIROVINA',
            'EXCESS_STOCK_RESERVATION',
        ]));
    }
    public function documentMarkers(): array { return array_values(array_unique([$this->documentMarker(), 'EXCESS_STOCK_CLEANUP_6400'])); }

    public function isExcessItem(array|object $item): bool
    {
        $value = is_array($item) ? ($item['acNote'] ?? '') : ($item->acNote ?? '');
        $value = strtoupper(trim((string) $value));
        foreach ($this->markers() as $marker) {
            if (str_starts_with($value, strtoupper($marker))) return true;
        }
        return false;
    }

    public function reservedByMaterial(ConnectionInterface $db): array
    {
        return $db->table('dbo.tHF_WOExItem as wi')
            ->join('dbo.tHF_WOEx as wo', 'wo.acKey', '=', 'wi.acKey')
            ->where(function ($query) {
                foreach ($this->markers() as $marker) {
                    $query->orWhereRaw("UPPER(LTRIM(RTRIM(ISNULL(wi.acNote, '')))) LIKE ?", [strtoupper($marker) . '%']);
                }
            })
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(wo.acStatusMF, '')))) <> 'Z'")
            ->groupBy('wi.acIdent')
            ->selectRaw('wi.acIdent, SUM(CAST(ISNULL(wi.anQty, 0) AS decimal(18,6))) AS reserved_qty')
            ->pluck('reserved_qty', 'acIdent')
            ->mapWithKeys(fn ($qty, $code) => [strtoupper(trim((string) $code)) => (string) $qty])
            ->all();
    }

    /** Returns only material that can still be assigned, never negative stock. */
    public function availableMaterials(ConnectionInterface $db): array
    {
        $reserved = $this->reservedByMaterial($db);
        return $db->table('dbo.tHE_Stock as s')
            ->join('dbo.tHE_SetItem as i', 'i.acIdent', '=', 's.acIdent')
            ->whereRaw("LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) = ?", [$this->warehouse()])
            ->groupBy('s.acIdent', 'i.acName', 'i.acUM', 'i.anQId')
            ->havingRaw('SUM(CAST(ISNULL(s.anStock, 0) AS decimal(18,6))) > 0')
            ->selectRaw('s.acIdent, MAX(i.acName) AS acName, MAX(i.acUM) AS acUM, MAX(i.anQId) AS ident_qid, SUM(CAST(ISNULL(s.anStock, 0) AS decimal(18,6))) AS physical_qty, MAX(CAST(ISNULL(s.anLastPrice, 0) AS decimal(18,6))) AS last_price')
            ->get()
            ->map(function ($row) use ($reserved) {
                $code = strtoupper(trim((string) $row->acIdent));
                $physical = (string) $row->physical_qty;
                $held = (string) ($reserved[$code] ?? '0');
                $available = bcsub($physical, $held, WorkOrderClosingCalculator::SCALE);
                return [
                    'code' => trim((string) $row->acIdent), 'name' => trim((string) $row->acName),
                    'unit' => trim((string) $row->acUM), 'ident_qid' => (int) $row->ident_qid,
                    'physical_qty' => $physical, 'reserved_qty' => $held,
                    'available_qty' => bccomp($available, '0', WorkOrderClosingCalculator::SCALE) > 0 ? $available : '0',
                    'price' => (string) $row->last_price,
                ];
            })
            ->filter(fn (array $row) => bccomp($row['available_qty'], '0', WorkOrderClosingCalculator::SCALE) > 0)
            ->sortByDesc('available_qty')->values()->all();
    }

    public function openWorkOrders(ConnectionInterface $db): array
    {
        return $db->table('dbo.tHF_WOEx')
            ->whereIn('acStatusMF', ['O', 'R'])
            ->orderBy('adDate')->orderBy('acKey')
            ->get(['acKey', 'acKeyView', 'adDate', 'anPlanQty'])
            ->map(fn ($row) => (array) $row)->all();
    }

    /** Builds either a target-date plan or stable per-finished-piece assignments. */
    public function preview(ConnectionInterface $db, ?int $limit = null): array
    {
        $orders = $this->openWorkOrders($db);
        if ($limit !== null) $orders = array_slice($orders, 0, max(0, $limit));

        return $this->previewForOrders($db, $orders);
    }

    /**
     * Build a plan for the supplied RN set.  Backfills deliberately provide
     * all open RNs; an interactive RN creation must provide only its new RN.
     */
    private function previewForOrders(ConnectionInterface $db, array $orders): array
    {
        $materials = $this->availableMaterials($db);
        $slots = count($orders) * max(1, (int) config('excess-stock.max_materials_per_work_order', 5));
        $mode = (string) config('excess-stock.assignment_mode', 'sales_price_percent');
        $percentage = (string) config('excess-stock.sales_price_percent', '0.07');
        $eurToKmRate = (string) config('excess-stock.sales_price_eur_to_km_rate', '1.958');
        if ($mode !== 'sales_price_percent'
            || bccomp($percentage, '0', WorkOrderClosingCalculator::SCALE) <= 0
            || bccomp($percentage, '1', WorkOrderClosingCalculator::SCALE) > 0
            || bccomp($eurToKmRate, '0', WorkOrderClosingCalculator::SCALE) <= 0) {
            throw new RuntimeException('Neispravno je podeĹˇen limit dodatnih sirovina po prodajnoj cijeni.');
        }
        return ['orders' => $orders, 'materials' => $materials, 'slots' => $slots, 'mode' => $mode, 'sales_price_percent' => $percentage, 'sales_price_eur_to_km_rate' => $eurToKmRate];
        /* Legacy target-date/fixed allocation retained below temporarily for historical reference.
        if ($mode === 'fixed' && bccomp($fixed, '0', WorkOrderClosingCalculator::SCALE) <= 0) {
            throw new RuntimeException('Fiksna količina dodatnih sirovina mora biti veća od nule.');
        }
        $assignmentsPerMaterial = $materials === [] ? 0 : max(1, intdiv($slots, count($materials)));
        $remainder = $materials === [] ? 0 : $slots % count($materials);
        $plan = [];
        foreach ($materials as $index => $material) {
            $opportunities = $mode === 'fixed'
                ? $this->fixedAssignmentCount($material['available_qty'], $fixed)
                : $assignmentsPerMaterial + ($index < $remainder ? 1 : 0);
            // The actual fixed quantity is resolved per RN in assignPreview:
            // fixed quantity per finished piece × that RN's planned quantity.
            $qty = $mode === 'fixed' ? $fixed : bcdiv($material['available_qty'], (string) max(1, $opportunities), WorkOrderClosingCalculator::SCALE);
            if (bccomp($qty, $material['available_qty'], WorkOrderClosingCalculator::SCALE) > 0) $qty = $material['available_qty'];
            $plan[] = $material + ['assignment_qty' => $qty, 'assignment_opportunities' => $opportunities];
        }
        return ['orders' => $orders, 'materials' => $plan, 'slots' => $slots, 'target_date' => (string) config('excess-stock.target_date'), 'mode' => $mode, 'fixed_quantity' => $fixed]; */
    }

    public function assignPreview(ConnectionInterface $db, ?int $limit = null): array
    {
        $preview = $this->preview($db, $limit);
        return $this->salesPriceAssignments($db, $preview);
        /* Legacy target-date/fixed allocator retained below temporarily for historical reference.
        $max = max(1, (int) config('excess-stock.max_materials_per_work_order', 5));
        $remaining = array_map(fn (array $material) => (int) $material['assignment_opportunities'], $preview['materials']);
        $remainingQuantity = array_map(fn (array $material) => (string) $material['available_qty'], $preview['materials']);
        $cursor = 0;
        $materialCount = count($preview['materials']);
        $assignments = [];
        foreach ($preview['orders'] as $order) {
            $lines = [];
            $seen = [];
            $attempts = 0;
            while (count($lines) < $max && $attempts < max(1, $materialCount * 2)) {
                if ($materialCount === 0) break;
                $index = $cursor % $materialCount;
                $cursor++;
                $attempts++;
                if (($remaining[$index] ?? 0) < 1) continue;
                $code = strtoupper((string) $preview['materials'][$index]['code']);
                if (isset($seen[$code])) continue;
                $seen[$code] = true;
                $material = $preview['materials'][$index];
                if (($preview['mode'] ?? '') === 'fixed') {
                    $plannedPieces = (string) ($order['anPlanQty'] ?? '0');
                    if (bccomp($plannedPieces, '0', WorkOrderClosingCalculator::SCALE) <= 0) {
                        $plannedPieces = '1';
                    }
                    $quantityForWorkOrder = bcmul($preview['fixed_quantity'], $plannedPieces, WorkOrderClosingCalculator::SCALE);
                    $quantity = bccomp($remainingQuantity[$index], $quantityForWorkOrder, WorkOrderClosingCalculator::SCALE) < 0
                        ? $remainingQuantity[$index]
                        : $quantityForWorkOrder;
                    if (bccomp($quantity, '0', WorkOrderClosingCalculator::SCALE) <= 0) {
                        $remaining[$index] = 0;
                        continue;
                    }
                    $material['assignment_qty'] = $quantity;
                    $remainingQuantity[$index] = bcsub($remainingQuantity[$index], $quantity, WorkOrderClosingCalculator::SCALE);
                } else {
                    $remaining[$index]--;
                }
                $lines[] = $material;
            }
            if ($lines === []) break;
            $assignments[] = ['work_order' => $order, 'materials' => $lines];
        }
        return $preview + ['assignments' => $assignments]; */
    }

    private function fixedAssignmentCount(string $available, string $fixed): int
    {
        $whole = max(0, (int) bcdiv($available, $fixed, 0));
        return bccomp(bcmul((string) $whole, $fixed, WorkOrderClosingCalculator::SCALE), $available, WorkOrderClosingCalculator::SCALE) < 0
            ? $whole + 1
            : max(1, $whole);
    }

    /** Selects up to five distinct materials whose combined value cannot exceed the RN cap. */
    private function salesPriceAssignments(ConnectionInterface $db, array $preview): array
    {
        $max = max(1, (int) config('excess-stock.max_materials_per_work_order', 5));
        $remainingQuantity = array_map(fn (array $material) => (string) $material['available_qty'], $preview['materials']);
        $assignments = [];
        $skipped = [];

        foreach ($preview['orders'] as $order) {
            $plannedPieces = (string) ($order['anPlanQty'] ?? '0');
            $price = $this->salesPriceForWorkOrder($db, (string) ($order['acKey'] ?? ''));
            if ($price === null || bccomp($plannedPieces, '0', WorkOrderClosingCalculator::SCALE) <= 0) {
                $skipped[] = $order + ['skip_reason' => $price === null ? 'Nije pronaÄ‘ena pozitivna prodajna cijena za RN ili artikl.' : 'Planirana koliÄŤina mora biti veÄ‡a od nule.'];
                continue;
            }

            $salesPrice = $price['price'];
            // Sales prices are EUR; material issue values are KM.
            $budgetPerPieceEur = bcmul($salesPrice, $preview['sales_price_percent'], WorkOrderClosingCalculator::SCALE);
            $budgetPerPiece = bcmul($budgetPerPieceEur, $preview['sales_price_eur_to_km_rate'], WorkOrderClosingCalculator::SCALE);
            $budgetTotal = bcmul($budgetPerPiece, $plannedPieces, WorkOrderClosingCalculator::SCALE);
            $eligible = [];
            foreach ($preview['materials'] as $index => $material) {
                if (bccomp($remainingQuantity[$index], '0', WorkOrderClosingCalculator::SCALE) > 0
                    && bccomp((string) $material['price'], '0', WorkOrderClosingCalculator::SCALE) > 0) {
                    $eligible[] = $index;
                }
            }
            shuffle($eligible);
            $eligible = $this->selectMaterialsWithCapacity($preview['materials'], $remainingQuantity, $eligible, $max, $budgetTotal);
            $remainingBudget = $budgetTotal;
            $lines = [];

            foreach ($eligible as $position => $index) {
                $material = $preview['materials'][$index];
                $lineBudget = bcdiv($remainingBudget, (string) (count($eligible) - $position), WorkOrderClosingCalculator::SCALE);
                $quantity = bcdiv($lineBudget, (string) $material['price'], WorkOrderClosingCalculator::SCALE);
                if (bccomp($quantity, $remainingQuantity[$index], WorkOrderClosingCalculator::SCALE) > 0) {
                    $quantity = $remainingQuantity[$index];
                }
                if (bccomp($quantity, '0', WorkOrderClosingCalculator::SCALE) <= 0) continue;
                $lineTotal = bcmul($quantity, (string) $material['price'], WorkOrderClosingCalculator::SCALE);
                $remainingQuantity[$index] = bcsub($remainingQuantity[$index], $quantity, WorkOrderClosingCalculator::SCALE);
                $remainingBudget = bcsub($remainingBudget, $lineTotal, WorkOrderClosingCalculator::SCALE);
                $lines[] = $material + [
                    'source_index' => $index,
                    'assignment_qty' => $quantity,
                    'assignment_total' => $lineTotal,
                    'assignment_per_piece' => bcdiv($lineTotal, $plannedPieces, WorkOrderClosingCalculator::SCALE),
                ];
            }
            // A material with low stock may not use its equal initial share.
            // Reallocate that remainder across the same five selected lines,
            // so an avoidable gap below the sales-price cap is never left.
            foreach ($eligible as $index) {
                if (bccomp($remainingBudget, '0', WorkOrderClosingCalculator::SCALE) <= 0) break;
                if (bccomp($remainingQuantity[$index], '0', WorkOrderClosingCalculator::SCALE) <= 0) continue;
                $material = $preview['materials'][$index];
                $quantity = bcdiv($remainingBudget, (string) $material['price'], WorkOrderClosingCalculator::SCALE);
                if (bccomp($quantity, $remainingQuantity[$index], WorkOrderClosingCalculator::SCALE) > 0) $quantity = $remainingQuantity[$index];
                if (bccomp($quantity, '0', WorkOrderClosingCalculator::SCALE) <= 0) continue;
                $total = bcmul($quantity, (string) $material['price'], WorkOrderClosingCalculator::SCALE);
                foreach ($lines as &$line) {
                    if (($line['source_index'] ?? null) !== $index) continue;
                    $line['assignment_qty'] = bcadd($line['assignment_qty'], $quantity, WorkOrderClosingCalculator::SCALE);
                    $line['assignment_total'] = bcadd($line['assignment_total'], $total, WorkOrderClosingCalculator::SCALE);
                    $line['assignment_per_piece'] = bcdiv($line['assignment_total'], $plannedPieces, WorkOrderClosingCalculator::SCALE);
                    break;
                }
                unset($line);
                $remainingQuantity[$index] = bcsub($remainingQuantity[$index], $quantity, WorkOrderClosingCalculator::SCALE);
                $remainingBudget = bcsub($remainingBudget, $total, WorkOrderClosingCalculator::SCALE);
            }
            if ($lines === []) continue;
            $assignedTotal = bcsub($budgetTotal, $remainingBudget, WorkOrderClosingCalculator::SCALE);
            $assignments[] = ['work_order' => $order + [
                'sales_price' => $salesPrice,
                'sales_price_source' => $price['source'],
                'budget_per_piece_eur' => $budgetPerPieceEur,
                'eur_to_km_rate' => $preview['sales_price_eur_to_km_rate'],
                'budget_per_piece' => $budgetPerPiece,
                'budget_total' => $budgetTotal,
                'assigned_total' => $assignedTotal,
                'assigned_per_piece' => bcdiv($assignedTotal, $plannedPieces, WorkOrderClosingCalculator::SCALE),
            ], 'materials' => $lines];
        }

        return $preview + ['assignments' => $assignments, 'skipped_orders' => $skipped];
    }

    /** Keeps the random selection but replaces low-capacity choices when needed to fund the cap. */
    private function selectMaterialsWithCapacity(array $materials, array $remainingQuantity, array $candidates, int $max, string $budget): array
    {
        $selected = array_slice($candidates, 0, $max);
        $unused = array_slice($candidates, count($selected));
        $capacity = static fn (int $index): string => bcmul($remainingQuantity[$index], (string) $materials[$index]['price'], WorkOrderClosingCalculator::SCALE);
        $total = '0';
        foreach ($selected as $index) $total = bcadd($total, $capacity($index), WorkOrderClosingCalculator::SCALE);
        foreach ($unused as $candidate) {
            if (bccomp($total, $budget, WorkOrderClosingCalculator::SCALE) >= 0 || $selected === []) break;
            $weakestPosition = 0;
            foreach ($selected as $position => $index) {
                if (bccomp($capacity($index), $capacity($selected[$weakestPosition]), WorkOrderClosingCalculator::SCALE) < 0) $weakestPosition = $position;
            }
            if (bccomp($capacity($candidate), $capacity($selected[$weakestPosition]), WorkOrderClosingCalculator::SCALE) <= 0) continue;
            $total = bcsub($total, $capacity($selected[$weakestPosition]), WorkOrderClosingCalculator::SCALE);
            $selected[$weakestPosition] = $candidate;
            $total = bcadd($total, $capacity($candidate), WorkOrderClosingCalculator::SCALE);
        }
        return $selected;
    }

    /** Linked sales-order price wins; an unlinked manual RN uses the article catalog price. */
    private function salesPriceForWorkOrder(ConnectionInterface $db, string $workOrderKey): ?array
    {
        $linked = $db->table('dbo.tHF_LinkWOExOrderItem as link')
            ->leftJoin('dbo.tHE_OrderItem as by_qid', 'by_qid.anQId', '=', 'link.anOrderItemQId')
            ->leftJoin('dbo.tHE_OrderItem as by_number', function ($join) {
                $join->on('by_number.acKey', '=', 'link.acLnkKey')->on('by_number.anNo', '=', 'link.anLnkNo');
            })
            ->where('link.acKey', $workOrderKey)
            ->orderBy('link.anQId')
            // Pantheon order lines commonly keep the customer selling price
            // in anRetailPrice while anPrice remains zero.
            ->selectRaw('COALESCE(NULLIF(by_qid.anPrice, 0), NULLIF(by_qid.anRetailPrice, 0), NULLIF(by_number.anPrice, 0), NULLIF(by_number.anRetailPrice, 0)) as price')
            ->value('price');
        if ($linked !== null && bccomp((string) $linked, '0', WorkOrderClosingCalculator::SCALE) > 0) {
            return ['price' => (string) $linked, 'source' => 'povezana stavka narudĹľbe'];
        }

        $catalog = $db->table('dbo.tHF_WOEx as wo')
            ->join('dbo.tHE_SetItem as item', 'item.acIdent', '=', 'wo.acIdent')
            ->where('wo.acKey', $workOrderKey)
            ->value('item.anPrice');
        if ($catalog !== null && bccomp((string) $catalog, '0', WorkOrderClosingCalculator::SCALE) > 0) {
            return ['price' => (string) $catalog, 'source' => 'katalog artikla'];
        }
        return null;
    }

    /**
     * Adds the calculated excess-stock reservation immediately after an RN
     * has been created. It is idempotent: a retry sees the marker rows and
     * never adds the same material twice.
     */
    public function assignToNewWorkOrder(ConnectionInterface $db, string $workOrderKey, int $userId): int
    {
        if (!(bool) config('excess-stock.enabled', false)
            || !(bool) config('excess-stock.assign_on_work_order_create', false)) {
            return 0;
        }

        $workOrderKey = trim($workOrderKey);
        if ($workOrderKey === '') {
            return 0;
        }

        // Do not reuse assignPreview() here: that is the batch/backfill
        // planner and evaluates every open RN.  Creation needs a plan for
        // this RN alone, while availableMaterials() still subtracts all
        // existing open-RN reservations from the warehouse balance.
        $order = $db->table('dbo.tHF_WOEx')
            ->where('acKey', $workOrderKey)
            ->whereIn('acStatusMF', ['O', 'R'])
            ->first(['acKey', 'acKeyView', 'adDate', 'anPlanQty']);
        if ($order === null) {
            return 0;
        }

        $plan = $this->salesPriceAssignments(
            $db,
            $this->previewForOrders($db, [(array) $order])
        );
        foreach ($plan['assignments'] as $assignment) {
            if (strcasecmp(trim((string) ($assignment['work_order']['acKey'] ?? '')), $workOrderKey) === 0) {
                return $this->assign($db, $assignment, $userId);
            }
        }

        return 0;
    }

    public function assign(ConnectionInterface $db, array $assignment, int $userId): int
    {
        $key = (string) ($assignment['work_order']['acKey'] ?? '');
        if ($key === '') throw new RuntimeException('Radni nalog za excess-stock dodjelu nije pronađen.');
        return $db->transaction(function () use ($db, $key, $assignment, $userId) {
            $order = $db->table('dbo.tHF_WOEx')->where('acKey', $key)->lockForUpdate()->first(['acStatusMF']);
            if ($order === null || strtoupper(trim((string) $order->acStatusMF)) === 'Z') return 0;
            $nextNo = ((int) $db->table('dbo.tHF_WOExItem')->where('acKey', $key)->max('anNo')) + 1;
            $qidIsIdentity = $db->selectOne("SELECT 1 AS present FROM sys.identity_columns WHERE object_id = OBJECT_ID('dbo.tHF_WOExItem') AND name = 'anQId'") !== null;
            $nextQid = $qidIsIdentity ? null : ((int) $db->table('dbo.tHF_WOExItem')->max('anQId')) + 1;
            $added = 0;
            foreach ((array) ($assignment['materials'] ?? []) as $material) {
                $code = trim((string) ($material['code'] ?? ''));
                $quantity = (string) ($material['assignment_qty'] ?? '0');
                if ($code === '' || bccomp($quantity, '0', WorkOrderClosingCalculator::SCALE) <= 0) continue;
                $alreadyExists = $db->table('dbo.tHF_WOExItem')->where('acKey', $key)->where('acIdent', $code)
                    ->where(function ($query) {
                        foreach ($this->markers() as $marker) $query->orWhere('acNote', 'like', $marker . '%');
                    })->exists();
                if ($alreadyExists) continue;

                // Preview data can be stale when jobs overlap. Locking the
                // source row and rechecking reservations prevents a future
                // dedicated 6400 from issuing more than physical stock.
                $physical = $db->table('dbo.tHE_Stock')
                    ->where('acIdent', $code)
                    ->whereRaw("LTRIM(RTRIM(ISNULL(acWarehouse, ''))) = ?", [$this->warehouse()])
                    ->lock('WITH (UPDLOCK, HOLDLOCK)')
                    ->sum($db->raw('CAST(ISNULL(anStock, 0) AS decimal(18,6))'));
                $reserved = $this->reservedByMaterial($db);
                $alreadyReserved = (string) ($reserved[strtoupper($code)] ?? '0');
                if (bccomp(bcsub((string) $physical, $alreadyReserved, WorkOrderClosingCalculator::SCALE), $quantity, WorkOrderClosingCalculator::SCALE) < 0) {
                    throw new RuntimeException("Nedovoljno slobodne zalihe dodatnih sirovina za materijal {$code}.");
                }
                $payload = [
                    'acKey' => $key, 'anNo' => $nextNo++, 'anVariant' => 0, 'acIdent' => $code,
                    'acUM' => substr((string) ($material['unit'] ?? ''), 0, 3), 'anPlanQty' => $quantity,
                    'anQty' => $quantity, 'anQty1' => $quantity, 'anQtyBase' => 0,
                    'acOperationType' => null,
                    'acNote' => 'Automatski popunjeno sa ' . mb_strtolower($this->warehouse()),
                    'adTimeIns' => now(), 'anUserIns' => $userId, 'adTimeChg' => now(), 'anUserChg' => $userId,
                ];
                if ($nextQid !== null) $payload['anQId'] = $nextQid++;
                $db->table('dbo.tHF_WOExItem')->insert($payload);
                $added++;
            }
            return $added;
        }, 3);
    }

    public function closeMaterials(ConnectionInterface $db, string $workOrderKey): array
    {
        return $db->table('dbo.tHF_WOExItem as wi')
            ->join('dbo.tHE_SetItem as i', 'i.acIdent', '=', 'wi.acIdent')
            ->leftJoin('dbo.tHE_Stock as s', function ($join) {
                $join->on('s.acIdent', '=', 'wi.acIdent')->whereRaw("LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) = ?", [$this->warehouse()]);
            })
            ->where('wi.acKey', $workOrderKey)
            ->where(function ($query) {
                foreach ($this->markers() as $marker) {
                    $query->orWhereRaw("UPPER(LTRIM(RTRIM(ISNULL(wi.acNote, '')))) LIKE ?", [strtoupper($marker) . '%']);
                }
            })
            ->orderBy('wi.anNo')->get(['wi.anQId as item_qid','wi.anPlanQty','wi.anQty','i.acIdent','i.acName','i.acUM','i.anQId as ident_qid','s.anLastPrice'])
            ->map(function ($row): array {
                // Normal UI editing can reset anQty after a backfill. The
                // flagged reservation's planned quantity is its immutable
                // source of truth for the dedicated 6400 issue.
                $planned = WorkOrderClosingCalculator::decimal($row->anPlanQty ?? '0');
                $actual = WorkOrderClosingCalculator::decimal($row->anQty ?? '0');
                $quantity = bccomp($planned, '0', WorkOrderClosingCalculator::SCALE) > 0 ? $planned : $actual;
                $price = WorkOrderClosingCalculator::decimal($row->anLastPrice ?? 0);
                return [
                    'item_qid' => (int) $row->item_qid,
                    'requires_close_time_preparation' => false,
                    'code' => trim((string) $row->acIdent),
                    'name' => trim((string) $row->acName),
                    'unit' => trim((string) $row->acUM),
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => bcmul($quantity, $price, WorkOrderClosingCalculator::SCALE),
                    'ident_qid' => (int) $row->ident_qid,
                ];
            })
            ->all();
    }

    public function notifyRecentIncomingMovements(ConnectionInterface $db): int
    {
        $recipient = trim((string) config('excess-stock.notification_recipient', ''));
        if ($recipient === '') return 0;
        $since = Carbon::now()->subDays(max(1, (int) config('excess-stock.notification_lookback_days', 2)));
        $rows = $db->table('dbo.tHE_Move as m')->join('dbo.tHE_MoveItem as mi','mi.acKey','=','m.acKey')
            ->whereRaw("LTRIM(RTRIM(ISNULL(m.acReceiver, ''))) = ?", [$this->warehouse()])
            ->where('m.acReceiverStock','Y')->where('m.adTimeIns','>=',$since)
            ->get(['m.acKey','m.acKeyView','m.acDocType','m.adDate','m.acIssuer','m.acReceiver','m.anUserIns','mi.anNo','mi.acIdent','mi.acName','mi.acUM','mi.anQty']);
        $sent = 0;
        foreach ($rows as $row) {
            $cacheKey = 'excess-stock-incoming:' . trim((string)$row->acKey) . ':' . (int)$row->anNo;
            if (Cache::has($cacheKey)) continue;
            Mail::raw(implode("\n", ['Nova zaliha dodana u skladište Dodatne sirovine','Materijal: '.trim((string)$row->acName),'Šifra: '.trim((string)$row->acIdent),'Količina: '.(string)$row->anQty.' '.trim((string)$row->acUM),'Skladište: '.$this->warehouse(),'Dokument: '.trim((string)($row->acKeyView ?: $row->acKey)),'Izvor: '.trim((string)$row->acIssuer),'User ID: '.(string)$row->anUserIns]), fn ($m) => $m->to($recipient)->subject('Nova zaliha dodana u skladište Dodatne sirovine'));
            // Mark it only after Laravel accepts the message. A mail outage
            // therefore leaves the movement eligible for the next run.
            Cache::put($cacheKey, true, now()->addDays(90));
            $sent++;
        }
        return $sent;
    }
}
