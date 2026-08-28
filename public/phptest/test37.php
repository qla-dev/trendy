<?php

/**
 * Read-only operation timing and value audit.
 *
 * Browser: /phptest/test37.php?document=26-6600-003818
 * CLI:     php public/phptest/test37.php "document=26-6600-003818"
 *
 * Uses WORK_ORDER_TARGET_DB_* settings (with DB_* fallbacks). No write SQL
 * is permitted by this diagnostic.
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest37_env(string $key, ?string $default = null): ?string
{
    static $values = null;
    static $resolved = [];

    $processValue = getenv($key);
    if ($processValue !== false && trim((string) $processValue) !== '') {
        return trim((string) $processValue);
    }

    if ($values === null) {
        $values = [];
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        foreach (is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $value = trim($value);
            if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            $values[trim($name)] = $value;
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

function phptest37_fail(string $message): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    http_response_code(400);
    echo '<!doctype html><meta charset="utf-8"><pre>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}

function phptest37_fetch_all($connection, string $sql, array $params = []): array
{
    if (preg_match('/^\s*SELECT\b/i', $sql) !== 1) {
        phptest37_fail('Only read-only SELECT queries are allowed.');
    }

    $statement = sqlsrv_query($connection, $sql, $params, ['QueryTimeout' => 30]);
    if ($statement === false) {
        phptest37_fail('Pantheon query failed: ' . print_r(sqlsrv_errors(), true));
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($statement);

    return $rows;
}

function phptest37_number(mixed $value): float
{
    return is_numeric((string) $value) ? (float) $value : 0.0;
}

function phptest37_display(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    return trim((string) $value);
}

function phptest37_format(float $value): string
{
    return number_format($value, 6, '.', '');
}

function phptest37_match(float $left, float $right): string
{
    return abs($left - $right) <= 0.000001 ? 'PASS' : 'FAIL';
}

function phptest37_render(array $rows): void
{
    if ($rows === []) {
        echo PHP_SAPI === 'cli' ? "No operation rows found.\n" : '<p>No operation rows found.</p>';
        return;
    }

    $columns = array_keys($rows[0]);
    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 280) . PHP_EOL;
        foreach ($rows as $row) {
            echo implode(' | ', array_map(static fn (string $column): string => (string) ($row[$column] ?? ''), $columns)) . PHP_EOL;
        }
        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $column) {
        echo '<th>' . htmlspecialchars((string) $column, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $failed = str_contains(implode(' ', array_map('strval', $row)), 'FAIL');
        echo '<tr' . ($failed ? ' class="fail"' : '') . '>';
        foreach ($columns as $column) {
            echo '<td>' . htmlspecialchars((string) ($row[$column] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

$documentInput = trim((string) ($_GET['document'] ?? '26-6600-003818'));
$digits = preg_replace('/\D+/', '', $documentInput) ?: '';
if ($digits === '') {
    phptest37_fail('Enter a valid 6600 document number.');
}

$host = trim((string) phptest37_env('WORK_ORDER_TARGET_DB_HOST', phptest37_env('DB_HOST', '')));
$port = trim((string) phptest37_env('WORK_ORDER_TARGET_DB_PORT', phptest37_env('DB_PORT', '1433')));
$database = trim((string) phptest37_env('WORK_ORDER_TARGET_DB_DATABASE', phptest37_env('DB_DATABASE', '')));
$username = (string) phptest37_env('WORK_ORDER_TARGET_DB_USERNAME', phptest37_env('DB_USERNAME', ''));
$password = (string) phptest37_env('WORK_ORDER_TARGET_DB_PASSWORD', phptest37_env('DB_PASSWORD', ''));

if ($host === '' || $database === '' || $username === '') {
    phptest37_fail('Missing work-order target database settings.');
}

$connection = sqlsrv_connect($host . ($port !== '' ? ',' . $port : ''), [
    'Database' => $database,
    'UID' => $username,
    'PWD' => $password,
    'CharacterSet' => 'UTF-8',
    'Encrypt' => strtolower((string) phptest37_env('WORK_ORDER_TARGET_DB_ENCRYPT', 'true')) !== 'false',
    'TrustServerCertificate' => strtolower((string) phptest37_env('WORK_ORDER_TARGET_DB_TRUST_SERVER_CERTIFICATE', 'true')) !== 'false',
    'LoginTimeout' => 10,
]);
if ($connection === false) {
    phptest37_fail('Could not connect to ' . $database . ': ' . print_r(sqlsrv_errors(), true));
}

try {
    $document = phptest37_fetch_all($connection, '
        SELECT TOP 1
            m.acKey AS document_key,
            m.acKeyView AS document_number,
            wo.acKey AS work_order_key,
            wo.acKeyView AS work_order_number,
            wo.anPlanQty,
            wo.anProducedQty
        FROM dbo.tHE_Move m
        LEFT JOIN dbo.tHF_LinkMoveWOEx l ON l.acKey = m.acKey
        LEFT JOIN dbo.tHF_WOEx wo ON wo.acKey = l.acLnkKey
        WHERE m.acDocType = \'6600\'
          AND (
              REPLACE(REPLACE(LTRIM(RTRIM(m.acKeyView)), \'-\', \'\'), \' \', \'\') = ?
              OR REPLACE(REPLACE(LTRIM(RTRIM(m.acKey)), \'-\', \'\'), \' \', \'\') = ?
          )
        ORDER BY m.adTimeIns DESC
    ', [$digits, $digits])[0] ?? null;

    if ($document === null || trim((string) ($document['work_order_key'] ?? '')) === '') {
        phptest37_fail('6600 document or linked work order not found: ' . $documentInput);
    }

    $rows = phptest37_fetch_all($connection, '
        SELECT
            m.acKeyView AS document_number,
            CONVERT(varchar(19), m.adTimeIns, 120) AS document_created_at,
            mi.anQId AS move_item_qid,
            mi.anNo AS line_no,
            mi.acIdent AS operation_code,
            mi.anQty AS document_total_minutes,
            mi.anPrice AS price_per_minute,
            mi.anPVValue AS document_line_value,
            res.anQty AS resource_minutes_per_piece,
            res.anPlanQty AS resource_planned_minutes_per_piece,
            res.anQty1 AS resource_minutes_per_piece_1,
            res.anExecutionPerc AS resource_execution_percent,
            res.acIssueFinished AS resource_finished,
            w.acWorker AS worker,
            w.anTn AS worker_tn_minutes_per_piece,
            w.anTime AS worker_total_minutes,
            w.anHoldUp AS worker_downtime,
            w.adBeginTime AS worker_started,
            w.adEndTime AS worker_ended
        FROM dbo.tHE_Move m
        INNER JOIN dbo.tHE_MoveItem mi ON mi.acKey = m.acKey
        LEFT JOIN dbo.tHF_WOExItemWork w ON w.anMoveItemQId = mi.anQId
        LEFT JOIN dbo.tHF_WOExItemResources res ON res.anWOExItemQId = w.anWOExItemQid
        WHERE m.acKey = ? AND m.acDocType = \'6600\'
        ORDER BY mi.anNo, w.anSubNo
    ', [(string) $document['document_key']]);

    $quantity = phptest37_number($document['anPlanQty'] ?? 0);
    $lines = [];
    foreach ($rows as $row) {
        $key = (string) $row['document_number'] . '|' . (string) $row['move_item_qid'];
        if (!isset($lines[$key])) {
            $lines[$key] = [
                'document' => phptest37_display($row['document_number']),
                'document_created_at' => phptest37_display($row['document_created_at']),
                'line' => phptest37_display($row['line_no']),
                'operation' => phptest37_display($row['operation_code']),
                'document_minutes' => phptest37_number($row['document_total_minutes']),
                'price' => phptest37_number($row['price_per_minute']),
                'line_value' => phptest37_number($row['document_line_value']),
                'resource_tn' => phptest37_number($row['resource_minutes_per_piece']),
                'resource_plan_tn' => phptest37_number($row['resource_planned_minutes_per_piece']),
                'resource_tn_1' => phptest37_number($row['resource_minutes_per_piece_1']),
                'resource_percent' => phptest37_display($row['resource_execution_percent']),
                'resource_finished' => phptest37_display($row['resource_finished']),
                'workers' => [],
                'worker_total' => 0.0,
                'worker_expected_total' => 0.0,
            ];
        }

        if (trim((string) ($row['worker'] ?? '')) !== '') {
            $tn = phptest37_number($row['worker_tn_minutes_per_piece']);
            $time = phptest37_number($row['worker_total_minutes']);
            $lines[$key]['workers'][] = phptest37_display($row['worker'])
                . ' (Tn ' . phptest37_format($tn)
                . ', Time ' . phptest37_format($time)
                . ', downtime ' . phptest37_format(phptest37_number($row['worker_downtime']))
                . ', ' . phptest37_display($row['worker_started'])
                . '–' . phptest37_display($row['worker_ended']) . ')';
            $lines[$key]['worker_total'] += $time;
            $lines[$key]['worker_expected_total'] += $tn * $quantity;
        }
    }

    $report = [];
    foreach ($lines as $line) {
        $hasWorkerRows = $line['workers'] !== [];
        $resourceScreenTotal = $line['resource_tn'] * $quantity;
        $expectedValue = $line['document_minutes'] * $line['price'];
        $report[] = [
            '6600 document' => $line['document'],
            'created at' => $line['document_created_at'],
            'line / operation' => $line['line'] . ' / ' . $line['operation'],
            'produced quantity' => phptest37_format($quantity),
            'workers: Tn / Time / downtime / clock' => implode('; ', $line['workers']) ?: '[no worker rows]',
            'worker Time expected (Tn × qty)' => $hasWorkerRows ? phptest37_format($line['worker_expected_total']) : 'N/A',
            'worker Time stored' => $hasWorkerRows ? phptest37_format($line['worker_total']) : 'N/A',
            'worker Time check' => $hasWorkerRows
                ? phptest37_match($line['worker_total'], $line['worker_expected_total'])
                : 'FAIL: no worker rows',
            '6600 total minutes' => phptest37_format($line['document_minutes']),
            '6600 vs worker total' => $hasWorkerRows
                ? phptest37_match($line['document_minutes'], $line['worker_total'])
                : 'N/A: no worker rows',
            'resource Tn' => phptest37_format($line['resource_tn']),
            'Pantheon screen total (resource Tn × qty)' => phptest37_format($resourceScreenTotal),
            'screen total vs 6600' => $hasWorkerRows
                ? phptest37_match($resourceScreenTotal, $line['document_minutes'])
                : 'N/A: no worker resource',
            'resource plan Tn / Qty1' => phptest37_format($line['resource_plan_tn']) . ' / ' . phptest37_format($line['resource_tn_1']),
            'resource complete / %' => $line['resource_finished'] . ' / ' . $line['resource_percent'],
            'price per minute' => phptest37_format($line['price']),
            'expected line value' => phptest37_format($expectedValue),
            'stored line value' => phptest37_format($line['line_value']),
            'price check' => phptest37_match($expectedValue, $line['line_value']),
        ];
    }

    if (PHP_SAPI !== 'cli') {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>RN timing audit</title>';
        echo '<style>body{font:14px/1.45 Arial,sans-serif;margin:24px;color:#1f2937;background:#f7f8fb}.form{display:flex;gap:10px;align-items:end;padding:14px;background:#fff;border:1px solid #d9e1eb;border-radius:8px}.form label{display:grid;gap:5px;font-weight:600}.form input{height:32px;width:220px}.form button{height:36px}.table-wrap{overflow:auto;background:#fff;border:1px solid #d9e1eb;border-radius:8px;margin-top:16px}table{border-collapse:collapse;font-size:12px;min-width:100%}th,td{padding:8px;border:1px solid #e5e7eb;text-align:left;vertical-align:top;white-space:nowrap}th{background:#edf2f8}.fail{background:#fff3cd}.note{margin:12px 0;padding:10px;background:#e8f4ff;border-radius:6px}</style></head><body>';
        echo '<h1>Operation time / Tn / value audit</h1>';
        echo '<form class="form" method="get"><label>6600 document<input name="document" value="' . htmlspecialchars($documentInput, ENT_QUOTES, 'UTF-8') . '"></label><button type="submit">Check</button></form>';
        echo '<p class="note">Checks: worker anTime = worker anTn × produced quantity; 6600 minutes = summed worker anTime; Pantheon screen total = resource Tn × quantity; line value = minutes × price.</p>';
        echo '<p>Database: <strong>' . htmlspecialchars($database, ENT_QUOTES, 'UTF-8') . '</strong> · Document: <strong>' . htmlspecialchars(phptest37_display($document['document_number']), ENT_QUOTES, 'UTF-8') . '</strong> · RN: <strong>' . htmlspecialchars(phptest37_display($document['work_order_number']), ENT_QUOTES, 'UTF-8') . '</strong></p>';
    } else {
        echo 'Database: ' . $database . ' | Document: ' . phptest37_display($document['document_number']) . ' | RN: ' . phptest37_display($document['work_order_number']) . PHP_EOL;
    }

    phptest37_render($report);

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
} finally {
    sqlsrv_close($connection);
}
