<?php

require __DIR__ . '/_conn.php';

$limit = max(10, min((int) ($_GET['limit'] ?? 200), 500));
$documentLike = '26-6400%';
$sql = "
    SELECT TOP {$limit}
        move.acKeyView AS document_key_view,
        mi.*
    FROM [dbo].[tHE_MoveItem] AS mi
    INNER JOIN [dbo].[tHE_Move] AS move
        ON move.acKey = mi.acKey
    WHERE move.acKeyView LIKE ?
    ORDER BY move.acKeyView DESC, mi.anNo ASC
";

$stmt = sqlsrv_query($conn, $sql, [$documentLike], ['QueryTimeout' => 30]);

if (!$stmt) {
    die('<pre>' . htmlspecialchars(print_r(sqlsrv_errors(), true)) . '</pre>');
}

$meta = sqlsrv_field_metadata($stmt);

echo '<h2>TABLE: dbo.tHE_MoveItem</h2>';
echo '<p>Filter dokumenta: <b>' . htmlspecialchars($documentLike, ENT_QUOTES, 'UTF-8') . '</b></p>';
echo '<p>Limit: <b>' . htmlspecialchars((string) $limit, ENT_QUOTES, 'UTF-8') . '</b></p>';

echo "<table border='1' cellpadding='4' cellspacing='0'>";
echo '<tr>';
foreach ($meta as $col) {
    echo '<th>' . htmlspecialchars((string) ($col['Name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</th>';
}
echo '</tr>';

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo '<tr>';

    foreach ($meta as $col) {
        $name = (string) ($col['Name'] ?? '');
        $value = $row[$name] ?? '';

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        echo '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>';
    }

    echo '</tr>';
}

echo '</table>';

sqlsrv_close($conn);
