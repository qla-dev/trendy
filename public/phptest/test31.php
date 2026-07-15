<?php

/*
 * test31.php
 * Read-only RN planned-quantity/BOM value inspector.
 *
 * This script is intentionally locked to BA_TRENDY_TESTNA. It shows only:
 * - tHF_WOEx.anPlanQty (RN header quantity)
 * - tHF_WOExItem.anPlanQty and anQty1 (RN position quantities)
 * - tHF_SetPrSt.anQty1, anGrossQty, anNetQty and acQtyFormula (current BOM)
 *
 * Browser: /phptest/test31.php?rn=26-6000-003619
 * CLI:     php public/phptest/test31.php "rn=26-6000-003619"
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

const PHPTEST31_DATABASE = 'BA_TRENDY_TESTNA';

function phptest31_env(string $key, ?string $default = null): ?string
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
        return (string) preg_replace_callback(
            '/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            function (array $matches) use (&$resolve, &$values, $stack): string {
                $referenced = $matches[1];

                if (!array_key_exists($referenced, $values) || in_array($referenced, $stack, true)) {
                    return $matches[0];
                }

                return $resolve($values[$referenced], [...$stack, $referenced]);
            },
            $value
        );
    };

    return $resolved[$key] = $resolve($values[$key], [$key]);
}

function phptest31_option(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return trim((string) (is_array($value) ? end($value) : $value));
}

function phptest31_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest31_value(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    return trim((string) $value);
}

function phptest31_fail(string $message): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    http_response_code(400);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN quantity inspector</title></head><body><p>' . phptest31_h($message) . '</p></body></html>';
    exit;
}

function phptest31_connect()
{
    $host = (string) phptest31_env('WORK_ORDER_TARGET_DB_HOST', phptest31_env('DB_HOST', ''));
    $port = (string) phptest31_env('WORK_ORDER_TARGET_DB_PORT', phptest31_env('DB_PORT', '1433'));
    $username = (string) phptest31_env('WORK_ORDER_TARGET_DB_USERNAME', phptest31_env('DB_USERNAME', ''));
    $password = (string) phptest31_env('WORK_ORDER_TARGET_DB_PASSWORD', phptest31_env('DB_PASSWORD', ''));

    $connection = sqlsrv_connect($host . ($port !== '' ? ',' . $port : ''), [
        'Database' => PHPTEST31_DATABASE,
        'UID' => $username,
        'PWD' => $password,
        'CharacterSet' => 'UTF-8',
        'Encrypt' => strtolower((string) phptest31_env('WORK_ORDER_TARGET_DB_ENCRYPT', 'true')) !== 'false',
        'TrustServerCertificate' => strtolower((string) phptest31_env('WORK_ORDER_TARGET_DB_TRUST_SERVER_CERTIFICATE', 'true')) !== 'false',
        'LoginTimeout' => 10,
    ]);

    if (!$connection) {
        phptest31_fail('Could not connect to BA_TRENDY_TESTNA: ' . print_r(sqlsrv_errors(), true));
    }

    return $connection;
}

function phptest31_fetch_all($connection, string $sql, array $params = []): array
{
    $statement = sqlsrv_query($connection, $sql, $params, ['QueryTimeout' => 60]);

    if (!$statement) {
        phptest31_fail('Query failed: ' . print_r(sqlsrv_errors(), true));
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($statement);

    return $rows;
}

function phptest31_rn_candidates(string $rn): array
{
    $normalized = preg_replace('/\D+/', '', $rn) ?? '';

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

    return array_values(array_unique($candidates));
}

function phptest31_render_rows(array $rows): void
{
    if ($rows === []) {
        echo PHP_SAPI === 'cli' ? "No matching positions.\n" : '<p class="muted">No matching positions.</p>';
        return;
    }

    $columns = array_keys($rows[0]);
    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL . str_repeat('-', 150) . PHP_EOL;
        foreach ($rows as $row) {
            echo implode(' | ', array_map(static fn ($column) => phptest31_value($row[$column] ?? null), $columns)) . PHP_EOL;
        }
        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $column) {
        echo '<th>' . phptest31_h((string) $column) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            echo '<td>' . phptest31_h(phptest31_value($row[$column] ?? null)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

$rnInput = phptest31_option('rn', '26-6000-003619');
$candidates = phptest31_rn_candidates($rnInput);

if ($candidates === []) {
    phptest31_fail('Enter a valid RN number.');
}

$connection = phptest31_connect();

try {
    $where = [];
    $params = [];
    foreach ($candidates as $candidate) {
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKey), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKeyView), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
    }

    $headers = phptest31_fetch_all(
        $connection,
        'SELECT TOP 1 acKey, acKeyView, acIdent, anPlanQty FROM dbo.tHF_WOEx WHERE ' . implode(' OR ', $where) . ' ORDER BY adTimeIns DESC, acKey DESC',
        $params
    );
    $header = $headers[0] ?? null;

    if ($header === null) {
        phptest31_fail('RN was not found in BA_TRENDY_TESTNA.');
    }

    $rows = phptest31_fetch_all($connection, '
        SELECT
            i.anNo AS [RN position],
            i.anVariant AS [Variant],
            i.acIdent AS [Material / operation],
            i.anPlanQty AS [WOExItem anPlanQty],
            i.anQty1 AS [WOExItem anQty1],
            ps.anQty1 AS [SetPrSt anQty1],
            ps.anGrossQty AS [SetPrSt anGrossQty],
            ps.anNetQty AS [SetPrSt anNetQty],
            ps.acQtyFormula AS [SetPrSt formula]
        FROM dbo.tHF_WOExItem i
        LEFT JOIN dbo.tHF_SetPrSt ps
            ON ps.acIdent = ?
            AND ps.acIdentChild = i.acIdent
            AND ps.anNo = i.anNo
            AND ps.anVariant = i.anVariant
        WHERE i.acKey = ?
        ORDER BY i.anNo, i.anVariant, i.anQId
    ', [trim((string) $header['acIdent']), trim((string) $header['acKey'])]);

    if (PHP_SAPI !== 'cli') {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>RN planned-quantity inspector</title>';
        echo '<style>body{font:14px/1.45 Arial,sans-serif;margin:24px;color:#1f2937;background:#f7f8fb}h1{margin:0 0 8px}.note{padding:10px 12px;background:#fff7dd;border:1px solid #edcf70;border-radius:6px;margin:10px 0}.form{display:flex;flex-wrap:wrap;align-items:end;gap:12px;padding:16px;background:#fff;border:1px solid #d9e1eb;border-radius:8px;margin:16px 0}.form label{display:grid;gap:5px;font-weight:600;color:#334155}.form input{height:36px;width:240px;padding:0 10px;border:1px solid #cbd5e1;border-radius:6px;font:inherit;box-sizing:border-box}.form input:focus{outline:2px solid #93c5fd;border-color:#2563eb}.form button{height:36px;padding:0 14px;border:0;border-radius:6px;background:#2563eb;color:#fff;font:600 14px Arial,sans-serif;cursor:pointer}.table-wrap{overflow:auto;background:#fff;border:1px solid #d9e1eb;border-radius:6px;margin:8px 0 24px}table{border-collapse:collapse;min-width:100%;font-size:13px}th,td{padding:8px 10px;border-right:1px solid #e6ebf1;border-bottom:1px solid #e0e6ee;text-align:left;white-space:nowrap}th{background:#edf2f8}.muted{color:#64748b}</style></head><body>';
        echo '<h1>RN planned-quantity inspector</h1>';
        echo '<div class="note">Read-only: BA_TRENDY_TESTNA only. The BOM columns are the current product-structure values, not a historical BOM snapshot.</div>';
        echo '<form class="form" method="get"><label for="rn">RN number<input id="rn" name="rn" value="' . phptest31_h($rnInput) . '" placeholder="26-6000-003619" autocomplete="off"></label><button type="submit">Show values</button></form>';
    }

    $headerRows = [[
        'RN' => phptest31_value($header['acKeyView'] ?? $header['acKey'] ?? null),
        'Finished product' => phptest31_value($header['acIdent'] ?? null),
        'WOEx anPlanQty' => phptest31_value($header['anPlanQty'] ?? null),
    ]];

    if (PHP_SAPI === 'cli') {
        echo "RN header\n";
    } else {
        echo '<h2>RN header</h2>';
    }
    phptest31_render_rows($headerRows);

    if (PHP_SAPI === 'cli') {
        echo "\nRN positions and current BOM values\n";
    } else {
        echo '<h2>RN positions and current BOM values</h2>';
    }
    phptest31_render_rows($rows);

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
} finally {
    sqlsrv_close($connection);
}
