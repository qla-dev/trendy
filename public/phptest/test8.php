<?php

/*
 * test8.php
 * Checks released-material RN price rows by comparing stored RN unit price with
 * the document line unit price, and shows the Pantheon linked-doc value formula:
 * quantity * anWOPrice.
 * Checks released-material RN price rows by comparing stored RN unit price with buy price from the catalog
 * and shows the Pantheon linked-doc value formula: quantity * anWOPrice.
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest8_bool_param(string $value): bool
{
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'da'], true);
}

function phptest8_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest8_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Released material RN price/value test</title></head><body>';
    echo '<pre>' . phptest8_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest8_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest8_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest8_format_value($value): string
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
        return phptest8_format_number($value);
    }

    return trim((string) $value);
}

function phptest8_format_number($value, int $scale = 4): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest8_build_query(array $params): string
{
    return http_build_query(array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    }));
}

function phptest8_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest8_h($title) . '</' . $tag . '>';
}

function phptest8_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest8_h($class) . '">' . phptest8_h($text) . '</div>';
}

function phptest8_render_table(string $title, array $rows, string $toolbarHtml = ''): void
{
    phptest8_render_heading($title, 3);

    if (PHP_SAPI !== 'cli' && $toolbarHtml !== '') {
        echo $toolbarHtml;
    }

    if (empty($rows)) {
        phptest8_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 160) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest8_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest8_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest8_h(phptest8_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest8_like_clause(string $expression, string $value, array &$params): string
{
    $params[] = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value) . '%';

    return $expression . ' LIKE ?';
}

$search = trim((string) ($_GET['search'] ?? ''));
$document = trim((string) ($_GET['document'] ?? ''));
$rn = trim((string) ($_GET['rn'] ?? ''));
$material = trim((string) ($_GET['material'] ?? ''));
$name = trim((string) ($_GET['name'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$sort = strtolower(trim((string) ($_GET['sort'] ?? 'doc_date')));
$dir = strtolower(trim((string) ($_GET['dir'] ?? 'desc')));
$limit = max(1, min(1000, (int) ($_GET['limit'] ?? 200)));
$all = phptest8_bool_param((string) ($_GET['all'] ?? '0'));
$onlyDiff = !array_key_exists('only_diff', $_GET) || phptest8_bool_param((string) ($_GET['only_diff'] ?? '1'));

if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = 'desc';
}

$dateTimeExpr = 'CASE WHEN m.adTimeIns IS NOT NULL THEN m.adTimeIns ELSE CAST(m.adDate AS datetime) END';
$trimMoveKeyExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acKey), '')))";
$trimMoveKeyViewExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acKeyView), '')))";
$trimWorkOrderExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), move_wo.acLnkKey), '')))";
$trimWorkOrderViewExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), wo.acKeyView), '')))";
$trimOrderExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), wo.acLnkKey), '')))";
$trimMaterialCodeExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acIdent), '')))";
$trimMaterialNameExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acName), '')))";
$catalogBuyPriceExpr = 'COALESCE(CAST(catalog_qid.anBuyPrice as float), CAST(catalog_code.anBuyPrice as float), 0)';
$expectedRnPriceExpr = $catalogBuyPriceExpr;
$storedRnPriceExpr = 'CAST(ISNULL(mi.anWOPrice, 0) as float)';
$priceDiffExpr = 'ROUND(' . $storedRnPriceExpr . ' - ' . $expectedRnPriceExpr . ', 4)';
$storedViewValueExpr = 'ROUND(CAST(ISNULL(mi.anQty, 0) as float) * ' . $storedRnPriceExpr . ', 4)';
$expectedViewValueExpr = 'ROUND(CAST(ISNULL(mi.anQty, 0) as float) * ' . $expectedRnPriceExpr . ', 4)';
$valueDiffExpr = 'ROUND(' . $storedViewValueExpr . ' - ' . $expectedViewValueExpr . ', 4)';
$catalogMatchExpr = 'CASE WHEN catalog_qid.anQId IS NULL AND catalog_code.anQId IS NULL THEN 0 ELSE 1 END';
$workOrderDisplayExpr = "COALESCE(NULLIF({$trimWorkOrderExpr}, ''), NULLIF({$trimWorkOrderViewExpr}, ''), NULLIF(LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acDoc2), ''))), ''), '')";

$sortMap = [
    'doc_date' => $dateTimeExpr,
    'doc' => $trimMoveKeyExpr,
    'rn' => $workOrderDisplayExpr,
    'order' => $trimOrderExpr,
    'code' => $trimMaterialCodeExpr,
    'name' => $trimMaterialNameExpr,
    'qty' => 'CAST(ISNULL(mi.anQty, 0) as float)',
    'buy_price' => $catalogBuyPriceExpr,
    'stored_rn_price' => $storedRnPriceExpr,
    'expected_rn_price' => $expectedRnPriceExpr,
    'diff' => $priceDiffExpr,
    'stored_view_value' => $storedViewValueExpr,
    'expected_view_value' => $expectedViewValueExpr,
    'value_diff' => $valueDiffExpr,
];

$sortExpr = $sortMap[$sort] ?? $sortMap['doc_date'];

$joins = "
    FROM [{$defaultSchema}].[tHE_Move] AS m
    INNER JOIN [{$defaultSchema}].[tHE_MoveItem] AS mi
        ON mi.acKey = m.acKey
    LEFT JOIN [{$defaultSchema}].[tHF_LinkMoveWOEx] AS move_wo
        ON move_wo.acKey = m.acKey
    LEFT JOIN [{$defaultSchema}].[tHF_WOEx] AS wo
        ON wo.acKey = move_wo.acLnkKey
    LEFT JOIN [{$defaultSchema}].[tHE_SetItem] AS catalog_qid
        ON catalog_qid.anQId = mi.anIdentQId
    LEFT JOIN [{$defaultSchema}].[tHE_SetItem] AS catalog_code
        ON catalog_qid.anQId IS NULL
        AND LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), catalog_code.acIdent), ''))) = {$trimMaterialCodeExpr}
";

$where = ["LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acDocType), ''))) = '6400'"];
$params = [];

if ($search !== '') {
    $searchClauses = [];
    $searchClauses[] = phptest8_like_clause($trimMoveKeyExpr, $search, $params);
    $searchClauses[] = phptest8_like_clause($trimMoveKeyViewExpr, $search, $params);
    $searchClauses[] = phptest8_like_clause($trimWorkOrderExpr, $search, $params);
    $searchClauses[] = phptest8_like_clause($trimWorkOrderViewExpr, $search, $params);
    $searchClauses[] = phptest8_like_clause($trimOrderExpr, $search, $params);
    $searchClauses[] = phptest8_like_clause($trimMaterialCodeExpr, $search, $params);
    $searchClauses[] = phptest8_like_clause($trimMaterialNameExpr, $search, $params);
    $where[] = '(' . implode(' OR ', $searchClauses) . ')';
}

if ($document !== '') {
    $where[] = '(' . phptest8_like_clause($trimMoveKeyExpr, $document, $params) . ' OR ' . phptest8_like_clause($trimMoveKeyViewExpr, $document, $params) . ')';
}

if ($rn !== '') {
    $where[] = '(' . phptest8_like_clause($trimWorkOrderExpr, $rn, $params) . ' OR ' . phptest8_like_clause($trimWorkOrderViewExpr, $rn, $params) . ' OR ' . phptest8_like_clause("LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acDoc2), '')))", $rn, $params) . ')';
}

if ($material !== '') {
    $where[] = phptest8_like_clause($trimMaterialCodeExpr, $material, $params);
}

if ($name !== '') {
    $where[] = phptest8_like_clause($trimMaterialNameExpr, $name, $params);
}

if ($dateFrom !== '') {
    $where[] = 'CAST(' . $dateTimeExpr . ' AS date) >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'CAST(' . $dateTimeExpr . ' AS date) <= ?';
    $params[] = $dateTo;
}

if ($onlyDiff) {
    $where[] = '(ABS(' . $priceDiffExpr . ') > 0.0001 OR ABS(' . $valueDiffExpr . ') > 0.0001)';
}

$whereSql = 'WHERE ' . implode("\n    AND ", $where);

$summarySql = "
    SELECT
        COUNT(*) AS total_rows,
        SUM(CASE WHEN ABS({$priceDiffExpr}) > 0.0001 THEN 1 ELSE 0 END) AS diff_rows,
        SUM(CASE WHEN ABS({$priceDiffExpr}) <= 0.0001 THEN 1 ELSE 0 END) AS match_rows,
        SUM(CASE WHEN ABS({$valueDiffExpr}) > 0.0001 THEN 1 ELSE 0 END) AS value_diff_rows,
        SUM(CASE WHEN {$catalogMatchExpr} = 0 THEN 1 ELSE 0 END) AS no_catalog_rows,
        SUM(CASE WHEN {$storedRnPriceExpr} = 0 THEN 1 ELSE 0 END) AS zero_rn_price_rows
    {$joins}
    {$whereSql}
";

$rowsSql = "
    SELECT " . ($all ? '' : 'TOP ' . $limit . ' ') . "
        {$trimMoveKeyExpr} AS document_key,
        COALESCE(NULLIF({$trimMoveKeyViewExpr}, ''), {$trimMoveKeyExpr}) AS document_number,
        CONVERT(varchar(19), {$dateTimeExpr}, 120) AS document_date,
        {$workOrderDisplayExpr} AS rn_number,
        {$trimOrderExpr} AS order_number,
        CAST(ISNULL(mi.anNo, 0) as int) AS line_no,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acIssuer), ''))) AS warehouse,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acDept), ''))) AS department,
        {$trimMaterialCodeExpr} AS material_code,
        {$trimMaterialNameExpr} AS material_name,
        UPPER(LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acUM), '')))) AS unit,
        CAST(ISNULL(mi.anQty, 0) as float) AS quantity,
        CAST({$catalogBuyPriceExpr} as float) AS buy_price,
        CAST({$storedRnPriceExpr} as float) AS stored_rn_price,
        CAST({$expectedRnPriceExpr} as float) AS expected_rn_price,
        CAST({$priceDiffExpr} as float) AS price_diff,
        CAST({$storedViewValueExpr} as float) AS stored_view_value,
        CAST({$expectedViewValueExpr} as float) AS expected_view_value,
        CAST({$valueDiffExpr} as float) AS value_diff,
        CASE
            WHEN {$catalogMatchExpr} = 0 THEN 'NO_CATALOG'
            WHEN ABS({$priceDiffExpr}) <= 0.0001 AND ABS({$valueDiffExpr}) <= 0.0001 THEN 'MATCH'
            ELSE 'DIFF'
        END AS status,
        CONVERT(varchar(19), COALESCE(catalog_qid.adTimeChg, catalog_code.adTimeChg), 120) AS item_changed_at
    {$joins}
    {$whereSql}
    ORDER BY {$sortExpr} {$dir}, {$trimMoveKeyExpr} DESC, CAST(ISNULL(mi.anNo, 0) as int) ASC
";

$summaryRows = phptest8_fetch_all($conn, $summarySql, $params);
$dataRows = phptest8_fetch_all($conn, $rowsSql, $params);

$summaryRow = $summaryRows[0] ?? [];
$summaryTable = [[
    'rows' => (int) ($summaryRow['total_rows'] ?? 0),
    'diff_rows' => (int) ($summaryRow['diff_rows'] ?? 0),
    'match_rows' => (int) ($summaryRow['match_rows'] ?? 0),
    'value_diff_rows' => (int) ($summaryRow['value_diff_rows'] ?? 0),
    'no_catalog_rows' => (int) ($summaryRow['no_catalog_rows'] ?? 0),
    'zero_rn_price_rows' => (int) ($summaryRow['zero_rn_price_rows'] ?? 0),
    'search' => $search !== '' ? $search : '-',
    'document' => $document !== '' ? $document : '-',
    'rn' => $rn !== '' ? $rn : '-',
    'material' => $material !== '' ? $material : '-',
    'name' => $name !== '' ? $name : '-',
    'date_from' => $dateFrom !== '' ? $dateFrom : '-',
    'date_to' => $dateTo !== '' ? $dateTo : '-',
    'only_diff' => $onlyDiff ? 'YES' : 'NO',
    'sort' => $sort,
    'dir' => strtoupper($dir),
    'limit' => $all ? 'ALL' : $limit,
]];

$links = [
    'show_all' => phptest8_build_query([
        'search' => $search,
        'document' => $document,
        'rn' => $rn,
        'material' => $material,
        'name' => $name,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort' => $sort,
        'dir' => $dir,
        'limit' => $limit,
        'only_diff' => $onlyDiff ? '1' : '0',
        'all' => '1',
    ]),
    'show_differences' => phptest8_build_query([
        'search' => $search,
        'document' => $document,
        'rn' => $rn,
        'material' => $material,
        'name' => $name,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort' => $sort,
        'dir' => $dir,
        'limit' => $limit,
        'only_diff' => '1',
        'all' => $all ? '1' : '0',
    ]),
    'show_everything' => phptest8_build_query([
        'search' => $search,
        'document' => $document,
        'rn' => $rn,
        'material' => $material,
        'name' => $name,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort' => $sort,
        'dir' => $dir,
        'limit' => $limit,
        'only_diff' => '0',
        'all' => $all ? '1' : '0',
    ]),
];

$toolbarHtml = '';

if (PHP_SAPI !== 'cli') {
    $toolbarHtml = '
        <form method="get" class="toolbar">
            <label>Search <input type="text" name="search" value="' . phptest8_h($search) . '" placeholder="dokument, RN, sifra, naziv"></label>
            <label>Dokument <input type="text" name="document" value="' . phptest8_h($document) . '" placeholder="26-6400-..."></label>
            <label>RN <input type="text" name="rn" value="' . phptest8_h($rn) . '" placeholder="26-6000-..."></label>
            <label>Sifra <input type="text" name="material" value="' . phptest8_h($material) . '" placeholder="PLOPLAZMA"></label>
            <label>Naziv <input type="text" name="name" value="' . phptest8_h($name) . '" placeholder="Naziv materijala"></label>
            <label>Od <input type="date" name="date_from" value="' . phptest8_h($dateFrom) . '"></label>
            <label>Do <input type="date" name="date_to" value="' . phptest8_h($dateTo) . '"></label>
            <label>Limit <input type="number" name="limit" min="1" max="1000" value="' . phptest8_h((string) $limit) . '"></label>
            <label>Sort
                <select name="sort">
                    <option value="doc_date"' . ($sort === 'doc_date' ? ' selected' : '') . '>Datum</option>
                    <option value="doc"' . ($sort === 'doc' ? ' selected' : '') . '>Dokument</option>
                    <option value="rn"' . ($sort === 'rn' ? ' selected' : '') . '>RN</option>
                    <option value="code"' . ($sort === 'code' ? ' selected' : '') . '>Sifra</option>
                    <option value="name"' . ($sort === 'name' ? ' selected' : '') . '>Naziv</option>
                    <option value="qty"' . ($sort === 'qty' ? ' selected' : '') . '>Kolicina</option>
                    <option value="buy_price"' . ($sort === 'buy_price' ? ' selected' : '') . '>Buy price</option>
                    <option value="stored_rn_price"' . ($sort === 'stored_rn_price' ? ' selected' : '') . '>Upisana RN cijena</option>
                    <option value="expected_rn_price"' . ($sort === 'expected_rn_price' ? ' selected' : '') . '>Ocekivana RN cijena</option>
                    <option value="diff"' . ($sort === 'diff' ? ' selected' : '') . '>Razlika cijena</option>
                    <option value="stored_view_value"' . ($sort === 'stored_view_value' ? ' selected' : '') . '>Upisana vrijednost view</option>
                    <option value="expected_view_value"' . ($sort === 'expected_view_value' ? ' selected' : '') . '>Ocekivana vrijednost view</option>
                    <option value="value_diff"' . ($sort === 'value_diff' ? ' selected' : '') . '>Razlika vrijednosti</option>
                </select>
            </label>
            <label>Dir
                <select name="dir">
                    <option value="desc"' . ($dir === 'desc' ? ' selected' : '') . '>DESC</option>
                    <option value="asc"' . ($dir === 'asc' ? ' selected' : '') . '>ASC</option>
                </select>
            </label>
            <label><input type="checkbox" name="only_diff" value="1"' . ($onlyDiff ? ' checked' : '') . '> Samo razlike</label>
            <label><input type="checkbox" name="all" value="1"' . ($all ? ' checked' : '') . '> Prikazi sve</label>
            <button type="submit">Trazi</button>
            <a href="?' . phptest8_h($links['show_differences']) . '">Samo razlike</a>
            <a href="?' . phptest8_h($links['show_everything']) . '">Sve sa filterima</a>
            <a href="?' . phptest8_h($links['show_all']) . '">Sve bez limita</a>
        </form>
    ';
}

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Released material RN price/value test</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;font-size:14px;line-height:1.45;margin:20px;color:#222}
        h1,h2,h3{margin:16px 0 8px}
        .meta,.note{padding:10px 12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .note{background:#fff8df;border-color:#f0d67b}
        .toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:end;padding:12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .toolbar label{display:flex;flex-direction:column;gap:4px;font-size:12px}
        .toolbar input,.toolbar select{min-width:140px;padding:6px 8px;font-size:13px}
        .toolbar a,.toolbar button{padding:7px 10px;font-size:13px}
        .table-wrap{overflow-x:auto;margin:8px 0 22px}
        table{border-collapse:collapse;min-width:1320px;background:#fff}
        th,td{border:1px solid #d8d8d8;padding:4px 6px;vertical-align:top;white-space:nowrap}
        th{background:#f1f3f5;text-align:left}
    </style></head><body>';
}

phptest8_render_heading('Released material RN price/value test', 1);
phptest8_render_note(
    'Pantheon za povezane dokumente cita RN cijenu iz dbo.tHE_MoveItem.anWOPrice, a vrijednost izvodi kao Kolicina * anWOPrice kroz dbo.vHF_WOLnkDocIssMate i dbo.vHE_ViewDocWOExDet.',
    'note'
);
phptest8_render_note(
    'Parametri: search, document, rn, material, name, date_from, date_to, limit, all=1, only_diff=1, sort=doc_date|doc|rn|order|code|name|qty|buy_price|stored_rn_price|expected_rn_price|diff|stored_view_value|expected_view_value|value_diff, dir=asc|desc',
    'meta'
);

phptest8_render_table('Summary', $summaryTable);
phptest8_render_table('RN material price rows', $dataRows, $toolbarHtml);

sqlsrv_close($conn);

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}
