<?php

$server   = "hostBApa1.datalab.ba,50387";
$database = "BA_TRENDY";
$username = "SQLTREN_ADM2";
$password = "#4^Sdgfx3VHy5G";

$conn = sqlsrv_connect($server, [
    "Database" => $database,
    "UID" => $username,
    "PWD" => $password,
    "CharacterSet" => "UTF-8",
]);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}

echo "<h2>ALL TABLES IN DATABASE: " . htmlspecialchars($database) . "</h2>";

$sql = "
    SELECT
        s.name AS schema_name,
        t.name AS table_name
    FROM sys.tables t
    INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
    ORDER BY s.name, t.name
";

$stmt = sqlsrv_query($conn, $sql);

if (!$stmt) {
    die(print_r(sqlsrv_errors(), true));
}

echo "<table border='1' cellpadding='4' cellspacing='0'>";
echo "<tr><th>#</th><th>Schema</th><th>Table</th></tr>";

$count = 0;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $count++;

    echo "<tr>";
    echo "<td>" . $count . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['schema_name']) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['table_name']) . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "<p>Total tables: " . $count . "</p>";

sqlsrv_close($conn);
