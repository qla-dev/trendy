<?php

/**
 * Read-only Pantheon protection catalogue inspector.
 *
 * Browser: /phptest/test36.php?code=Lakiranje
 * CLI:     php public/phptest/test36.php "code=Lakiranje"
 *
 * Shows the exact dbo.tHE_CostDrv columns used by the eNalog protection
 * feature. It never inserts, updates, or deletes Pantheon data.
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function test36_env(string $key, ?string $default = null): ?string
{
    static $values = null;
    static $resolved = [];

    if ($values === null) {
        $values = [];
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        foreach (is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$name, $value] = explode('=', $line, 2);
            $value = trim($value);
            if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) $value = substr($value, 1, -1);
            $values[trim($name)] = $value;
        }
    }

    if (!array_key_exists($key, $values)) return $default;
    if (array_key_exists($key, $resolved)) return $resolved[$key];

    $resolve = function (string $value, array $stack = []) use (&$resolve, &$values): string {
        return (string) preg_replace_callback('/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/', function (array $matches) use (&$resolve, &$values, $stack): string {
            $referenced = $matches[1];
            if (!array_key_exists($referenced, $values) || in_array($referenced, $stack, true)) return $matches[0];
            return $resolve($values[$referenced], [...$stack, $referenced]);
        }, $value);
    };

    return $resolved[$key] = $resolve((string) $values[$key], [$key]);
}

function test36_value(mixed $value): string
{
    if ($value === null) return 'NULL';
    if ($value instanceof DateTimeInterface) return $value->format('Y-m-d H:i:s');
    return trim((string) $value);
}

function test36_h(mixed $value): string
{
    return htmlspecialchars(test36_value($value), ENT_QUOTES, 'UTF-8');
}

function test36_query($connection, string $sql, array $params = []): array
{
    if (preg_match('/^\s*SELECT\b/i', $sql) !== 1) throw new RuntimeException('Only SELECT queries are allowed.');
    $statement = sqlsrv_query($connection, $sql, $params, ['QueryTimeout' => 30]);
    if ($statement === false) throw new RuntimeException(print_r(sqlsrv_errors(), true));
    $rows = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) $rows[] = $row;
    sqlsrv_free_stmt($statement);
    return $rows;
}

function test36_table(array $rows): void
{
    if ($rows === []) { echo '<p class="muted">Nema podataka.</p>'; return; }
    $columns = array_keys($rows[0]);
    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $column) echo '<th>' . test36_h($column) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) echo '<td>' . test36_h($row[$column] ?? null) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

$code = trim((string) ($_GET['code'] ?? ''));
$host = (string) test36_env('DB_HOST', '');
$port = (string) test36_env('DB_PORT', '1433');
$connection = sqlsrv_connect($host . ($port !== '' ? ',' . $port : ''), [
    'Database' => test36_env('DB_DATABASE', ''),
    'UID' => test36_env('DB_USERNAME', ''),
    'PWD' => str_replace('}', '}}', (string) test36_env('DB_PASSWORD', '')),
    'CharacterSet' => 'UTF-8',
    'Encrypt' => strtolower((string) test36_env('DB_ENCRYPT', 'true')) !== 'false',
    'TrustServerCertificate' => strtolower((string) test36_env('DB_TRUST_SERVER_CERTIFICATE', 'true')) !== 'false',
    'LoginTimeout' => 10,
]);

if ($connection === false) {
    http_response_code(500);
    exit('<pre>' . test36_h(print_r(sqlsrv_errors(), true)) . '</pre>');
}

try {
    $schema = test36_query($connection, "
        SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'tHE_CostDrv'
          AND COLUMN_NAME IN ('acCostDrv', 'acName', 'anFieldNA', 'anQId', 'acStatus', 'acClassif', 'acConsignee', 'acDept', 'acSuprCostDrv', 'acResponsiblePerson', 'anUserIns', 'anUserChg', 'adTimeIns', 'adTimeChg')
        ORDER BY ORDINAL_POSITION
    ");
    $catalogue = test36_query($connection, "
        SELECT acCostDrv AS code, acName AS name, anFieldNA AS lead_time_weeks, acStatus AS status, acNote AS note, anQId AS qid
        FROM dbo.tHE_CostDrv
        WHERE LTRIM(RTRIM(ISNULL(acCostDrv, ''))) <> ''
        ORDER BY acCostDrv
    ");
    $selected = $code === '' ? [] : test36_query($connection, "
        SELECT acCostDrv, acName, anFieldNA, anFieldNB, anFieldNC, anFieldND, anFieldNE, anFieldNF, anFieldNG, anFieldNH, anFieldNI, anFieldNJ, acNote, anQId
        FROM dbo.tHE_CostDrv
        WHERE LTRIM(RTRIM(acCostDrv)) = ?
    ", [$code]);
} catch (Throwable $exception) {
    sqlsrv_close($connection);
    http_response_code(500);
    exit('<pre>' . test36_h($exception->getMessage()) . '</pre>');
}
sqlsrv_close($connection);
?>
<!doctype html><html lang="bs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pantheon zaštite provjera</title>
<style>body{margin:0;background:#f5f7fb;color:#1d2939;font:14px/1.45 system-ui,sans-serif}.wrap{max-width:1400px;margin:28px auto;padding:0 20px}.card{background:#fff;border:1px solid #dfe5ef;border-radius:10px;padding:20px;margin-top:16px}.muted{color:#667085}.table-wrap{overflow:auto}table{border-collapse:collapse;min-width:100%}th,td{padding:8px 10px;border:1px solid #dfe5ef;text-align:left;white-space:nowrap}th{background:#eef4ff}form{display:flex;gap:10px;align-items:end}label{display:grid;gap:4px;font-weight:600}input{width:280px;padding:8px 10px;border:1px solid #98a2b3;border-radius:6px;font:inherit}button{padding:9px 13px;background:#175cd3;color:#fff;border:0;border-radius:6px;font:inherit}</style></head>
<body><main class="wrap"><h1>Pantheon zaštite – read-only provjera</h1><p class="muted">Ne mijenja podatke. Sedmice se čitaju iz <code>dbo.tHE_CostDrv.anFieldNA</code>.</p>
<div class="card"><form method="get"><label>Kod zaštite za poređenje<input name="code" value="<?= test36_h($code) ?>" placeholder="npr. Lakiranje"></label><button>Provjeri</button></form></div>
<div class="card"><h2>Kolone koje eNalog koristi pri dodavanju zaštite</h2><?php test36_table($schema); ?></div>
<div class="card"><h2>Postojeće zaštite</h2><p class="muted">Vrijednost 0 znači da još nema eksplicitno upisanih sedmica u Pantheonu.</p><?php test36_table($catalogue); ?></div>
<?php if ($code !== ''): ?><div class="card"><h2>Puni set trajanja za “<?= test36_h($code) ?>”</h2><?php test36_table($selected); ?></div><?php endif; ?>
</main></body></html>
