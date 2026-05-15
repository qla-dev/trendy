<?php

/*
 * test7.php
 * Lists catalog materials with stock by warehouse and calculates average price from stock value / stock qty.
 */

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest7_env(string $key, ?string $default = null): ?string
{
    static $values = null;

    if ($values === null) {
        $values = [];
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

        if (is_file($path) && is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                $line = trim((string) $line);

                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$k, $v] = explode('=', $line, 2);
                $k = trim((string) $k);
                $v = trim((string) $v);

                if (
                    $v !== ''
                    && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))
                ) {
                    $v = substr($v, 1, -1);
                }

                $values[$k] = $v;
            }
        }
    }

    return array_key_exists($key, $values) ? $values[$key] : $default;
}

function phptest7_config(): array
{
    return [
        'host' => (string) phptest7_env('DB_HOST', 'hostBApa1.datalab.ba'),
        'port' => (string) phptest7_env('DB_PORT', '50387'),
        'database' => (string) phptest7_env('DB_DATABASE', 'BA_TRENDY'),
        'username' => (string) phptest7_env('DB_USERNAME', 'SQLTREN_ADM2'),
        'password' => (string) phptest7_env('DB_PASSWORD', '#4^Sdgfx3VHy5G'),
        'schema' => phptest7_safe_identifier((string) phptest7_env('DB_SCHEMA', 'dbo'), 'dbo'),
        'catalog_table' => phptest7_safe_identifier(
            (string) phptest7_env('WORK_ORDER_CATALOG_ITEMS_TABLE', 'tHE_SetItem'),
            'tHE_SetItem'
        ),
        'stock_table' => phptest7_safe_identifier(
            (string) phptest7_env('WORK_ORDER_STOCK_TABLE', 'tHE_Stock'),
            'tHE_Stock'
        ),
    ];
}

function phptest7_safe_identifier(string $value, string $fallback): string
{
    $value = trim($value);

    if ($value === '' || !preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        return $fallback;
    }

    return $value;
}

function phptest7_quote_identifier(string $value): string
{
    return '[' . str_replace(']', ']]', $value) . ']';
}

function phptest7_connect(array $config): PDO
{
    $host = $config['host'] . ',' . $config['port'];
    $database = $config['database'];
    $username = $config['username'];
    $password = $config['password'];

    $dsnCandidates = [
        'sqlsrv:Server=' . $host . ';Database=' . $database,
        'sqlsrv:Server=' . $host . ';Database=' . $database . ';Encrypt=0;TrustServerCertificate=1',
        'odbc:Driver={ODBC Driver 18 for SQL Server};Server=' . $host . ';Database=' . $database . ';Encrypt=no;TrustServerCertificate=yes;',
        'odbc:Driver={ODBC Driver 11 for SQL Server};Server=' . $host . ';Database=' . $database . ';',
    ];

    $errors = [];

    foreach ($dsnCandidates as $dsn) {
        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            return $pdo;
        } catch (Throwable $e) {
            $errors[] = $dsn . ' => ' . $e->getMessage();
        }
    }

    throw new RuntimeException("Nije moguce otvoriti SQL konekciju.\n" . implode("\n", $errors));
}

function phptest7_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function phptest7_csv_values(string $value): array
{
    $parts = array_map('trim', explode(',', $value));

    return array_values(array_filter($parts, static function ($part) {
        return $part !== '';
    }));
}

function phptest7_bool_param(string $value): bool
{
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'da'], true);
}

function phptest7_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest7_build_query(array $params): string
{
    return http_build_query(array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    }));
}

function phptest7_hidden_input(string $name, string $value): string
{
    return '<input type="hidden" name="' . phptest7_h($name) . '" value="' . phptest7_h($value) . '">';
}

function phptest7_format_value($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    if ($value === null) {
        return 'NULL';
    }

    if (is_float($value) || is_int($value) || (is_string($value) && is_numeric($value))) {
        return phptest7_format_number($value);
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return trim((string) $value);
}

function phptest7_format_number($value, int $scale = 4): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest7_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Material average price test</title></head><body>';
    echo '<pre>' . phptest7_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest7_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest7_h($title) . '</' . $tag . '>';
}

function phptest7_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest7_h($class) . '">' . phptest7_h($text) . '</div>';
}

function phptest7_render_table(string $title, array $rows, string $toolbarHtml = ''): void
{
    phptest7_render_heading($title, 3);

    if (PHP_SAPI !== 'cli' && $toolbarHtml !== '') {
        echo $toolbarHtml;
    }

    if (empty($rows)) {
        phptest7_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 120) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest7_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest7_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest7_h(phptest7_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest7_materials_toolbar_html(array $state): string
{
    $search = trim((string) ($state['search'] ?? ''));
    $materialName = trim((string) ($state['material_name'] ?? ''));
    $ident = trim((string) ($state['ident'] ?? ''));
    $setValue = trim((string) ($state['set'] ?? ''));
    $warehouse = trim((string) ($state['warehouse'] ?? ''));
    $stockOnly = !empty($state['stock_only']) ? '1' : '0';
    $sort = trim((string) ($state['sort'] ?? 'avg_price'));
    $dir = trim((string) ($state['dir'] ?? 'desc'));

    $baseParams = [
        'all' => '1',
        'name' => $materialName,
        'ident' => $ident,
        'set' => $setValue,
        'warehouse' => $warehouse,
        'stock_only' => $stockOnly,
        'sort' => $sort,
        'dir' => $dir,
    ];

    $showAllHref = '?' . phptest7_build_query($baseParams);
    $clearSearchHref = '?' . phptest7_build_query(array_merge($baseParams, [
        'search' => '',
    ]));

    $html = '<div class="materials-toolbar">';
    $html .= '<form method="get" class="materials-search-form">';
    $html .= '<div class="toolbar-row">';
    $html .= '<input type="search" name="search" value="' . phptest7_h($search) . '" placeholder="Trazi po sifri, nazivu, opisu ili kodu materijala">';
    $html .= phptest7_hidden_input('all', '1');
    $html .= phptest7_hidden_input('sort', $sort);
    $html .= phptest7_hidden_input('dir', $dir);
    $html .= phptest7_hidden_input('stock_only', $stockOnly);

    if ($materialName !== '') {
        $html .= phptest7_hidden_input('name', $materialName);
    }

    if ($ident !== '') {
        $html .= phptest7_hidden_input('ident', $ident);
    }

    if ($setValue !== '') {
        $html .= phptest7_hidden_input('set', $setValue);
    }

    if ($warehouse !== '') {
        $html .= phptest7_hidden_input('warehouse', $warehouse);
    }

    $html .= '<button type="submit">Trazi u materijalima</button>';
    $html .= '<a class="button-link" href="' . phptest7_h($showAllHref) . '">Prikazi sve</a>';

    if ($search !== '') {
        $html .= '<a class="button-link subtle-link" href="' . phptest7_h($clearSearchHref) . '">Ocisti search</a>';
    }

    $html .= '</div>';
    $html .= '<div class="muted">Search ovdje radi nad kompletnim rezultatom jer salje novi upit sa <code>all=1</code>.</div>';
    $html .= '</form>';
    $html .= '</div>';

    return $html;
}

function phptest7_sort_clause(string $sort, string $dir): string
{
    $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

    switch ($sort) {
        case 'name':
            return "UPPER(material_name) {$dir}, UPPER(material_code) ASC";
        case 'stock_qty':
            return "CASE WHEN stock_qty IS NULL THEN 1 ELSE 0 END ASC, stock_qty {$dir}, UPPER(material_code) ASC";
        case 'stock_value':
            return "CASE WHEN stock_value IS NULL THEN 1 ELSE 0 END ASC, stock_value {$dir}, UPPER(material_code) ASC";
        case 'last_price':
            return "CASE WHEN last_price IS NULL THEN 1 ELSE 0 END ASC, last_price {$dir}, UPPER(material_code) ASC";
        case 'buy_price':
            return "CASE WHEN buy_price IS NULL THEN 1 ELSE 0 END ASC, buy_price {$dir}, UPPER(material_code) ASC";
        case 'sales_price':
            return "CASE WHEN sales_price IS NULL THEN 1 ELSE 0 END ASC, sales_price {$dir}, UPPER(material_code) ASC";
        case 'changed':
            return "COALESCE(stock_changed_at, item_changed_at) {$dir}, UPPER(material_code) ASC";
        case 'code':
            return "CASE WHEN LEFT(material_code, 1) LIKE '[A-Za-z]' THEN 0 WHEN LEFT(material_code, 1) LIKE '[0-9]' THEN 2 ELSE 1 END ASC, UPPER(material_code) {$dir}";
        case 'avg_price':
        default:
            return "CASE WHEN avg_price IS NULL THEN 1 ELSE 0 END ASC, avg_price {$dir}, UPPER(material_code) ASC";
    }
}

$config = phptest7_config();
$schema = phptest7_quote_identifier($config['schema']);
$catalogTable = phptest7_quote_identifier($config['catalog_table']);
$stockTable = phptest7_quote_identifier($config['stock_table']);

$limitParam = trim((string) ($_GET['limit'] ?? '200'));
$showAll = phptest7_bool_param((string) ($_GET['all'] ?? $_GET['show_all'] ?? '0'))
    || strtolower($limitParam) === 'all';
$limit = max(1, min((int) (is_numeric($limitParam) ? $limitParam : 200), 1000));
$search = trim((string) ($_GET['search'] ?? ''));
$materialName = trim((string) ($_GET['name'] ?? $_GET['material_name'] ?? ''));
$ident = trim((string) ($_GET['ident'] ?? ''));
$warehouse = trim((string) ($_GET['warehouse'] ?? ''));
$setFilters = phptest7_csv_values((string) ($_GET['set'] ?? ''));
$stockOnly = phptest7_bool_param((string) ($_GET['stock_only'] ?? '0'));
$sort = trim((string) ($_GET['sort'] ?? 'avg_price'));
$dir = strtolower(trim((string) ($_GET['dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Material average price test</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;font-size:14px;line-height:1.4;margin:20px;color:#222}
        h1,h2,h3{margin:16px 0 8px}
        .meta,.note{padding:10px 12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .note{background:#fff8df;border-color:#f0d67b}
        .table-wrap{overflow-x:auto;margin:8px 0 22px}
        table{border-collapse:collapse;min-width:1200px;background:#fff}
        th,td{border:1px solid #d8d8d8;padding:4px 6px;vertical-align:top;white-space:nowrap}
        th{background:#f1f3f5;text-align:left}
        .materials-toolbar{padding:10px 12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .materials-search-form{display:flex;flex-direction:column;gap:8px}
        .toolbar-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .toolbar-row input[type=search]{flex:1 1 340px;min-width:280px;padding:8px 10px}
        .toolbar-row button,.button-link{display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border:1px solid #c9c9c9;background:#fff;color:#222;text-decoration:none;cursor:pointer}
        .subtle-link{background:#f7f7f7}
        .muted{color:#666;font-size:12px}
        code{font-family:Consolas,monospace}
    </style></head><body>';
}

try {
    $pdo = phptest7_connect($config);

    $joinConditions = [
        "LTRIM(RTRIM(ISNULL(s.acIdent, ''))) = LTRIM(RTRIM(ISNULL(i.acIdent, '')))",
    ];
    $where = [
        "LTRIM(RTRIM(ISNULL(i.acIdent, ''))) <> ''",
    ];
    $having = [];
    $params = [];

    if ($warehouse !== '') {
        $joinConditions[] = "LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) = ?";
        $params[] = $warehouse;
    }

    if ($ident !== '') {
        $where[] = "LTRIM(RTRIM(ISNULL(i.acIdent, ''))) = ?";
        $params[] = $ident;
    }

    if ($materialName !== '') {
        $where[] = "(
            i.acName LIKE ?
            OR i.acDescr LIKE ?
        )";
        $nameLike = '%' . $materialName . '%';
        array_push($params, $nameLike, $nameLike);
    }

    if ($search !== '') {
        $where[] = "(
            i.acIdent LIKE ?
            OR i.acName LIKE ?
            OR i.acDescr LIKE ?
            OR i.acCode LIKE ?
        )";
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    if (!empty($setFilters)) {
        $placeholders = implode(', ', array_fill(0, count($setFilters), '?'));
        $where[] = "LTRIM(RTRIM(ISNULL(i.acSetOfItem, ''))) IN ({$placeholders})";
        array_push($params, ...$setFilters);
    }

    if ($stockOnly) {
        $having[] = "ABS(COALESCE(SUM(CAST(ISNULL(s.anStock, 0) AS float)), 0)) > 0.000001";
    }

    $baseSql = "
        SELECT
            LTRIM(RTRIM(ISNULL(i.acIdent, ''))) AS material_code,
            LTRIM(RTRIM(COALESCE(NULLIF(i.acName, ''), NULLIF(i.acDescr, ''), i.acIdent, ''))) AS material_name,
            LTRIM(RTRIM(ISNULL(i.acUM, ''))) AS material_um,
            LTRIM(RTRIM(ISNULL(i.acSetOfItem, ''))) AS material_set,
            COUNT(CASE WHEN LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) <> '' THEN 1 END) AS stock_rows,
            COUNT(DISTINCT NULLIF(LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))), '')) AS warehouse_count,
            MIN(NULLIF(LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))), '')) AS warehouse_sample,
            CAST(COALESCE(SUM(CAST(ISNULL(s.anStock, 0) AS float)), 0) AS float) AS stock_qty,
            CAST(COALESCE(SUM(CAST(ISNULL(s.anValue, 0) AS float)), 0) AS float) AS stock_value,
            CAST(
                CASE
                    WHEN ABS(COALESCE(SUM(CAST(ISNULL(s.anStock, 0) AS float)), 0)) < 0.000001 THEN NULL
                    ELSE COALESCE(SUM(CAST(ISNULL(s.anValue, 0) AS float)), 0)
                        / NULLIF(COALESCE(SUM(CAST(ISNULL(s.anStock, 0) AS float)), 0), 0)
                END
                AS float
            ) AS avg_price,
            CAST(MAX(CAST(ISNULL(s.anLastPrice, 0) AS float)) AS float) AS last_price,
            CAST(MAX(CAST(ISNULL(i.anBuyPrice, 0) AS float)) AS float) AS buy_price,
            CAST(MAX(CAST(ISNULL(i.anPrice, 0) AS float)) AS float) AS sales_price,
            CAST(MAX(CAST(ISNULL(i.anPrStPrice, 0) AS float)) AS float) AS prst_price,
            CAST(MAX(CAST(ISNULL(i.anQId, 0) AS bigint)) AS bigint) AS item_qid,
            MAX(i.adTimeChg) AS item_changed_at,
            MAX(s.adTimeChg) AS stock_changed_at
        FROM {$schema}.{$catalogTable} AS i
        LEFT JOIN {$schema}.{$stockTable} AS s
            ON " . implode(' AND ', $joinConditions) . "
        WHERE " . implode(' AND ', $where) . "
        GROUP BY
            i.acIdent,
            i.acName,
            i.acDescr,
            i.acUM,
            i.acSetOfItem
    ";

    if (!empty($having)) {
        $baseSql .= "\nHAVING " . implode(' AND ', $having);
    }

    $topClause = $showAll ? '' : "TOP {$limit} ";
    $sql = "
        SELECT {$topClause}*
        FROM (
            {$baseSql}
        ) AS m
        ORDER BY " . phptest7_sort_clause($sort, $dir);

    $materials = phptest7_fetch_all($pdo, $sql, $params);

    phptest7_render_heading('Material average price test', 1);
    phptest7_render_note(
        'Tabela materijala: ' . $config['schema'] . '.' . $config['catalog_table']
        . ' | tabela zaliha: ' . $config['schema'] . '.' . $config['stock_table'],
        'meta'
    );
    phptest7_render_note(
        'srednja cijena = SUM(anValue) / SUM(anStock). Za poredjenje se prikazuje i zadnja cijena iz anLastPrice.',
        'note'
    );
    phptest7_render_note(
        'Parametri: ident, name ili material_name, search, set, warehouse, limit, all=1, stock_only=1, sort=avg_price|code|name|stock_qty|stock_value|last_price|buy_price|sales_price|changed, dir=asc|desc',
        'meta'
    );

    $summaryRows = [[
        'rows' => count($materials),
        'ident' => $ident !== '' ? $ident : '-',
        'name' => $materialName !== '' ? $materialName : '-',
        'search' => $search !== '' ? $search : '-',
        'set' => !empty($setFilters) ? implode(', ', $setFilters) : '-',
        'warehouse' => $warehouse !== '' ? $warehouse : '-',
        'all' => $showAll ? 'YES' : 'NO',
        'stock_only' => $stockOnly ? 'YES' : 'NO',
        'sort' => $sort,
        'dir' => strtoupper($dir),
        'limit' => $showAll ? 'ALL' : $limit,
    ]];

    phptest7_render_table('Summary', $summaryRows);
    phptest7_render_table(
        'Materials',
        $materials,
        PHP_SAPI !== 'cli'
            ? phptest7_materials_toolbar_html([
                'search' => $search,
                'material_name' => $materialName,
                'ident' => $ident,
                'set' => !empty($setFilters) ? implode(',', $setFilters) : '',
                'warehouse' => $warehouse,
                'stock_only' => $stockOnly,
                'sort' => $sort,
                'dir' => $dir,
            ])
            : ''
    );

    if ($ident !== '') {
        $detailParams = [$ident];
        $detailSql = "
            SELECT
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
                CAST(ISNULL(s.anQId, 0) AS bigint) AS stock_qid,
                s.adTimeIns,
                s.adTimeChg
            FROM {$schema}.{$stockTable} AS s
            WHERE LTRIM(RTRIM(ISNULL(s.acIdent, ''))) = ?
        ";

        if ($warehouse !== '') {
            $detailSql .= " AND LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) = ?";
            $detailParams[] = $warehouse;
        }

        $detailSql .= " ORDER BY warehouse ASC, stock_qid ASC";

        $details = phptest7_fetch_all($pdo, $detailSql, $detailParams);
        phptest7_render_table('Per-warehouse stock detail for ' . $ident, $details);
    }
} catch (Throwable $e) {
    phptest7_fail($e);
}

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}
