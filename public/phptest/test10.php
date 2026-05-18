<?php

/*
 * test10.php
 * Groups stock materials by material name and highlights names that exist on two or more different warehouses.
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest10_bool_param(string $value): bool
{
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'da'], true);
}

function phptest10_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest10_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Warehouse material-name duplicate test</title></head><body>';
    echo '<pre>' . phptest10_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest10_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest10_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest10_format_number($value, int $scale = 4): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest10_format_value($value): string
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
        return phptest10_format_number($value);
    }

    return trim((string) $value);
}

function phptest10_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest10_h($title) . '</' . $tag . '>';
}

function phptest10_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest10_h($class) . '">' . phptest10_h($text) . '</div>';
}

function phptest10_render_table(string $title, array $rows, string $toolbarHtml = ''): void
{
    phptest10_render_heading($title, 3);

    if (PHP_SAPI !== 'cli' && $toolbarHtml !== '') {
        echo $toolbarHtml;
    }

    if (empty($rows)) {
        phptest10_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 180) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest10_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest10_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest10_h(phptest10_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest10_build_query(array $params): string
{
    return http_build_query(array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    }));
}

function phptest10_like_clause(string $expression, string $value, array &$params): string
{
    $params[] = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value) . '%';

    return $expression . ' LIKE ?';
}

function phptest10_name_key(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

    if ($value === '') {
        return '';
    }

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($value, 'UTF-8')
        : strtoupper($value);
}

function phptest10_join_preview(array $values, int $limit = 5): string
{
    $values = array_values(array_filter(array_map(static function ($value) {
        return trim((string) $value);
    }, $values), static function ($value) {
        return $value !== '';
    }));

    sort($values, SORT_NATURAL | SORT_FLAG_CASE);
    $slice = array_slice($values, 0, $limit);
    $text = implode(', ', $slice);

    if (count($values) > $limit) {
        $text .= ' +' . (count($values) - $limit) . ' more';
    }

    return $text;
}

function phptest10_timestamp($value): int
{
    if ($value instanceof DateTimeInterface) {
        return $value->getTimestamp();
    }

    $text = trim((string) $value);
    if ($text === '') {
        return 0;
    }

    $time = strtotime($text);

    return $time === false ? 0 : $time;
}

$search = trim((string) ($_GET['search'] ?? ''));
$materialName = trim((string) ($_GET['name'] ?? $_GET['material_name'] ?? ''));
$warehouse = trim((string) ($_GET['warehouse'] ?? ''));
$set = trim((string) ($_GET['set'] ?? ''));
$sort = strtolower(trim((string) ($_GET['sort'] ?? 'warehouses')));
$dir = strtolower(trim((string) ($_GET['dir'] ?? 'desc')));
$limit = max(1, min(1000, (int) ($_GET['limit'] ?? 200)));
$all = phptest10_bool_param((string) ($_GET['all'] ?? '0'));
$duplicatesOnly = !array_key_exists('duplicates_only', $_GET) || phptest10_bool_param((string) ($_GET['duplicates_only'] ?? '1'));
$nonzeroOnly = phptest10_bool_param((string) ($_GET['nonzero_only'] ?? '0'));

if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = 'desc';
}

$where = [
    "LTRIM(RTRIM(ISNULL(i.acIdent, ''))) <> ''",
    "LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) <> ''",
];
$params = [];

if ($materialName !== '') {
    $where[] = '(
        i.acName LIKE ?
        OR i.acDescr LIKE ?
    )';
    $nameLike = '%' . $materialName . '%';
    array_push($params, $nameLike, $nameLike);
}

if ($search !== '') {
    $where[] = '(
        i.acIdent LIKE ?
        OR i.acName LIKE ?
        OR i.acDescr LIKE ?
        OR i.acCode LIKE ?
        OR s.acWarehouse LIKE ?
    )';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

if ($warehouse !== '') {
    $where[] = "LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) = ?";
    $params[] = $warehouse;
}

if ($set !== '') {
    $setFilters = array_values(array_filter(array_map('trim', explode(',', $set)), static function ($value) {
        return $value !== '';
    }));

    if (!empty($setFilters)) {
        $placeholders = implode(', ', array_fill(0, count($setFilters), '?'));
        $where[] = "LTRIM(RTRIM(ISNULL(i.acSetOfItem, ''))) IN ({$placeholders})";
        array_push($params, ...$setFilters);
    }
}

if ($nonzeroOnly) {
    $where[] = 'ABS(CAST(ISNULL(s.anStock, 0) AS float)) > 0.000001';
}

$sql = "
    SELECT
        LTRIM(RTRIM(ISNULL(i.acIdent, ''))) AS material_code,
        LTRIM(RTRIM(COALESCE(NULLIF(i.acName, ''), NULLIF(i.acDescr, ''), i.acIdent, ''))) AS material_name,
        LTRIM(RTRIM(ISNULL(i.acSetOfItem, ''))) AS material_set,
        UPPER(LTRIM(RTRIM(ISNULL(i.acUM, '')))) AS material_um,
        LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) AS warehouse,
        CAST(ISNULL(s.anStock, 0) AS float) AS stock_qty,
        CAST(ISNULL(s.anValue, 0) AS float) AS stock_value,
        CAST(
            CASE
                WHEN ABS(CAST(ISNULL(s.anStock, 0) AS float)) < 0.000001 THEN NULL
                ELSE CAST(ISNULL(s.anValue, 0) AS float) / NULLIF(CAST(ISNULL(s.anStock, 0) AS float), 0)
            END
            AS float
        ) AS avg_price,
        CAST(ISNULL(s.anLastPrice, 0) AS float) AS last_price,
        CAST(ISNULL(i.anBuyPrice, 0) AS float) AS buy_price,
        CAST(ISNULL(i.anQId, 0) AS bigint) AS item_qid,
        CAST(ISNULL(s.anQId, 0) AS bigint) AS stock_qid,
        i.adTimeChg AS item_changed_at,
        s.adTimeChg AS stock_changed_at
    FROM [{$defaultSchema}].[tHE_SetItem] AS i
    INNER JOIN [{$defaultSchema}].[tHE_Stock] AS s
        ON LTRIM(RTRIM(ISNULL(s.acIdent, ''))) = LTRIM(RTRIM(ISNULL(i.acIdent, '')))
    WHERE " . implode("\n      AND ", $where) . "
    ORDER BY
        material_name ASC,
        warehouse ASC,
        material_code ASC,
        stock_qid ASC
";

$detailRows = phptest10_fetch_all($conn, $sql, $params);

$groups = [];

foreach ($detailRows as $row) {
    $nameDisplay = trim((string) ($row['material_name'] ?? ''));
    $nameKey = phptest10_name_key($nameDisplay);

    if ($nameKey === '') {
        continue;
    }

    if (!array_key_exists($nameKey, $groups)) {
        $groups[$nameKey] = [
            'material_name' => $nameDisplay,
            'material_sets' => [],
            'material_codes' => [],
            'warehouses' => [],
            'stock_rows' => 0,
            'stock_qty_total' => 0.0,
            'stock_value_total' => 0.0,
            'last_price_max' => null,
            'buy_price_max' => null,
            'latest_changed_at' => null,
            'latest_changed_at_ts' => 0,
            'details' => [],
        ];
    }

    $group = &$groups[$nameKey];
    $code = trim((string) ($row['material_code'] ?? ''));
    $warehouseName = trim((string) ($row['warehouse'] ?? ''));
    $materialSet = trim((string) ($row['material_set'] ?? ''));
    $stockQty = is_numeric((string) ($row['stock_qty'] ?? null)) ? (float) $row['stock_qty'] : 0.0;
    $stockValue = is_numeric((string) ($row['stock_value'] ?? null)) ? (float) $row['stock_value'] : 0.0;
    $lastPrice = is_numeric((string) ($row['last_price'] ?? null)) ? (float) $row['last_price'] : null;
    $buyPrice = is_numeric((string) ($row['buy_price'] ?? null)) ? (float) $row['buy_price'] : null;
    $stockChangedAt = $row['stock_changed_at'] ?? null;
    $itemChangedAt = $row['item_changed_at'] ?? null;
    $bestChangedAt = phptest10_timestamp($stockChangedAt) >= phptest10_timestamp($itemChangedAt)
        ? $stockChangedAt
        : $itemChangedAt;
    $bestChangedTs = phptest10_timestamp($bestChangedAt);

    if ($materialSet !== '') {
        $group['material_sets'][$materialSet] = true;
    }

    if ($code !== '') {
        $group['material_codes'][$code] = true;
    }

    if ($warehouseName !== '') {
        $group['warehouses'][$warehouseName] = true;
    }

    $group['stock_rows']++;
    $group['stock_qty_total'] += $stockQty;
    $group['stock_value_total'] += $stockValue;

    if ($lastPrice !== null && ($group['last_price_max'] === null || $lastPrice > $group['last_price_max'])) {
        $group['last_price_max'] = $lastPrice;
    }

    if ($buyPrice !== null && ($group['buy_price_max'] === null || $buyPrice > $group['buy_price_max'])) {
        $group['buy_price_max'] = $buyPrice;
    }

    if ($bestChangedTs >= $group['latest_changed_at_ts']) {
        $group['latest_changed_at_ts'] = $bestChangedTs;
        $group['latest_changed_at'] = $bestChangedAt;
    }

    $group['details'][] = $row;
    unset($group);
}

$groupRows = [];

foreach ($groups as $nameKey => $group) {
    $warehouseCount = count($group['warehouses']);
    $codesCount = count($group['material_codes']);

    if ($duplicatesOnly && $warehouseCount < 2) {
        continue;
    }

    $groupRows[] = [
        'name_key' => $nameKey,
        'material_name' => $group['material_name'],
        'material_sets' => phptest10_join_preview(array_keys($group['material_sets']), 3),
        'material_codes_count' => $codesCount,
        'material_codes_sample' => phptest10_join_preview(array_keys($group['material_codes']), 6),
        'warehouse_count' => $warehouseCount,
        'warehouses' => phptest10_join_preview(array_keys($group['warehouses']), 8),
        'stock_rows' => $group['stock_rows'],
        'stock_qty_total' => $group['stock_qty_total'],
        'stock_value_total' => $group['stock_value_total'],
        'last_price_max' => $group['last_price_max'],
        'buy_price_max' => $group['buy_price_max'],
        'latest_changed_at' => $group['latest_changed_at'],
    ];
}

$sortMap = [
    'name' => 'material_name',
    'codes' => 'material_codes_count',
    'warehouses' => 'warehouse_count',
    'stock_rows' => 'stock_rows',
    'stock_qty' => 'stock_qty_total',
    'stock_value' => 'stock_value_total',
    'last_price' => 'last_price_max',
    'buy_price' => 'buy_price_max',
    'changed' => 'latest_changed_at',
];

$sortKey = $sortMap[$sort] ?? 'warehouse_count';

usort($groupRows, static function (array $a, array $b) use ($sortKey, $dir): int {
    $left = $a[$sortKey] ?? null;
    $right = $b[$sortKey] ?? null;

    if ($left instanceof DateTimeInterface) {
        $left = $left->getTimestamp();
    }

    if ($right instanceof DateTimeInterface) {
        $right = $right->getTimestamp();
    }

    if (is_string($left) || is_string($right)) {
        $compare = strcasecmp(trim((string) $left), trim((string) $right));
    } elseif ($left == $right) {
        $compare = 0;
    } else {
        $compare = ($left <=> $right);
    }

    if ($compare === 0) {
        $compare = strcasecmp((string) ($a['material_name'] ?? ''), (string) ($b['material_name'] ?? ''));
    }

    return $dir === 'asc' ? $compare : -$compare;
});

$groupsTotal = count($groupRows);
$groupRowsShown = $all ? $groupRows : array_slice($groupRows, 0, $limit);
$shownGroupKeys = array_flip(array_map(static function (array $row) {
    return (string) ($row['name_key'] ?? '');
}, $groupRowsShown));

$detailRowsShown = [];
foreach ($groupRowsShown as $groupRow) {
    $nameKey = (string) ($groupRow['name_key'] ?? '');
    if ($nameKey === '' || !isset($groups[$nameKey])) {
        continue;
    }

    foreach ($groups[$nameKey]['details'] as $detailRow) {
        $detailRowsShown[] = [
            'material_name' => $detailRow['material_name'] ?? '',
            'material_code' => $detailRow['material_code'] ?? '',
            'material_set' => $detailRow['material_set'] ?? '',
            'material_um' => $detailRow['material_um'] ?? '',
            'warehouse' => $detailRow['warehouse'] ?? '',
            'stock_qty' => $detailRow['stock_qty'] ?? null,
            'stock_value' => $detailRow['stock_value'] ?? null,
            'avg_price' => $detailRow['avg_price'] ?? null,
            'last_price' => $detailRow['last_price'] ?? null,
            'buy_price' => $detailRow['buy_price'] ?? null,
            'stock_qid' => $detailRow['stock_qid'] ?? null,
            'item_qid' => $detailRow['item_qid'] ?? null,
            'stock_changed_at' => $detailRow['stock_changed_at'] ?? null,
            'item_changed_at' => $detailRow['item_changed_at'] ?? null,
        ];
    }
}

usort($detailRowsShown, static function (array $a, array $b): int {
    foreach (['material_name', 'warehouse', 'material_code'] as $key) {
        $compare = strcasecmp(trim((string) ($a[$key] ?? '')), trim((string) ($b[$key] ?? '')));
        if ($compare !== 0) {
            return $compare;
        }
    }

    return ((int) ($a['stock_qid'] ?? 0)) <=> ((int) ($b['stock_qid'] ?? 0));
});

$summaryRows = [[
    'detail_rows_total' => count($detailRows),
    'group_rows_total' => $groupsTotal,
    'group_rows_shown' => count($groupRowsShown),
    'detail_rows_shown' => count($detailRowsShown),
    'name' => $materialName !== '' ? $materialName : '-',
    'search' => $search !== '' ? $search : '-',
    'set' => $set !== '' ? $set : '-',
    'warehouse' => $warehouse !== '' ? $warehouse : '-',
    'duplicates_only' => $duplicatesOnly ? 'YES' : 'NO',
    'nonzero_only' => $nonzeroOnly ? 'YES' : 'NO',
    'sort' => $sort,
    'dir' => strtoupper($dir),
    'limit' => $all ? 'ALL' : $limit,
]];

$links = [
    'all' => phptest10_build_query([
        'search' => $search,
        'material_name' => $materialName,
        'warehouse' => $warehouse,
        'set' => $set,
        'sort' => $sort,
        'dir' => $dir,
        'duplicates_only' => $duplicatesOnly ? '1' : '0',
        'nonzero_only' => $nonzeroOnly ? '1' : '0',
        'all' => '1',
    ]),
    'duplicates' => phptest10_build_query([
        'search' => $search,
        'material_name' => $materialName,
        'warehouse' => $warehouse,
        'set' => $set,
        'sort' => $sort,
        'dir' => $dir,
        'duplicates_only' => '1',
        'nonzero_only' => $nonzeroOnly ? '1' : '0',
        'all' => $all ? '1' : '0',
    ]),
    'all_names' => phptest10_build_query([
        'search' => $search,
        'material_name' => $materialName,
        'warehouse' => $warehouse,
        'set' => $set,
        'sort' => $sort,
        'dir' => $dir,
        'duplicates_only' => '0',
        'nonzero_only' => $nonzeroOnly ? '1' : '0',
        'all' => $all ? '1' : '0',
    ]),
];

$toolbarHtml = '';

if (PHP_SAPI !== 'cli') {
    $toolbarHtml = '
        <form method="get" class="toolbar">
            <label>Search <input type="text" name="search" value="' . phptest10_h($search) . '" placeholder="sifra, naziv, opis, skladiste"></label>
            <label>Naziv <input type="text" name="material_name" value="' . phptest10_h($materialName) . '" placeholder="Naziv materijala"></label>
            <label>Skladiste <input type="text" name="warehouse" value="' . phptest10_h($warehouse) . '" placeholder="Naziv skladista"></label>
            <label>Set <input type="text" name="set" value="' . phptest10_h($set) . '" placeholder="101,120"></label>
            <label>Limit <input type="number" name="limit" min="1" max="1000" value="' . phptest10_h((string) $limit) . '"></label>
            <label>Sort
                <select name="sort">
                    <option value="warehouses"' . ($sort === 'warehouses' ? ' selected' : '') . '>Broj skladista</option>
                    <option value="name"' . ($sort === 'name' ? ' selected' : '') . '>Naziv</option>
                    <option value="codes"' . ($sort === 'codes' ? ' selected' : '') . '>Broj sifri</option>
                    <option value="stock_qty"' . ($sort === 'stock_qty' ? ' selected' : '') . '>Kolicina</option>
                    <option value="stock_value"' . ($sort === 'stock_value' ? ' selected' : '') . '>Vrijednost</option>
                    <option value="last_price"' . ($sort === 'last_price' ? ' selected' : '') . '>Zadnja cijena</option>
                    <option value="buy_price"' . ($sort === 'buy_price' ? ' selected' : '') . '>Buy price</option>
                    <option value="changed"' . ($sort === 'changed' ? ' selected' : '') . '>Promjena</option>
                </select>
            </label>
            <label>Dir
                <select name="dir">
                    <option value="desc"' . ($dir === 'desc' ? ' selected' : '') . '>DESC</option>
                    <option value="asc"' . ($dir === 'asc' ? ' selected' : '') . '>ASC</option>
                </select>
            </label>
            <label><input type="checkbox" name="duplicates_only" value="1"' . ($duplicatesOnly ? ' checked' : '') . '> Samo vise skladista</label>
            <label><input type="checkbox" name="nonzero_only" value="1"' . ($nonzeroOnly ? ' checked' : '') . '> Samo stock != 0</label>
            <label><input type="checkbox" name="all" value="1"' . ($all ? ' checked' : '') . '> Prikazi sve</label>
            <button type="submit">Trazi</button>
            <a href="?' . phptest10_h($links['duplicates']) . '">Samo duplikati</a>
            <a href="?' . phptest10_h($links['all_names']) . '">Svi nazivi</a>
            <a href="?' . phptest10_h($links['all']) . '">Sve bez limita</a>
        </form>
    ';
}

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Warehouse material-name duplicate test</title>';
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
        table{border-collapse:collapse;min-width:1480px;background:#fff}
        th,td{border:1px solid #d8d8d8;padding:4px 6px;vertical-align:top;white-space:nowrap}
        th{background:#f1f3f5;text-align:left}
    </style></head><body>';
}

phptest10_render_heading('Warehouse material-name duplicate test', 1);
phptest10_render_note(
    'Test grupise materijale po nazivu i provjerava da li se isti naziv pojavljuje na dva ili vise razlicitih skladista.',
    'note'
);
phptest10_render_note(
    'Parametri: search, name ili material_name, warehouse, set, limit, all=1, duplicates_only=1, nonzero_only=1, sort=warehouses|name|codes|stock_qty|stock_value|last_price|buy_price|changed, dir=asc|desc',
    'meta'
);

phptest10_render_table('Summary', $summaryRows);
phptest10_render_table('Grouped names across warehouses', $groupRowsShown, $toolbarHtml);
phptest10_render_table('Per-warehouse stock rows for shown groups', $detailRowsShown);

sqlsrv_close($conn);

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}
