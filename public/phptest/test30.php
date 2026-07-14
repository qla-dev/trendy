<?php

/*
 * test30.php
 * Read-only work-order schedule diagnostic.
 *
 * Shows every stored work-order date/time field, the linked order-item
 * delivery deadline, and the protection-based start-date rule used by
 * eNalog. It never substitutes a calculated value for a stored value.
 *
 * Parameters:
 * - rn=2660000003618
 * - schema=dbo
 */

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest30_env(string $key, ?string $default = null): ?string
{
    static $values = null;
    static $resolved = [];

    if ($values === null) {
        $values = [];
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        $lines = is_file($path) && is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES) : false;

        foreach ($lines ?: [] as $line) {
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
                $referencedKey = $matches[1];

                if (!array_key_exists($referencedKey, $values) || in_array($referencedKey, $stack, true)) {
                    return $matches[0];
                }

                return $resolve($values[$referencedKey], [...$stack, $referencedKey]);
            },
            $value
        );
    };

    return $resolved[$key] = $resolve($values[$key], [$key]);
}

function phptest30_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest30_option(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    if (is_array($value)) {
        $value = end($value);
    }

    return trim((string) $value);
}

function phptest30_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Work-order schedule diagnostic</title></head><body>';
    echo '<pre>' . phptest30_h($message) . '</pre></body></html>';
    exit;
}

function phptest30_bool_env(string $key, bool $default): bool
{
    $value = strtolower(trim((string) phptest30_env($key, $default ? 'true' : 'false')));

    if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function phptest30_connect(): array
{
    $host = (string) phptest30_env('WORK_ORDER_TARGET_DB_HOST', phptest30_env('DB_HOST', ''));
    $port = (string) phptest30_env('WORK_ORDER_TARGET_DB_PORT', phptest30_env('DB_PORT', '1433'));
    $database = (string) phptest30_env('WORK_ORDER_TARGET_DB_DATABASE', 'BA_TRENDY_TESTNA');
    $username = (string) phptest30_env('WORK_ORDER_TARGET_DB_USERNAME', phptest30_env('DB_USERNAME', ''));
    $password = (string) phptest30_env('WORK_ORDER_TARGET_DB_PASSWORD', phptest30_env('DB_PASSWORD', ''));

    if ($host === '' || $username === '') {
        phptest30_fail('Missing work-order target database host or username.');
    }

    $server = $host . ($port !== '' ? ',' . $port : '');
    $conn = sqlsrv_connect($server, [
        'Database' => $database,
        'UID' => $username,
        'PWD' => $password,
        'CharacterSet' => 'UTF-8',
        'Encrypt' => phptest30_bool_env('WORK_ORDER_TARGET_DB_ENCRYPT', false),
        'TrustServerCertificate' => phptest30_bool_env('WORK_ORDER_TARGET_DB_TRUST_SERVER_CERTIFICATE', true),
        'LoginTimeout' => 10,
    ]);

    if (!$conn) {
        phptest30_fail(sqlsrv_errors());
    }

    return [$conn, $database];
}

function phptest30_identifier(string $value, string $fallback): string
{
    return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1 ? $value : $fallback;
}

function phptest30_table(string $schema, string $table): string
{
    return '[' . str_replace(']', ']]', $schema) . '].[' . str_replace(']', ']]', $table) . ']';
}

function phptest30_fetch_one($conn, string $sql, array $params = []): ?array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => 30]);

    if (!$stmt) {
        phptest30_fail(sqlsrv_errors());
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    return $row === false ? null : $row;
}

function phptest30_value($value): string
{
    if ($value === null) {
        return '';
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    return trim((string) $value);
}

function phptest30_date($value): ?DateTimeImmutable
{
    $value = phptest30_value($value);

    if ($value === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return null;
    }
}

function phptest30_date_value(array $row, array $columns, ?string &$source): ?DateTimeImmutable
{
    foreach ($columns as $column) {
        if (!array_key_exists($column, $row)) {
            continue;
        }

        $date = phptest30_date($row[$column]);
        if ($date !== null) {
            $source = $column;
            return $date->setTime(0, 0, 0);
        }
    }

    return null;
}

function phptest30_normalize_protection(string $value): string
{
    $value = trim($value);

    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

function phptest30_protection_lead_weeks(string $protection): int
{
    $normalized = phptest30_normalize_protection($protection);

    if ($normalized === '') {
        return 2;
    }

    return in_array($normalized, ['plazma+lakiranje', 'plazmanitriranje'], true)
        ? 4
        : 3;
}

/**
 * @return array<string, string>
 */
function phptest30_work_order_date_rows(array $workOrder): array
{
    $preferredColumns = [
        'adSchedStartTime',
        'adSchedEndTime',
        'adDeliveryDeadline',
        'adDateOut',
        'adDate',
        'adLnkDate',
        'adWOFinishDate',
        'adDateIns',
        'adTimeIns',
        'adTimeChg',
    ];
    $dateColumns = [];

    foreach ($preferredColumns as $column) {
        if (array_key_exists($column, $workOrder)) {
            $dateColumns[] = $column;
        }
    }

    foreach (array_keys($workOrder) as $column) {
        if (in_array($column, $dateColumns, true)
            || preg_match('/^ad.*(?:date|time|start|end|deadline|delivery|due)/i', (string) $column) !== 1) {
            continue;
        }

        $dateColumns[] = (string) $column;
    }

    $rows = [];

    foreach ($dateColumns as $column) {
        $rows['Stored ' . $column] = phptest30_display_datetime(phptest30_date($workOrder[$column] ?? null));
    }

    return $rows;
}

function phptest30_display_date(?DateTimeImmutable $date): string
{
    return $date === null ? '-' : $date->format('d.m.Y');
}

function phptest30_display_datetime(?DateTimeImmutable $date): string
{
    if ($date === null) {
        return '-';
    }

    return $date->format('H:i:s') === '00:00:00'
        ? $date->format('d.m.Y')
        : $date->format('d.m.Y H:i');
}

function phptest30_render_rows(array $rows): void
{
    if (PHP_SAPI === 'cli') {
        foreach ($rows as $label => $value) {
            echo $label . ': ' . $value . PHP_EOL;
        }

        return;
    }

    echo '<table style="border-collapse:collapse;min-width:620px">';
    foreach ($rows as $label => $value) {
        echo '<tr><th style="border:1px solid #ccc;padding:6px;text-align:left">'
            . phptest30_h((string) $label)
            . '</th><td style="border:1px solid #ccc;padding:6px">'
            . phptest30_h((string) $value)
            . '</td></tr>';
    }
    echo '</table>';
}

$defaultSchema = (string) phptest30_env('DB_SCHEMA', 'dbo');
$schema = phptest30_identifier(phptest30_option('schema', $defaultSchema), 'dbo');
$workOrderTable = phptest30_identifier(phptest30_option('work_order_table', 'tHF_WOEx'), 'tHF_WOEx');
$orderItemTable = phptest30_identifier(phptest30_option('order_item_table', 'tHE_OrderItem'), 'tHE_OrderItem');
$rn = phptest30_option('rn', '2660000003618');

try {
    [$conn, $database] = phptest30_connect();

    $workOrder = phptest30_fetch_one(
        $conn,
        'SELECT TOP 1 * FROM ' . phptest30_table($schema, $workOrderTable)
            . ' WHERE acKey = ? OR acKeyView = ? ORDER BY adTimeIns DESC, acKey DESC',
        [$rn, $rn]
    );

    if ($workOrder === null) {
        phptest30_fail('Work order was not found: ' . $rn);
    }

    $orderKey = phptest30_value($workOrder['acLnkKey'] ?? null);
    $orderPosition = phptest30_value($workOrder['anLnkNo'] ?? null);
    $orderItem = null;

    if ($orderKey !== '' && $orderPosition !== '') {
        $orderItem = phptest30_fetch_one(
            $conn,
            'SELECT TOP 1 * FROM ' . phptest30_table($schema, $orderItemTable)
                . ' WHERE acKey = ? AND anNo = ? ORDER BY adTimeIns DESC, anQId DESC',
            [$orderKey, $orderPosition]
        );
    }

    $deadlineSource = '';
    $deliveryDeadline = is_array($orderItem)
        ? phptest30_date_value($orderItem, ['adDeliveryDeadline', 'adDateOut', 'adDueDate', 'adDeliveryDate'], $deadlineSource)
        : null;

    if ($deliveryDeadline === null) {
        $deliveryDeadline = phptest30_date_value(
            $workOrder,
            ['adDeliveryDeadline', 'adDateOut'],
            $deadlineSource
        );
        $deadlineSource = $deadlineSource !== '' ? 'work order.' . $deadlineSource : '';
    } else {
        $deadlineSource = 'order item.' . $deadlineSource;
    }

    $storedStart = phptest30_date($workOrder['adSchedStartTime'] ?? null);
    $storedEnd = phptest30_date($workOrder['adSchedEndTime'] ?? null);
    $workOrderNote = phptest30_value($workOrder['acNote'] ?? null);
    $isEnaLog = stripos($workOrderNote, 'eNalog.app') !== false;
    $protection = phptest30_value($workOrder['acCostDrv'] ?? null);
    $leadWeeks = phptest30_protection_lead_weeks($protection);
    $plannedStart = $deliveryDeadline?->modify('-' . ($leadWeeks * 7) . ' days')->setTime(0, 0, 0) ?? $storedStart;

    if ($plannedStart !== null && $deliveryDeadline !== null && $isEnaLog) {
        $plannedStart = $plannedStart->setTime(14, 0, 0);
    } elseif ($plannedStart !== null && $deliveryDeadline !== null && $storedStart !== null) {
        $plannedStart = $plannedStart->setTime(
            (int) $storedStart->format('H'),
            (int) $storedStart->format('i'),
            (int) $storedStart->format('s')
        );
    }

    $plannedEnd = $deliveryDeadline ?? $storedEnd;

    if ($plannedEnd !== null && $deliveryDeadline !== null && $isEnaLog) {
        $plannedEnd = $plannedEnd->setTime(14, 0, 0);
    }

    if (PHP_SAPI !== 'cli') {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Work-order schedule diagnostic</title>';
        echo '<style>body{font:14px/1.45 Arial,sans-serif;margin:24px;color:#222}h1{margin-bottom:8px}.note{margin:12px 0;padding:10px;background:#eef7ff;border:1px solid #b9d9f5}table{margin-top:14px}</style>';
        echo '</head><body>';
    }

    echo PHP_SAPI === 'cli'
        ? PHP_EOL . 'Work-order schedule diagnostic' . PHP_EOL . str_repeat('=', 32) . PHP_EOL
        : '<h1>Work-order schedule diagnostic</h1>';

    if (PHP_SAPI !== 'cli') {
        echo '<div class="note">Read-only check. “Stored” values come directly from the work-order row. “Expected” is calculated from the linked delivery deadline and the selected protection.</div>';
    }

    $rows = [
        'Database' => $database,
        'Work order' => phptest30_value($workOrder['acKeyView'] ?? $workOrder['acKey'] ?? $rn),
        'Work-order key' => phptest30_value($workOrder['acKey'] ?? null),
        'Linked order key' => $orderKey !== '' ? $orderKey : '-',
        'Linked order position' => $orderPosition !== '' ? $orderPosition : '-',
        'Created via eNalog' => $isEnaLog ? 'YES' : 'NO',
        'Protection (acCostDrv)' => $protection !== '' ? $protection : 'None selected',
        'Applied lead time' => $leadWeeks . ' week(s)',
        'Delivery deadline source' => $deadlineSource !== '' ? $deadlineSource : '-',
        'Order-item delivery deadline' => phptest30_display_date($deliveryDeadline),
        'Expected Planirani start' => phptest30_display_datetime($plannedStart),
        'Expected Planirani kraj / end' => phptest30_display_datetime($plannedEnd),
        'Stored start matches expected date' => $storedStart !== null && $plannedStart !== null
            ? ($storedStart->format('Y-m-d') === $plannedStart->format('Y-m-d') ? 'YES' : 'NO')
            : 'N/A',
        'Stored start and end are the same date' => $storedStart !== null && $storedEnd !== null
            ? ($storedStart->format('Y-m-d') === $storedEnd->format('Y-m-d') ? 'YES - check required' : 'NO')
            : 'N/A',
        'Rule' => 'delivery deadline - ' . $leadWeeks . ' week(s)',
        'eNalog start/end time rule' => '14:00',
    ];

    foreach (phptest30_work_order_date_rows($workOrder) as $label => $value) {
        $rows[$label] = $value;
    }

    phptest30_render_rows($rows);

    sqlsrv_close($conn);

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
} catch (Throwable $exception) {
    if (is_resource($conn)) {
        sqlsrv_close($conn);
    }

    phptest30_fail($exception);
}
