<?php

/**
 * Read-only Pantheon work-order status inspector.
 *
 * Browser: /phptest/test35.php?rn=26-6000-003612
 * CLI:     php public/phptest/test35.php "rn=26-6000-003612"
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest35_env(string $key, ?string $default = null): ?string
{
    static $values = null;
    static $resolved = [];

    if ($values === null) {
        $values = [];
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        foreach (is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$candidateKey, $value] = explode('=', $line, 2);
            $value = trim($value);
            if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            $values[trim($candidateKey)] = $value;
        }
    }

    if (!array_key_exists($key, $values)) {
        return $default;
    }
    if (array_key_exists($key, $resolved)) {
        return $resolved[$key];
    }

    $resolve = function (string $value, array $stack = []) use (&$resolve, &$values): string {
        return (string) preg_replace_callback('/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/', function (array $matches) use (&$resolve, &$values, $stack): string {
            $referenced = $matches[1];
            if (!array_key_exists($referenced, $values) || in_array($referenced, $stack, true)) {
                return $matches[0];
            }

            return $resolve($values[$referenced], [...$stack, $referenced]);
        }, $value);
    };

    return $resolved[$key] = $resolve($values[$key], [$key]);
}

function phptest35_value(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    return trim((string) $value);
}

function phptest35_h(mixed $value): string
{
    return htmlspecialchars(phptest35_value($value), ENT_QUOTES, 'UTF-8');
}

function phptest35_fail(string $message): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    http_response_code(400);
    echo '<!doctype html><meta charset="utf-8"><pre>' . phptest35_h($message) . '</pre>';
    exit;
}

function phptest35_fetch_all($connection, string $sql, array $params = []): array
{
    if (preg_match('/^\s*SELECT\b/i', $sql) !== 1) {
        phptest35_fail('Only read-only SELECT queries are allowed.');
    }

    $statement = sqlsrv_query($connection, $sql, $params, ['QueryTimeout' => 30]);
    if ($statement === false) {
        phptest35_fail('Pantheon query failed: ' . print_r(sqlsrv_errors(), true));
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($statement);

    return $rows;
}

function phptest35_candidates(string $value): array
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if ($digits === '') {
        return [];
    }

    $candidates = [$digits];
    if (strlen($digits) === 12) {
        $candidates[] = substr($digits, 0, 6) . '0' . substr($digits, 6);
    }
    if (strlen($digits) === 13 && substr($digits, 6, 1) === '0') {
        $candidates[] = substr($digits, 0, 6) . substr($digits, 7);
    }

    return array_values(array_unique($candidates));
}

function phptest35_render_table(array $rows): void
{
    if ($rows === []) {
        echo PHP_SAPI === 'cli' ? "No rows found.\n" : '<p class="muted">No rows found.</p>';
        return;
    }

    $columns = array_keys($rows[0]);
    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL . str_repeat('-', 180) . PHP_EOL;
        foreach ($rows as $row) {
            echo implode(' | ', array_map(fn (string $column) => phptest35_value($row[$column] ?? null), $columns)) . PHP_EOL;
        }
        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $column) {
        echo '<th>' . phptest35_h($column) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            echo '<td>' . phptest35_h($row[$column] ?? null) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

$rn = trim((string) ($_GET['rn'] ?? ''));
$candidates = phptest35_candidates($rn);
if ($candidates === []) {
    phptest35_fail('Enter a work-order number, for example: 26-6000-003612');
}

$host = (string) phptest35_env('WORK_ORDER_TARGET_DB_HOST', phptest35_env('DB_HOST', ''));
$port = (string) phptest35_env('WORK_ORDER_TARGET_DB_PORT', phptest35_env('DB_PORT', '1433'));
$database = (string) phptest35_env('WORK_ORDER_TARGET_DB_DATABASE', phptest35_env('DB_DATABASE', ''));
$username = (string) phptest35_env('WORK_ORDER_TARGET_DB_USERNAME', phptest35_env('DB_USERNAME', ''));
$password = (string) phptest35_env('WORK_ORDER_TARGET_DB_PASSWORD', phptest35_env('DB_PASSWORD', ''));
// The SQLSRV driver requires every closing brace in a password to be escaped.
$connectionPassword = str_replace('}', '}}', $password);

$connection = sqlsrv_connect($host . ($port !== '' ? ',' . $port : ''), [
    'Database' => $database,
    'UID' => $username,
    'PWD' => $connectionPassword,
    'CharacterSet' => 'UTF-8',
    'Encrypt' => strtolower((string) phptest35_env('WORK_ORDER_TARGET_DB_ENCRYPT', 'true')) !== 'false',
    'TrustServerCertificate' => strtolower((string) phptest35_env('WORK_ORDER_TARGET_DB_TRUST_SERVER_CERTIFICATE', 'true')) !== 'false',
    'LoginTimeout' => 10,
]);
if ($connection === false) {
    phptest35_fail('Could not connect to the configured Pantheon target: ' . print_r(sqlsrv_errors(), true));
}

try {
    $placeholders = implode(', ', array_fill(0, count($candidates), '?'));
    $workOrders = phptest35_fetch_all($connection, "
        SELECT TOP 1
            acKey AS work_order_key,
            acKeyView AS work_order_number,
            acStatus AS pantheon_status,
            acStatusMF AS pantheon_manufacturing_status,
            acReceiveFinished AS receive_finished,
            anProducedQty AS produced_quantity,
            adWOFinishDate AS finished_at,
            adTimeChg AS last_changed_at,
            anUserChg AS last_changed_by,
            CASE
                WHEN LTRIM(RTRIM(ISNULL(acStatus, ''))) = 'I'
                 AND LTRIM(RTRIM(ISNULL(acStatusMF, ''))) = 'Z'
                 AND LTRIM(RTRIM(ISNULL(acReceiveFinished, ''))) = 'Y'
                    THEN 'YES'
                ELSE 'NO'
            END AS closed_in_pantheon
        FROM dbo.tHF_WOEx
        WHERE REPLACE(REPLACE(LTRIM(RTRIM(acKey)), '-', ''), ' ', '') IN ({$placeholders})
           OR REPLACE(REPLACE(LTRIM(RTRIM(acKeyView)), '-', ''), ' ', '') IN ({$placeholders})
        ORDER BY adTimeChg DESC, acKey DESC
    ", [...$candidates, ...$candidates]);

    if ($workOrders === []) {
        phptest35_fail('Work order was not found: ' . $rn);
    }

    $workOrder = $workOrders[0];
    $workOrderKey = phptest35_value($workOrder['work_order_key'] ?? '');
    $statusColumns = phptest35_fetch_all($connection, "
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'dbo'
          AND TABLE_NAME = 'tHF_WOEx'
          AND (
              LOWER(COLUMN_NAME) LIKE '%status%'
              OR LOWER(COLUMN_NAME) LIKE '%finish%'
              OR LOWER(COLUMN_NAME) LIKE '%receive%'
              OR LOWER(COLUMN_NAME) LIKE '%produced%'
              OR LOWER(COLUMN_NAME) LIKE '%issue%'
              OR LOWER(COLUMN_NAME) LIKE '%execution%'
              OR LOWER(COLUMN_NAME) LIKE '%close%'
              OR LOWER(COLUMN_NAME) LIKE '%complete%'
          )
        ORDER BY ORDINAL_POSITION
    ");
    $statusColumnNames = array_map(fn (array $column) => (string) ($column['COLUMN_NAME'] ?? ''), $statusColumns);
    $statusColumnNames = array_values(array_filter($statusColumnNames, fn (string $column) => $column !== ''));
    $quotedStatusColumns = implode(', ', array_map(fn (string $column) => '[' . str_replace(']', ']]', $column) . ']', $statusColumnNames));
    $statusFieldValues = $quotedStatusColumns === '' ? [] : phptest35_fetch_all(
        $connection,
        'SELECT ' . $quotedStatusColumns . ' FROM dbo.tHF_WOEx WHERE acKey = ?',
        [$workOrderKey]
    );
    $documents = phptest35_fetch_all($connection, "
        SELECT
            m.acKeyView AS document_number,
            m.acDocType AS document_type,
            l.acType AS work_order_link_type,
            m.anValue AS document_value,
            m.adTimeIns AS created_at
        FROM dbo.tHF_LinkMoveWOEx AS l
        INNER JOIN dbo.tHE_Move AS m ON m.acKey = l.acKey
        WHERE LTRIM(RTRIM(l.acLnkKey)) = ?
          AND m.acDocType IN ('2005', '6400', '6600', '6100', '7100')
        ORDER BY m.adTimeIns, m.acKey
    ", [$workOrderKey]);

    if (PHP_SAPI === 'cli') {
        echo 'Pantheon database: ' . $database . PHP_EOL . 'Work-order status:' . PHP_EOL;
        phptest35_render_table([$workOrder]);
        echo PHP_EOL . 'All status/closing-related tHF_WOEx fields:' . PHP_EOL;
        phptest35_render_table($statusFieldValues);
        echo PHP_EOL . 'Linked closing documents:' . PHP_EOL;
        phptest35_render_table($documents);
        exit;
    }
    ?>
    <!doctype html>
    <html lang="bs">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Pantheon WO status check</title>
      <style>
        body{margin:0;background:#f5f7fb;color:#1d2939;font:14px/1.45 system-ui,sans-serif}.wrap{max-width:1200px;margin:28px auto;padding:0 20px}.card{background:#fff;border:1px solid #dfe5ef;border-radius:10px;padding:20px;margin-top:16px}.ok{color:#067647}.no{color:#b42318}.muted{color:#667085}.table-wrap{overflow:auto}table{border-collapse:collapse;min-width:100%}th,td{padding:9px 10px;border:1px solid #dfe5ef;text-align:left;white-space:nowrap}th{background:#eef4ff}form{display:flex;gap:10px;align-items:end;flex-wrap:wrap}label{display:grid;gap:4px;font-weight:600}input{width:230px;padding:8px 10px;border:1px solid #98a2b3;border-radius:6px;font:inherit}button{padding:9px 13px;background:#175cd3;color:#fff;border:0;border-radius:6px;font:inherit;cursor:pointer}
      </style>
    </head>
    <body><main class="wrap">
      <h1>Pantheon status check</h1>
      <p class="muted">Read-only check against the configured Pantheon target. No data is inserted, updated, or deleted.</p>
      <div class="card"><form method="get"><label>Radni nalog<input name="rn" value="<?= phptest35_h($rn) ?>" autocomplete="off"></label><button type="submit">Provjeri status</button></form></div>
      <div class="card"><h2 class="<?= ($workOrder['closed_in_pantheon'] ?? 'NO') === 'YES' ? 'ok' : 'no' ?>">Pantheon closed: <?= phptest35_h($workOrder['closed_in_pantheon'] ?? 'NO') ?></h2><p class="muted">Expected closed values: <code>acStatus=I</code>, <code>acStatusMF=Z</code>, <code>acReceiveFinished=Y</code>.</p><?php phptest35_render_table([$workOrder]); ?></div>
      <div class="card"><h2>All status/closing-related tHF_WOEx fields</h2><p class="muted">Columns whose names contain status, finish, receive, produced, issue, execution, close, or complete.</p><?php phptest35_render_table($statusFieldValues); ?></div>
      <div class="card"><h2>Linked closing documents</h2><?php phptest35_render_table($documents); ?></div>
    </main></body></html>
    <?php
} finally {
    sqlsrv_close($connection);
}
