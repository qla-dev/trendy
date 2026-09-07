<?php

/**
 * Native-SQLSRV Testna-only one-off backfill for additional-material
 * reservations. This bypasses the CLI PDO SQLSRV connection issue, while
 * retaining the production workflow's sales-price percentage calculation.
 *
 * Example:
 * php scripts/backfill-excess-stock-testna-native.php --work-orders=26-6000-003600,26-6000-003593 --apply --user=0
 */

require dirname(__DIR__) . '/public/phptest/_conn.php';

$options = getopt('', ['work-orders:', 'apply', 'replace-existing', 'verify', 'user::']);
$requested = array_values(array_filter(array_map('trim', explode(',', (string) ($options['work-orders'] ?? '')))));
if ($requested === []) {
    fwrite(STDERR, "Provide --work-orders=26-6000-...,...\n");
    exit(1);
}

function query($conn, string $sql, array $params = []): array
{
    $statement = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => 60]);
    if (!$statement) throw new RuntimeException(print_r(sqlsrv_errors(), true));
    $rows = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) $rows[] = $row;
    sqlsrv_free_stmt($statement);
    return $rows;
}

function scalar($conn, string $sql, array $params = []): mixed
{
    $rows = query($conn, $sql, $params);
    return $rows[0] ? array_values($rows[0])[0] : null;
}

function decimal(mixed $value): string { return number_format((float) $value, 6, '.', ''); }

try {
    $database = (string) scalar($conn, 'SELECT DB_NAME() AS database_name');
    if (strtoupper($database) !== 'BA_TRENDY_TESTNA') {
        throw new RuntimeException("Write blocked: connected database is {$database}, not BA_TRENDY_TESTNA.");
    }

    $placeholders = implode(',', array_fill(0, count($requested), '?'));
    $orders = query($conn, "
        SELECT acKey, acKeyView, anPlanQty, acStatusMF
        FROM dbo.tHF_WOEx
        WHERE acKeyView IN ({$placeholders}) AND acStatusMF IN ('O', 'R')
        ORDER BY acKeyView
    ", $requested);
    if (count($orders) !== count($requested)) {
        throw new RuntimeException('All requested WO numbers must exist and be open in BA_TRENDY_TESTNA.');
    }

    if (isset($options['verify'])) {
        $rows = query($conn, "
            SELECT wo.acKeyView, wi.anNo, wi.acIdent, wi.acUM, wi.anPlanQty, wi.anQty, wi.acNote
            FROM dbo.tHF_WOExItem wi
            INNER JOIN dbo.tHF_WOEx wo ON wo.acKey = wi.acKey
            WHERE wo.acKeyView IN ({$placeholders})
              AND UPPER(LTRIM(RTRIM(ISNULL(wi.acNote, '')))) LIKE 'REZERVACIJA_DODATNIH_SIROVINA|%'
            ORDER BY wo.acKeyView, wi.anNo
        ", $requested);
        foreach ($rows as $row) {
            printf("%s | %s | %s %s | note: %s\n", $row['acKeyView'], $row['anNo'], $row['anPlanQty'], $row['acIdent'], $row['acNote']);
        }
        printf("VERIFIED: %d additional-material reservation rows in BA_TRENDY_TESTNA.\n", count($rows));
        exit(0);
    }

    $warehouse = 'Skladište dodatnih sirovina';
    $reservedRows = query($conn, "
        SELECT wi.acIdent, SUM(CAST(ISNULL(wi.anQty, 0) AS decimal(18,6))) AS reserved_qty
        FROM dbo.tHF_WOExItem wi
        INNER JOIN dbo.tHF_WOEx wo ON wo.acKey = wi.acKey
        WHERE wo.acStatusMF <> 'Z' AND UPPER(LTRIM(RTRIM(ISNULL(wi.acNote, '')))) LIKE 'REZERVACIJA_DODATNIH_SIROVINA|%'
        GROUP BY wi.acIdent
    ");
    $reserved = [];
    foreach ($reservedRows as $row) $reserved[strtoupper(trim((string) $row['acIdent']))] = (float) $row['reserved_qty'];

    $materials = query($conn, "
        SELECT s.acIdent, MAX(i.acName) AS acName, MAX(i.acUM) AS acUM, MAX(i.anQId) AS ident_qid,
               SUM(CAST(ISNULL(s.anStock, 0) AS decimal(18,6))) AS physical_qty,
               MAX(CAST(ISNULL(s.anLastPrice, 0) AS decimal(18,6))) AS price
        FROM dbo.tHE_Stock s
        INNER JOIN dbo.tHE_SetItem i ON i.acIdent = s.acIdent
        WHERE LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) = ?
        GROUP BY s.acIdent
        HAVING SUM(CAST(ISNULL(s.anStock, 0) AS decimal(18,6))) > 0
    ", [$warehouse]);
    $available = [];
    foreach ($materials as $material) {
        $code = strtoupper(trim((string) $material['acIdent']));
        $qty = (float) $material['physical_qty'] - ($reserved[$code] ?? 0);
        if ($qty > 0 && (float) $material['price'] > 0) {
            $material['available_qty'] = $qty;
            $available[] = $material;
        }
    }
    if ($available === []) throw new RuntimeException('No priced, available materials exist in the additional-material warehouse.');

    mt_srand(260907); // The dry-run and apply use the same random material choices.
    $plan = [];
    foreach ($orders as $order) {
        $price = scalar($conn, "
            SELECT TOP 1 COALESCE(NULLIF(by_qid.anPrice, 0), NULLIF(by_number.anPrice, 0)) AS price
            FROM dbo.tHF_LinkWOExOrderItem link
            LEFT JOIN dbo.tHE_OrderItem by_qid ON by_qid.anQId = link.anOrderItemQId
            LEFT JOIN dbo.tHE_OrderItem by_number ON by_number.acKey = link.acLnkKey AND by_number.anNo = link.anLnkNo
            WHERE link.acKey = ? ORDER BY link.anQId
        ", [$order['acKey']]);
        $pieces = (float) $order['anPlanQty'];
        if ((float) $price <= 0 || $pieces <= 0) throw new RuntimeException("WO {$order['acKeyView']} has no positive linked sales price or planned quantity.");
        // Sales prices are in EUR; material prices and issued values are in KM.
        $perPieceCap = (float) $price * 0.07 * 1.958;
        $remainingBudget = $perPieceCap * $pieces;
        $eligible = array_keys(array_filter($available, fn ($material) => $material['available_qty'] > 0));
        shuffle($eligible);
        $selected = array_slice($eligible, 0, 5);
        $unused = array_slice($eligible, count($selected));
        $capacity = fn (int $index): float => $available[$index]['available_qty'] * (float) $available[$index]['price'];
        $totalCapacity = array_sum(array_map($capacity, $selected));
        foreach ($unused as $candidate) {
            if ($totalCapacity + 0.000001 >= $remainingBudget || $selected === []) break;
            $weakest = array_key_first($selected);
            foreach ($selected as $position => $index) if ($capacity($index) < $capacity($selected[$weakest])) $weakest = $position;
            if ($capacity($candidate) <= $capacity($selected[$weakest])) continue;
            $totalCapacity += $capacity($candidate) - $capacity($selected[$weakest]);
            $selected[$weakest] = $candidate;
        }
        $eligible = $selected;
        $lines = [];
        foreach ($eligible as $position => $index) {
            $material = $available[$index];
            $lineBudget = $remainingBudget / (count($eligible) - $position);
            $quantity = min($material['available_qty'], $lineBudget / (float) $material['price']);
            $quantity = floor($quantity * 1000000) / 1000000;
            if ($quantity <= 0) continue;
            $total = $quantity * (float) $material['price'];
            $available[$index]['available_qty'] -= $quantity;
            $remainingBudget -= $total;
            $lines[] = $material + ['source_index' => $index, 'quantity' => $quantity, 'total' => $total];
        }
        // Use any left-over budget on the already selected five materials.
        foreach ($eligible as $index) {
            if ($remainingBudget <= 0.000001 || $available[$index]['available_qty'] <= 0) break;
            $quantity = min($available[$index]['available_qty'], $remainingBudget / (float) $available[$index]['price']);
            $quantity = floor($quantity * 1000000) / 1000000;
            if ($quantity <= 0) continue;
            $total = $quantity * (float) $available[$index]['price'];
            foreach ($lines as &$line) {
                if ($line['source_index'] !== $index) continue;
                $line['quantity'] += $quantity;
                $line['total'] += $total;
                break;
            }
            unset($line);
            $available[$index]['available_qty'] -= $quantity;
            $remainingBudget -= $total;
        }
        if ($lines === []) throw new RuntimeException("WO {$order['acKeyView']} cannot receive a positive material quantity within its cap.");
        $plan[] = ['order' => $order, 'sales_price' => (float) $price, 'cap_per_piece' => $perPieceCap, 'budget_total' => $perPieceCap * $pieces, 'lines' => $lines];
    }

    foreach ($plan as $assignment) {
        $total = array_sum(array_column($assignment['lines'], 'total'));
        printf("%s | sales %.4f | cap/pc %.6f | assigned %.6f | total %.6f\n", $assignment['order']['acKeyView'], $assignment['sales_price'], $assignment['cap_per_piece'], $total / (float) $assignment['order']['anPlanQty'], $total);
        foreach ($assignment['lines'] as $line) printf("  %s | %s | qty %s | price %.6f | value %.6f\n", trim($line['acIdent']), trim($line['acName']), decimal($line['quantity']), (float) $line['price'], $line['total']);
    }

    if (!isset($options['apply'])) {
        echo "DRY RUN ONLY: no reservations or stock movements were written.\n";
        exit(0);
    }

    if (!sqlsrv_begin_transaction($conn)) throw new RuntimeException(print_r(sqlsrv_errors(), true));
    $qidIsIdentity = scalar($conn, "SELECT CASE WHEN EXISTS (SELECT 1 FROM sys.identity_columns WHERE object_id = OBJECT_ID('dbo.tHF_WOExItem') AND name = 'anQId') THEN 1 ELSE 0 END") === 1;
    $nextQid = $qidIsIdentity ? null : ((int) scalar($conn, 'SELECT ISNULL(MAX(anQId), 0) + 1 FROM dbo.tHF_WOExItem'));
    foreach ($plan as $assignment) {
        $key = $assignment['order']['acKey'];
        $existing = scalar($conn, "SELECT COUNT(*) FROM dbo.tHF_WOExItem WHERE acKey = ? AND UPPER(LTRIM(RTRIM(ISNULL(acNote, '')))) LIKE 'REZERVACIJA_DODATNIH_SIROVINA|%'", [$key]);
        if ((int) $existing > 0 && !isset($options['replace-existing'])) {
            throw new RuntimeException("WO {$assignment['order']['acKeyView']} already has additional-material reservations; no duplicate write was made.");
        }
        if ((int) $existing > 0) {
            query($conn, "DELETE FROM dbo.tHF_WOExItem WHERE acKey = ? AND UPPER(LTRIM(RTRIM(ISNULL(acNote, '')))) LIKE 'REZERVACIJA_DODATNIH_SIROVINA|%'", [$key]);
        }
        $nextNo = (int) scalar($conn, 'SELECT ISNULL(MAX(anNo), 0) + 1 FROM dbo.tHF_WOExItem WHERE acKey = ?', [$key]);
        foreach ($assignment['lines'] as $line) {
            $quantity = decimal($line['quantity']);
            $note = 'REZERVACIJA_DODATNIH_SIROVINA|skladiste=' . $warehouse . '|nacin=7_posto_prodajne_cijene';
            $columns = 'acKey, anNo, anVariant, acIdent, acUM, anPlanQty, anQty, anQty1, anQtyBase, acOperationType, acNote, adTimeIns, anUserIns, adTimeChg, anUserChg';
            $values = '?, ?, 0, ?, ?, ?, ?, ?, 0, NULL, ?, GETDATE(), ?, GETDATE(), ?';
            $params = [$key, $nextNo++, trim($line['acIdent']), substr(trim($line['acUM']), 0, 3), $quantity, $quantity, $quantity, $note, (int) ($options['user'] ?? 0), (int) ($options['user'] ?? 0)];
            if (!$qidIsIdentity) {
                $columns .= ', anQId';
                $values .= ', ?';
                $params[] = $nextQid++;
            }
            query($conn, "INSERT INTO dbo.tHF_WOExItem ({$columns}) VALUES ({$values})", $params);
        }
    }
    if (!sqlsrv_commit($conn)) throw new RuntimeException(print_r(sqlsrv_errors(), true));
    echo "APPLIED: Testna reservations inserted. No physical stock movement was created.\n";
} catch (Throwable $exception) {
    @sqlsrv_rollback($conn);
    fwrite(STDERR, 'Backfill failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    sqlsrv_close($conn);
}
