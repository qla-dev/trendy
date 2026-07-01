<?php

/*
 * test28.php
 * Read-only Pantheon sastavnica schema and work-order relationship inspector.
 *
 * Shows:
 * - column metadata for product sastavnica and work-order related tables
 * - known relationship map between those tables
 * - optional sample rows for an RN and/or product
 *
 * Browser examples:
 * - /phptest/test28.php
 * - /phptest/test28.php?rn=26-6000-003534
 * - /phptest/test28.php?product=6242488
 *
 * CLI examples:
 * - php public/phptest/test28.php "rn=26-6000-003534"
 * - php public/phptest/test28.php "product=6242488"
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest28_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest28_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Sastavnica schema inspector</title></head><body>';
    echo '<pre>' . phptest28_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest28_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest28_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest28_option(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    if (is_array($value)) {
        $value = end($value);
    }

    return trim((string) $value);
}

function phptest28_identifier(string $value, string $fallback): string
{
    return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1 ? $value : $fallback;
}

function phptest28_norm_digits(string $value): string
{
    $normalized = preg_replace('/\D+/', '', $value);

    return is_string($normalized) ? $normalized : '';
}

function phptest28_rn_candidates(string $value): array
{
    $normalized = phptest28_norm_digits($value);

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

    return array_values(array_unique(array_filter($candidates)));
}

function phptest28_value($value, int $maxLength = 4000): string
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
        $formatted = number_format((float) $value, 6, '.', '');
        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    $string = trim((string) $value);

    if (function_exists('mb_strlen') && mb_strlen($string, 'UTF-8') > $maxLength) {
        return mb_substr($string, 0, $maxLength, 'UTF-8') . '...';
    }

    return $string;
}

function phptest28_table_exists($conn, string $schema, string $table): bool
{
    $rows = phptest28_fetch_all($conn, "
        SELECT 1 AS found
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
    ", [$schema, $table]);

    return $rows !== [];
}

function phptest28_columns($conn, string $schema, string $table): array
{
    if (!phptest28_table_exists($conn, $schema, $table)) {
        return [[
            'table' => $schema . '.' . $table,
            'column' => '(table missing)',
            'type' => '',
            'nullable' => '',
            'default' => '',
            'identity' => '',
            'computed' => '',
            'ordinal' => '',
        ]];
    }

    $rows = phptest28_fetch_all($conn, "
        SELECT
            c.ORDINAL_POSITION AS ordinal,
            c.COLUMN_NAME AS column_name,
            c.DATA_TYPE AS data_type,
            c.CHARACTER_MAXIMUM_LENGTH AS max_length,
            c.NUMERIC_PRECISION AS numeric_precision,
            c.NUMERIC_SCALE AS numeric_scale,
            c.IS_NULLABLE AS is_nullable,
            c.COLUMN_DEFAULT AS column_default,
            sc.is_identity,
            sc.is_computed
        FROM INFORMATION_SCHEMA.COLUMNS c
        LEFT JOIN sys.schemas ss
            ON ss.name = c.TABLE_SCHEMA
        LEFT JOIN sys.tables st
            ON st.schema_id = ss.schema_id
           AND st.name = c.TABLE_NAME
        LEFT JOIN sys.columns sc
            ON sc.object_id = st.object_id
           AND sc.name = c.COLUMN_NAME
        WHERE c.TABLE_SCHEMA = ?
          AND c.TABLE_NAME = ?
        ORDER BY c.ORDINAL_POSITION
    ", [$schema, $table]);

    return array_map(static function (array $row) use ($schema, $table): array {
        $type = (string) ($row['data_type'] ?? '');
        $maxLength = $row['max_length'] ?? null;
        $precision = $row['numeric_precision'] ?? null;
        $scale = $row['numeric_scale'] ?? null;

        if ($maxLength !== null && is_numeric((string) $maxLength)) {
            $type .= '(' . $maxLength . ')';
        } elseif ($precision !== null && is_numeric((string) $precision)) {
            $type .= '(' . $precision . ',' . $scale . ')';
        }

        return [
            'table' => $schema . '.' . $table,
            'ordinal' => $row['ordinal'] ?? '',
            'column' => $row['column_name'] ?? '',
            'type' => $type,
            'nullable' => $row['is_nullable'] ?? '',
            'default' => $row['column_default'] ?? '',
            'identity' => ((int) ($row['is_identity'] ?? 0)) === 1 ? 'YES' : '',
            'computed' => ((int) ($row['is_computed'] ?? 0)) === 1 ? 'YES' : '',
        ];
    }, $rows);
}

function phptest28_column_names($conn, string $schema, string $table): array
{
    $rows = phptest28_fetch_all($conn, "
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

function phptest28_select_or_null(array $columns, string $alias, string $column, string $as): string
{
    if (in_array($column, $columns, true)) {
        return "{$alias}.{$column} AS {$as}";
    }

    return "CAST(NULL AS nvarchar(4000)) AS {$as}";
}

function phptest28_locate_work_order($conn, string $schema, string $rn): ?array
{
    $trimmed = trim($rn);

    if ($trimmed === '') {
        $rows = phptest28_fetch_all($conn, "
            SELECT TOP 1 *
            FROM [{$schema}].[tHF_WOEx]
            WHERE CONVERT(nvarchar(max), ISNULL(acNote, '')) LIKE '%eNalog.app%'
            ORDER BY adTimeIns DESC, acKey DESC
        ");

        return $rows[0] ?? null;
    }

    $candidates = phptest28_rn_candidates($trimmed);

    if ($candidates === []) {
        return null;
    }

    $where = [];
    $params = [];

    foreach ($candidates as $candidate) {
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKey), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKeyView), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
    }

    $rows = phptest28_fetch_all($conn, "
        SELECT TOP 1 *
        FROM [{$schema}].[tHF_WOEx]
        WHERE " . implode(' OR ', $where) . "
        ORDER BY adTimeIns DESC, acKey DESC
    ", $params);

    return $rows[0] ?? null;
}

function phptest28_relationship_rows(string $schema): array
{
    return [
        [
            'relationship' => 'RN header -> RN sastavnica lines',
            'from' => "{$schema}.tHF_WOEx.acKey",
            'to' => "{$schema}.tHF_WOExItem.acKey",
            'meaning' => 'Work-order header owns work-order item/material/operation rows.',
        ],
        [
            'relationship' => 'RN line -> RN resource rows',
            'from' => "{$schema}.tHF_WOExItem.anQId",
            'to' => "{$schema}.tHF_WOExItemResources.anWOExItemQId",
            'meaning' => 'Operation/resource detail rows for a work-order item.',
        ],
        [
            'relationship' => 'RN header -> product/basic sastavnica',
            'from' => "{$schema}.tHF_WOEx.acIdent",
            'to' => "{$schema}.tHF_SetPrSt.acIdent",
            'meaning' => 'Finished product code points to its product BOM.',
        ],
        [
            'relationship' => 'Product sastavnica line -> component',
            'from' => "{$schema}.tHF_SetPrSt.acIdentChild",
            'to' => "{$schema}.tHE_SetItem.acIdent",
            'meaning' => 'Component/material/operation item code.',
        ],
        [
            'relationship' => 'Product sastavnica line -> product resource rows',
            'from' => "{$schema}.tHF_SetPrSt.anQId",
            'to' => "{$schema}.tHF_SetPrStResources.anPrstQId",
            'meaning' => 'Basic BOM resource details used by Pantheon copy procedures.',
        ],
        [
            'relationship' => 'RN resource row -> RN tools/rules',
            'from' => "{$schema}.tHF_WOExItemResources.anQId",
            'to' => "{$schema}.tHF_WOExItemTools.anResursQID / tHF_WOExItemToolsSml.anResursQID",
            'meaning' => 'Tool details copied from product sastavnica resources.',
        ],
        [
            'relationship' => 'RN line -> RN rule rows',
            'from' => "{$schema}.tHF_WOExItem.anQId",
            'to' => "{$schema}.tHF_WOExItemRules.anWOExItemQId",
            'meaning' => 'Rule details copied from product sastavnica.',
        ],
        [
            'relationship' => 'RN -> sales/order item link',
            'from' => "{$schema}.tHF_WOEx.acKey",
            'to' => "{$schema}.tHF_LinkWOExOrderItem.acKey",
            'meaning' => 'Links generated RN to order/order item.',
        ],
    ];
}

function phptest28_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min(6, $level));
    echo '<' . $tag . '>' . phptest28_h($title) . '</' . $tag . '>';
}

function phptest28_render_table(string $title, array $rows): void
{
    phptest28_render_heading($title, 3);

    if ($rows === []) {
        if (PHP_SAPI === 'cli') {
            echo "No rows.\n";
        } else {
            echo '<div class="note">No rows.</div>';
        }
        return;
    }

    $columns = array_keys($rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 180) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest28_value($row[$column] ?? null, 1200);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest28_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            $wide = in_array($column, ['default', 'meaning', 'acNote', 'acFieldSE', 'note', 'header_note'], true);
            echo '<td' . ($wide ? ' class="wide"' : '') . '>' . phptest28_h(phptest28_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

$schema = phptest28_identifier(phptest28_option('schema', $defaultSchema ?: 'dbo'), $defaultSchema ?: 'dbo');
$rnInput = phptest28_option('rn');
$productInput = phptest28_option('product');

$tables = [
    'tHF_SetPrSt',
    'tHF_SetPrStResources',
    'tHF_SetPrStTools',
    'tHF_SetPrStToolsSml',
    'tHF_SetPrStRules',
    'tHF_WOEx',
    'tHF_WOExItem',
    'tHF_WOExItemResources',
    'tHF_WOExItemTools',
    'tHF_WOExItemToolsSml',
    'tHF_WOExItemRules',
    'tHF_WOExRegOper',
    'tHF_LinkWOExOrderItem',
];

$columnRows = [];
foreach ($tables as $table) {
    $columnRows = array_merge($columnRows, phptest28_columns($conn, $schema, $table));
}

$workOrder = phptest28_locate_work_order($conn, $schema, $rnInput);
$workOrderKey = $workOrder !== null ? trim((string) ($workOrder['acKey'] ?? '')) : '';
$productCode = $productInput !== ''
    ? $productInput
    : ($workOrder !== null ? trim((string) ($workOrder['acIdent'] ?? '')) : '');

$summaryRows = [[
    'schema' => $schema,
    'rn_input' => $rnInput === '' ? '(blank - latest eNalog RN sample)' : $rnInput,
    'resolved_rn' => $workOrder !== null ? trim((string) ($workOrder['acKeyView'] ?? $workOrder['acKey'] ?? '')) : '(not found)',
    'work_order_key' => $workOrderKey,
    'product' => $productCode,
    'tables_listed' => count($tables),
]];

$headerRows = [];
if ($workOrder !== null) {
    $headerRows[] = [
        'acKey' => $workOrder['acKey'] ?? null,
        'acKeyView' => $workOrder['acKeyView'] ?? null,
        'acDocType' => $workOrder['acDocType'] ?? null,
        'acCreateFrom' => $workOrder['acCreateFrom'] ?? null,
        'acStatus' => $workOrder['acStatus'] ?? null,
        'acStatusMF' => $workOrder['acStatusMF'] ?? null,
        'acIdent' => $workOrder['acIdent'] ?? null,
        'anPlanQty' => $workOrder['anPlanQty'] ?? null,
        'acLnkKey' => $workOrder['acLnkKey'] ?? null,
        'anLnkNo' => $workOrder['anLnkNo'] ?? null,
        'anLnkPrStNo' => $workOrder['anLnkPrStNo'] ?? null,
        'acLnkPrStIdentParent' => $workOrder['acLnkPrStIdentParent'] ?? null,
        'anLnkPrStSubNo' => $workOrder['anLnkPrStSubNo'] ?? null,
        'anLnkPrStNoParent' => $workOrder['anLnkPrStNoParent'] ?? null,
        'anQId' => $workOrder['anQId'] ?? null,
        'acNote' => $workOrder['acNote'] ?? null,
        'adTimeIns' => $workOrder['adTimeIns'] ?? null,
        'adTimeChg' => $workOrder['adTimeChg'] ?? null,
    ];
}

$setPrStColumns = phptest28_column_names($conn, $schema, 'tHF_SetPrSt');
$woColumns = phptest28_column_names($conn, $schema, 'tHF_WOEx');
$woItemColumns = phptest28_column_names($conn, $schema, 'tHF_WOExItem');
$setPrStNote = phptest28_select_or_null($setPrStColumns, 'ps', 'acNote', 'acNote');
$woItemNote = phptest28_select_or_null($woItemColumns, 'wi', 'acNote', 'acNote');
$woQtySeriesExpr = in_array('anQtySeries', $woColumns, true)
    ? 'CAST(ISNULL(w.anQtySeries, 0) AS float)'
    : 'CAST(1 AS float)';
$issueQtyExpr = "ROUND(CASE
    WHEN ISNULL(w.anPlanQty, 0) = 0 OR {$woQtySeriesExpr} = 0 THEN ISNULL(wi.anPlanQty, 0)
    ELSE ISNULL(wi.anPlanQty, 0) * ISNULL(w.anPlanQty, 0) / {$woQtySeriesExpr}
END, 4)";

$productRows = [];
if ($productCode !== '' && phptest28_table_exists($conn, $schema, 'tHF_SetPrSt')) {
    $productRows = phptest28_fetch_all($conn, "
        SELECT
            ps.anQId,
            ps.acIdent,
            ps.anNo,
            ps.anVariant,
            ps.anVariantSubLvl,
            ps.acIdentChild,
            ps.acDescr,
            ps.acOperationType,
            ps.acDelayType,
            ps.acUM,
            ps.anGrossQty,
            ps.anNetQty,
            ps.anQty1,
            ps.anBatch,
            ps.acQtyFormula,
            ps.anQtyBase,
            ps.acFieldSE,
            {$setPrStNote},
            ps.adTimeIns,
            ps.adTimeChg,
            ps.anUserIns,
            ps.anUserChg
        FROM [{$schema}].[tHF_SetPrSt] ps
        WHERE LTRIM(RTRIM(ps.acIdent)) = LTRIM(RTRIM(?))
        ORDER BY ps.anNo, ps.anQId
    ", [$productCode]);
}

$woItemRows = [];
if ($workOrderKey !== '' && phptest28_table_exists($conn, $schema, 'tHF_WOExItem')) {
    $woItemRows = phptest28_fetch_all($conn, "
        SELECT
            wi.anQId,
            wi.acKey,
            wi.anNo,
            wi.anVariant,
            wi.anVariantSubLvl,
            wi.acIdent,
            wi.acDescr,
            wi.acOperationType,
            wi.acDelayType,
            wi.acUM,
            wi.anPlanQty,
            wi.anQty,
            wi.anQty1,
            wi.anBatch,
            wi.acQtyFormula,
            wi.anQtyBase,
            wi.acFieldSE,
            {$woItemNote},
            wi.acIssueFinished,
            wi.acTaskState,
            wi.acOrigin,
            wi.adTimeIns,
            wi.adTimeChg,
            wi.anUserIns,
            wi.anUserChg
        FROM [{$schema}].[tHF_WOExItem] wi
        WHERE wi.acKey = ?
        ORDER BY wi.anNo, wi.anQId
    ", [$workOrderKey]);
}

$comparisonRows = [];
if ($workOrderKey !== '' && $productCode !== '') {
    $comparisonRows = phptest28_fetch_all($conn, "
        WITH product_lines AS (
            SELECT *
            FROM [{$schema}].[tHF_SetPrSt]
            WHERE LTRIM(RTRIM(acIdent)) = LTRIM(RTRIM(?))
        ),
        rn_lines AS (
            SELECT *
            FROM [{$schema}].[tHF_WOExItem]
            WHERE acKey = ?
        )
        SELECT
            ps.anNo AS line,
            ps.acIdentChild AS product_component,
            wi.acIdent AS work_order_component,
            CASE
                WHEN wi.anNo IS NULL THEN 'Product sastavnica line missing from RN'
                ELSE 'match'
            END AS match_status,
            ps.anGrossQty AS product_anGrossQty,
            wi.anPlanQty AS rn_item_anPlanQty,
            w.anPlanQty AS rn_header_anPlanQty,
            CAST(ISNULL(wi.anPlanQty, 0) * ISNULL(w.anPlanQty, 0) AS float) AS rn_computed_slash_rn,
            ps.acOperationType AS product_type,
            wi.acOperationType AS rn_type,
            ps.anQId AS product_line_qid,
            wi.anQId AS rn_line_qid
        FROM product_lines ps
        CROSS JOIN [{$schema}].[tHF_WOEx] w
        LEFT JOIN rn_lines wi
            ON wi.anNo = ps.anNo
           AND LTRIM(RTRIM(wi.acIdent)) = LTRIM(RTRIM(ps.acIdentChild))
        WHERE w.acKey = ?

        UNION ALL

        SELECT
            wi.anNo AS line,
            ps.acIdentChild AS product_component,
            wi.acIdent AS work_order_component,
            CASE
                WHEN ps.anNo IS NULL THEN 'RN line not found in product sastavnica by line/component'
                ELSE 'line number collision/different component'
            END AS match_status,
            ps.anGrossQty AS product_anGrossQty,
            wi.anPlanQty AS rn_item_anPlanQty,
            w.anPlanQty AS rn_header_anPlanQty,
            CAST(ISNULL(wi.anPlanQty, 0) * ISNULL(w.anPlanQty, 0) AS float) AS rn_computed_slash_rn,
            ps.acOperationType AS product_type,
            wi.acOperationType AS rn_type,
            ps.anQId AS product_line_qid,
            wi.anQId AS rn_line_qid
        FROM rn_lines wi
        CROSS JOIN [{$schema}].[tHF_WOEx] w
        LEFT JOIN product_lines ps
            ON ps.anNo = wi.anNo
           AND LTRIM(RTRIM(ps.acIdentChild)) = LTRIM(RTRIM(wi.acIdent))
        WHERE w.acKey = ?
          AND ps.anQId IS NULL
        ORDER BY line, product_line_qid, rn_line_qid
    ", [$productCode, $workOrderKey, $workOrderKey, $workOrderKey]);
}

$issueQuantityRows = [];
if ($workOrderKey !== '' && phptest28_table_exists($conn, $schema, 'tHF_WOExItem')) {
    $issueQuantityRows = phptest28_fetch_all($conn, "
        SELECT
            wi.anNo AS line,
            wi.acIdent AS component,
            wi.acDescr AS description,
            wi.acOperationType AS item_type,
            wi.acUM AS unit,
            wi.anQty1 AS prep_material_kolicina_anQty1,
            wi.anPlanQty AS rn_item_anPlanQty,
            wi.anQty AS issued_qty_anIQty,
            w.anPlanQty AS rn_header_anPlanQty,
            {$woQtySeriesExpr} AS rn_header_anQtySeries,
            {$issueQtyExpr} AS izdavanje_kolicina_calc_anCQty,
            {$issueQtyExpr} - ISNULL(wi.anQty, 0) AS izdavanje_remaining_calc_anPQty,
            CASE
                WHEN ABS(ISNULL(wi.anQty1, 0)) > 0.000001 AND ABS({$issueQtyExpr}) <= 0.000001
                    THEN 'prep/material quantity exists in anQty1, but Izdavanje is zero because anPlanQty is zero'
                WHEN ABS(ISNULL(wi.anQty1, 0) - ISNULL(wi.anPlanQty, 0)) > 0.000001
                    THEN 'anQty1 and anPlanQty differ'
                ELSE 'ok'
            END AS diagnosis
        FROM [{$schema}].[tHF_WOExItem] wi
        INNER JOIN [{$schema}].[tHF_WOEx] w
            ON w.acKey = wi.acKey
        WHERE wi.acKey = ?
        ORDER BY wi.anNo, wi.anQId
    ", [$workOrderKey]);
}

$resourceRows = [];
if ($workOrderKey !== '' && phptest28_table_exists($conn, $schema, 'tHF_WOExItemResources')) {
    $resourceRows = phptest28_fetch_all($conn, "
        SELECT
            r.anQId AS resource_anQId,
            r.anWOExItemQId,
            wi.anNo AS item_line,
            wi.acIdent AS item_ident,
            r.acResursID,
            r.acResType,
            r.anPlanQty,
            r.anQty,
            r.anQty1,
            r.anQty2,
            r.anExecutionPerc,
            r.acQtyFormula,
            r.adTimeIns,
            r.adTimeChg,
            r.anUserIns,
            r.anUserChg
        FROM [{$schema}].[tHF_WOExItemResources] r
        INNER JOIN [{$schema}].[tHF_WOExItem] wi
            ON wi.anQId = r.anWOExItemQId
        WHERE wi.acKey = ?
        ORDER BY wi.anNo, r.anQId
    ", [$workOrderKey]);
}

$linkRows = [];
if ($workOrderKey !== '' && phptest28_table_exists($conn, $schema, 'tHF_LinkWOExOrderItem')) {
    $linkRows = phptest28_fetch_all($conn, "
        SELECT *
        FROM [{$schema}].[tHF_LinkWOExOrderItem]
        WHERE acKey = ?
        ORDER BY anNo, anQId
    ", [$workOrderKey]);
}

try {
    if (PHP_SAPI !== 'cli') {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Sastavnica schema inspector</title>';
        echo '<style>
            body{font:14px/1.45 Arial,sans-serif;margin:24px;background:#f5f7fb;color:#172033}
            h1{margin:0 0 8px;font-size:26px}
            h2{margin-top:28px}
            h3{margin:20px 0 8px}
            .intro,.note{color:#536070}
            form{display:flex;gap:10px;flex-wrap:wrap;align-items:end;background:#fff;border:1px solid #d8e0ea;border-radius:8px;padding:14px;margin:16px 0}
            label{font-weight:700}
            input{display:block;margin-top:4px;padding:8px 9px;border:1px solid #b9c4d3;border-radius:5px;min-width:190px}
            button{padding:9px 14px;border:0;border-radius:5px;background:#1d4ed8;color:#fff;font-weight:700}
            .table-wrap{overflow:auto;background:#fff;border:1px solid #d8e0ea;border-radius:8px;margin-bottom:16px}
            table{border-collapse:collapse;min-width:100%;font-size:13px}
            th,td{padding:7px 9px;border-right:1px solid #edf1f5;border-bottom:1px solid #e5eaf1;text-align:left;vertical-align:top;white-space:nowrap}
            th{position:sticky;top:0;background:#edf2f8;color:#445064}
            td.wide{white-space:pre-wrap;min-width:260px;max-width:620px;word-break:break-word}
            code{background:#e9eef5;border-radius:4px;padding:2px 5px}
        </style></head><body>';
    }

    phptest28_render_heading('Sastavnica schema inspector', 1);

    if (PHP_SAPI !== 'cli') {
        echo '<p class="intro">Read-only diagnostic for product sastavnica tables and the work-order tables Pantheon uses after a BOM is copied to an RN.</p>';
        echo '<form method="get">';
        echo '<label>RN<input name="rn" value="' . phptest28_h($rnInput) . '" placeholder="26-6000-003534"></label>';
        echo '<label>Product<input name="product" value="' . phptest28_h($productInput) . '" placeholder="6242488"></label>';
        echo '<label>Schema<input name="schema" value="' . phptest28_h($schema) . '"></label>';
        echo '<button type="submit">Inspect</button>';
        echo '</form>';
    }

    phptest28_render_table('Resolved sample', $summaryRows);
    phptest28_render_table('Known relationships', phptest28_relationship_rows($schema));
    phptest28_render_table('All relevant table columns', $columnRows);
    phptest28_render_table('Selected RN header: tHF_WOEx', $headerRows);
    phptest28_render_table('Selected product sastavnica rows: tHF_SetPrSt', $productRows);
    phptest28_render_table('Selected RN item rows: tHF_WOExItem', $woItemRows);
    phptest28_render_table('Product sastavnica vs RN item match by line/component', $comparisonRows);
    phptest28_render_table('Pantheon Izdavanje quantity calculation from RN item rows', $issueQuantityRows);
    phptest28_render_table('Selected RN item resource rows: tHF_WOExItemResources', $resourceRows);
    phptest28_render_table('Selected RN order link rows: tHF_LinkWOExOrderItem', $linkRows);

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }

    sqlsrv_close($conn);
} catch (Throwable $exception) {
    if (is_resource($conn)) {
        sqlsrv_close($conn);
    }

    phptest28_fail($exception);
}
