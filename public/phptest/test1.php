<?php

require __DIR__ . '/_conn.php';

echo "<h2>ALL TABLES IN DATABASE: " . htmlspecialchars($database) . "</h2>";

$sql = "
    SELECT
        s.name AS schema_name,
        o.name AS object_name,
        CASE o.type WHEN 'U' THEN 'TABLE' WHEN 'V' THEN 'VIEW' ELSE o.type END AS object_type
    FROM sys.objects o
    INNER JOIN sys.schemas s ON o.schema_id = s.schema_id
    WHERE o.type IN ('U', 'V')
    ORDER BY s.name, object_type, object_name
";

$stmt = sqlsrv_query($conn, $sql);

if (!$stmt) {
    die(print_r(sqlsrv_errors(), true));
}

echo "<table border='1' cellpadding='4' cellspacing='0'>";
echo "<tr><th>#</th><th>Schema</th><th>Type</th><th>Name</th></tr>";

$count = 0;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $count++;

    echo "<tr>";
    echo "<td>" . $count . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['schema_name']) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['object_type']) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['object_name']) . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "<p>Total objects: " . $count . "</p>";

sqlsrv_close($conn);
