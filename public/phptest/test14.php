<?php

/*
 * test14.php
 * Raw pregled Pantheon tHE_Order tabele sa filtrom po pojedinacnoj narudzbi.
 * Pretraga gleda acKey, acKeyView i acDoc1.
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest14_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest14_format_value($value): string
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

function phptest14_fail($errors): void
{
    $payload = print_r($errors, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $payload . PHP_EOL);
        exit(1);
    }

    echo '<pre>' . phptest14_h($payload) . '</pre>';
    exit;
}

function phptest14_norm(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function phptest14_candidates(string $value): array
{
    $normalized = phptest14_norm($value);

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

    return array_values(array_unique(array_filter($candidates, function ($candidate) {
        return $candidate !== '';
    })));
}

function phptest14_fetch_rows($conn, string $schema, string $table, string $orderInput, int $limit): array
{
    $qualifiedTable = '[' . $schema . '].[' . $table . ']';
    $trimmedInput = trim($orderInput);
    $candidates = phptest14_candidates($trimmedInput);
    $params = [];

    if ($trimmedInput !== '' && !empty($candidates)) {
        $whereParts = [];

        foreach ($candidates as $candidate) {
            $whereParts[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKey), '-', ''), ' ', '') = ?";
            $params[] = $candidate;

            $whereParts[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKeyView), '-', ''), ' ', '') = ?";
            $params[] = $candidate;

            $whereParts[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acDoc1), '-', ''), ' ', '') = ?";
            $params[] = $candidate;
        }

        $sql = "
            SELECT TOP ($limit) *
            FROM {$qualifiedTable}
            WHERE " . implode(' OR ', $whereParts) . "
            ORDER BY adTimeIns DESC, acKey DESC
        ";
    } else {
        $sql = "
            SELECT TOP ($limit) *
            FROM {$qualifiedTable}
            ORDER BY adTimeIns DESC, acKey DESC
        ";
    }

    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => 30]);

    if (!$stmt) {
        phptest14_fail(sqlsrv_errors());
    }

    $meta = sqlsrv_field_metadata($stmt);
    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return [$meta, $rows, $candidates];
}

$schema = trim((string) ($_GET['schema'] ?? $defaultSchema ?: 'dbo'));
$schema = preg_match('/^[A-Za-z0-9_]+$/', $schema) ? $schema : ($defaultSchema ?: 'dbo');
$table = trim((string) ($_GET['table'] ?? 'tHE_Order'));
$table = preg_match('/^[A-Za-z0-9_]+$/', $table) ? $table : 'tHE_Order';
$order = trim((string) ($_GET['order'] ?? ''));
$limit = (int) ($_GET['limit'] ?? ($order !== '' ? 20 : 50));
$limit = max(1, min($limit, 200));

[$meta, $rows, $candidates] = phptest14_fetch_rows($conn, $schema, $table, $order, $limit);

if (PHP_SAPI === 'cli') {
    echo "TABLE: {$schema}.{$table}" . PHP_EOL;
    echo "ORDER INPUT: " . ($order !== '' ? $order : '[latest]') . PHP_EOL;
    echo "CANDIDATES: " . (!empty($candidates) ? implode(', ', $candidates) : '-') . PHP_EOL;
    echo "ROWS: " . count($rows) . PHP_EOL . PHP_EOL;

    if (empty($rows)) {
        echo "No rows." . PHP_EOL;
        sqlsrv_close($conn);
        exit(0);
    }

    $columns = array_map(function ($col) {
        return (string) ($col['Name'] ?? '');
    }, $meta ?: []);

    echo implode(' | ', $columns) . PHP_EOL;
    echo str_repeat('-', 120) . PHP_EOL;

    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $column) {
            $values[] = phptest14_format_value($row[$column] ?? null);
        }

        echo implode(' | ', $values) . PHP_EOL;
    }

    sqlsrv_close($conn);
    exit(0);
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>Raw orders table</title>';
echo '<style>
    body{font-family:Arial,sans-serif;font-size:14px;line-height:1.4;margin:20px;color:#222}
    h1,h2{margin:16px 0 8px}
    .meta,.note{padding:10px 12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
    .note{background:#fff8df;border-color:#f0d67b}
    .table-wrap{overflow:auto;margin:8px 0 22px}
    table{border-collapse:collapse;min-width:960px;background:#fff}
    th,td{border:1px solid #d8d8d8;padding:4px 6px;vertical-align:top;white-space:nowrap}
    th{background:#f1f3f5;text-align:left;position:sticky;top:0}
    .muted{color:#666}
    form{display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin:10px 0 18px}
    label{display:grid;gap:4px;font-size:12px;font-weight:700;color:#555}
    input{padding:8px 10px;border:1px solid #ccd3da;border-radius:6px;min-width:180px}
    button{padding:8px 14px;border:0;border-radius:6px;background:#0d6efd;color:#fff;cursor:pointer}
</style></head><body>';

echo '<h1>Raw tabela narudzbi</h1>';
echo '<div class="note">Provjera po broju narudzbe ide preko <b>order</b> parametra. Pretraga gleda <b>acKey</b>, <b>acKeyView</b> i <b>acDoc1</b>.</div>';

echo '<form method="get">';
echo '<label>Broj narudzbe<input type="text" name="order" value="' . phptest14_h($order) . '" placeholder="npr. 26-0110-0000928"></label>';
echo '<label>Limit<input type="text" name="limit" value="' . phptest14_h((string) $limit) . '"></label>';
echo '<input type="hidden" name="schema" value="' . phptest14_h($schema) . '">';
echo '<input type="hidden" name="table" value="' . phptest14_h($table) . '">';
echo '<button type="submit">Prikazi</button>';
echo '</form>';

echo '<div class="meta">';
echo '<b>Tabela:</b> ' . phptest14_h($schema . '.' . $table) . '<br>';
echo '<b>Order input:</b> ' . phptest14_h($order !== '' ? $order : '[latest]') . '<br>';
echo '<b>Kandidati:</b> ' . phptest14_h(!empty($candidates) ? implode(', ', $candidates) : '-') . '<br>';
echo '<b>Broj redova:</b> ' . phptest14_h((string) count($rows));
echo '</div>';

if (empty($rows)) {
    echo '<div class="note">Nema redova za trazeni broj narudzbe.</div>';
    echo '</body></html>';
    sqlsrv_close($conn);
    exit;
}

$columns = array_map(function ($col) {
    return (string) ($col['Name'] ?? '');
}, $meta ?: []);

echo '<div class="table-wrap"><table><thead><tr>';
foreach ($columns as $column) {
    echo '<th>' . phptest14_h($column) . '</th>';
}
echo '</tr></thead><tbody>';

foreach ($rows as $row) {
    echo '<tr>';
    foreach ($columns as $column) {
        echo '<td>' . phptest14_h(phptest14_format_value($row[$column] ?? null)) . '</td>';
    }
    echo '</tr>';
}

echo '</tbody></table></div>';
echo '</body></html>';

sqlsrv_close($conn);
