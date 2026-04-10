<?php

$server   = "hostBApa1.datalab.ba,50387";
$database = "BA_TRENDY";
$username = "SQLTREN_ADM2";
$password = "#4^Sdgfx3VHy5G";

$schema = "dbo";
$table = "tHE_OrderItem";
$orderDisplay = trim((string) ($_GET['order'] ?? '26-0110-000928'));
$orderKey = preg_replace('/\D+/', '', $orderDisplay);
$orderKeyCandidates = array_values(array_unique(array_filter([
    $orderKey,
    strlen($orderKey) === 12 ? substr($orderKey, 0, 6) . '0' . substr($orderKey, 6) : '',
])));

$conn = sqlsrv_connect($server, [
    "Database" => $database,
    "UID"      => $username,
    "PWD"      => $password,
    "CharacterSet" => "UTF-8",
    "LoginTimeout" => 10,
]);

if (!$conn) {
    die('<pre>' . htmlspecialchars(print_r(sqlsrv_errors(), true)) . '</pre>');
}

function qcol(string $column): string
{
    return '[' . str_replace(']', ']]', $column) . ']';
}

$columnsStmt = sqlsrv_query($conn, "
    SELECT COLUMN_NAME, DATA_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
    ORDER BY ORDINAL_POSITION
", [$schema, $table], ["QueryTimeout" => 15]);

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

$orderBy = in_array('anNo', $columns, true) ? ' ORDER BY [anNo]' : '';
$sql = "SELECT TOP 20 * FROM [$schema].[$table]";

if (!empty($whereParts)) {
    $sql .= " WHERE " . implode(" OR ", $whereParts);
}

$sql .= $orderBy;

$stmt = sqlsrv_query($conn, $sql, $params, ["QueryTimeout" => 20]);

if (!$stmt) {
    die('<pre>' . htmlspecialchars($sql . "\n\n" . print_r(sqlsrv_errors(), true)) . '</pre>');
}

echo "<h2>TABLE: " . htmlspecialchars("$schema.$table") . "</h2>";
echo "<p>Order: " . htmlspecialchars($orderDisplay) . " / " . htmlspecialchars($orderKey) . "</p>";
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
