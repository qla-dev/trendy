<?php

/*
 * test9.php
 * Shows RN-linked material issue rows with anRTPrice and related price fields from the Pantheon issue context.
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest9_bool_param(string $value): bool
{
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'da'], true);
}

function phptest9_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest9_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN anRTPrice test</title></head><body>';
    echo '<pre>' . phptest9_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest9_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest9_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest9_format_number($value, int $scale = 4): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest9_format_value($value): string
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
        return phptest9_format_number($value);
    }

    return trim((string) $value);
}

function phptest9_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest9_h($title) . '</' . $tag . '>';
}

function phptest9_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest9_h($class) . '">' . phptest9_h($text) . '</div>';
}

function phptest9_render_table(string $title, array $rows, string $toolbarHtml = ''): void
{
    phptest9_render_heading($title, 3);

    if (PHP_SAPI !== 'cli' && $toolbarHtml !== '') {
        echo $toolbarHtml;
    }

    if (empty($rows)) {
        phptest9_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 180) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest9_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest9_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest9_h(phptest9_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest9_build_query(array $params): string
{
    return http_build_query(array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    }));
}

function phptest9_like_clause(string $expression, string $value, array &$params): string
{
    $params[] = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value) . '%';

    return $expression . ' LIKE ?';
}

$search = trim((string) ($_GET['search'] ?? ''));
$document = trim((string) ($_GET['document'] ?? ''));
$rn = trim((string) ($_GET['rn'] ?? ''));
$material = trim((string) ($_GET['material'] ?? ''));
$name = trim((string) ($_GET['name'] ?? ''));
$docType = trim((string) ($_GET['doc_type'] ?? '6400'));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$sort = strtolower(trim((string) ($_GET['sort'] ?? 'doc_date')));
$dir = strtolower(trim((string) ($_GET['dir'] ?? 'desc')));
$limit = max(1, min(1000, (int) ($_GET['limit'] ?? 200)));
$all = phptest9_bool_param((string) ($_GET['all'] ?? '0'));
$onlyNonZero = phptest9_bool_param((string) ($_GET['only_nonzero'] ?? '0'));
$onlyZero = phptest9_bool_param((string) ($_GET['only_zero'] ?? '0'));

if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = 'desc';
}

if ($onlyNonZero && $onlyZero) {
    $onlyZero = false;
}

$dateTimeExpr = 'CASE WHEN m.adTimeIns IS NOT NULL THEN m.adTimeIns ELSE CAST(m.adDate AS datetime) END';
$trimDocumentKeyExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acKey), '')))";
$trimDocumentViewExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acKeyView), '')))";
$trimRnKeyExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), w.acKey), '')))";
$trimRnViewExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), w.acKeyView), '')))";
$trimOrderExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), w.acLnkKey), '')))";
$trimMaterialCodeExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acIdent), '')))";
$trimMaterialNameExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acName), '')))";
$anRTPriceExpr = 'CAST(ISNULL(mi.anRTPrice, 0) as float)';
$anWOPriceExpr = 'CAST(ISNULL(mi.anWOPrice, 0) as float)';
$anPriceExpr = 'CAST(ISNULL(mi.anPrice, 0) as float)';
$anStockPriceExpr = 'CAST(ISNULL(mi.anStockPrice, 0) as float)';
$anPriceCurrencyExpr = 'CAST(ISNULL(mi.anPriceCurrency, 0) as float)';
$rtValueExpr = 'ROUND(CAST(ISNULL(mi.anQty, 0) as float) * ' . $anRTPriceExpr . ', 4)';
$woValueExpr = 'ROUND(CAST(ISNULL(mi.anQty, 0) as float) * ' . $anWOPriceExpr . ', 4)';
$qtyExpr = 'CAST(ISNULL(mi.anQty, 0) as float)';
$docTypeExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acDocType), '')))";
$rtStatusExpr = "CASE WHEN ABS({$anRTPriceExpr}) <= 0.0001 THEN 'ZERO' ELSE 'NONZERO' END";

$sortMap = [
    'doc_date' => $dateTimeExpr,
    'doc' => $trimDocumentKeyExpr,
    'rn' => $trimRnKeyExpr,
    'order' => $trimOrderExpr,
    'line_no' => 'CAST(ISNULL(mi.anNo, 0) as int)',
    'rn_item_no' => 'CAST(ISNULL(wi.anNo, 0) as int)',
    'code' => $trimMaterialCodeExpr,
    'name' => $trimMaterialNameExpr,
    'qty' => $qtyExpr,
    'rt_price' => $anRTPriceExpr,
    'wo_price' => $anWOPriceExpr,
    'price' => $anPriceExpr,
    'stock_price' => $anStockPriceExpr,
    'price_currency' => $anPriceCurrencyExpr,
    'rt_value' => $rtValueExpr,
    'wo_value' => $woValueExpr,
];

$sortExpr = $sortMap[$sort] ?? $sortMap['doc_date'];

$joins = "
    FROM [{$defaultSchema}].[tHE_MoveItem] AS mi
    INNER JOIN [{$defaultSchema}].[tHE_Move] AS m
        ON m.anQId = mi.anMoveQId
    INNER JOIN [{$defaultSchema}].[tHF_LinkMoveItemWOExItem] AS link
        ON link.anMoveItemQId = mi.anQId
        AND LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), link.acType), ''))) = 'PP'
    INNER JOIN [{$defaultSchema}].[tHF_WOExItem] AS wi
        ON wi.anQId = link.anWOExItemQid
    INNER JOIN [{$defaultSchema}].[tHF_WOEx] AS w
        ON w.acKey = wi.acKey
";

$where = [];
$params = [];

if ($docType !== '') {
    $where[] = $docTypeExpr . ' = ?';
    $params[] = $docType;
}

if ($search !== '') {
    $searchClauses = [];
    $searchClauses[] = phptest9_like_clause($trimDocumentKeyExpr, $search, $params);
    $searchClauses[] = phptest9_like_clause($trimDocumentViewExpr, $search, $params);
    $searchClauses[] = phptest9_like_clause($trimRnKeyExpr, $search, $params);
    $searchClauses[] = phptest9_like_clause($trimRnViewExpr, $search, $params);
    $searchClauses[] = phptest9_like_clause($trimOrderExpr, $search, $params);
    $searchClauses[] = phptest9_like_clause($trimMaterialCodeExpr, $search, $params);
    $searchClauses[] = phptest9_like_clause($trimMaterialNameExpr, $search, $params);
    $where[] = '(' . implode(' OR ', $searchClauses) . ')';
}

if ($document !== '') {
    $where[] = '(' . phptest9_like_clause($trimDocumentKeyExpr, $document, $params) . ' OR ' . phptest9_like_clause($trimDocumentViewExpr, $document, $params) . ')';
}

if ($rn !== '') {
    $where[] = '(' . phptest9_like_clause($trimRnKeyExpr, $rn, $params) . ' OR ' . phptest9_like_clause($trimRnViewExpr, $rn, $params) . ')';
}

if ($material !== '') {
    $where[] = phptest9_like_clause($trimMaterialCodeExpr, $material, $params);
}

if ($name !== '') {
    $where[] = phptest9_like_clause($trimMaterialNameExpr, $name, $params);
}

if ($dateFrom !== '') {
    $where[] = 'CAST(' . $dateTimeExpr . ' AS date) >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'CAST(' . $dateTimeExpr . ' AS date) <= ?';
    $params[] = $dateTo;
}

if ($onlyNonZero) {
    $where[] = 'ABS(' . $anRTPriceExpr . ') > 0.0001';
}

if ($onlyZero) {
    $where[] = 'ABS(' . $anRTPriceExpr . ') <= 0.0001';
}

$whereSql = empty($where) ? '' : 'WHERE ' . implode("\n    AND ", $where);

$summarySql = "
    SELECT
        COUNT(*) AS total_rows,
        SUM(CASE WHEN ABS({$anRTPriceExpr}) <= 0.0001 THEN 1 ELSE 0 END) AS zero_rtprice_rows,
        SUM(CASE WHEN ABS({$anRTPriceExpr}) > 0.0001 THEN 1 ELSE 0 END) AS nonzero_rtprice_rows,
        COUNT(DISTINCT {$trimDocumentKeyExpr}) AS documents_count,
        COUNT(DISTINCT {$trimRnKeyExpr}) AS rn_count
    {$joins}
    {$whereSql}
";

$rowsSql = "
    SELECT " . ($all ? '' : 'TOP ' . $limit . ' ') . "
        {$trimDocumentKeyExpr} AS document_key,
        COALESCE(NULLIF({$trimDocumentViewExpr}, ''), {$trimDocumentKeyExpr}) AS document_number,
        {$docTypeExpr} AS document_type,
        CONVERT(varchar(19), {$dateTimeExpr}, 120) AS document_date,
        {$trimRnKeyExpr} AS rn_key,
        COALESCE(NULLIF({$trimRnViewExpr}, ''), {$trimRnKeyExpr}) AS rn_number,
        {$trimOrderExpr} AS order_number,
        CAST(ISNULL(mi.anNo, 0) as int) AS line_no,
        CAST(ISNULL(wi.anNo, 0) as int) AS rn_item_no,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acIssuer), ''))) AS warehouse,
        LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acDept), ''))) AS department,
        {$trimMaterialCodeExpr} AS material_code,
        {$trimMaterialNameExpr} AS material_name,
        UPPER(LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acUM), '')))) AS unit,
        {$qtyExpr} AS quantity,
        CAST({$anRTPriceExpr} as float) AS anRTPrice,
        CAST({$anWOPriceExpr} as float) AS anWOPrice,
        CAST({$anPriceExpr} as float) AS anPrice,
        CAST({$anStockPriceExpr} as float) AS anStockPrice,
        CAST({$anPriceCurrencyExpr} as float) AS anPriceCurrency,
        CAST({$rtValueExpr} as float) AS anRTValue,
        CAST({$woValueExpr} as float) AS anWOValue,
        {$rtStatusExpr} AS rtprice_status,
        CONVERT(varchar(19), mi.adTimeChg, 120) AS move_item_changed_at
    {$joins}
    {$whereSql}
    ORDER BY {$sortExpr} {$dir}, {$trimDocumentKeyExpr} DESC, CAST(ISNULL(mi.anNo, 0) as int) ASC
";

$summaryRows = phptest9_fetch_all($conn, $summarySql, $params);
$dataRows = phptest9_fetch_all($conn, $rowsSql, $params);

$summaryRow = $summaryRows[0] ?? [];
$summaryTable = [[
    'rows' => (int) ($summaryRow['total_rows'] ?? 0),
    'zero_rtprice_rows' => (int) ($summaryRow['zero_rtprice_rows'] ?? 0),
    'nonzero_rtprice_rows' => (int) ($summaryRow['nonzero_rtprice_rows'] ?? 0),
    'documents_count' => (int) ($summaryRow['documents_count'] ?? 0),
    'rn_count' => (int) ($summaryRow['rn_count'] ?? 0),
    'search' => $search !== '' ? $search : '-',
    'document' => $document !== '' ? $document : '-',
    'rn' => $rn !== '' ? $rn : '-',
    'material' => $material !== '' ? $material : '-',
    'name' => $name !== '' ? $name : '-',
    'doc_type' => $docType !== '' ? $docType : '-',
    'date_from' => $dateFrom !== '' ? $dateFrom : '-',
    'date_to' => $dateTo !== '' ? $dateTo : '-',
    'only_nonzero' => $onlyNonZero ? 'YES' : 'NO',
    'only_zero' => $onlyZero ? 'YES' : 'NO',
    'sort' => $sort,
    'dir' => strtoupper($dir),
    'limit' => $all ? 'ALL' : $limit,
]];

$links = [
    'all' => phptest9_build_query([
        'search' => $search,
        'document' => $document,
        'rn' => $rn,
        'material' => $material,
        'name' => $name,
        'doc_type' => $docType,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort' => $sort,
        'dir' => $dir,
        'limit' => $limit,
        'all' => '1',
        'only_nonzero' => $onlyNonZero ? '1' : '0',
        'only_zero' => $onlyZero ? '1' : '0',
    ]),
    'only_nonzero' => phptest9_build_query([
        'search' => $search,
        'document' => $document,
        'rn' => $rn,
        'material' => $material,
        'name' => $name,
        'doc_type' => $docType,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort' => $sort,
        'dir' => $dir,
        'limit' => $limit,
        'all' => $all ? '1' : '0',
        'only_nonzero' => '1',
    ]),
    'only_zero' => phptest9_build_query([
        'search' => $search,
        'document' => $document,
        'rn' => $rn,
        'material' => $material,
        'name' => $name,
        'doc_type' => $docType,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort' => $sort,
        'dir' => $dir,
        'limit' => $limit,
        'all' => $all ? '1' : '0',
        'only_zero' => '1',
    ]),
    'mixed' => phptest9_build_query([
        'search' => $search,
        'document' => $document,
        'rn' => $rn,
        'material' => $material,
        'name' => $name,
        'doc_type' => $docType,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort' => $sort,
        'dir' => $dir,
        'limit' => $limit,
        'all' => $all ? '1' : '0',
    ]),
];

$toolbarHtml = '';

if (PHP_SAPI !== 'cli') {
    $toolbarHtml = '
        <form method="get" class="toolbar">
            <label>Search <input type="text" name="search" value="' . phptest9_h($search) . '" placeholder="dokument, RN, sifra, naziv"></label>
            <label>Dokument <input type="text" name="document" value="' . phptest9_h($document) . '" placeholder="26-6400-..."></label>
            <label>RN <input type="text" name="rn" value="' . phptest9_h($rn) . '" placeholder="26-6000-..."></label>
            <label>Sifra <input type="text" name="material" value="' . phptest9_h($material) . '" placeholder="PLOPLAZMA"></label>
            <label>Naziv <input type="text" name="name" value="' . phptest9_h($name) . '" placeholder="Naziv materijala"></label>
            <label>Doc type <input type="text" name="doc_type" value="' . phptest9_h($docType) . '" placeholder="6400"></label>
            <label>Od <input type="date" name="date_from" value="' . phptest9_h($dateFrom) . '"></label>
            <label>Do <input type="date" name="date_to" value="' . phptest9_h($dateTo) . '"></label>
            <label>Limit <input type="number" name="limit" min="1" max="1000" value="' . phptest9_h((string) $limit) . '"></label>
            <label>Sort
                <select name="sort">
                    <option value="doc_date"' . ($sort === 'doc_date' ? ' selected' : '') . '>Datum</option>
                    <option value="doc"' . ($sort === 'doc' ? ' selected' : '') . '>Dokument</option>
                    <option value="rn"' . ($sort === 'rn' ? ' selected' : '') . '>RN</option>
                    <option value="code"' . ($sort === 'code' ? ' selected' : '') . '>Sifra</option>
                    <option value="name"' . ($sort === 'name' ? ' selected' : '') . '>Naziv</option>
                    <option value="qty"' . ($sort === 'qty' ? ' selected' : '') . '>Kolicina</option>
                    <option value="rt_price"' . ($sort === 'rt_price' ? ' selected' : '') . '>anRTPrice</option>
                    <option value="wo_price"' . ($sort === 'wo_price' ? ' selected' : '') . '>anWOPrice</option>
                    <option value="price"' . ($sort === 'price' ? ' selected' : '') . '>anPrice</option>
                    <option value="rt_value"' . ($sort === 'rt_value' ? ' selected' : '') . '>anRTValue</option>
                    <option value="wo_value"' . ($sort === 'wo_value' ? ' selected' : '') . '>anWOValue</option>
                </select>
            </label>
            <label>Dir
                <select name="dir">
                    <option value="desc"' . ($dir === 'desc' ? ' selected' : '') . '>DESC</option>
                    <option value="asc"' . ($dir === 'asc' ? ' selected' : '') . '>ASC</option>
                </select>
            </label>
            <label><input type="checkbox" name="only_nonzero" value="1"' . ($onlyNonZero ? ' checked' : '') . '> Samo anRTPrice != 0</label>
            <label><input type="checkbox" name="only_zero" value="1"' . ($onlyZero ? ' checked' : '') . '> Samo anRTPrice = 0</label>
            <label><input type="checkbox" name="all" value="1"' . ($all ? ' checked' : '') . '> Prikazi sve</label>
            <button type="submit">Trazi</button>
            <a href="?' . phptest9_h($links['only_nonzero']) . '">Samo anRTPrice != 0</a>
            <a href="?' . phptest9_h($links['only_zero']) . '">Samo anRTPrice = 0</a>
            <a href="?' . phptest9_h($links['mixed']) . '">Sve sa filterima</a>
            <a href="?' . phptest9_h($links['all']) . '">Sve bez limita</a>
        </form>
    ';
}

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN anRTPrice test</title>';
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
        table{border-collapse:collapse;min-width:1560px;background:#fff}
        th,td{border:1px solid #d8d8d8;padding:4px 6px;vertical-align:top;white-space:nowrap}
        th{background:#f1f3f5;text-align:left}
    </style></head><body>';
}

phptest9_render_heading('RN anRTPrice test', 1);
phptest9_render_note(
    'Test prati isti Pantheon kontekst kao Izdavanja > Materijali: tHE_MoveItem + tHF_LinkMoveItemWOExItem + tHF_WOExItem + tHF_WOEx. Prikazuje RN, dokument i sva bitna price polja oko anRTPrice.',
    'note'
);
phptest9_render_note(
    'Parametri: search, document, rn, material, name, doc_type, date_from, date_to, limit, all=1, only_nonzero=1, only_zero=1, sort=doc_date|doc|rn|code|name|qty|rt_price|wo_price|price|rt_value|wo_value, dir=asc|desc',
    'meta'
);

phptest9_render_table('Summary', $summaryTable);
phptest9_render_table('RN / document rows with anRTPrice', $dataRows, $toolbarHtml);

sqlsrv_close($conn);

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}
