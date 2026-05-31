<?php

/*
 * test18.php
 * Pregled sifrarnika prioriteta radnih naloga iz dbo.tHE_SetDeliveryPriority.
 *
 * Parametri:
 * - active_only=1   (default 0)
 * - priority=5
 * - limit=100
 * - schema=dbo
 * - table=tHE_SetDeliveryPriority
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest18_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest18_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Priority lookup test</title></head><body>';
    echo '<pre>' . phptest18_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest18_fetch_all($conn, string $sql, array $params = [], int $timeout = 30): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest18_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest18_identifier(string $value, string $fallback): string
{
    $trimmed = trim($value);

    if ($trimmed === '' || preg_match('/^[A-Za-z0-9_]+$/', $trimmed) !== 1) {
        return $fallback;
    }

    return $trimmed;
}

function phptest18_format_number($value, int $scale = 4): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest18_format_value($value): string
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
        return phptest18_format_number($value);
    }

    return trim((string) $value);
}

function phptest18_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest18_h($title) . '</' . $tag . '>';
}

function phptest18_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest18_h($class) . '">' . phptest18_h($text) . '</div>';
}

function phptest18_render_table(string $title, array $rows): void
{
    phptest18_render_heading($title, 3);

    if (empty($rows)) {
        phptest18_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 220) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest18_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest18_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest18_h(phptest18_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest18_fetch_schema_columns($conn, string $schema, string $table): array
{
    return phptest18_fetch_all(
        $conn,
        "
            SELECT
                COLUMN_NAME,
                DATA_TYPE,
                CHARACTER_MAXIMUM_LENGTH,
                NUMERIC_PRECISION,
                NUMERIC_SCALE,
                IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ",
        [$schema, $table]
    );
}

$schema = phptest18_identifier((string) ($_GET['schema'] ?? $defaultSchema ?: 'dbo'), $defaultSchema ?: 'dbo');
$table = phptest18_identifier((string) ($_GET['table'] ?? 'tHE_SetDeliveryPriority'), 'tHE_SetDeliveryPriority');
$priorityFilter = trim((string) ($_GET['priority'] ?? ''));
$activeOnly = (string) ($_GET['active_only'] ?? '0') === '1';
$limit = (int) ($_GET['limit'] ?? 100);
$limit = max(1, min($limit, 500));

$qualifiedTable = '[' . $schema . '].[' . $table . ']';
$where = [];
$params = [];

if ($priorityFilter !== '' && is_numeric($priorityFilter)) {
    $where[] = 'anPriority = ?';
    $params[] = (int) $priorityFilter;
}

if ($activeOnly) {
    $where[] = 'ISNULL(abActive, 0) = 1';
}

$sql = "
    SELECT TOP ($limit) *
    FROM {$qualifiedTable}
";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= "
    ORDER BY
        CASE WHEN anPriority IS NULL THEN 1 ELSE 0 END,
        anPriority ASC,
        acPriority ASC,
        acName ASC
";

$rows = phptest18_fetch_all($conn, $sql, $params);
$columns = phptest18_fetch_schema_columns($conn, $schema, $table);

$summaryRows = [[
    'table' => $schema . '.' . $table,
    'active_only' => $activeOnly ? '1' : '0',
    'priority_filter' => $priorityFilter !== '' ? $priorityFilter : '-',
    'row_count' => count($rows),
    'column_count' => count($columns),
]];

if (PHP_SAPI === 'cli') {
    echo 'TABLE: ' . $schema . '.' . $table . PHP_EOL;
    echo 'ACTIVE ONLY: ' . ($activeOnly ? '1' : '0') . PHP_EOL;
    echo 'PRIORITY FILTER: ' . ($priorityFilter !== '' ? $priorityFilter : '-') . PHP_EOL;
    echo 'ROWS: ' . count($rows) . PHP_EOL;
    echo 'COLUMNS: ' . count($columns) . PHP_EOL;

    phptest18_render_table('Summary', $summaryRows);
    phptest18_render_table('Table columns', $columns);
    phptest18_render_table('Priority lookup rows', $rows);
    exit;
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>Priority lookup test</title>';
echo '<style>
    body{font-family:Segoe UI,Arial,sans-serif;margin:24px;background:#f5f7fb;color:#1f2937}
    h1,h2,h3{margin:0 0 12px}
    .note{margin:0 0 18px;padding:12px 14px;background:#eef6ff;border:1px solid #cfe0ff;border-radius:12px}
    .toolbar{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 20px;padding:14px;background:#fff;border:1px solid #d7e2ee;border-radius:14px}
    label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:#475569}
    input,select{min-width:120px;padding:9px 10px;border:1px solid #cbd5e1;border-radius:10px;background:#fff}
    button{padding:10px 16px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer}
    .table-wrap{overflow-x:auto;margin:8px 0 22px}
    table{border-collapse:collapse;min-width:960px;background:#fff;width:100%}
    th,td{border:1px solid #d7e2ee;padding:8px 10px;text-align:left;vertical-align:top}
    th{background:#eef2ff}
 </style></head><body>';

echo '<h1>Priority lookup test</h1>';
echo '<div class="note">Ovaj test cita sifrarnik prioriteta koji aplikacija koristi za <b>anPriority</b> na radnom nalogu. Po kodu aplikacije to je tabela <b>' . phptest18_h($schema . '.' . $table) . '</b>.</div>';

echo '<form method="get" class="toolbar">';
echo '<label>Schema<input type="text" name="schema" value="' . phptest18_h($schema) . '"></label>';
echo '<label>Table<input type="text" name="table" value="' . phptest18_h($table) . '"></label>';
echo '<label>Priority<input type="text" name="priority" value="' . phptest18_h($priorityFilter) . '" placeholder="npr. 5"></label>';
echo '<label>Limit<input type="number" name="limit" min="1" max="500" value="' . phptest18_h((string) $limit) . '"></label>';
echo '<label>Active only<select name="active_only">';
echo '<option value="0"' . (!$activeOnly ? ' selected' : '') . '>Ne</option>';
echo '<option value="1"' . ($activeOnly ? ' selected' : '') . '>Da</option>';
echo '</select></label>';
echo '<label style="justify-content:flex-end"><span>&nbsp;</span><button type="submit">Pokreni test</button></label>';
echo '</form>';

phptest18_render_table('Summary', $summaryRows);
phptest18_render_table('Table columns', $columns);
phptest18_render_table('Priority lookup rows', $rows);

echo '</body></html>';
