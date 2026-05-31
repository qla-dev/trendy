<?php

/*
 * test16.php
 * Pregled sastavnice proizvoda i RN stavki za isti proizvod.
 * Korisno da se vidi gdje se upisuju kolicine u tHF_SetPrSt i tHF_WOExItem.
 *
 * Parametri:
 * - rn=26-6400-0002834
 * - product=12345
 * - limit=100
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest16_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest16_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Product BOM trace test</title></head><body>';
    echo '<pre>' . phptest16_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest16_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest16_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest16_norm(string $value): string
{
    $normalized = preg_replace('/\D+/', '', $value);

    return is_string($normalized) ? $normalized : '';
}

function phptest16_candidates(string $value): array
{
    $normalized = phptest16_norm($value);

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

function phptest16_format_number($value, int $scale = 4): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest16_format_value($value): string
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
        return phptest16_format_number($value);
    }

    return trim((string) $value);
}

function phptest16_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest16_h($title) . '</' . $tag . '>';
}

function phptest16_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest16_h($class) . '">' . phptest16_h($text) . '</div>';
}

function phptest16_render_table(string $title, array $rows): void
{
    phptest16_render_heading($title, 3);

    if (empty($rows)) {
        phptest16_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 220) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest16_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest16_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest16_h(phptest16_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest16_locate_work_order($conn, string $schema, string $input): array
{
    $trimmedInput = trim($input);
    $candidates = phptest16_candidates($trimmedInput);

    if ($trimmedInput === '' || empty($candidates)) {
        return [
            'input' => $trimmedInput,
            'normalized' => phptest16_norm($trimmedInput),
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

    $rows = phptest16_fetch_all($conn, $sql, $params);

    return [
        'input' => $trimmedInput,
        'normalized' => phptest16_norm($trimmedInput),
        'candidates' => $candidates,
        'row' => $rows[0] ?? null,
    ];
}

function phptest16_fetch_schema_columns($conn, string $schema, string $table): array
{
    return phptest16_fetch_all(
        $conn,
        "
            SELECT
                COLUMN_NAME,
                DATA_TYPE,
                CHARACTER_MAXIMUM_LENGTH,
                NUMERIC_PRECISION,
                NUMERIC_SCALE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ",
        [$schema, $table]
    );
}

function phptest16_filter_interesting_columns(array $rows): array
{
    return array_values(array_filter($rows, static function (array $row): bool {
        $column = strtolower(trim((string) ($row['COLUMN_NAME'] ?? '')));

        if ($column === '') {
            return false;
        }

        foreach ([
            'ident',
            'descr',
            'note',
            'field',
            'qty',
            'gross',
            'plan',
            'batch',
            'um',
            'statement',
            'oper',
            'delay',
            'no',
        ] as $needle) {
            if (str_contains($column, $needle)) {
                return true;
            }
        }

        return false;
    }));
}

$schema = trim((string) ($_GET['schema'] ?? $defaultSchema ?: 'dbo'));
$schema = preg_match('/^[A-Za-z0-9_]+$/', $schema) ? $schema : ($defaultSchema ?: 'dbo');
$rn = trim((string) ($_GET['rn'] ?? ''));
$product = trim((string) ($_GET['product'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 100);
$limit = max(1, min($limit, 300));

$lookup = phptest16_locate_work_order($conn, $schema, $rn);
$workOrder = $lookup['row'];
$resolvedProduct = $product !== ''
    ? $product
    : trim((string) ($workOrder['acIdent'] ?? ''));
$workOrderKey = trim((string) ($workOrder['acKey'] ?? ''));
$workOrderView = trim((string) ($workOrder['acKeyView'] ?? ''));

$productStructureTable = 'tHF_SetPrSt';
$workOrderItemTable = 'tHF_WOExItem';

$structureColumns = phptest16_filter_interesting_columns(
    phptest16_fetch_schema_columns($conn, $schema, $productStructureTable)
);
$workOrderItemColumns = phptest16_filter_interesting_columns(
    phptest16_fetch_schema_columns($conn, $schema, $workOrderItemTable)
);

$bomRows = [];
if ($resolvedProduct !== '') {
    $bomRows = phptest16_fetch_all(
        $conn,
        "
            SELECT TOP ($limit) *
            FROM [{$schema}].[{$productStructureTable}]
            WHERE LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?
            ORDER BY anNo, anQId
        ",
        [$resolvedProduct]
    );
}

$workOrderItemRows = [];
if ($workOrderKey !== '') {
    $workOrderItemRows = phptest16_fetch_all(
        $conn,
        "
            SELECT TOP ($limit) *
            FROM [{$schema}].[{$workOrderItemTable}]
            WHERE acKey = ?
            ORDER BY anNo, anQId
        ",
        [$workOrderKey]
    );
}

$summaryRows = [[
    'rn_input' => $rn !== '' ? $rn : '-',
    'rn_candidates' => !empty($lookup['candidates']) ? implode(', ', $lookup['candidates']) : '-',
    'rn_key' => $workOrderKey !== '' ? $workOrderKey : '-',
    'rn_view' => $workOrderView !== '' ? $workOrderView : '-',
    'product_input' => $product !== '' ? $product : '-',
    'resolved_product' => $resolvedProduct !== '' ? $resolvedProduct : '-',
    'bom_rows' => count($bomRows),
    'wo_item_rows' => count($workOrderItemRows),
    'bom_table' => $schema . '.' . $productStructureTable,
    'wo_item_table' => $schema . '.' . $workOrderItemTable,
]];

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Product BOM trace test</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;font-size:14px;line-height:1.45;margin:20px;color:#222}
        h1,h2,h3{margin:16px 0 8px}
        .meta,.note{padding:10px 12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .note{background:#fff8df;border-color:#f0d67b}
        .toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:end;padding:12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .toolbar label{display:flex;flex-direction:column;gap:4px;font-size:12px}
        .toolbar input{min-width:220px;padding:6px 8px;font-size:13px}
        .toolbar button{padding:7px 10px;font-size:13px}
        .table-wrap{overflow-x:auto;margin:8px 0 22px}
        table{border-collapse:collapse;min-width:1280px;background:#fff}
        th,td{border:1px solid #d8d8d8;padding:4px 6px;vertical-align:top;white-space:nowrap}
        th{background:#f1f3f5;text-align:left}
    </style></head><body>';
}

phptest16_render_heading('Product BOM trace test', 1);
phptest16_render_note(
    'Test vraca sifrarnik sastavnice proizvoda iz tHF_SetPrSt i RN stavke iz tHF_WOExItem, da se vidi gdje zavrsavaju kolicine i povezane kolone.'
);

if (PHP_SAPI !== 'cli') {
    echo '<form method="get" class="toolbar">';
    echo '<label>RN<input type="text" name="rn" value="' . phptest16_h($rn) . '" placeholder="npr. 26-6400-0002834"></label>';
    echo '<label>Proizvod<input type="text" name="product" value="' . phptest16_h($product) . '" placeholder="npr. sifra proizvoda"></label>';
    echo '<label>Limit<input type="text" name="limit" value="' . phptest16_h((string) $limit) . '"></label>';
    echo '<input type="hidden" name="schema" value="' . phptest16_h($schema) . '">';
    echo '<button type="submit">Prikazi</button>';
    echo '</form>';
}

phptest16_render_table('Summary', $summaryRows);
phptest16_render_table($productStructureTable . ' interesting columns', $structureColumns);
phptest16_render_table($workOrderItemTable . ' interesting columns', $workOrderItemColumns);
phptest16_render_table($productStructureTable . ' rows', $bomRows);
phptest16_render_table($workOrderItemTable . ' rows', $workOrderItemRows);

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}

sqlsrv_close($conn);
