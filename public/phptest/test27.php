<?php

/*
 * test27.php
 * Read-only RN sastavnica quantity trace.
 *
 * Shows:
 * - RN header and header note from tHF_WOEx.acNote
 * - where Pantheon's "/RN" value comes from
 * - product sastavnica rows from tHF_SetPrSt
 * - work-order rows from tHF_WOExItem
 * - optional item resource rows from tHF_WOExItemResources
 *
 * Parameters:
 * - rn=26-6000-003514
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest27_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest27_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN /RN quantity trace</title></head><body>';
    echo '<pre>' . phptest27_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest27_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest27_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest27_norm(string $value): string
{
    $normalized = preg_replace('/\D+/', '', $value);

    return is_string($normalized) ? $normalized : '';
}

function phptest27_candidates(string $value): array
{
    $normalized = phptest27_norm($value);

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

function phptest27_locate_work_order($conn, string $schema, string $input): array
{
    $trimmedInput = trim($input);
    $candidates = phptest27_candidates($trimmedInput);

    if ($trimmedInput === '' || empty($candidates)) {
        return [
            'input' => $trimmedInput,
            'normalized' => phptest27_norm($trimmedInput),
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

    $rows = phptest27_fetch_all($conn, $sql, $params);

    return [
        'input' => $trimmedInput,
        'normalized' => phptest27_norm($trimmedInput),
        'candidates' => $candidates,
        'row' => $rows[0] ?? null,
    ];
}

function phptest27_format_number($value, int $scale = 4): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest27_format_value($value): string
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
        return phptest27_format_number($value);
    }

    return trim((string) $value);
}

function phptest27_float($value): float
{
    return is_numeric((string) $value) ? (float) $value : 0.0;
}

function phptest27_table_columns($conn, string $schema, string $table): array
{
    $rows = phptest27_fetch_all($conn, "
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ", [$schema, $table]);

    return array_values(array_map(static function (array $row): string {
        return (string) ($row['COLUMN_NAME'] ?? '');
    }, $rows));
}

function phptest27_has_column(array $columns, string $column): bool
{
    return in_array($column, $columns, true);
}

function phptest27_select_column_or_null(array $columns, string $alias, string $column, string $selectAlias): string
{
    if (phptest27_has_column($columns, $column)) {
        return "{$alias}.{$column} AS {$selectAlias}";
    }

    return "CAST(NULL AS nvarchar(4000)) AS {$selectAlias}";
}

function phptest27_row_status(array $row): string
{
    $type = strtoupper(trim((string) ($row['type'] ?? '')));
    $slashRn = abs(phptest27_float($row['Pantheon /RN'] ?? null));
    $qty = abs(phptest27_float($row['tHF_WOExItem.anQty'] ?? null));
    $qty1 = abs(phptest27_float($row['tHF_WOExItem.anQty1'] ?? null));

    if ($type !== 'M') {
        return 'operation/non-material';
    }

    if ($slashRn > 0.000001 && $qty <= 0.000001 && $qty1 <= 0.000001) {
        return 'PLANNED VALUE BEFORE SCAN';
    }

    if ($slashRn > 0.000001 && abs($slashRn - max($qty, $qty1)) > 0.000001 && max($qty, $qty1) > 0.000001) {
        return 'SCAN/ACTUAL DIFFERS FROM /RN';
    }

    if ($slashRn <= 0.000001 && max($qty, $qty1) <= 0.000001) {
        return 'zero until scan';
    }

    return 'ok/check';
}

function phptest27_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest27_h($title) . '</' . $tag . '>';
}

function phptest27_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest27_h($class) . '">' . phptest27_h($text) . '</div>';
}

function phptest27_cell_class(string $column, $value): string
{
    $classes = [];

    if (str_contains($column, '/RN') || str_contains($column, 'anPlanQty') || str_contains($column, 'formula')) {
        $classes[] = 'source';
    }

    if (str_contains($column, 'acNote') || str_contains($column, 'header_note')) {
        $classes[] = 'note-cell';
    }

    if ($column === 'status') {
        $status = strtoupper(trim((string) $value));
        if (str_contains($status, 'BEFORE SCAN') || str_contains($status, 'DIFFERS')) {
            $classes[] = 'warn-cell';
        }
    }

    return implode(' ', $classes);
}

function phptest27_render_table(string $title, array $rows): void
{
    phptest27_render_heading($title, 3);

    if (empty($rows)) {
        phptest27_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 220) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest27_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        $class = phptest27_cell_class((string) $column, null);
        echo '<th' . ($class !== '' ? ' class="' . phptest27_h($class) . '"' : '') . '>' . phptest27_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $status = strtoupper(trim((string) ($row['status'] ?? '')));
        $rowClass = str_contains($status, 'BEFORE SCAN') || str_contains($status, 'DIFFERS') ? ' class="warn-row"' : '';
        echo '<tr' . $rowClass . '>';

        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            $class = phptest27_cell_class((string) $column, $value);
            echo '<td' . ($class !== '' ? ' class="' . phptest27_h($class) . '"' : '') . '>' . phptest27_h(phptest27_format_value($value)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

$rn = trim((string) ($_GET['rn'] ?? '26-6000-003514'));
$schema = preg_replace('/[^A-Za-z0-9_]/', '', (string) $defaultSchema) ?: 'dbo';
$lookup = phptest27_locate_work_order($conn, $schema, $rn);
$workOrder = $lookup['row'];

if ($workOrder === null) {
    if (PHP_SAPI !== 'cli') {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>RN /RN quantity trace</title></head><body>';
    }

    phptest27_render_heading('RN /RN quantity trace', 1);
    phptest27_render_note('Work order not found. Input: ' . $rn . '. Candidates: ' . implode(', ', $lookup['candidates']), 'warn');

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }

    exit;
}

$workOrderKey = trim((string) ($workOrder['acKey'] ?? ''));
$workOrderView = trim((string) ($workOrder['acKeyView'] ?? $workOrderKey));
$productCode = trim((string) ($workOrder['acIdent'] ?? ''));
$workOrderQty = phptest27_float($workOrder['anPlanQty'] ?? 0);
$workOrderItemColumns = phptest27_table_columns($conn, $schema, 'tHF_WOExItem');
$productStructureColumns = phptest27_table_columns($conn, $schema, 'tHF_SetPrSt');
$itemAcNoteSelect = phptest27_select_column_or_null($workOrderItemColumns, 'i', 'acNote', 'itemAcNote');
$structureAcNoteSelect = phptest27_select_column_or_null($productStructureColumns, 'ps', 'acNote', 'structureAcNote');

$headerRows = [[
    'tHF_WOEx.acKeyView' => $workOrderView,
    'tHF_WOEx.acKey' => $workOrderKey,
    'product' => $productCode,
    'tHF_WOEx.anPlanQty (RN qty)' => $workOrder['anPlanQty'] ?? null,
    'produced_qty' => $workOrder['anProducedQty'] ?? null,
    'status' => trim((string) ($workOrder['acStatus'] ?? '')) . '/' . trim((string) ($workOrder['acStatusMF'] ?? '')),
    'order_link' => trim((string) ($workOrder['acLnkKeyView'] ?? $workOrder['acLnkKey'] ?? '')),
    'order_position' => $workOrder['anLnkNo'] ?? null,
    'header_note tHF_WOEx.acNote' => $workOrder['acNote'] ?? null,
    'inserted_by' => $workOrder['anUserIns'] ?? null,
    'changed_by' => $workOrder['anUserChg'] ?? null,
    'inserted_at' => $workOrder['adTimeIns'] ?? null,
    'changed_at' => $workOrder['adTimeChg'] ?? null,
]];

$formulaRows = [[
    'Pantheon UI label' => '/RN',
    'not a physical DB column' => 'calculated display value',
    'formula' => 'tHF_WOExItem.anPlanQty * tHF_WOEx.anPlanQty',
    'this RN header qty' => $workOrderQty,
    'header note location' => 'tHF_WOEx.acNote',
    'material note location' => phptest27_has_column($workOrderItemColumns, 'acNote')
        ? 'tHF_WOExItem.acNote'
        : 'tHF_WOExItem.acFieldSE',
]];

$itemRowsRaw = phptest27_fetch_all($conn, "
    SELECT
        i.anQId,
        i.anNo,
        i.acIdent,
        i.acDescr,
        i.acOperationType,
        i.acUM,
        i.anPlanQty,
        i.anQty,
        i.anQty1,
        i.anQtySE,
        i.anQtyBase,
        i.anIssuePerc,
        {$itemAcNoteSelect},
        i.acFieldSE,
        i.acQtyFormula,
        i.acDelayType,
        i.acIssueFinished,
        i.acAutoPrepType,
        i.acTaskState,
        i.acOrigin,
        i.anUserIns,
        i.anUserChg,
        i.adTimeIns,
        i.adTimeChg,
        CAST(ISNULL(i.anPlanQty, 0) * ? AS float) AS computedSlashRN
    FROM [{$schema}].[tHF_WOExItem] AS i
    WHERE i.acKey = ?
    ORDER BY i.anNo, i.anQId
", [$workOrderQty, $workOrderKey]);

$itemRows = [];
foreach ($itemRowsRaw as $row) {
    $mapped = [
        'line' => $row['anNo'] ?? null,
        'anQId' => $row['anQId'] ?? null,
        'type' => trim((string) ($row['acOperationType'] ?? '')),
        'material/operation' => trim((string) ($row['acIdent'] ?? '')),
        'description' => trim((string) ($row['acDescr'] ?? '')),
        'UM' => trim((string) ($row['acUM'] ?? '')),
        'tHF_WOExItem.anPlanQty' => $row['anPlanQty'] ?? null,
        'x tHF_WOEx.anPlanQty' => $workOrderQty,
        'Pantheon /RN' => $row['computedSlashRN'] ?? null,
        'tHF_WOExItem.anQty' => $row['anQty'] ?? null,
        'tHF_WOExItem.anQty1' => $row['anQty1'] ?? null,
        'issue_percent' => $row['anIssuePerc'] ?? null,
        'Napomena tHF_WOExItem.acNote' => $row['itemAcNote'] ?? null,
        'dimensions/note acFieldSE' => $row['acFieldSE'] ?? null,
        'formula acQtyFormula' => $row['acQtyFormula'] ?? null,
        'delay/issue/state' => trim((string) ($row['acDelayType'] ?? '')) . '/' . trim((string) ($row['acIssueFinished'] ?? '')) . '/' . trim((string) ($row['acTaskState'] ?? '')),
        'origin' => trim((string) ($row['acOrigin'] ?? '')),
        'inserted_by' => $row['anUserIns'] ?? null,
        'changed_by' => $row['anUserChg'] ?? null,
        'inserted_at' => $row['adTimeIns'] ?? null,
        'changed_at' => $row['adTimeChg'] ?? null,
    ];
    $mapped['status'] = phptest27_row_status($mapped);
    $itemRows[] = $mapped;
}

$structureRows = [];
if ($productCode !== '') {
    $structureRowsRaw = phptest27_fetch_all($conn, "
        SELECT
            ps.anQId,
            ps.anNo,
            ps.acIdent,
            ps.acIdentChild,
            ps.acDescr,
            ps.acOperationType,
            ps.acUM,
            ps.anGrossQty,
            ps.anNetQty,
            ps.anQty1,
            ps.anQtyBase,
            ps.anBatch,
            {$structureAcNoteSelect},
            ps.acFieldSE,
            ps.acQtyFormula,
            ps.anUserIns,
            ps.anUserChg,
            ps.adTimeIns,
            ps.adTimeChg,
            CAST(ISNULL(ps.anGrossQty, 0) * ? AS float) AS expectedSlashRN
        FROM [{$schema}].[tHF_SetPrSt] AS ps
        WHERE ps.acIdent = ?
        ORDER BY ps.anNo, ps.anQId
    ", [$workOrderQty, $productCode]);

    foreach ($structureRowsRaw as $row) {
        $structureRows[] = [
            'line' => $row['anNo'] ?? null,
            'structure_anQId' => $row['anQId'] ?? null,
            'product tHF_SetPrSt.acIdent' => trim((string) ($row['acIdent'] ?? '')),
            'component acIdentChild' => trim((string) ($row['acIdentChild'] ?? '')),
            'description' => trim((string) ($row['acDescr'] ?? '')),
            'type' => trim((string) ($row['acOperationType'] ?? '')),
            'UM' => trim((string) ($row['acUM'] ?? '')),
            'tHF_SetPrSt.anGrossQty' => $row['anGrossQty'] ?? null,
            'x tHF_WOEx.anPlanQty' => $workOrderQty,
            'expected /RN if copied' => $row['expectedSlashRN'] ?? null,
            'anNetQty' => $row['anNetQty'] ?? null,
            'anQty1' => $row['anQty1'] ?? null,
            'Napomena tHF_SetPrSt.acNote' => $row['structureAcNote'] ?? null,
            'dimensions/note acFieldSE' => $row['acFieldSE'] ?? null,
            'formula acQtyFormula' => $row['acQtyFormula'] ?? null,
            'inserted_by' => $row['anUserIns'] ?? null,
            'changed_by' => $row['anUserChg'] ?? null,
            'inserted_at' => $row['adTimeIns'] ?? null,
            'changed_at' => $row['adTimeChg'] ?? null,
        ];
    }
}

$resourceRows = phptest27_fetch_all($conn, "
    SELECT
        r.anQId AS resource_anQId,
        r.anWOExItemQId,
        i.anNo AS item_line,
        i.acIdent AS item_ident,
        i.acDescr AS item_description,
        r.acResursID,
        r.acResType,
        r.anPlanQty AS resource_anPlanQty,
        r.anQty AS resource_anQty,
        r.anQty1 AS resource_anQty1,
        r.anQty2 AS resource_anQty2,
        r.anExecutionPerc,
        r.acQtyFormula,
        r.acIssueFinished,
        r.anUserIns,
        r.anUserChg,
        r.adTimeIns,
        r.adTimeChg
    FROM [{$schema}].[tHF_WOExItemResources] AS r
    LEFT JOIN [{$schema}].[tHF_WOExItem] AS i
        ON i.anQId = r.anWOExItemQId
    WHERE i.acKey = ?
    ORDER BY i.anNo, r.anQId
", [$workOrderKey]);

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN /RN quantity trace</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;margin:24px;background:#f7f9fb;color:#1f2933}
        h1{font-size:24px;margin:0 0 16px}
        h2{margin-top:26px}
        h3{margin:22px 0 8px}
        .note{background:#eef6ff;border:1px solid #b7d7ff;border-radius:6px;padding:10px 12px;margin:8px 0}
        .warn{background:#fff4e5;border-color:#ffd28a}
        form{display:flex;gap:8px;align-items:end;margin:12px 0 18px}
        label{font-weight:600}
        input{padding:7px 9px;border:1px solid #b7c4d1;border-radius:4px}
        button{padding:8px 12px;border:1px solid #2f6fed;background:#2f6fed;color:#fff;border-radius:4px;cursor:pointer}
        .table-wrap{overflow:auto;background:#fff;border:1px solid #d6dde5;border-radius:6px;margin-bottom:14px}
        table{border-collapse:collapse;min-width:100%;font-size:13px}
        th,td{border-bottom:1px solid #e5e9ef;border-right:1px solid #edf1f5;padding:7px 9px;text-align:left;vertical-align:top;white-space:nowrap}
        th{position:sticky;top:0;background:#edf2f7;font-weight:700}
        .source{background:#fff2cc;font-weight:700}
        .note-cell{background:#e8f4ff;white-space:normal;min-width:320px}
        .warn-row td{background:#fff8e8}
        .warn-row td.source,.warn-cell{background:#ffd9a8;font-weight:700}
        code{background:#eef2f6;padding:2px 4px;border-radius:4px}
    </style>';
    echo '</head><body>';
}

phptest27_render_heading('RN /RN quantity trace', 1);

if (PHP_SAPI !== 'cli') {
    echo '<form method="get">';
    echo '<label>RN<br><input type="text" name="rn" value="' . phptest27_h($rn) . '" placeholder="26-6000-003514"></label>';
    echo '<button type="submit">Open</button>';
    echo '</form>';
}

phptest27_render_note(
    'Read-only diagnostic. Highlighted yellow columns are the source of Pantheon /RN: tHF_WOExItem.anPlanQty multiplied by the RN header quantity tHF_WOEx.anPlanQty.'
);
phptest27_render_table('Formula / location', $formulaRows);
phptest27_render_table('RN header and note', $headerRows);
phptest27_render_table('Product sastavnica rows from tHF_SetPrSt', $structureRows);
phptest27_render_table('Work order rows from tHF_WOExItem with computed Pantheon /RN', $itemRows);
phptest27_render_table('Work order item resource subrows from tHF_WOExItemResources', $resourceRows);

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}
