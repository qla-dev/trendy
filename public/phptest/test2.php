<?php

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest_identifier(string $value, string $fallback): string
{
    $value = trim($value);

    if ($value === '') {
        return $fallback;
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        return $fallback;
    }

    return $value;
}

$schema = phptest_identifier((string) ($_GET['schema'] ?? ''), $defaultSchema !== '' ? $defaultSchema : 'dbo');
$table = phptest_identifier((string) ($_GET['table'] ?? ''), 'tHE_OrderItem');
$limit = (int) ($_GET['limit'] ?? 20);
$limit = max(1, min($limit, 200));

$orderDisplay = trim((string) ($_GET['order'] ?? '26-0110-000928'));
$orderKey = preg_replace('/\\D+/', '', $orderDisplay);
$orderKeyCandidates = array_values(array_unique(array_filter([
    $orderKey,
    strlen($orderKey) === 12 ? substr($orderKey, 0, 6) . '0' . substr($orderKey, 6) : '',
])));

$qidRaw = trim((string) ($_GET['qid'] ?? ''));
$qidCandidates = array_values(array_unique(array_filter(array_map(function ($value) {
    return trim((string) preg_replace('/\\D+/', '', $value));
}, preg_split('/\\s*,\\s*/', $qidRaw, -1, PREG_SPLIT_NO_EMPTY) ?: []), function ($value) {
    return $value !== '';
})));

$keyRaw = trim((string) ($_GET['key'] ?? ''));
$keyNormalized = preg_replace('/\\s+/', ' ', $keyRaw);
$keyCompact = str_replace(['-', ' '], '', $keyNormalized);

function qcol(string $column): string
{
    return '[' . str_replace(']', ']]', $column) . ']';
}

$columnsStmt = sqlsrv_query($conn, "
    SELECT COLUMN_NAME, DATA_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
    ORDER BY ORDINAL_POSITION
", [$schema, $table], ["QueryTimeout" => 20]);

if (!$columnsStmt) {
    die('<pre>' . htmlspecialchars(print_r(sqlsrv_errors(), true)) . '</pre>');
}

$columns = [];
$columnTypes = [];

while ($column = sqlsrv_fetch_array($columnsStmt, SQLSRV_FETCH_ASSOC)) {
    $columnName = (string) $column['COLUMN_NAME'];
    $columns[] = $columnName;
    $columnTypes[$columnName] = (string) $column['DATA_TYPE'];
}

$whereParts = [];
$params = [];

foreach (['acKey', 'acLnkKey', 'acOrderKey', 'order_key'] as $columnName) {
    if (in_array($columnName, $columns, true) && !empty($orderKeyCandidates)) {
        $normalizedColumn = "REPLACE(REPLACE(CONVERT(nvarchar(255), " . qcol($columnName) . "), '-', ''), ' ', '')";
        $placeholders = implode(', ', array_fill(0, count($orderKeyCandidates), '?'));
        $whereParts[] = "$normalizedColumn IN ($placeholders)";
        array_push($params, ...$orderKeyCandidates);
    }
}

foreach (['acKeyView', 'acRefNo1', 'acOrderNo', 'order_number'] as $columnName) {
    if (in_array($columnName, $columns, true)) {
        $whereParts[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), " . qcol($columnName) . "), '-', ''), ' ', '') = ?";
        $params[] = $orderKey !== '' ? $orderKey : str_replace(['-', ' '], '', $orderDisplay);
    }
}

foreach (['acKey', 'acKeyView', 'acLnkKey', 'acLnkKeyView'] as $columnName) {
    if ($keyCompact !== '' && in_array($columnName, $columns, true)) {
        $whereParts[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), " . qcol($columnName) . "), '-', ''), ' ', '') = ?";
        $params[] = $keyCompact;
    }
}

foreach (['anOrderItemQId', 'anWOExQId', 'anMoveItemQId', 'anQId', 'anDocQId'] as $columnName) {
    if (in_array($columnName, $columns, true) && !empty($qidCandidates)) {
        $placeholders = implode(', ', array_fill(0, count($qidCandidates), '?'));
        $whereParts[] = qcol($columnName) . " IN ($placeholders)";
        array_push($params, ...$qidCandidates);
    }
}

$orderBy = in_array('anNo', $columns, true) ? ' ORDER BY [anNo]' : '';
$sql = "SELECT TOP $limit * FROM [$schema].[$table]";

if (!empty($whereParts)) {
    $sql .= " WHERE " . implode(" OR ", $whereParts);
}

$sql .= $orderBy;

$stmt = sqlsrv_query($conn, $sql, $params, ["QueryTimeout" => 30]);

if (!$stmt) {
    die('<pre>' . htmlspecialchars($sql . "\n\n" . print_r(sqlsrv_errors(), true)) . '</pre>');
}

echo "<h2>TABLE: " . htmlspecialchars("$schema.$table") . "</h2>";
echo "<p>Order: " . htmlspecialchars($orderDisplay) . " / " . htmlspecialchars($orderKey) . "</p>";
echo "<p>QId: " . htmlspecialchars($qidRaw) . "</p>";
echo "<p>Key: " . htmlspecialchars($keyRaw) . "</p>";
echo "<h3>Columns</h3>";
echo "<table border='1' cellpadding='4' cellspacing='0'><tr><th>#</th><th>Name</th><th>Type</th></tr>";

foreach ($columns as $index => $columnName) {
    echo "<tr><td>" . ($index + 1) . "</td><td>" . htmlspecialchars($columnName) . "</td><td>" . htmlspecialchars($columnTypes[$columnName] ?? '') . "</td></tr>";
}

echo "</table>";
echo "<h3>Rows</h3>";
echo "<table border='1' cellpadding='4' cellspacing='0'><tr>";

foreach ($columns as $columnName) {
    echo "<th>" . htmlspecialchars($columnName) . "</th>";
}

echo "</tr>";

$count = 0;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $count++;
    echo "<tr>";

    foreach ($columns as $columnName) {
        $value = $row[$columnName] ?? null;

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        echo "<td>" . htmlspecialchars((string) $value) . "</td>";
    }

    echo "</tr>";
}

echo "</table>";
echo "<p>Rows: " . $count . "</p>";

sqlsrv_close($conn);
