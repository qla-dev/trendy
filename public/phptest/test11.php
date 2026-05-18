<?php

/*
 * test11.php
 * Shows the RN price trail for a work order:
 * - tHE_MoveItem.anWOPrice
 * - tHE_MoveItem.anPrice
 * - tHE_OrderItem.anRTPrice
 * - tHE_OrderItem.anPrice
 * - mHF_WOPostPrice.anPrice
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest11_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest11_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN price trail test</title></head><body>';
    echo '<pre>' . phptest11_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest11_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest11_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest11_norm(string $value): string
{
    $normalized = preg_replace('/\D+/', '', $value);

    return is_string($normalized) ? $normalized : '';
}

function phptest11_candidates(string $value): array
{
    $normalized = phptest11_norm($value);

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

    return array_values(array_unique(array_filter($candidates, static function ($candidate) {
        return $candidate !== '';
    })));
}

function phptest11_format_number($value, int $scale = 4): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest11_format_value($value): string
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

    if (is_float($value) || is_int($value) || (is_string($value) && is_numeric($value))) {
        return phptest11_format_number($value);
    }

    return trim((string) $value);
}

function phptest11_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest11_h($title) . '</' . $tag . '>';
}

function phptest11_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest11_h($class) . '">' . phptest11_h($text) . '</div>';
}

function phptest11_render_table(string $title, array $rows): void
{
    phptest11_render_heading($title, 3);

    if (empty($rows)) {
        phptest11_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 220) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest11_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest11_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest11_h(phptest11_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest11_locate_work_order($conn, string $schema, string $input): array
{
    $trimmedInput = trim($input);
    $candidates = phptest11_candidates($trimmedInput);

    if ($trimmedInput === '' || empty($candidates)) {
        return [
            'input' => $trimmedInput,
            'normalized' => phptest11_norm($trimmedInput),
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

    $rows = phptest11_fetch_all($conn, $sql, $params);

    return [
        'input' => $trimmedInput,
        'normalized' => phptest11_norm($trimmedInput),
        'candidates' => $candidates,
        'row' => $rows[0] ?? null,
    ];
}

$rn = trim((string) ($_GET['rn'] ?? '2664000002834'));
$lookup = phptest11_locate_work_order($conn, $defaultSchema, $rn);
$workOrder = $lookup['row'];

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN price trail test</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;font-size:14px;line-height:1.45;margin:20px;color:#222}
        h1,h2,h3{margin:16px 0 8px}
        .meta,.note{padding:10px 12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .note{background:#fff8df;border-color:#f0d67b}
        .toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:end;padding:12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .toolbar label{display:flex;flex-direction:column;gap:4px;font-size:12px}
        .toolbar input{min-width:220px;padding:6px 8px;font-size:13px}
        .toolbar a,.toolbar button{padding:7px 10px;font-size:13px}
        .table-wrap{overflow-x:auto;margin:8px 0 22px}
        table{border-collapse:collapse;min-width:1280px;background:#fff}
        th,td{border:1px solid #d8d8d8;padding:4px 6px;vertical-align:top;white-space:nowrap}
        th{background:#f1f3f5;text-align:left}
    </style></head><body>';
}

phptest11_render_heading('RN price trail test', 1);
phptest11_render_note(
    'Vraca trazeni trag cijena za RN: tHE_MoveItem.anWOPrice, tHE_MoveItem.anPrice, tHE_OrderItem.anRTPrice, tHE_OrderItem.anPrice, pa zatim mHF_WOPostPrice.anPrice.',
    'note'
);
phptest11_render_note(
    'Parametar: rn=' . ($rn !== '' ? $rn : '2660000002889'),
    'meta'
);

if (PHP_SAPI !== 'cli') {
    echo '<form method="get" class="toolbar">';
    echo '<label>RN <input type="text" name="rn" value="' . phptest11_h($rn) . '" placeholder="2660000002889"></label>';
    echo '<button type="submit">Trazi</button>';
    echo '</form>';
}

if (!$workOrder) {
    phptest11_render_note(
        'RN nije pronadjen. Kandidati za trazenje: ' . implode(', ', $lookup['candidates']),
        'note'
    );

    sqlsrv_close($conn);

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }

    exit;
}

$workOrderKey = trim((string) ($workOrder['acKey'] ?? ''));
$summaryRows = [[
    'input_rn' => $lookup['input'],
    'normalized_rn' => $lookup['normalized'],
    'matched_acKey' => $workOrderKey,
    'matched_acKeyView' => trim((string) ($workOrder['acKeyView'] ?? '')),
    'acDocType' => trim((string) ($workOrder['acDocType'] ?? '')),
    'linked_order' => trim((string) ($workOrder['acLnkKey'] ?? '')),
    'linked_order_view' => trim((string) ($workOrder['acLnkKeyView'] ?? '')),
    'product_ident' => trim((string) ($workOrder['acIdent'] ?? '')),
    'product_name' => trim((string) ($workOrder['acName'] ?? '')),
    'plan_qty' => $workOrder['anPlanQty'] ?? null,
    'produced_qty' => $workOrder['anProducedQty'] ?? null,
    'status' => trim((string) ($workOrder['acStatus'] ?? '')),
    'wo_qid' => $workOrder['anQId'] ?? null,
]];

$orderItemsSql = "
    SELECT
        link.anQId AS link_qid,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), link.acType), ''))) AS link_type,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), link.acLnkKey), ''))) AS order_key_from_link,
        CAST(ISNULL(link.anLnkNo, 0) as int) AS order_line_from_link,
        CAST(ISNULL(link.anOrderItemQId, 0) as int) AS anOrderItemQId,
        CAST(ISNULL(COALESCE(oi_qid.anQId, oi_pair.anQId), 0) as int) AS order_item_qid,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), COALESCE(oi_qid.acKey, oi_pair.acKey)), ''))) AS order_key,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), COALESCE(oi_qid.acKey, oi_pair.acKey, link.acLnkKey)), ''))) AS order_number,
        CAST(ISNULL(COALESCE(oi_qid.anNo, oi_pair.anNo), 0) as int) AS order_item_no,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), COALESCE(oi_qid.acIdent, oi_pair.acIdent)), ''))) AS ident,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), COALESCE(oi_qid.acName, oi_pair.acName)), ''))) AS name,
        CAST(ISNULL(COALESCE(oi_qid.anQty, oi_pair.anQty), 0) as float) AS quantity,
        UPPER(LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), COALESCE(oi_qid.acUM, oi_pair.acUM)), '')))) AS unit,
        CAST(ISNULL(COALESCE(oi_qid.anRTPrice, oi_pair.anRTPrice), 0) as float) AS anRTPrice,
        CAST(ISNULL(COALESCE(oi_qid.anPrice, oi_pair.anPrice), 0) as float) AS anPrice,
        ROUND(
            CAST(ISNULL(COALESCE(oi_qid.anRTPrice, oi_pair.anRTPrice), 0) as float)
            - CAST(ISNULL(COALESCE(oi_qid.anPrice, oi_pair.anPrice), 0) as float),
            4
        ) AS rt_minus_price,
        CONVERT(varchar(19), COALESCE(oi_qid.adTimeChg, oi_pair.adTimeChg, oi_qid.adTimeIns, oi_pair.adTimeIns), 120) AS changed_at
    FROM [{$defaultSchema}].[tHF_LinkWOExOrderItem] AS link
    LEFT JOIN [{$defaultSchema}].[tHE_OrderItem] AS oi_qid
        ON oi_qid.anQId = link.anOrderItemQId
    LEFT JOIN [{$defaultSchema}].[tHE_OrderItem] AS oi_pair
        ON oi_qid.anQId IS NULL
        AND oi_pair.acKey = link.acLnkKey
        AND oi_pair.anNo = link.anLnkNo
    WHERE link.acKey = ?
    ORDER BY order_key, order_item_no, link_qid
";

$moveItemsSql = "
    SELECT
        CAST(ISNULL(wi.anQId, 0) as int) AS wo_item_qid,
        CAST(ISNULL(wi.anNo, 0) as int) AS wo_item_no,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), wi.acIdent), ''))) AS wo_item_ident,
        CAST(ISNULL(link.anQId, 0) as int) AS link_qid,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), link.acType), ''))) AS link_type,
        CAST(ISNULL(link.anMoveItemQId, 0) as int) AS link_move_item_qid,
        CAST(ISNULL(mi.anQId, 0) as int) AS move_item_qid,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acKey), ''))) AS document_key,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acKeyView), ''))) AS document_number,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acDocType), ''))) AS document_type,
        CONVERT(varchar(19), CASE WHEN m.adTimeIns IS NOT NULL THEN m.adTimeIns ELSE CAST(m.adDate AS datetime) END, 120) AS document_date,
        CAST(ISNULL(mi.anNo, 0) as int) AS document_line_no,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acIdent), ''))) AS ident,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acName), ''))) AS name,
        CAST(ISNULL(mi.anQty, 0) as float) AS quantity,
        UPPER(LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acUM), '')))) AS unit,
        CAST(ISNULL(mi.anWOPrice, 0) as float) AS anWOPrice,
        CAST(ISNULL(mi.anPrice, 0) as float) AS anPrice,
        ROUND(CAST(ISNULL(mi.anWOPrice, 0) as float) - CAST(ISNULL(mi.anPrice, 0) as float), 4) AS wo_minus_price,
        CONVERT(varchar(19), mi.adTimeChg, 120) AS changed_at
    FROM [{$defaultSchema}].[tHF_WOExItem] AS wi
    INNER JOIN [{$defaultSchema}].[tHF_LinkMoveItemWOExItem] AS link
        ON link.anWOExItemQid = wi.anQId
    INNER JOIN [{$defaultSchema}].[tHE_MoveItem] AS mi
        ON mi.anQId = link.anMoveItemQId
    INNER JOIN [{$defaultSchema}].[tHE_Move] AS m
        ON m.anQId = mi.anMoveQId
    WHERE wi.acKey = ?
    ORDER BY wi.anNo, document_date DESC, document_key DESC, document_line_no ASC
";

$postPriceSql = "
    SELECT
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), acWOKey), ''))) AS acWOKey,
        CAST(ISNULL(anWONo, 0) as int) AS anWONo,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), acIdent), ''))) AS ident,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), acProcType), ''))) AS acProcType,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), acType), ''))) AS acType,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), acWarehouse), ''))) AS acWarehouse,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), acResursID), ''))) AS acResursID,
        CAST(ISNULL(anPrice, 0) as float) AS anPrice,
        CAST(ISNULL(anQty, 0) as float) AS anQty,
        CAST(ISNULL(anMoveItemQId, 0) as int) AS anMoveItemQId,
        CAST(ISNULL(anID, 0) as int) AS anID
    FROM [{$defaultSchema}].[mHF_WOPostPrice]
    WHERE acWOKey = ?
    ORDER BY anID DESC, anWONo ASC, ident ASC
";

$orderItems = phptest11_fetch_all($conn, $orderItemsSql, [$workOrderKey]);
$moveItems = phptest11_fetch_all($conn, $moveItemsSql, [$workOrderKey]);
$postPrices = phptest11_fetch_all($conn, $postPriceSql, [$workOrderKey]);

$counts = [[
    'order_item_rows' => count($orderItems),
    'move_item_rows' => count($moveItems),
    'post_price_rows' => count($postPrices),
]];

phptest11_render_table('RN summary', $summaryRows);
phptest11_render_table('Row counts', $counts);
phptest11_render_table('tHE_OrderItem prices (anRTPrice / anPrice)', $orderItems);
phptest11_render_table('tHE_MoveItem prices (anWOPrice / anPrice)', $moveItems);
phptest11_render_table('mHF_WOPostPrice prices (anPrice)', $postPrices);

sqlsrv_close($conn);

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}
