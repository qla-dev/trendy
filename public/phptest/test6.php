<?php

/*
 * test6.php
 * Compares two Pantheon work-order snapshots and shows related RN, order, move, and resource data.
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest6_norm(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function phptest6_candidates(string $value): array
{
    $normalized = phptest6_norm($value);

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

function phptest6_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest6_format_value($value): string
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

function phptest6_fail($errors): void
{
    $payload = print_r($errors, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $payload . PHP_EOL);
        exit(1);
    }

    echo '<pre>' . phptest6_h($payload) . '</pre>';
    exit;
}

function phptest6_fetch_all($conn, string $sql, array $params = [], int $timeout = 30): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest6_fail(sqlsrv_errors());
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest6_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest6_h($title) . '</' . $tag . '>';
}

function phptest6_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest6_h($class) . '">' . phptest6_h($text) . '</div>';
}

function phptest6_render_table(string $title, array $rows): void
{
    phptest6_render_heading($title, 3);

    if (empty($rows)) {
        phptest6_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 80) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest6_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap">';
    echo '<table>';
    echo '<thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest6_h((string) $column) . '</th>';
    }

    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest6_h(phptest6_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest6_locate_work_order($conn, string $schema, string $input): array
{
    $trimmedInput = trim($input);
    $candidates = phptest6_candidates($trimmedInput);

    if ($trimmedInput === '' || empty($candidates)) {
        return [
            'input' => $trimmedInput,
            'normalized' => phptest6_norm($trimmedInput),
            'candidates' => $candidates,
            'row' => null,
        ];
    }

    $where = [];
    $params = [];

    foreach ($candidates as $candidate) {
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKey), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKeyView), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
    }

    $sql = "
        SELECT TOP 1 *
        FROM [{$schema}].[tHF_WOEx]
        WHERE " . implode(' OR ', $where) . "
        ORDER BY adTimeIns DESC, acKey DESC
    ";

    $rows = phptest6_fetch_all($conn, $sql, $params);

    return [
        'input' => $trimmedInput,
        'normalized' => phptest6_norm($trimmedInput),
        'candidates' => $candidates,
        'row' => $rows[0] ?? null,
    ];
}

function phptest6_placeholder_list(int $count): string
{
    return implode(', ', array_fill(0, $count, '?'));
}

function phptest6_unique_pairs(array $pairs): array
{
    $unique = [];

    foreach ($pairs as $pair) {
        $key = trim((string) ($pair['acKey'] ?? ''));
        $no = (int) ($pair['anNo'] ?? 0);

        if ($key === '' || $no < 1) {
            continue;
        }

        $unique[$key . '|' . $no] = [
            'acKey' => $key,
            'anNo' => $no,
        ];
    }

    return array_values($unique);
}

function phptest6_unique_strings(array $values): array
{
    return array_values(array_unique(array_filter(array_map(function ($value) {
        return trim((string) $value);
    }, $values), function ($value) {
        return $value !== '';
    })));
}

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN snapshot compare</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;font-size:14px;line-height:1.4;margin:20px;color:#222}
        h1,h2,h3{margin:16px 0 8px}
        .meta,.note{padding:10px 12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .note{background:#fff8df;border-color:#f0d67b}
        .table-wrap{overflow-x:auto;margin:8px 0 22px}
        table{border-collapse:collapse;min-width:960px;background:#fff}
        th,td{border:1px solid #d8d8d8;padding:4px 6px;vertical-align:top;white-space:nowrap}
        th{background:#f1f3f5;text-align:left}
        .muted{color:#666}
    </style></head><body>';
}

$rn1Input = trim((string) ($_GET['rn1'] ?? '26-6000-002731'));
$rn2Input = trim((string) ($_GET['rn2'] ?? '26-6000-002766'));

phptest6_render_heading('Pantheon RN snapshot compare', 1);
phptest6_render_note(
    'Params: rn1, rn2. Supports visible number or internal key. Defaults: 26-6000-002731 and 26-6000-002732.',
    'meta'
);

$targets = [
    phptest6_locate_work_order($conn, $defaultSchema, $rn1Input),
    phptest6_locate_work_order($conn, $defaultSchema, $rn2Input),
];

$summaryRows = [];
foreach ($targets as $target) {
    $row = is_array($target['row'] ?? null) ? $target['row'] : [];

    $summaryRows[] = [
        'input' => $target['input'] ?? '',
        'normalized' => $target['normalized'] ?? '',
        'candidates' => implode(', ', (array) ($target['candidates'] ?? [])),
        'found' => empty($row) ? 'NO' : 'YES',
        'acKey' => $row['acKey'] ?? '',
        'acKeyView' => $row['acKeyView'] ?? '',
        'acIdent' => $row['acIdent'] ?? '',
        'acName' => $row['acName'] ?? '',
        'acLnkKey' => $row['acLnkKey'] ?? '',
        'anLnkNo' => $row['anLnkNo'] ?? '',
        'acStatusMF' => $row['acStatusMF'] ?? '',
        'adTimeIns' => $row['adTimeIns'] ?? null,
    ];
}

phptest6_render_table('Located work orders', $summaryRows);

foreach ($targets as $target) {
    $workOrderRow = is_array($target['row'] ?? null) ? $target['row'] : [];
    $label = trim((string) ($workOrderRow['acKeyView'] ?? ($target['input'] ?? '')));

    phptest6_render_heading('Work order: ' . ($label !== '' ? $label : '[not found]'), 2);

    if (empty($workOrderRow)) {
        phptest6_render_note('Work order was not found.');
        continue;
    }

    $workOrderKey = trim((string) ($workOrderRow['acKey'] ?? ''));
    $productIdent = trim((string) ($workOrderRow['acIdent'] ?? ''));
    $orderKey = trim((string) ($workOrderRow['acLnkKey'] ?? ''));
    $orderNo = (int) ($workOrderRow['anLnkNo'] ?? 0);

    phptest6_render_table('tHF_WOEx header', [$workOrderRow]);

    $items = phptest6_fetch_all(
        $conn,
        "SELECT * FROM [{$defaultSchema}].[tHF_WOExItem] WHERE acKey = ? ORDER BY anNo, anQId",
        [$workOrderKey]
    );
    phptest6_render_table('tHF_WOExItem', $items);

    $itemQids = [];
    foreach ($items as $item) {
        $qid = (int) ($item['anQId'] ?? 0);
        if ($qid > 0) {
            $itemQids[] = $qid;
        }
    }

    $resources = [];
    if (!empty($itemQids)) {
        $resourceSql = "
            SELECT *
            FROM [{$defaultSchema}].[tHF_WOExItemResources]
            WHERE anWOExItemQId IN (" . phptest6_placeholder_list(count($itemQids)) . ")
            ORDER BY anWOExItemQId, anQId
        ";
        $resources = phptest6_fetch_all($conn, $resourceSql, $itemQids);
    }
    phptest6_render_table('tHF_WOExItemResources', $resources);

    $regOperations = phptest6_fetch_all(
        $conn,
        "SELECT * FROM [{$defaultSchema}].[tHF_WOExRegOper] WHERE acKey = ? ORDER BY anNo, adEventTime, adTimeIns, anQId",
        [$workOrderKey]
    );
    phptest6_render_table('tHF_WOExRegOper', $regOperations);

    $orderLinks = phptest6_fetch_all(
        $conn,
        "SELECT * FROM [{$defaultSchema}].[vHF_LinkWOExOrderItem] WHERE acKey = ? ORDER BY anLnkNo, anNo, adTimeIns",
        [$workOrderKey]
    );
    phptest6_render_table('vHF_LinkWOExOrderItem', $orderLinks);

    $orderRefs = [];
    foreach ($orderLinks as $orderLink) {
        $orderRefs[] = [
            'acKey' => $orderLink['acLnkKey'] ?? '',
            'anNo' => $orderLink['anLnkNo'] ?? 0,
        ];
    }

    if ($orderKey !== '' && $orderNo > 0) {
        $orderRefs[] = [
            'acKey' => $orderKey,
            'anNo' => $orderNo,
        ];
    }

    $orderRefs = phptest6_unique_pairs($orderRefs);
    $orderItems = [];

    if (!empty($orderRefs)) {
        $orderWhere = [];
        $orderParams = [];

        foreach ($orderRefs as $orderRef) {
            $orderWhere[] = '(acKey = ? AND anNo = ?)';
            $orderParams[] = $orderRef['acKey'];
            $orderParams[] = $orderRef['anNo'];
        }

        $orderItems = phptest6_fetch_all(
            $conn,
            "SELECT * FROM [{$defaultSchema}].[tHE_OrderItem] WHERE " . implode(' OR ', $orderWhere) . " ORDER BY acKey, anNo",
            $orderParams
        );
    }
    phptest6_render_table('tHE_OrderItem', $orderItems);

    $bomRows = [];
    if ($productIdent !== '') {
        $bomRows = phptest6_fetch_all(
            $conn,
            "SELECT * FROM [{$defaultSchema}].[tHF_SetPrSt] WHERE acIdent = ? ORDER BY anNo, anQId",
            [$productIdent]
        );
    }
    phptest6_render_table('tHF_SetPrSt', $bomRows);

    $catalogIdents = [$productIdent];
    foreach ($items as $item) {
        $catalogIdents[] = $item['acIdent'] ?? '';
    }
    foreach ($bomRows as $bomRow) {
        $catalogIdents[] = $bomRow['acIdentChild'] ?? '';
    }
    foreach ($orderItems as $orderItem) {
        $catalogIdents[] = $orderItem['acIdent'] ?? '';
    }

    $catalogRows = [];
    $catalogIdents = phptest6_unique_strings($catalogIdents);
    if (!empty($catalogIdents)) {
        $catalogSql = "
            SELECT acIdent, acName, acSetOfItem, acUM, acFieldSE, anPrstTime, acPrstUMTime, acDescr, adTimeIns, adTimeChg, anQId
            FROM [{$defaultSchema}].[tHE_SetItem]
            WHERE acIdent IN (" . phptest6_placeholder_list(count($catalogIdents)) . ")
            ORDER BY acIdent
        ";
        $catalogRows = phptest6_fetch_all($conn, $catalogSql, $catalogIdents);
    }
    phptest6_render_table('tHE_SetItem (referenced items)', $catalogRows);

    $orderItemQids = [];
    foreach ($orderItems as $orderItem) {
        $qid = (int) ($orderItem['anQId'] ?? 0);
        if ($qid > 0) {
            $orderItemQids[] = $qid;
        }
    }

    $moveLinks = [];
    if (!empty($orderItemQids)) {
        $moveLinkSql = "
            SELECT *
            FROM [{$defaultSchema}].[tHE_LinkMoveItemOrderItem]
            WHERE anOrderItemQId IN (" . phptest6_placeholder_list(count($orderItemQids)) . ")
            ORDER BY adTimeIns DESC, anMoveItemQId DESC
        ";
        $moveLinks = phptest6_fetch_all($conn, $moveLinkSql, $orderItemQids);
    }
    phptest6_render_table('tHE_LinkMoveItemOrderItem', $moveLinks);

    $moveItemQids = [];
    foreach ($moveLinks as $moveLink) {
        $moveItemQid = (int) ($moveLink['anMoveItemQId'] ?? 0);
        if ($moveItemQid > 0) {
            $moveItemQids[] = $moveItemQid;
        }
    }

    $moveTrace = [];
    if (!empty($moveItemQids)) {
        $moveTraceSql = "
            SELECT
                l.anOrderItemQId,
                l.anMoveItemQId,
                l.adTimeIns AS linkTimeIns,
                m.acKey,
                m.acKeyView,
                m.acDocType,
                m.acDoc1,
                m.acDoc2,
                m.acNote,
                m.adDate,
                m.adTimeIns AS moveTimeIns,
                mi.anNo,
                mi.acIdent,
                mi.acName,
                mi.acUM,
                mi.anQty,
                mi.acNote AS moveItemNote
            FROM [{$defaultSchema}].[tHE_LinkMoveItemOrderItem] AS l
            INNER JOIN [{$defaultSchema}].[tHE_MoveItem] AS mi
                ON mi.anQId = l.anMoveItemQId
            INNER JOIN [{$defaultSchema}].[tHE_Move] AS m
                ON m.acKey = mi.acKey
            WHERE l.anMoveItemQId IN (" . phptest6_placeholder_list(count($moveItemQids)) . ")
            ORDER BY l.adTimeIns DESC, mi.anNo ASC
        ";
        $moveTrace = phptest6_fetch_all($conn, $moveTraceSql, $moveItemQids);
    }
    phptest6_render_table('Move trace (Move + MoveItem)', $moveTrace);
}

sqlsrv_close($conn);

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}
