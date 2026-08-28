<?php

/*
 * Shared read-only comparison engine for test32.php and test33.php.
 *
 * The database is resolved from WORK_ORDER_TARGET_DB_DATABASE (falling back
 * to DB_DATABASE). This file contains no INSERT, UPDATE, DELETE, MERGE,
 * EXEC or DDL statement.
 */

declare(strict_types=1);

if (!defined('PHPTEST_CLOSE_KIND') || !defined('PHPTEST_CLOSE_DOC_TYPE') || !defined('PHPTEST_CLOSE_TITLE')) {
    throw new RuntimeException('The closing diagnostic wrapper is incomplete.');
}

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

const PHPTEST_CLOSE_DEFAULT_PANTHEON_RN = '26-6000-003429';
const PHPTEST_CLOSE_DEFAULT_ENALOG_RN = '26-6000-003059';

function phptest_close_env(string $key, ?string $default = null): ?string
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

function phptest_close_database(): string
{
    return trim((string) phptest_close_env(
        'WORK_ORDER_TARGET_DB_DATABASE',
        phptest_close_env('DB_DATABASE', '')
    ));
}

function phptest_close_option(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return trim((string) (is_array($value) ? end($value) : $value));
}

function phptest_close_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest_close_fail(string $message): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    http_response_code(400);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Closing diagnostic</title></head><body><pre>'
        . phptest_close_h($message)
        . '</pre></body></html>';
    exit;
}

function phptest_close_connect()
{
    $host = (string) phptest_close_env('WORK_ORDER_TARGET_DB_HOST', phptest_close_env('DB_HOST', ''));
    $port = (string) phptest_close_env('WORK_ORDER_TARGET_DB_PORT', phptest_close_env('DB_PORT', '1433'));
    $username = (string) phptest_close_env('WORK_ORDER_TARGET_DB_USERNAME', phptest_close_env('DB_USERNAME', ''));
    $password = (string) phptest_close_env('WORK_ORDER_TARGET_DB_PASSWORD', phptest_close_env('DB_PASSWORD', ''));
    $database = phptest_close_database();

    if ($host === '' || $username === '' || $database === '') {
        phptest_close_fail('Missing work-order target database connection settings.');
    }

    $connection = sqlsrv_connect($host . ($port !== '' ? ',' . $port : ''), [
        'Database' => $database,
        'UID' => $username,
        'PWD' => $password,
        'CharacterSet' => 'UTF-8',
        'Encrypt' => strtolower((string) phptest_close_env('WORK_ORDER_TARGET_DB_ENCRYPT', 'true')) !== 'false',
        'TrustServerCertificate' => strtolower((string) phptest_close_env('WORK_ORDER_TARGET_DB_TRUST_SERVER_CERTIFICATE', 'true')) !== 'false',
        'LoginTimeout' => 10,
    ]);

    if (!$connection) {
        phptest_close_fail('Could not connect to ' . $database . ': ' . print_r(sqlsrv_errors(), true));
    }

    return $connection;
}

function phptest_close_fetch_all($connection, string $sql, array $params = []): array
{
    if (preg_match('/^\s*(SELECT|WITH)\b/i', $sql) !== 1) {
        phptest_close_fail('Blocked non-read-only SQL in closing diagnostic.');
    }

    $statement = sqlsrv_query($connection, $sql, $params, ['QueryTimeout' => 60]);

    if (!$statement) {
        phptest_close_fail('Query failed: ' . print_r(sqlsrv_errors(), true));
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($statement);

    return $rows;
}

function phptest_close_fetch_one($connection, string $sql, array $params = []): ?array
{
    return phptest_close_fetch_all($connection, $sql, $params)[0] ?? null;
}

function phptest_close_rn_candidates(string $rn): array
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

function phptest_close_find_work_order($connection, string $rn): array
{
    $candidates = phptest_close_rn_candidates($rn);

    if ($candidates === []) {
        phptest_close_fail('Invalid RN number: ' . $rn);
    }

    $conditions = [];
    $params = [];
    foreach ($candidates as $candidate) {
        $conditions[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKey), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
        $conditions[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKeyView), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
    }

    $row = phptest_close_fetch_one(
        $connection,
        'SELECT TOP 1 * FROM dbo.tHF_WOEx WHERE ' . implode(' OR ', $conditions) . ' ORDER BY adTimeIns DESC, acKey DESC',
        $params
    );

    if ($row === null) {
        phptest_close_fail('RN not found in ' . phptest_close_database() . ': ' . $rn);
    }

    return $row;
}

function phptest_close_rows_by_ids($connection, string $table, string $column, array $ids): array
{
    $ids = array_values(array_filter(array_unique($ids), static fn ($value): bool => $value !== null && $value !== ''));

    if ($ids === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));

    return phptest_close_fetch_all(
        $connection,
        "SELECT * FROM dbo.{$table} WHERE {$column} IN ({$placeholders})",
        $ids
    );
}

function phptest_close_doc_for_work_order($connection, string $workOrderKey, string $docType): ?array
{
    return phptest_close_fetch_one($connection, '
        SELECT TOP 1 m.*
        FROM dbo.tHE_Move m
        INNER JOIN dbo.tHF_LinkMoveWOEx link ON link.anMoveQId = m.anQId
        WHERE link.acLnkKey = ? AND m.acDocType = ?
        ORDER BY m.adTimeIns DESC, m.anQId DESC
    ', [$workOrderKey, $docType]);
}

function phptest_close_record_id(string $prefix, array $row, array $columns): string
{
    $parts = [];
    foreach ($columns as $column) {
        $parts[] = phptest_close_value($row[$column] ?? null);
    }

    return $prefix . ':' . implode(':', $parts);
}

function phptest_close_add_record(array &$records, string $table, string $recordId, ?array $row): void
{
    $records[$table . '|' . $recordId] = [
        'table' => $table,
        'record_id' => $recordId,
        'row' => $row,
    ];
}

function phptest_close_add_numbered_records(
    array &$records,
    string $table,
    string $prefix,
    array $rows,
    callable $baseKey
): void {
    $counts = [];

    foreach ($rows as $row) {
        $base = trim((string) $baseKey($row));
        $base = $base !== '' ? $base : 'blank';
        $counts[$base] = ($counts[$base] ?? 0) + 1;
        phptest_close_add_record($records, $table, $prefix . ':' . $base . ':' . $counts[$base], $row);
    }
}

function phptest_close_load_document_records(
    $connection,
    array &$records,
    string $workOrderKey,
    string $docType,
    bool $includeWorkRows
): void {
    $document = phptest_close_doc_for_work_order($connection, $workOrderKey, $docType);

    if ($document === null) {
        return;
    }

    $moveQId = $document['anQId'] ?? null;
    $documentKey = trim((string) ($document['acKey'] ?? ''));
    phptest_close_add_record($records, 'tHE_Move', 'document:' . $docType, $document);

    $fxRows = phptest_close_fetch_all(
        $connection,
        'SELECT * FROM dbo.tHE_MoveFXRate WHERE anMoveQId = ? ORDER BY anQId',
        [$moveQId]
    );
    phptest_close_add_numbered_records(
        $records,
        'tHE_MoveFXRate',
        'document:' . $docType . ':fx',
        $fxRows,
        static fn (array $row): string => trim((string) ($row['acCurrency1'] ?? ''))
    );

    $headerLinks = phptest_close_fetch_all(
        $connection,
        'SELECT * FROM dbo.tHF_LinkMoveWOEx WHERE anMoveQId = ? ORDER BY anNo, anQId',
        [$moveQId]
    );
    phptest_close_add_numbered_records(
        $records,
        'tHF_LinkMoveWOEx',
        'document:' . $docType . ':wo-link',
        $headerLinks,
        static fn (array $row): string => trim((string) ($row['acType'] ?? ''))
    );

    $items = phptest_close_fetch_all(
        $connection,
        'SELECT * FROM dbo.tHE_MoveItem WHERE anMoveQId = ? ORDER BY anNo, anQId',
        [$moveQId]
    );
    $itemLogicalIds = [];
    $itemCounts = [];

    foreach ($items as $item) {
        $ident = trim((string) ($item['acIdent'] ?? '')) ?: 'blank';
        $itemCounts[$ident] = ($itemCounts[$ident] ?? 0) + 1;
        $logicalId = 'document:' . $docType . ':item:' . $ident . ':' . $itemCounts[$ident];
        $itemLogicalIds[(string) ($item['anQId'] ?? '')] = $logicalId;
        phptest_close_add_record($records, 'tHE_MoveItem', $logicalId, $item);
    }

    $moveItemQIds = array_values(array_filter(array_map(
        static fn (array $row) => $row['anQId'] ?? null,
        $items
    )));
    $itemLinks = phptest_close_rows_by_ids($connection, 'tHF_LinkMoveItemWOExItem', 'anMoveItemQId', $moveItemQIds);
    $linkCounts = [];

    foreach ($itemLinks as $link) {
        $parent = $itemLogicalIds[(string) ($link['anMoveItemQId'] ?? '')] ?? ('document:' . $docType . ':item:unknown');
        $linkCounts[$parent] = ($linkCounts[$parent] ?? 0) + 1;
        phptest_close_add_record(
            $records,
            'tHF_LinkMoveItemWOExItem',
            $parent . ':wo-item-link:' . $linkCounts[$parent],
            $link
        );
    }

    if ($includeWorkRows) {
        $workRows = phptest_close_rows_by_ids($connection, 'tHF_WOExItemWork', 'anMoveItemQId', $moveItemQIds);
        $workCounts = [];

        foreach ($workRows as $work) {
            $parent = $itemLogicalIds[(string) ($work['anMoveItemQId'] ?? '')] ?? ('document:' . $docType . ':item:unknown');
            $workCounts[$parent] = ($workCounts[$parent] ?? 0) + 1;
            phptest_close_add_record(
                $records,
                'tHF_WOExItemWork',
                $parent . ':work:' . $workCounts[$parent],
                $work
            );
        }
    }

    if ($documentKey !== '') {
        $moveItemWorkRows = phptest_close_fetch_all(
            $connection,
            'SELECT * FROM dbo.tHE_MoveItemWork WHERE acKey = ? ORDER BY anNo, anSubNo, anQId',
            [$documentKey]
        );
        phptest_close_add_numbered_records(
            $records,
            'tHE_MoveItemWork',
            'document:' . $docType . ':move-item-work',
            $moveItemWorkRows,
            static fn (array $row): string => trim((string) ($row['acIdent'] ?? ''))
        );
    }
}

function phptest_close_load_side($connection, array $workOrder): array
{
    $records = [];
    $workOrderKey = trim((string) ($workOrder['acKey'] ?? ''));
    phptest_close_add_record($records, 'tHF_WOEx', 'work-order', $workOrder);

    $items = phptest_close_fetch_all(
        $connection,
        'SELECT * FROM dbo.tHF_WOExItem WHERE acKey = ? ORDER BY anNo, anQId',
        [$workOrderKey]
    );
    $itemIds = [];
    $itemQIdToLogical = [];
    $itemCounts = [];

    foreach ($items as $item) {
        $kind = strtoupper(trim((string) ($item['acOperationType'] ?? ''))) === 'D' ? 'operation' : 'material';
        $ident = trim((string) ($item['acIdent'] ?? '')) ?: 'blank';
        $base = $kind . ':' . $ident;
        $itemCounts[$base] = ($itemCounts[$base] ?? 0) + 1;
        $logicalId = 'wo-item:' . $base . ':' . $itemCounts[$base];
        $itemQId = $item['anQId'] ?? null;
        $itemIds[] = $itemQId;
        $itemQIdToLogical[(string) $itemQId] = $logicalId;
        phptest_close_add_record($records, 'tHF_WOExItem', $logicalId, $item);
    }

    $resources = phptest_close_rows_by_ids($connection, 'tHF_WOExItemResources', 'anWOExItemQId', $itemIds);
    $resourceCounts = [];
    foreach ($resources as $resource) {
        $parent = $itemQIdToLogical[(string) ($resource['anWOExItemQId'] ?? '')] ?? 'wo-item:unknown';
        $resourceCounts[$parent] = ($resourceCounts[$parent] ?? 0) + 1;
        phptest_close_add_record(
            $records,
            'tHF_WOExItemResources',
            $parent . ':resource:' . $resourceCounts[$parent],
            $resource
        );
    }

    phptest_close_load_document_records(
        $connection,
        $records,
        $workOrderKey,
        (string) PHPTEST_CLOSE_DOC_TYPE,
        PHPTEST_CLOSE_KIND === 'operations'
    );

    if (PHPTEST_CLOSE_KIND === 'receipt') {
        // The 6400 material issue is a confirmed costing input for the 6100 receipt.
        phptest_close_load_document_records($connection, $records, $workOrderKey, '6400', false);
    }

    return $records;
}

function phptest_close_metadata($connection, array $tables): array
{
    if ($tables === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($tables), '?'));
    $rows = phptest_close_fetch_all($connection, "
        SELECT
            c.TABLE_NAME,
            c.COLUMN_NAME,
            c.DATA_TYPE,
            c.IS_NULLABLE,
            CAST(COLUMNPROPERTY(OBJECT_ID(c.TABLE_SCHEMA + '.' + c.TABLE_NAME), c.COLUMN_NAME, 'IsComputed') AS int) AS IS_COMPUTED
        FROM INFORMATION_SCHEMA.COLUMNS c
        WHERE c.TABLE_SCHEMA = 'dbo' AND c.TABLE_NAME IN ({$placeholders})
        ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION
    ", $tables);
    $metadata = [];

    foreach ($rows as $row) {
        $metadata[(string) $row['TABLE_NAME']][(string) $row['COLUMN_NAME']] = [
            'data_type' => (string) $row['DATA_TYPE'],
            'nullable' => (string) $row['IS_NULLABLE'],
            'computed' => ((int) ($row['IS_COMPUTED'] ?? 0)) === 1 ? 'YES' : 'NO',
        ];
    }

    return $metadata;
}

function phptest_close_value(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s.u');
    }

    if (is_float($value)) {
        return rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
    }

    return trim((string) $value);
}

function phptest_close_values_equal(mixed $left, mixed $right): bool
{
    if ($left === null || $right === null) {
        return $left === $right;
    }

    if ($left instanceof DateTimeInterface || $right instanceof DateTimeInterface) {
        return phptest_close_value($left) === phptest_close_value($right);
    }

    if (is_numeric((string) $left) && is_numeric((string) $right)) {
        return abs((float) $left - (float) $right) <= 0.000000001;
    }

    return trim((string) $left) === trim((string) $right);
}

function phptest_close_relevant(string $table, string $column, bool $recordMissing, bool $different): bool
{
    if ($recordMissing) {
        return true;
    }

    if (!$different) {
        return false;
    }

    return preg_match(
        '/(?:key|qid|doctype|status|qty|plan|produced|time|price|value|cost|worker|warehouse|issuer|receiver|ident|um|date|user|posted|finished|lnk|dept|stock|currency|type)/i',
        $table . '.' . $column
    ) === 1;
}

function phptest_close_comparison_rows(
    array $pantheonRecords,
    array $enalogRecords,
    array $metadata,
    bool $showAll
): array {
    $recordKeys = array_values(array_unique([...array_keys($pantheonRecords), ...array_keys($enalogRecords)]));
    sort($recordKeys);
    $output = [];

    foreach ($recordKeys as $recordKey) {
        $pantheon = $pantheonRecords[$recordKey] ?? null;
        $enalog = $enalogRecords[$recordKey] ?? null;
        $table = (string) ($pantheon['table'] ?? $enalog['table'] ?? '');
        $recordId = (string) ($pantheon['record_id'] ?? $enalog['record_id'] ?? '');
        $pantheonRow = is_array($pantheon['row'] ?? null) ? $pantheon['row'] : null;
        $enalogRow = is_array($enalog['row'] ?? null) ? $enalog['row'] : null;
        $recordMissing = $pantheonRow === null || $enalogRow === null;
        $columns = array_values(array_unique([
            ...array_keys($metadata[$table] ?? []),
            ...array_keys($pantheonRow ?? []),
            ...array_keys($enalogRow ?? []),
        ]));

        foreach ($columns as $column) {
            $pantheonHas = $pantheonRow !== null && array_key_exists($column, $pantheonRow);
            $enalogHas = $enalogRow !== null && array_key_exists($column, $enalogRow);
            $pantheonValue = $pantheonHas ? $pantheonRow[$column] : null;
            $enalogValue = $enalogHas ? $enalogRow[$column] : null;
            $different = !$pantheonHas || !$enalogHas || !phptest_close_values_equal($pantheonValue, $enalogValue);
            $relevant = phptest_close_relevant($table, (string) $column, $recordMissing, $different);

            if (!$showAll && !$relevant) {
                continue;
            }

            $columnMeta = $metadata[$table][$column] ?? [];
            $output[] = [
                'table name' => $table,
                'record identifier' => $recordId,
                'column name' => (string) $column,
                'Pantheon-created value' => $pantheonHas ? phptest_close_value($pantheonValue) : '[MISSING]',
                'eNalog-created/prepared value' => $enalogHas ? phptest_close_value($enalogValue) : '[MISSING]',
                'data type' => (string) ($columnMeta['data_type'] ?? '[UNKNOWN]'),
                'nullable' => (string) ($columnMeta['nullable'] ?? '[UNKNOWN]'),
                'computed' => (string) ($columnMeta['computed'] ?? '[UNKNOWN]'),
                'field missing' => (!$pantheonHas || !$enalogHas) ? 'YES' : 'NO',
                'difference may be relevant' => $relevant ? 'YES' : 'NO',
            ];
        }
    }

    return $output;
}

function phptest_close_render_table(array $rows): void
{
    if ($rows === []) {
        echo PHP_SAPI === 'cli' ? "No comparison rows.\n" : '<p class="muted">No comparison rows.</p>';
        return;
    }

    $columns = array_keys($rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 240) . PHP_EOL;
        foreach ($rows as $row) {
            echo implode(' | ', array_map(
                static fn (string $column): string => phptest_close_value($row[$column] ?? null),
                $columns
            )) . PHP_EOL;
        }
        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $column) {
        echo '<th>' . phptest_close_h((string) $column) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $relevant = ($row['difference may be relevant'] ?? 'NO') === 'YES';
        echo '<tr' . ($relevant ? ' class="relevant"' : '') . '>';
        foreach ($columns as $column) {
            echo '<td>' . phptest_close_h(phptest_close_value($row[$column] ?? null)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

/**
 * Makes the worker-time relationship visible without requiring users to find
 * anTn/anTime among the full raw-record comparison. eNalog writes anTn as
 * minutes per piece and anTime as the total for the completed quantity.
 * Native Pantheon rows are reference data only: their anTn can be a shared
 * operation norm while anTime is each worker's actual duration.
 */
function phptest_close_timing_audit_rows(array $records, array $workOrder, string $source): array
{
    $quantity = (float) ($workOrder['anPlanQty'] ?? 0);
    $moveItems = [];

    foreach ($records as $record) {
        if (($record['table'] ?? '') !== 'tHE_MoveItem' || !is_array($record['row'] ?? null)) {
            continue;
        }

        $row = $record['row'];
        $qid = (string) ($row['anQId'] ?? '');
        if ($qid !== '') {
            $moveItems[$qid] = [
                'identifier' => (string) ($record['record_id'] ?? ''),
                'total_minutes' => $row['anQty'] ?? null,
            ];
        }
    }

    $rows = [];
    foreach ($records as $record) {
        if (($record['table'] ?? '') !== 'tHF_WOExItemWork' || !is_array($record['row'] ?? null)) {
            continue;
        }

        $row = $record['row'];
        $perPiece = (float) ($row['anTn'] ?? 0);
        $storedTotal = (float) ($row['anTime'] ?? 0);
        $expectedTotal = $perPiece * $quantity;
        $moveItem = $moveItems[(string) ($row['anMoveItemQId'] ?? '')] ?? [];
        $usesEnalogRule = $source === 'eNalog';
        $matches = abs($storedTotal - $expectedTotal) <= 0.000001;

        $rows[] = [
            'source' => $source,
            'worker' => phptest_close_value($row['acWorker'] ?? null),
            'operation line' => (string) ($moveItem['identifier'] ?? '[unlinked]'),
            'anTn (min/piece)' => number_format($perPiece, 6, '.', ''),
            'produced quantity' => number_format($quantity, 6, '.', ''),
            'expected anTime (min)' => $usesEnalogRule ? number_format($expectedTotal, 6, '.', '') : 'reference only',
            'stored anTime (min)' => number_format($storedTotal, 6, '.', ''),
            'anTime check' => $usesEnalogRule ? ($matches ? 'PASS' : 'FAIL') : 'REFERENCE',
            '6600 line total (min)' => phptest_close_value($moveItem['total_minutes'] ?? null),
            'downtime (min)' => phptest_close_value($row['anHoldUp'] ?? null),
            'start' => phptest_close_value($row['adBeginTime'] ?? null),
            'end' => phptest_close_value($row['adEndTime'] ?? null),
        ];
    }

    return $rows;
}

$pantheonRnInput = phptest_close_option('pantheon_rn', PHPTEST_CLOSE_DEFAULT_PANTHEON_RN);
$enalogRnInput = phptest_close_option('enalog_rn', PHPTEST_CLOSE_DEFAULT_ENALOG_RN);
$showAll = phptest_close_option('all', '0') === '1';
$connection = phptest_close_connect();

try {
    $pantheonWorkOrder = phptest_close_find_work_order($connection, $pantheonRnInput);
    $enalogWorkOrder = phptest_close_find_work_order($connection, $enalogRnInput);
    $pantheonRecords = phptest_close_load_side($connection, $pantheonWorkOrder);
    $enalogRecords = phptest_close_load_side($connection, $enalogWorkOrder);
    $tables = array_values(array_unique(array_map(
        static fn (array $record): string => (string) $record['table'],
        [...array_values($pantheonRecords), ...array_values($enalogRecords)]
    )));
    $metadata = phptest_close_metadata($connection, $tables);
    $comparisonRows = phptest_close_comparison_rows($pantheonRecords, $enalogRecords, $metadata, $showAll);
    $timingAuditRows = PHPTEST_CLOSE_KIND === 'operations'
        ? [
            ...phptest_close_timing_audit_rows($pantheonRecords, $pantheonWorkOrder, 'Pantheon'),
            ...phptest_close_timing_audit_rows($enalogRecords, $enalogWorkOrder, 'eNalog'),
        ]
        : [];

    $summary = [[
        'database' => phptest_close_database(),
        'comparison' => (string) PHPTEST_CLOSE_TITLE,
        'document type' => (string) PHPTEST_CLOSE_DOC_TYPE,
        'Pantheon RN' => phptest_close_value($pantheonWorkOrder['acKeyView'] ?? $pantheonWorkOrder['acKey'] ?? null),
        'Pantheon item' => phptest_close_value($pantheonWorkOrder['acIdent'] ?? null),
        'Pantheon quantity' => phptest_close_value($pantheonWorkOrder['anPlanQty'] ?? null),
        'eNalog RN' => phptest_close_value($enalogWorkOrder['acKeyView'] ?? $enalogWorkOrder['acKey'] ?? null),
        'eNalog item' => phptest_close_value($enalogWorkOrder['acIdent'] ?? null),
        'eNalog quantity' => phptest_close_value($enalogWorkOrder['anPlanQty'] ?? null),
        'rows shown' => (string) count($comparisonRows),
    ]];

    if (PHP_SAPI !== 'cli') {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>' . phptest_close_h((string) PHPTEST_CLOSE_TITLE) . '</title>';
        echo '<style>body{font:14px/1.45 Arial,sans-serif;margin:24px;color:#1f2937;background:#f7f8fb}h1{margin:0 0 8px}.note{padding:10px 12px;background:#fff7dd;border:1px solid #edcf70;border-radius:6px;margin:10px 0}.form{display:flex;flex-wrap:wrap;align-items:end;gap:12px;padding:16px;background:#fff;border:1px solid #d9e1eb;border-radius:8px;margin:16px 0}.form label{display:grid;gap:5px;font-weight:600}.form input{height:36px;width:230px;padding:0 10px;border:1px solid #cbd5e1;border-radius:6px}.form button{height:36px;padding:0 14px;border:0;border-radius:6px;background:#2563eb;color:#fff;font-weight:600}.table-wrap{overflow:auto;background:#fff;border:1px solid #d9e1eb;border-radius:6px;margin:8px 0 24px;max-height:68vh}table{border-collapse:collapse;min-width:100%;font-size:12px}th,td{padding:7px 9px;border-right:1px solid #e6ebf1;border-bottom:1px solid #e0e6ee;text-align:left;white-space:nowrap}th{position:sticky;top:0;background:#edf2f8;z-index:1}.relevant{background:#fff3cd}.muted{color:#64748b}</style></head><body>';
        echo '<h1>' . phptest_close_h((string) PHPTEST_CLOSE_TITLE) . '</h1>';
        echo '<div class="note">Read-only. Uses the configured <code>WORK_ORDER_TARGET_DB_DATABASE</code> (or <code>DB_DATABASE</code> fallback). Yellow rows are potentially relevant differences. Use <code>all=1</code> to include non-relevant/equal fields.</div>';
        echo '<form class="form" method="get"><label>Pantheon-closed RN<input name="pantheon_rn" value="' . phptest_close_h($pantheonRnInput) . '"></label><label>eNalog-prepared/closed RN<input name="enalog_rn" value="' . phptest_close_h($enalogRnInput) . '"></label><label>Show all fields<input name="all" value="' . ($showAll ? '1' : '0') . '" inputmode="numeric"></label><button type="submit">Compare</button></form>';
        echo '<h2>Comparison context</h2>';
    }

    phptest_close_render_table($summary);

    if ($timingAuditRows !== []) {
        if (PHP_SAPI !== 'cli') {
            echo '<h2>Worker timing audit</h2>';
            echo '<p>For eNalog rows, expected worker <code>anTime</code> is <code>anTn × produced quantity</code>. A PASS confirms the stored total matches that rule. Native Pantheon rows are reference-only because their <code>anTn</code> may be a shared operation norm.</p>';
        } else {
            echo PHP_EOL . 'Worker timing audit (eNalog: anTime = anTn × produced quantity)' . PHP_EOL;
        }
        phptest_close_render_table($timingAuditRows);
    }

    if (PHP_SAPI !== 'cli') {
        echo '<h2>Field comparison</h2>';
    } else {
        echo PHP_EOL . 'Field comparison' . PHP_EOL;
    }
    phptest_close_render_table($comparisonRows);

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
} finally {
    sqlsrv_close($connection);
}

