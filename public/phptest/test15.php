<?php

/*
 * test15.php
 * Raw pregled sifrarnika statusa dbo.tPA_SetDocTypeStat.
 * Moze raditi direktno po acDocType + acStatus ili indirektno preko broja narudzbe iz tHE_Order.
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest15_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest15_format_value($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return trim((string) $value);
}

function phptest15_fail($errors): void
{
    $payload = print_r($errors, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $payload . PHP_EOL);
        exit(1);
    }

    echo '<pre>' . phptest15_h($payload) . '</pre>';
    exit;
}

function phptest15_fetch_all($stmt): array
{
    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    return $rows;
}

function phptest15_norm(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function phptest15_candidates(string $value): array
{
    $normalized = phptest15_norm($value);

    if ($normalized === '') {
        return [];
    }

    $candidates = [$normalized];

    if (strlen($normalized) === 12) {
        $candidates[] = substr($normalized, 0, 6) . '0' . substr($normalized, 6);
    }

    if (strlen($normalized) === 13 && substr($normalized, 6, 1) === '0') {
        $candidates[] = substr($normalized, 0, 6) . substr($normalized, 7);
    }

    return array_values(array_unique(array_filter($candidates, function ($candidate) {
        return $candidate !== '';
    })));
}

function phptest15_fetch_order_rows($conn, string $schema, string $orderTable, string $orderInput, int $limit): array
{
    $trimmedInput = trim($orderInput);
    $candidates = phptest15_candidates($trimmedInput);

    if ($trimmedInput === '' || empty($candidates)) {
        return [[], []];
    }

    $qualifiedTable = '[' . $schema . '].[' . $orderTable . ']';
    $params = [];
    $whereParts = [];

    foreach ($candidates as $candidate) {
        $whereParts[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKey), '-', ''), ' ', '') = ?";
        $params[] = $candidate;

        $whereParts[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKeyView), '-', ''), ' ', '') = ?";
        $params[] = $candidate;

        $whereParts[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acDoc1), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
    }

    $sql = "
        SELECT TOP ($limit) *
        FROM {$qualifiedTable}
        WHERE " . implode(' OR ', $whereParts) . "
        ORDER BY adTimeIns DESC, acKey DESC
    ";

    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => 30]);

    if (!$stmt) {
        phptest15_fail(sqlsrv_errors());
    }

    $meta = sqlsrv_field_metadata($stmt);
    $rows = phptest15_fetch_all($stmt);
    sqlsrv_free_stmt($stmt);

    return [$meta ?: [], $rows];
}

function phptest15_fetch_status_rows(
    $conn,
    string $schema,
    string $statusTable,
    string $docType,
    string $status,
    int $limit
): array {
    $qualifiedTable = '[' . $schema . '].[' . $statusTable . ']';
    $where = [];
    $params = [];

    if ($docType !== '') {
        $where[] = "LTRIM(RTRIM(ISNULL(acDocType, ''))) = ?";
        $params[] = $docType;
    }

    if ($status !== '') {
        $where[] = "LTRIM(RTRIM(ISNULL(acStatus, ''))) = ?";
        $params[] = $status;
    }

    $sql = "
        SELECT TOP ($limit) *
        FROM {$qualifiedTable}
    ";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY acDocType ASC, acStatus ASC";

    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => 30]);

    if (!$stmt) {
        phptest15_fail(sqlsrv_errors());
    }

    $meta = sqlsrv_field_metadata($stmt);
    $rows = phptest15_fetch_all($stmt);
    sqlsrv_free_stmt($stmt);

    return [$meta ?: [], $rows];
}

$schema = trim((string) ($_GET['schema'] ?? $defaultSchema ?: 'dbo'));
$schema = preg_match('/^[A-Za-z0-9_]+$/', $schema) ? $schema : ($defaultSchema ?: 'dbo');
$orderTable = trim((string) ($_GET['order_table'] ?? 'tHE_Order'));
$orderTable = preg_match('/^[A-Za-z0-9_]+$/', $orderTable) ? $orderTable : 'tHE_Order';
$statusTable = trim((string) ($_GET['status_table'] ?? 'tPA_SetDocTypeStat'));
$statusTable = preg_match('/^[A-Za-z0-9_]+$/', $statusTable) ? $statusTable : 'tPA_SetDocTypeStat';
$order = trim((string) ($_GET['order'] ?? ''));
$docType = trim((string) ($_GET['doc_type'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 20);
$limit = max(1, min($limit, 200));

[$orderMeta, $orderRows] = phptest15_fetch_order_rows($conn, $schema, $orderTable, $order, $limit);

if ($docType === '' && !empty($orderRows)) {
    $docType = trim((string) ($orderRows[0]['acDocType'] ?? ''));
}

if ($status === '' && !empty($orderRows)) {
    $status = trim((string) ($orderRows[0]['acStatus'] ?? ''));
}

[$statusMeta, $statusRows] = phptest15_fetch_status_rows($conn, $schema, $statusTable, $docType, $status, $limit);

if (PHP_SAPI === 'cli') {
    echo "STATUS TABLE: {$schema}.{$statusTable}" . PHP_EOL;
    echo "ORDER INPUT: " . ($order !== '' ? $order : '-') . PHP_EOL;
    echo "DOC TYPE: " . ($docType !== '' ? $docType : '-') . PHP_EOL;
    echo "STATUS: " . ($status !== '' ? $status : '-') . PHP_EOL;
    echo "ORDER ROWS: " . count($orderRows) . PHP_EOL;
    echo "STATUS ROWS: " . count($statusRows) . PHP_EOL;

    if (!empty($statusRows)) {
        echo PHP_EOL;
        $columns = array_map(function ($col) {
            return (string) ($col['Name'] ?? '');
        }, $statusMeta ?: []);
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 120) . PHP_EOL;

        foreach ($statusRows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = phptest15_format_value($row[$column] ?? null);
            }
            echo implode(' | ', $values) . PHP_EOL;
        }
    } else {
        echo PHP_EOL . "No status rows." . PHP_EOL;
    }

    sqlsrv_close($conn);
    exit(0);
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>Order status lookup</title>';
echo '<style>
    body{font-family:Arial,sans-serif;font-size:14px;line-height:1.4;margin:20px;color:#222}
    h1,h2,h3{margin:16px 0 8px}
    .meta,.note{padding:10px 12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
    .note{background:#fff8df;border-color:#f0d67b}
    .table-wrap{overflow:auto;margin:8px 0 22px}
    table{border-collapse:collapse;min-width:960px;background:#fff}
    th,td{border:1px solid #d8d8d8;padding:4px 6px;vertical-align:top;white-space:nowrap}
    th{background:#f1f3f5;text-align:left;position:sticky;top:0}
    form{display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin:10px 0 18px}
    label{display:grid;gap:4px;font-size:12px;font-weight:700;color:#555}
    input{padding:8px 10px;border:1px solid #ccd3da;border-radius:6px;min-width:160px}
    button{padding:8px 14px;border:0;border-radius:6px;background:#0d6efd;color:#fff;cursor:pointer}
 </style></head><body>';

echo '<h1>Šifrarnik statusa narudžbi</h1>';
echo '<div class="note">Možeš tražiti direktno po <b>doc_type</b> i <b>status</b>, ili samo po <b>order</b> broju pa skripta sama uzme <b>acDocType</b> i <b>acStatus</b> iz <b>tHE_Order</b>.</div>';

echo '<form method="get">';
echo '<label>Broj narudžbe<input type="text" name="order" value="' . phptest15_h($order) . '" placeholder="npr. 26-0110-0000928"></label>';
echo '<label>Doc type<input type="text" name="doc_type" value="' . phptest15_h($docType) . '" placeholder="npr. 0110"></label>';
echo '<label>Status<input type="text" name="status" value="' . phptest15_h($status) . '" placeholder="npr. 1"></label>';
echo '<label>Limit<input type="text" name="limit" value="' . phptest15_h((string) $limit) . '"></label>';
echo '<input type="hidden" name="schema" value="' . phptest15_h($schema) . '">';
echo '<input type="hidden" name="order_table" value="' . phptest15_h($orderTable) . '">';
echo '<input type="hidden" name="status_table" value="' . phptest15_h($statusTable) . '">';
echo '<button type="submit">Prikaži</button>';
echo '</form>';

echo '<div class="meta">';
echo '<b>Status table:</b> ' . phptest15_h($schema . '.' . $statusTable) . '<br>';
echo '<b>Order lookup table:</b> ' . phptest15_h($schema . '.' . $orderTable) . '<br>';
echo '<b>Order input:</b> ' . phptest15_h($order !== '' ? $order : '-') . '<br>';
echo '<b>Resolved doc type:</b> ' . phptest15_h($docType !== '' ? $docType : '-') . '<br>';
echo '<b>Resolved status:</b> ' . phptest15_h($status !== '' ? $status : '-');
echo '</div>';

if (!empty($orderRows)) {
    echo '<h2>Raw order redovi</h2>';
    $orderColumns = array_map(function ($col) {
        return (string) ($col['Name'] ?? '');
    }, $orderMeta ?: []);
    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($orderColumns as $column) {
        echo '<th>' . phptest15_h($column) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($orderRows as $row) {
        echo '<tr>';
        foreach ($orderColumns as $column) {
            echo '<td>' . phptest15_h(phptest15_format_value($row[$column] ?? null)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

echo '<h2>Raw status šifrarnik</h2>';
if (empty($statusRows)) {
    echo '<div class="note">Nema redova za zadani doc type/status.</div>';
} else {
    $statusColumns = array_map(function ($col) {
        return (string) ($col['Name'] ?? '');
    }, $statusMeta ?: []);
    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($statusColumns as $column) {
        echo '<th>' . phptest15_h($column) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($statusRows as $row) {
        echo '<tr>';
        foreach ($statusColumns as $column) {
            echo '<td>' . phptest15_h(phptest15_format_value($row[$column] ?? null)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

echo '</body></html>';

sqlsrv_close($conn);
