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

    if ($value === '*') {
        return $value;
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        return $fallback;
    }

    return $value;
}

$schema = phptest_identifier((string) ($_GET['schema'] ?? ''), $defaultSchema !== '' ? $defaultSchema : 'dbo');
$like = trim((string) ($_GET['like'] ?? ''));
$order = trim((string) ($_GET['order'] ?? '26-0110-000928'));
$limit = (int) ($_GET['limit'] ?? 200);
$limit = max(10, min($limit, 500));

$where = "o.type IN ('U', 'V') AND s.name NOT IN ('sys', 'INFORMATION_SCHEMA')";
$params = [];

if ($schema !== '*') {
    $where .= " AND s.name = ?";
    $params[] = $schema;
}

if ($like !== '') {
    $where .= " AND o.name LIKE ?";
    $params[] = '%' . $like . '%';
}

$sql = "
    SELECT TOP ($limit)
        s.name AS schema_name,
        o.name AS object_name,
        CASE o.type WHEN 'U' THEN 'TABLE' WHEN 'V' THEN 'VIEW' ELSE o.type END AS object_type,
        MAX(CASE WHEN c.name = 'anOrderItemQId' THEN 1 ELSE 0 END) AS has_order_item_qid,
        MAX(CASE WHEN c.name IN ('acKey', 'acKeyView') THEN 1 ELSE 0 END) AS has_key,
        MAX(CASE WHEN c.name IN ('acLnkKey', 'acLnkKeyView') THEN 1 ELSE 0 END) AS has_link_key,
        MAX(CASE WHEN c.name IN ('anWOExQId', 'anWOQId') THEN 1 ELSE 0 END) AS has_wo_qid,
        MAX(CASE WHEN c.name IN ('anMoveItemQId', 'anMoveQId') THEN 1 ELSE 0 END) AS has_move_qid,
        MAX(CASE WHEN c.name IN ('adTimeIns', 'adTimeChg', 'adDate', 'adTime') THEN 1 ELSE 0 END) AS has_time,
        MAX(CASE WHEN c.name IN ('acType', 'acTypeView', 'acDocType', 'acDocTypeView') THEN 1 ELSE 0 END) AS has_type,
        MAX(CASE WHEN c.name IN ('acStatus', 'acStatusMF', 'status') THEN 1 ELSE 0 END) AS has_status,
        MAX(CASE WHEN c.name IN ('anProducedQty') THEN 1 ELSE 0 END) AS has_produced_qty,
        (
            10 * MAX(CASE WHEN c.name = 'anOrderItemQId' THEN 1 ELSE 0 END)
            + 6 * MAX(CASE WHEN c.name IN ('acLnkKey', 'acLnkKeyView') THEN 1 ELSE 0 END)
            + 5 * MAX(CASE WHEN c.name IN ('acKey', 'acKeyView') THEN 1 ELSE 0 END)
            + 4 * MAX(CASE WHEN c.name IN ('adTimeIns', 'adTimeChg', 'adDate', 'adTime') THEN 1 ELSE 0 END)
            + CASE WHEN o.name LIKE '%WOEx%' THEN 5 ELSE 0 END
            + CASE WHEN o.name LIKE '%MoveItem%' THEN 4 ELSE 0 END
            + CASE WHEN o.name LIKE '%Link%' THEN 3 ELSE 0 END
            + CASE WHEN o.name LIKE '%Proiz%' OR o.name LIKE '%Proizv%' THEN 2 ELSE 0 END
            + CASE WHEN o.name LIKE '%Naroc%' OR o.name LIKE '%Narud%' OR o.name LIKE '%Kup%' THEN 2 ELSE 0 END
        ) AS score
    FROM sys.objects o
    INNER JOIN sys.schemas s ON s.schema_id = o.schema_id
    INNER JOIN sys.columns c ON c.object_id = o.object_id
    WHERE $where
    GROUP BY s.name, o.name, o.type
    ORDER BY score DESC, o.name ASC
";

$stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => 30]);
if (!$stmt) {
    die('<pre>' . htmlspecialchars($sql . "\n\n" . print_r(sqlsrv_errors(), true)) . '</pre>');
}

$rows = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}

if (PHP_SAPI === 'cli') {
    foreach ($rows as $row) {
        $schemaName = (string) ($row['schema_name'] ?? '');
        $objectName = (string) ($row['object_name'] ?? '');
        $type = (string) ($row['object_type'] ?? '');
        $score = (string) ($row['score'] ?? '');
        $flags = [];

        if (!empty($row['has_order_item_qid'])) {
            $flags[] = 'OrderItemQId';
        }
        if (!empty($row['has_link_key'])) {
            $flags[] = 'LinkKey';
        }
        if (!empty($row['has_wo_qid'])) {
            $flags[] = 'WOQId';
        }
        if (!empty($row['has_move_qid'])) {
            $flags[] = 'MoveQId';
        }
        if (!empty($row['has_key'])) {
            $flags[] = 'Key';
        }
        if (!empty($row['has_time'])) {
            $flags[] = 'Time';
        }
        if (!empty($row['has_type'])) {
            $flags[] = 'TypeCols';
        }
        if (!empty($row['has_status'])) {
            $flags[] = 'StatusCols';
        }
        if (!empty($row['has_produced_qty'])) {
            $flags[] = 'ProducedQty';
        }

        echo str_pad($score, 3, ' ', STR_PAD_LEFT) . "  $schemaName.$objectName ($type)";
        if (!empty($flags)) {
            echo '  [' . implode(', ', $flags) . ']';
        }
        echo PHP_EOL;
    }

    sqlsrv_close($conn);
    exit(0);
}

echo "<h2>Prenos Candidates</h2>";
echo "<p>Schema: <b>" . htmlspecialchars($schema) . "</b></p>";
echo "<p>Like: <b>" . htmlspecialchars($like) . "</b></p>";
echo "<p>Order passthrough: <b>" . htmlspecialchars($order) . "</b></p>";

echo "<table border='1' cellpadding='4' cellspacing='0'>";
echo "<tr>";
echo "<th>#</th>";
echo "<th>Score</th>";
echo "<th>Schema</th>";
echo "<th>Type</th>";
echo "<th>Name</th>";
echo "<th>OrderItemQId</th>";
echo "<th>LinkKey</th>";
echo "<th>WOQId</th>";
echo "<th>MoveQId</th>";
echo "<th>Key</th>";
echo "<th>Time</th>";
echo "<th>TypeCols</th>";
echo "<th>StatusCols</th>";
echo "<th>ProducedQty</th>";
echo "</tr>";

foreach ($rows as $i => $row) {
    $schemaName = (string) ($row['schema_name'] ?? '');
    $objectName = (string) ($row['object_name'] ?? '');
    $type = (string) ($row['object_type'] ?? '');
    $score = (string) ($row['score'] ?? '');
    $href = 'test2.php?schema=' . rawurlencode($schemaName) . '&table=' . rawurlencode($objectName) . '&order=' . rawurlencode($order) . '&limit=20';

    echo "<tr>";
    echo "<td>" . ($i + 1) . "</td>";
    echo "<td><b>" . htmlspecialchars($score) . "</b></td>";
    echo "<td>" . htmlspecialchars($schemaName) . "</td>";
    echo "<td>" . htmlspecialchars($type) . "</td>";
    echo "<td><a href=\"" . htmlspecialchars($href) . "\">" . htmlspecialchars($objectName) . "</a></td>";
    echo "<td>" . htmlspecialchars((string) ($row['has_order_item_qid'] ?? 0)) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['has_link_key'] ?? 0)) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['has_wo_qid'] ?? 0)) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['has_move_qid'] ?? 0)) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['has_key'] ?? 0)) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['has_time'] ?? 0)) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['has_type'] ?? 0)) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['has_status'] ?? 0)) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['has_produced_qty'] ?? 0)) . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "<p>Rows: " . count($rows) . "</p>";

sqlsrv_close($conn);
