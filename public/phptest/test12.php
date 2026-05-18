<?php

/*
 * test12.php
 * Updates dbo.tHE_MoveItem.anWOPrice for move items linked to a specific work order.
 * Default mode is preview only. Actual update runs only when apply=1.
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest12_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest12_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN anWOPrice update test</title></head><body>';
    echo '<pre>' . phptest12_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest12_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest12_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest12_execute($conn, string $sql, array $params = [], int $timeout = 60)
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest12_fail(sqlsrv_errors());
    }

    return $stmt;
}

function phptest12_bool_param(string $value): bool
{
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'da'], true);
}

function phptest12_norm(string $value): string
{
    $normalized = preg_replace('/\D+/', '', $value);

    return is_string($normalized) ? $normalized : '';
}

function phptest12_candidates(string $value): array
{
    $normalized = phptest12_norm($value);

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

function phptest12_format_number($value, int $scale = 4): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest12_format_value($value): string
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
        return phptest12_format_number($value);
    }

    return trim((string) $value);
}

function phptest12_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest12_h($title) . '</' . $tag . '>';
}

function phptest12_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest12_h($class) . '">' . phptest12_h($text) . '</div>';
}

function phptest12_render_table(string $title, array $rows): void
{
    phptest12_render_heading($title, 3);

    if (empty($rows)) {
        phptest12_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 220) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest12_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest12_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest12_h(phptest12_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest12_locate_work_order($conn, string $schema, string $input): array
{
    $trimmedInput = trim($input);
    $candidates = phptest12_candidates($trimmedInput);

    if ($trimmedInput === '' || empty($candidates)) {
        return [
            'input' => $trimmedInput,
            'normalized' => phptest12_norm($trimmedInput),
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

    $rows = phptest12_fetch_all($conn, $sql, $params);

    return [
        'input' => $trimmedInput,
        'normalized' => phptest12_norm($trimmedInput),
        'candidates' => $candidates,
        'row' => $rows[0] ?? null,
    ];
}

function phptest12_target_rows($conn, string $schema, string $workOrderKey): array
{
    $sql = "
        SELECT
            CAST(ISNULL(wi.anQId, 0) as int) AS wo_item_qid,
            CAST(ISNULL(wi.anNo, 0) as int) AS wo_item_no,
            LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), wi.acIdent), ''))) AS wo_item_ident,
            CAST(ISNULL(link.anQId, 0) as int) AS link_qid,
            LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), link.acType), ''))) AS link_type,
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
            CAST(ISNULL(mi.anWOPrice, 0) as float) AS old_anWOPrice,
            CAST(ISNULL(mi.anPrice, 0) as float) AS anPrice,
            CONVERT(varchar(19), mi.adTimeChg, 120) AS changed_at
        FROM [{$schema}].[tHF_WOExItem] AS wi
        INNER JOIN [{$schema}].[tHF_LinkMoveItemWOExItem] AS link
            ON link.anWOExItemQid = wi.anQId
            AND LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), link.acType), ''))) = 'PP'
        INNER JOIN [{$schema}].[tHE_MoveItem] AS mi
            ON mi.anQId = link.anMoveItemQId
        INNER JOIN [{$schema}].[tHE_Move] AS m
            ON m.anQId = mi.anMoveQId
        WHERE wi.acKey = ?
        ORDER BY wi.anNo, document_date DESC, document_key DESC, document_line_no ASC
    ";

    return phptest12_fetch_all($conn, $sql, [$workOrderKey]);
}

$rn = trim((string) ($_GET['rn'] ?? '2660000002889'));
$value = trim((string) ($_GET['value'] ?? '10'));
$apply = phptest12_bool_param((string) ($_GET['apply'] ?? '0'));

if ($value === '' || !is_numeric($value)) {
    phptest12_fail('Parametar "value" mora biti numericki.');
}

$numericValue = (float) $value;
$lookup = phptest12_locate_work_order($conn, $defaultSchema, $rn);
$workOrder = $lookup['row'];

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN anWOPrice update test</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;font-size:14px;line-height:1.45;margin:20px;color:#222}
        h1,h2,h3{margin:16px 0 8px}
        .meta,.note{padding:10px 12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .note{background:#fff8df;border-color:#f0d67b}
        .ok{background:#eaf8ea;border-color:#8fd19e}
        .warn{background:#fff4d6;border-color:#f0c36d}
        .toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:end;padding:12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .toolbar label{display:flex;flex-direction:column;gap:4px;font-size:12px}
        .toolbar input{min-width:200px;padding:6px 8px;font-size:13px}
        .toolbar a,.toolbar button{padding:7px 10px;font-size:13px}
        .table-wrap{overflow-x:auto;margin:8px 0 22px}
        table{border-collapse:collapse;min-width:1280px;background:#fff}
        th,td{border:1px solid #d8d8d8;padding:4px 6px;vertical-align:top;white-space:nowrap}
        th{background:#f1f3f5;text-align:left}
    </style></head><body>';
}

phptest12_render_heading('RN anWOPrice update test', 1);
phptest12_render_note(
    'Skripta cilja dbo.tHE_MoveItem.anWOPrice za move-item redove vezane na RN preko tHF_LinkMoveItemWOExItem (acType = PP). Default je preview; stvarni update radi samo sa apply=1.',
    'note'
);
phptest12_render_note(
    'Parametri: rn=' . $rn . ', value=' . phptest12_format_number($numericValue) . ', apply=' . ($apply ? '1' : '0'),
    'meta'
);

if (PHP_SAPI !== 'cli') {
    echo '<form method="get" class="toolbar">';
    echo '<label>RN <input type="text" name="rn" value="' . phptest12_h($rn) . '" placeholder="2660000002889"></label>';
    echo '<label>Nova anWOPrice <input type="text" name="value" value="' . phptest12_h((string) $value) . '" placeholder="10"></label>';
    echo '<label>Apply 1/0 <input type="text" name="apply" value="' . ($apply ? '1' : '0') . '" placeholder="0"></label>';
    echo '<button type="submit">Pokreni</button>';
    echo '</form>';
}

if (!$workOrder) {
    phptest12_render_note(
        'RN nije pronadjen. Kandidati za trazenje: ' . implode(', ', $lookup['candidates']),
        'warn'
    );

    sqlsrv_close($conn);

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }

    exit;
}

$workOrderKey = trim((string) ($workOrder['acKey'] ?? ''));
$beforeRows = phptest12_target_rows($conn, $defaultSchema, $workOrderKey);

$summaryRows = [[
    'input_rn' => $lookup['input'],
    'matched_acKey' => $workOrderKey,
    'matched_acKeyView' => trim((string) ($workOrder['acKeyView'] ?? '')),
    'acDocType' => trim((string) ($workOrder['acDocType'] ?? '')),
    'linked_order' => trim((string) ($workOrder['acLnkKey'] ?? '')),
    'product_ident' => trim((string) ($workOrder['acIdent'] ?? '')),
    'product_name' => trim((string) ($workOrder['acName'] ?? '')),
    'target_value' => $numericValue,
    'apply' => $apply ? 'YES' : 'NO',
    'target_move_item_rows' => count($beforeRows),
]];

phptest12_render_table('RN summary', $summaryRows);
phptest12_render_table('Preview before update', $beforeRows);

if (empty($beforeRows)) {
    phptest12_render_note('Nema move-item redova za update.', 'warn');

    sqlsrv_close($conn);

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }

    exit;
}

if (!$apply) {
    phptest12_render_note('Preview mode: nista nije upisano. Za stvarni update pozovi sa apply=1.', 'warn');

    sqlsrv_close($conn);

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }

    exit;
}

if (!sqlsrv_begin_transaction($conn)) {
    phptest12_fail(sqlsrv_errors());
}

try {
    $updateSql = "
        UPDATE mi
        SET mi.anWOPrice = ?
        FROM [{$defaultSchema}].[tHE_MoveItem] AS mi
        INNER JOIN [{$defaultSchema}].[tHF_LinkMoveItemWOExItem] AS link
            ON link.anMoveItemQId = mi.anQId
            AND LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), link.acType), ''))) = 'PP'
        INNER JOIN [{$defaultSchema}].[tHF_WOExItem] AS wi
            ON wi.anQId = link.anWOExItemQid
        WHERE wi.acKey = ?
    ";

    $stmt = phptest12_execute($conn, $updateSql, [$numericValue, $workOrderKey]);
    $affectedRows = sqlsrv_rows_affected($stmt);
    sqlsrv_free_stmt($stmt);

    if (!sqlsrv_commit($conn)) {
        phptest12_fail(sqlsrv_errors());
    }

    $afterRows = phptest12_target_rows($conn, $defaultSchema, $workOrderKey);

    phptest12_render_note(
        'Update zavrsen. Affected rows: ' . ($affectedRows === false ? 'UNKNOWN' : (string) $affectedRows),
        'ok'
    );
    phptest12_render_table('Rows after update', $afterRows);
} catch (Throwable $e) {
    sqlsrv_rollback($conn);
    phptest12_fail($e);
}

sqlsrv_close($conn);

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}
