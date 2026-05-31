<?php

/*
 * test19.php
 * Provjera prioriteta na konkretnom radnom nalogu i mapiranje kroz tHE_SetDeliveryPriority.
 *
 * Parametri:
 * - rn=26-6000-003020
 * - limit=5
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest19_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest19_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN priority trace</title></head><body>';
    echo '<pre>' . phptest19_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest19_fetch_all($conn, string $sql, array $params = [], int $timeout = 30): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest19_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest19_fetch_table_columns($conn, string $schema, string $table): array
{
    $rows = phptest19_fetch_all(
        $conn,
        "
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ",
        [$schema, $table]
    );

    return array_values(array_map(static function (array $row): string {
        return trim((string) ($row['COLUMN_NAME'] ?? ''));
    }, $rows));
}

function phptest19_norm(string $value): string
{
    $normalized = preg_replace('/\D+/', '', $value);

    return is_string($normalized) ? $normalized : '';
}

function phptest19_candidates(string $value): array
{
    $normalized = phptest19_norm($value);

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

function phptest19_format_value($value): string
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

    return trim((string) $value);
}

function phptest19_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest19_h($title) . '</' . $tag . '>';
}

function phptest19_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest19_h($class) . '">' . phptest19_h($text) . '</div>';
}

function phptest19_render_table(string $title, array $rows): void
{
    phptest19_render_heading($title, 3);

    if (empty($rows)) {
        phptest19_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 220) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest19_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest19_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest19_h(phptest19_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

$schema = preg_match('/^[A-Za-z0-9_]+$/', (string) ($defaultSchema ?: 'dbo')) === 1
    ? (string) ($defaultSchema ?: 'dbo')
    : 'dbo';
$rn = trim((string) ($_GET['rn'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 5);
$limit = max(1, min($limit, 20));
$candidates = phptest19_candidates($rn);
$workOrderRows = [];
$priorityRows = [];
$workOrderColumns = phptest19_fetch_table_columns($conn, $schema, 'tHF_WOEx');

if ($rn !== '' && !empty($candidates)) {
    $where = [];
    $params = [];

    foreach ($candidates as $candidate) {
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKey), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKeyView), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
    }

    $requestedColumns = [
        'acKey',
        'acKeyView',
        'acDocType',
        'acStatus',
        'acStatusMF',
        'anPriority',
        'acPriority',
        'priority',
        'adDate',
        'adTimeChg',
    ];
    $selectedColumns = array_values(array_filter($requestedColumns, static function (string $column) use ($workOrderColumns): bool {
        return in_array($column, $workOrderColumns, true);
    }));

    if (!empty($selectedColumns)) {
        $quotedColumns = implode(",\n                ", array_map(static function (string $column): string {
            return '[' . $column . ']';
        }, $selectedColumns));

        $workOrderRows = phptest19_fetch_all(
            $conn,
            "
                SELECT TOP ($limit)
                    {$quotedColumns}
                FROM [{$schema}].[tHF_WOEx]
                WHERE " . implode(' OR ', $where) . "
                ORDER BY adTimeIns DESC, acKey DESC
            ",
            $params
        );
    }

    $priorityCodes = array_values(array_unique(array_filter(array_map(static function (array $row): int {
        return (int) ($row['anPriority'] ?? 0);
    }, $workOrderRows), static function (int $code): bool {
        return $code > 0;
    })));

    if (!empty($priorityCodes)) {
        $placeholders = implode(', ', array_fill(0, count($priorityCodes), '?'));
        $priorityRows = phptest19_fetch_all(
            $conn,
            "
                SELECT
                    anPriority,
                    acName,
                    abDefault,
                    abActive,
                    acPriority,
                    adTimeChg
                FROM [{$schema}].[tHE_SetDeliveryPriority]
                WHERE anPriority IN ({$placeholders})
                ORDER BY anPriority ASC
            ",
            $priorityCodes
        );
    }
}

$summaryRows = [[
    'rn_input' => $rn !== '' ? $rn : '-',
    'candidate_count' => count($candidates),
    'work_order_rows' => count($workOrderRows),
    'priority_rows' => count($priorityRows),
]];

if (PHP_SAPI === 'cli') {
    phptest19_render_table('Summary', $summaryRows);
    phptest19_render_table('Work order rows', $workOrderRows);
    phptest19_render_table('Priority lookup rows', $priorityRows);
    exit;
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>RN priority trace</title>';
echo '<style>
    body{font-family:Segoe UI,Arial,sans-serif;margin:24px;background:#f5f7fb;color:#1f2937}
    h1,h2,h3{margin:0 0 12px}
    .note{margin:0 0 18px;padding:12px 14px;background:#eef6ff;border:1px solid #cfe0ff;border-radius:12px}
    .toolbar{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 20px;padding:14px;background:#fff;border:1px solid #d7e2ee;border-radius:14px}
    label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:#475569}
    input{min-width:180px;padding:9px 10px;border:1px solid #cbd5e1;border-radius:10px;background:#fff}
    button{padding:10px 16px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer}
    .table-wrap{overflow-x:auto;margin:8px 0 22px}
    table{border-collapse:collapse;min-width:960px;background:#fff;width:100%}
    th,td{border:1px solid #d7e2ee;padding:8px 10px;text-align:left;vertical-align:top}
    th{background:#eef2ff}
 </style></head><body>';

echo '<h1>RN priority trace</h1>';
echo '<div class="note">Test pokazuje koji je prioritet upisan na RN headeru i kako se taj broj mapira kroz <b>' . phptest19_h($schema . '.tHE_SetDeliveryPriority') . '</b>.</div>';
echo '<form method="get" class="toolbar">';
echo '<label>RN<input type="text" name="rn" value="' . phptest19_h($rn) . '" placeholder="npr. 26-6000-003020"></label>';
echo '<label>Limit<input type="number" name="limit" min="1" max="20" value="' . phptest19_h((string) $limit) . '"></label>';
echo '<label style="justify-content:flex-end"><span>&nbsp;</span><button type="submit">Pokreni test</button></label>';
echo '</form>';

phptest19_render_table('Summary', $summaryRows);
phptest19_render_table('Work order rows', $workOrderRows);
phptest19_render_table('Priority lookup rows', $priorityRows);

echo '</body></html>';
