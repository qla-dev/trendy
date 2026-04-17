<?php

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest_norm(string $value): string
{
    return preg_replace('/\\D+/', '', $value);
}

function phptest_qcol(string $column): string
{
    return '[' . str_replace(']', ']]', $column) . ']';
}

function phptest_fetch_columns($conn, string $schema, string $object): array
{
    $stmt = sqlsrv_query($conn, "
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ", [$schema, $object], ['QueryTimeout' => 20]);

    if (!$stmt) {
        return [];
    }

    $cols = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $cols[] = (string) ($row['COLUMN_NAME'] ?? '');
    }

    return array_values(array_filter($cols, function ($c) {
        return $c !== '';
    }));
}

function phptest_first_existing(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function phptest_best_time_column(array $columns): ?string
{
    return phptest_first_existing($columns, [
        'adTimeIns',
        'adTimeChg',
        'adDate',
        'adTime',
        'dtCreated',
        'dtChanged',
    ]);
}

function phptest_format_value($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    return trim((string) $value);
}

function phptest_out(string $line = ''): void
{
    if (PHP_SAPI === 'cli') {
        echo $line . PHP_EOL;
        return;
    }

    echo htmlspecialchars($line) . "<br>";
}

$orderDisplay = trim((string) ($_GET['order'] ?? '26-0110-000928'));
$orderKey = phptest_norm($orderDisplay);
$orderKeyCandidates = array_values(array_unique(array_filter([
    $orderKey,
    strlen($orderKey) === 12 ? substr($orderKey, 0, 6) . '0' . substr($orderKey, 6) : '',
])));

phptest_out("ORDER: $orderDisplay");
phptest_out("ORDER_KEY: $orderKey");
phptest_out("ORDER_KEY_CANDIDATES: " . implode(', ', $orderKeyCandidates));
phptest_out();

// 1) Order items
$orderItemTable = 'tHE_OrderItem';
$orderItemCols = phptest_fetch_columns($conn, $defaultSchema, $orderItemTable);

$orderItemQidCol = phptest_first_existing($orderItemCols, ['anQId', 'anQID', 'anQid']);
$orderItemPosCol = phptest_first_existing($orderItemCols, ['anNo', 'anLnkNo', 'anPos']);
$orderItemKeyCol = phptest_first_existing($orderItemCols, ['acKey', 'acOrderKey', 'acLnkKey']);
$orderItemKeyViewCol = phptest_first_existing($orderItemCols, ['acKeyView', 'acOrderNo', 'acRefNo1']);

$orderItemSelect = array_values(array_filter([
    $orderItemQidCol,
    $orderItemPosCol,
    $orderItemKeyCol,
    $orderItemKeyViewCol,
]));

$orderItemSql = "SELECT TOP 200 " . (empty($orderItemSelect) ? '*' : implode(', ', array_map('phptest_qcol', $orderItemSelect))) .
    " FROM [$defaultSchema].[$orderItemTable]";
$orderItemWhere = [];
$orderItemParams = [];

if ($orderItemKeyCol !== null && !empty($orderKeyCandidates)) {
    $placeholders = implode(', ', array_fill(0, count($orderKeyCandidates), '?'));
    $orderItemWhere[] = phptest_qcol($orderItemKeyCol) . " IN ($placeholders)";
    array_push($orderItemParams, ...$orderKeyCandidates);
}

if ($orderItemKeyViewCol !== null && $orderKey !== '') {
    $orderItemWhere[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), " . phptest_qcol($orderItemKeyViewCol) . "), '-', ''), ' ', '') = ?";
    $orderItemParams[] = $orderKey;
}

if (!empty($orderItemWhere)) {
    $orderItemSql .= ' WHERE ' . implode(' OR ', $orderItemWhere);
}

if ($orderItemPosCol !== null) {
    $orderItemSql .= ' ORDER BY ' . phptest_qcol($orderItemPosCol);
}

$orderItemsStmt = sqlsrv_query($conn, $orderItemSql, $orderItemParams, ['QueryTimeout' => 30]);
$orderItems = [];
if ($orderItemsStmt) {
    while ($row = sqlsrv_fetch_array($orderItemsStmt, SQLSRV_FETCH_ASSOC)) {
        $orderItems[] = $row;
    }
}

phptest_out("ORDER ITEMS: " . count($orderItems));
$orderItemQids = [];
foreach ($orderItems as $row) {
    $qid = $orderItemQidCol !== null ? phptest_norm((string) ($row[$orderItemQidCol] ?? '')) : '';
    if ($qid !== '') {
        $orderItemQids[$qid] = true;
    }

    $pos = $orderItemPosCol !== null ? phptest_format_value($row[$orderItemPosCol] ?? '') : '';
    phptest_out("  - qid=$qid pos=$pos");
}
phptest_out();

// 2) Prenos footprint: Link WOEx <-> OrderItem
$woLinkObject = 'vHF_LinkWOExOrderItem';
$woLinkCols = phptest_fetch_columns($conn, $defaultSchema, $woLinkObject);
$woLinkTimeCol = phptest_best_time_column($woLinkCols);
$woLinkOrderItemQidCol = phptest_first_existing($woLinkCols, ['anOrderItemQId', 'anOrderItemQID', 'anOrderItemQid']);
$woLinkOrderKeyCol = phptest_first_existing($woLinkCols, ['acLnkKey', 'acOrderKey', 'acKey']);
$woLinkOrderKeyViewCol = phptest_first_existing($woLinkCols, ['acLnkKeyView', 'acOrderNo', 'acKeyView']);
$woLinkWorkOrderKeyCol = phptest_first_existing($woLinkCols, ['acKey', 'acWOKey', 'acDocKey']);
$woLinkWorkOrderKeyViewCol = phptest_first_existing($woLinkCols, ['acKeyView', 'acWOKeyView', 'acDocKeyView']);
$woLinkTypeCol = phptest_first_existing($woLinkCols, ['acType', 'acDocType']);
$woLinkTypeViewCol = phptest_first_existing($woLinkCols, ['acTypeView', 'acDocTypeView']);

$woLinkSelect = array_values(array_unique(array_filter([
    $woLinkTimeCol,
    $woLinkOrderItemQidCol,
    $woLinkOrderKeyCol,
    $woLinkOrderKeyViewCol,
    $woLinkWorkOrderKeyCol,
    $woLinkWorkOrderKeyViewCol,
    $woLinkTypeCol,
    $woLinkTypeViewCol,
    'anLnkNo',
    'anNo',
])));
$woLinkSelect = array_values(array_filter($woLinkSelect, function ($col) use ($woLinkCols) {
    return in_array($col, $woLinkCols, true);
}));

$woLinkSql = "SELECT TOP 200 " . (empty($woLinkSelect) ? '*' : implode(', ', array_map('phptest_qcol', $woLinkSelect))) .
    " FROM [$defaultSchema].[$woLinkObject]";
$woLinkWhere = [];
$woLinkParams = [];

if ($woLinkOrderItemQidCol !== null && !empty($orderItemQids)) {
    $qids = array_keys($orderItemQids);
    $placeholders = implode(', ', array_fill(0, count($qids), '?'));
    $woLinkWhere[] = phptest_qcol($woLinkOrderItemQidCol) . " IN ($placeholders)";
    array_push($woLinkParams, ...$qids);
}

if ($woLinkOrderKeyCol !== null && !empty($orderKeyCandidates)) {
    $placeholders = implode(', ', array_fill(0, count($orderKeyCandidates), '?'));
    $woLinkWhere[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), " . phptest_qcol($woLinkOrderKeyCol) . "), '-', ''), ' ', '') IN ($placeholders)";
    array_push($woLinkParams, ...$orderKeyCandidates);
}

if ($woLinkOrderKeyViewCol !== null && $orderKey !== '') {
    $woLinkWhere[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), " . phptest_qcol($woLinkOrderKeyViewCol) . "), '-', ''), ' ', '') = ?";
    $woLinkParams[] = $orderKey;
}

if (!empty($woLinkWhere)) {
    $woLinkSql .= ' WHERE ' . implode(' OR ', $woLinkWhere);
}

if ($woLinkTimeCol !== null) {
    $woLinkSql .= ' ORDER BY ' . phptest_qcol($woLinkTimeCol) . ' DESC';
}

$woLinksStmt = sqlsrv_query($conn, $woLinkSql, $woLinkParams, ['QueryTimeout' => 30]);
$woLinks = [];
if ($woLinksStmt) {
    while ($row = sqlsrv_fetch_array($woLinksStmt, SQLSRV_FETCH_ASSOC)) {
        $woLinks[] = $row;
    }
}

phptest_out("WO LINK (Prenos) rows in $woLinkObject: " . count($woLinks));
$workOrderKeys = [];
foreach ($woLinks as $row) {
    $t = $woLinkTimeCol !== null ? phptest_format_value($row[$woLinkTimeCol] ?? '') : '';
    $qid = $woLinkOrderItemQidCol !== null ? phptest_norm((string) ($row[$woLinkOrderItemQidCol] ?? '')) : '';
    $woKeyView = $woLinkWorkOrderKeyViewCol !== null ? phptest_format_value($row[$woLinkWorkOrderKeyViewCol] ?? '') : '';
    $woKey = $woLinkWorkOrderKeyCol !== null ? phptest_norm((string) ($row[$woLinkWorkOrderKeyCol] ?? '')) : '';
    $typeView = $woLinkTypeViewCol !== null ? phptest_format_value($row[$woLinkTypeViewCol] ?? '') : '';
    $type = $woLinkTypeCol !== null ? phptest_format_value($row[$woLinkTypeCol] ?? '') : '';

    if ($woKey !== '') {
        $workOrderKeys[$woKey] = true;
    } elseif ($woKeyView !== '') {
        $workOrderKeys[phptest_norm($woKeyView)] = true;
    }

    phptest_out("  - t=$t qid=$qid wo=$woKeyView type=$type/$typeView");
}
phptest_out();

// 3) Work orders
$woTable = 'tHF_WOEx';
$woCols = phptest_fetch_columns($conn, $defaultSchema, $woTable);
$woTimeCol = phptest_best_time_column($woCols);
$woKeyCol = phptest_first_existing($woCols, ['acKey', 'acWOKey']);
$woKeyViewCol = phptest_first_existing($woCols, ['acKeyView', 'acWOKeyView']);
$woProducedCol = phptest_first_existing($woCols, ['anProducedQty']);

$workOrderKeyList = array_values(array_unique(array_filter(array_map('phptest_norm', array_keys($workOrderKeys)))));
$workOrders = [];

if (!empty($workOrderKeyList) && $woKeyCol !== null) {
    $select = array_values(array_filter([$woTimeCol, $woKeyCol, $woKeyViewCol, $woProducedCol]));
    $select = array_values(array_filter($select, function ($col) use ($woCols) {
        return $col !== null && in_array($col, $woCols, true);
    }));

    $placeholders = implode(', ', array_fill(0, count($workOrderKeyList), '?'));
    $woSql = "SELECT TOP 200 " . (empty($select) ? '*' : implode(', ', array_map('phptest_qcol', $select))) .
        " FROM [$defaultSchema].[$woTable] WHERE REPLACE(REPLACE(CONVERT(nvarchar(255), " . phptest_qcol($woKeyCol) . "), '-', ''), ' ', '') IN ($placeholders)";

    if ($woTimeCol !== null) {
        $woSql .= ' ORDER BY ' . phptest_qcol($woTimeCol) . ' DESC';
    }

    $woStmt = sqlsrv_query($conn, $woSql, $workOrderKeyList, ['QueryTimeout' => 30]);
    if ($woStmt) {
        while ($row = sqlsrv_fetch_array($woStmt, SQLSRV_FETCH_ASSOC)) {
            $workOrders[] = $row;
        }
    }
}

phptest_out("WO rows in $woTable: " . count($workOrders));
foreach ($workOrders as $row) {
    $t = $woTimeCol !== null ? phptest_format_value($row[$woTimeCol] ?? '') : '';
    $woKeyView = $woKeyViewCol !== null ? phptest_format_value($row[$woKeyViewCol] ?? '') : '';
    $prod = $woProducedCol !== null ? phptest_format_value($row[$woProducedCol] ?? '') : '';
    phptest_out("  - t=$t wo=$woKeyView producedQty=$prod");
}
phptest_out();

// 4) Move footprint: Link MoveItem <-> OrderItem
$moveLinkTable = 'tHE_LinkMoveItemOrderItem';
$moveLinkCols = phptest_fetch_columns($conn, $defaultSchema, $moveLinkTable);
$moveLinkTimeCol = phptest_best_time_column($moveLinkCols);
$moveLinkOrderItemQidCol = phptest_first_existing($moveLinkCols, ['anOrderItemQId', 'anOrderItemQID', 'anOrderItemQid']);
$moveLinkMoveItemQidCol = phptest_first_existing($moveLinkCols, ['anMoveItemQId', 'anMoveItemQID', 'anMoveItemQid', 'anQId', 'anQID', 'anQid']);

$moveLinkSelect = array_values(array_unique(array_filter([
    $moveLinkTimeCol,
    $moveLinkOrderItemQidCol,
    $moveLinkMoveItemQidCol,
    'anNo',
    'acKey',
    'acKeyView',
])));
$moveLinkSelect = array_values(array_filter($moveLinkSelect, function ($col) use ($moveLinkCols) {
    return in_array($col, $moveLinkCols, true);
}));

$moveLinkSql = "SELECT TOP 200 " . (empty($moveLinkSelect) ? '*' : implode(', ', array_map('phptest_qcol', $moveLinkSelect))) .
    " FROM [$defaultSchema].[$moveLinkTable]";
$moveLinkWhere = [];
$moveLinkParams = [];

if ($moveLinkOrderItemQidCol !== null && !empty($orderItemQids)) {
    $qids = array_keys($orderItemQids);
    $placeholders = implode(', ', array_fill(0, count($qids), '?'));
    $moveLinkWhere[] = phptest_qcol($moveLinkOrderItemQidCol) . " IN ($placeholders)";
    array_push($moveLinkParams, ...$qids);
}

if (!empty($moveLinkWhere)) {
    $moveLinkSql .= ' WHERE ' . implode(' OR ', $moveLinkWhere);
}

if ($moveLinkTimeCol !== null) {
    $moveLinkSql .= ' ORDER BY ' . phptest_qcol($moveLinkTimeCol) . ' DESC';
}

$moveLinksStmt = sqlsrv_query($conn, $moveLinkSql, $moveLinkParams, ['QueryTimeout' => 30]);
$moveLinks = [];
if ($moveLinksStmt) {
    while ($row = sqlsrv_fetch_array($moveLinksStmt, SQLSRV_FETCH_ASSOC)) {
        $moveLinks[] = $row;
    }
}

phptest_out("MOVE LINK rows in $moveLinkTable: " . count($moveLinks));
$moveItemQids = [];
foreach ($moveLinks as $row) {
    $t = $moveLinkTimeCol !== null ? phptest_format_value($row[$moveLinkTimeCol] ?? '') : '';
    $qid = $moveLinkOrderItemQidCol !== null ? phptest_norm((string) ($row[$moveLinkOrderItemQidCol] ?? '')) : '';
    $moveQid = $moveLinkMoveItemQidCol !== null ? phptest_norm((string) ($row[$moveLinkMoveItemQidCol] ?? '')) : '';

    if ($moveQid !== '') {
        $moveItemQids[$moveQid] = true;
    }

    phptest_out("  - t=$t orderItemQid=$qid moveItemQid=$moveQid");
}
phptest_out();

// 5) Move items
$moveTable = 'tHE_MoveItem';
$moveCols = phptest_fetch_columns($conn, $defaultSchema, $moveTable);
$moveTimeCol = phptest_best_time_column($moveCols);
$moveQidCol = phptest_first_existing($moveCols, ['anQId', 'anQID', 'anQid']);
$moveKeyViewCol = phptest_first_existing($moveCols, ['acKeyView', 'acKey']);

$moveItemQidList = array_values(array_unique(array_filter(array_map('phptest_norm', array_keys($moveItemQids)))));
$moveItems = [];

if (!empty($moveItemQidList) && $moveQidCol !== null) {
    $select = array_values(array_filter([$moveTimeCol, $moveQidCol, $moveKeyViewCol]));
    $select = array_values(array_filter($select, function ($col) use ($moveCols) {
        return $col !== null && in_array($col, $moveCols, true);
    }));

    $placeholders = implode(', ', array_fill(0, count($moveItemQidList), '?'));
    $moveSql = "SELECT TOP 200 " . (empty($select) ? '*' : implode(', ', array_map('phptest_qcol', $select))) .
        " FROM [$defaultSchema].[$moveTable] WHERE " . phptest_qcol($moveQidCol) . " IN ($placeholders)";

    if ($moveTimeCol !== null) {
        $moveSql .= ' ORDER BY ' . phptest_qcol($moveTimeCol) . ' DESC';
    }

    $moveStmt = sqlsrv_query($conn, $moveSql, $moveItemQidList, ['QueryTimeout' => 30]);
    if ($moveStmt) {
        while ($row = sqlsrv_fetch_array($moveStmt, SQLSRV_FETCH_ASSOC)) {
            $moveItems[] = $row;
        }
    }
}

phptest_out("MOVE ITEM rows in $moveTable: " . count($moveItems));
foreach ($moveItems as $row) {
    $t = $moveTimeCol !== null ? phptest_format_value($row[$moveTimeCol] ?? '') : '';
    $doc = $moveKeyViewCol !== null ? phptest_format_value($row[$moveKeyViewCol] ?? '') : '';
    $qid = $moveQidCol !== null ? phptest_norm((string) ($row[$moveQidCol] ?? '')) : '';
    phptest_out("  - t=$t moveItemQid=$qid doc=$doc");
}

sqlsrv_close($conn);

