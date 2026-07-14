<?php

/*
 * test6.php
 * Read-only eNalog / Pantheon work-order quantity comparator.
 *
 * This script is intentionally locked to BA_TRENDY_TESTNA. It compares one
 * eNalog-created RN with one RN created directly in Pantheon, and highlights
 * quantity, weight, formula, resource and audit differences that can affect
 * Pantheon recalculation when an RN is opened for processing.
 *
 * Parameters:
 * - enalog=26-6000-003617       (or rn1)
 * - pantheon=26-6000-000279     (or rn2)
 * - user_id=57
 * - schema=dbo
 *
 * With no RN parameters, the latest eNalog RN that has a directly-created
 * Pantheon RN for the same finished product is selected automatically.
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

const PHPTEST6_DATABASE = 'BA_TRENDY_TESTNA';

function phptest6_env(string $key, ?string $default = null): ?string
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

function phptest6_option(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    if (is_array($value)) {
        $value = end($value);
    }

    return trim((string) $value);
}

function phptest6_identifier(string $value, string $fallback): string
{
    return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1 ? $value : $fallback;
}

function phptest6_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest6_fail(mixed $error): void
{
    $message = $error instanceof Throwable
        ? $error->getMessage() . "\n" . $error->getTraceAsString()
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN quantity comparator</title></head><body>';
    echo '<pre>' . phptest6_h($message) . '</pre></body></html>';
    exit;
}

function phptest6_bool_env(string $key, bool $default): bool
{
    $value = strtolower(trim((string) phptest6_env($key, $default ? 'true' : 'false')));

    return match ($value) {
        '1', 'true', 'yes', 'on' => true,
        '0', 'false', 'no', 'off' => false,
        default => $default,
    };
}

function phptest6_connect(): array
{
    $host = (string) phptest6_env('WORK_ORDER_TARGET_DB_HOST', phptest6_env('DB_HOST', ''));
    $port = (string) phptest6_env('WORK_ORDER_TARGET_DB_PORT', phptest6_env('DB_PORT', '1433'));
    $username = (string) phptest6_env('WORK_ORDER_TARGET_DB_USERNAME', phptest6_env('DB_USERNAME', ''));
    $password = (string) phptest6_env('WORK_ORDER_TARGET_DB_PASSWORD', phptest6_env('DB_PASSWORD', ''));

    if ($host === '' || $username === '') {
        phptest6_fail('Missing WORK_ORDER_TARGET_DB_HOST or WORK_ORDER_TARGET_DB_USERNAME.');
    }

    $connection = sqlsrv_connect($host . ($port !== '' ? ',' . $port : ''), [
        'Database' => PHPTEST6_DATABASE,
        'UID' => $username,
        'PWD' => $password,
        'CharacterSet' => 'UTF-8',
        'Encrypt' => phptest6_bool_env('WORK_ORDER_TARGET_DB_ENCRYPT', true),
        'TrustServerCertificate' => phptest6_bool_env('WORK_ORDER_TARGET_DB_TRUST_SERVER_CERTIFICATE', true),
        'LoginTimeout' => 10,
    ]);

    if (!$connection) {
        phptest6_fail(sqlsrv_errors());
    }

    return [$connection, PHPTEST6_DATABASE];
}

function phptest6_table(string $schema, string $table): string
{
    return '[' . str_replace(']', ']]', $schema) . '].[' . str_replace(']', ']]', $table) . ']';
}

function phptest6_fetch_all($connection, string $sql, array $params = []): array
{
    $statement = sqlsrv_query($connection, $sql, $params, ['QueryTimeout' => 60]);

    if (!$statement) {
        phptest6_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($statement);

    return $rows;
}

function phptest6_fetch_one($connection, string $sql, array $params = []): ?array
{
    $rows = phptest6_fetch_all($connection, $sql, $params);

    return $rows[0] ?? null;
}

function phptest6_value(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return trim((string) $value);
}

function phptest6_normalize_rn(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function phptest6_rn_candidates(string $value): array
{
    $normalized = phptest6_normalize_rn($value);

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

function phptest6_find_work_order($connection, string $schema, string $input, bool $enalog): ?array
{
    $input = trim($input);

    if ($input === '') {
        $where = $enalog
            ? "CONVERT(nvarchar(max), ISNULL(acNote, '')) LIKE '%eNalog.app%'"
            : "CONVERT(nvarchar(max), ISNULL(acNote, '')) NOT LIKE '%eNalog.app%'";

        return phptest6_fetch_one(
            $connection,
            'SELECT TOP 1 * FROM ' . phptest6_table($schema, 'tHF_WOEx') . ' WHERE ' . $where . ' ORDER BY adTimeIns DESC, acKey DESC'
        );
    }

    $where = [];
    $params = [];

    foreach (phptest6_rn_candidates($input) as $candidate) {
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKey), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKeyView), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
    }

    if ($where === []) {
        return null;
    }

    return phptest6_fetch_one(
        $connection,
        'SELECT TOP 1 * FROM ' . phptest6_table($schema, 'tHF_WOEx') . ' WHERE ' . implode(' OR ', $where) . ' ORDER BY adTimeIns DESC, acKey DESC',
        $params
    );
}

function phptest6_find_direct_match($connection, string $schema, array $enalogRow): ?array
{
    $product = trim((string) ($enalogRow['acIdent'] ?? ''));

    if ($product === '') {
        return null;
    }

    return phptest6_fetch_one(
        $connection,
        'SELECT TOP 1 * FROM ' . phptest6_table($schema, 'tHF_WOEx')
            . " WHERE acIdent = ? AND CONVERT(nvarchar(max), ISNULL(acNote, '')) NOT LIKE '%eNalog.app%'"
            . ' ORDER BY adTimeIns DESC, acKey DESC',
        [$product]
    );
}

function phptest6_find_enalog_match($connection, string $schema, array $pantheonRow): ?array
{
    $product = trim((string) ($pantheonRow['acIdent'] ?? ''));

    if ($product === '') {
        return null;
    }

    return phptest6_fetch_one(
        $connection,
        'SELECT TOP 1 * FROM ' . phptest6_table($schema, 'tHF_WOEx')
            . " WHERE acIdent = ? AND CONVERT(nvarchar(max), ISNULL(acNote, '')) LIKE '%eNalog.app%'"
            . ' ORDER BY adTimeIns DESC, acKey DESC',
        [$product]
    );
}

function phptest6_find_enalog_with_direct_match($connection, string $schema): ?array
{
    $workOrders = phptest6_table($schema, 'tHF_WOEx');

    return phptest6_fetch_one(
        $connection,
        'SELECT TOP 1 e.* FROM ' . $workOrders . ' e'
            . " WHERE CONVERT(nvarchar(max), ISNULL(e.acNote, '')) LIKE '%eNalog.app%'"
            . ' AND EXISTS ('
            . 'SELECT 1 FROM ' . $workOrders . ' p'
            . " WHERE p.acIdent = e.acIdent AND CONVERT(nvarchar(max), ISNULL(p.acNote, '')) NOT LIKE '%eNalog.app%'"
            . ') ORDER BY e.adTimeIns DESC, e.acKey DESC'
    );
}

/** @return array<string, array{type:string,nullable:string,computed:string}> */
function phptest6_column_metadata($connection, string $schema, string $table): array
{
    $rows = phptest6_fetch_all($connection, "
        SELECT
            c.COLUMN_NAME,
            c.DATA_TYPE,
            c.IS_NULLABLE,
            COLUMNPROPERTY(
                OBJECT_ID(QUOTENAME(c.TABLE_SCHEMA) + '.' + QUOTENAME(c.TABLE_NAME)),
                c.COLUMN_NAME,
                'IsComputed'
            ) AS is_computed
        FROM INFORMATION_SCHEMA.COLUMNS c
        WHERE c.TABLE_SCHEMA = ? AND c.TABLE_NAME = ?
        ORDER BY c.ORDINAL_POSITION
    ", [$schema, $table]);
    $metadata = [];

    foreach ($rows as $row) {
        $column = (string) ($row['COLUMN_NAME'] ?? '');
        if ($column === '') {
            continue;
        }

        $metadata[$column] = [
            'type' => (string) ($row['DATA_TYPE'] ?? ''),
            'nullable' => (string) ($row['IS_NULLABLE'] ?? ''),
            'computed' => ((int) ($row['is_computed'] ?? 0)) === 1 ? 'YES' : 'NO',
        ];
    }

    return $metadata;
}

function phptest6_fetch_related_rows($connection, string $schema, string $table, array $metadata, string $workOrderKey): array
{
    if ($metadata === [] || $workOrderKey === '') {
        return [];
    }

    if (array_key_exists('acKey', $metadata)) {
        return phptest6_fetch_all(
            $connection,
            'SELECT * FROM ' . phptest6_table($schema, $table) . ' WHERE acKey = ?',
            [$workOrderKey]
        );
    }

    return [];
}

function phptest6_metadata_column(array $metadata, string $candidate): ?string
{
    foreach (array_keys($metadata) as $column) {
        if (strcasecmp($column, $candidate) === 0) {
            return $column;
        }
    }

    return null;
}

function phptest6_row_value(array $row, string $candidate): mixed
{
    foreach ($row as $column => $value) {
        if (strcasecmp((string) $column, $candidate) === 0) {
            return $value;
        }
    }

    return null;
}

function phptest6_is_relevant_column(string $column): bool
{
    if (in_array($column, ['anUserIns', 'anUserChg', 'adTimeIns', 'adTimeChg'], true)) {
        return true;
    }

    return preg_match('/weight|qty|quantity|planned|issued|remaining|norm|mass|volume|bruto|neto|formula|factor|perc/i', $column) === 1;
}

function phptest6_record_key(array $row, array $keyColumns, string $fallbackPrefix): string
{
    $parts = [];

    foreach ($keyColumns as $column) {
        if (!array_key_exists($column, $row)) {
            continue;
        }

        $parts[] = $column . '=' . phptest6_value($row[$column]);
    }

    if ($parts !== []) {
        return implode('; ', $parts);
    }

    return $fallbackPrefix . ':' . md5(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
}

/** @return array<string, array{enalog:?array,pantheon:?array,record:string}> */
function phptest6_pair_rows(array $enalogRows, array $pantheonRows, array $keyColumns, string $fallbackPrefix): array
{
    $pairs = [];

    foreach ($enalogRows as $index => $row) {
        $key = phptest6_record_key($row, $keyColumns, $fallbackPrefix . '-enalog-' . $index);
        $pairs[$key] = [
            'enalog' => $row,
            'pantheon' => null,
            'record' => $key,
        ];
    }

    foreach ($pantheonRows as $index => $row) {
        $key = phptest6_record_key($row, $keyColumns, $fallbackPrefix . '-pantheon-' . $index);

        if (!array_key_exists($key, $pairs)) {
            $pairs[$key] = [
                'enalog' => null,
                'pantheon' => $row,
                'record' => $key,
            ];
            continue;
        }

        $pairs[$key]['pantheon'] = $row;
    }

    return $pairs;
}

function phptest6_values_differ(mixed $left, mixed $right): bool
{
    if ($left === null || $right === null) {
        return $left !== $right;
    }

    if ($left instanceof DateTimeInterface || $right instanceof DateTimeInterface) {
        return phptest6_value($left) !== phptest6_value($right);
    }

    if (is_numeric((string) $left) && is_numeric((string) $right)) {
        return abs((float) $left - (float) $right) > 0.000001;
    }

    return trim((string) $left) !== trim((string) $right);
}

function phptest6_is_zero(mixed $value): bool
{
    return $value !== null
        && !$value instanceof DateTimeInterface
        && is_numeric((string) $value)
        && abs((float) $value) <= 0.000001;
}

function phptest6_warning(string $column, ?array $enalogRow, ?array $pantheonRow, int $trackedUserId): string
{
    if ($enalogRow === null) {
        return 'Missing from the eNalog-created RN; Pantheon has a related record that eNalog did not create.';
    }

    if ($pantheonRow === null) {
        return 'Present only on the eNalog-created RN; verify that this extra record is intentional.';
    }

    $enalogValue = $enalogRow[$column] ?? null;
    $pantheonValue = $pantheonRow[$column] ?? null;

    if (in_array($column, ['anUserIns', 'anUserChg'], true)) {
        if ((int) $enalogValue === $trackedUserId || (int) $pantheonValue === $trackedUserId) {
            return 'Audit difference involving tracked Pantheon user ID ' . $trackedUserId . '.';
        }

        return 'User/audit difference; use with adTimeIns and adTimeChg to identify the last writer.';
    }

    if (in_array($column, ['adTimeIns', 'adTimeChg'], true)) {
        return 'Timestamp difference; use together with the user columns to identify the last writer.';
    }

    if ((phptest6_is_zero($enalogValue) && $pantheonValue === null) || ($enalogValue === null && phptest6_is_zero($pantheonValue))) {
        return 'Zero-versus-NULL: one path explicitly writes 0 while the other leaves the field unset; Pantheon may treat these differently during recalculation.';
    }

    if (phptest6_is_zero($enalogValue) && !phptest6_is_zero($pantheonValue)) {
        return 'eNalog has 0 while Pantheon has a non-zero value; this can make a recalculation or processing form replace a manual weight with 0.';
    }

    if (preg_match('/anPlanQty|planned/i', $column) === 1) {
        return 'Planned/calculated quantity differs. Pantheon processing can use this field instead of the displayed or normative quantity.';
    }

    if (preg_match('/anQty1|anQtyBase|anQty3|norm/i', $column) === 1) {
        return 'Normative/input quantity differs. This is often an input to Pantheon formula and planned-quantity calculations.';
    }

    if (preg_match('/formula|factor|perc/i', $column) === 1) {
        return 'Formula/factor/percentage differs and can change the calculated quantity on reopen.';
    }

    return 'Relevant work-order field differs between eNalog and the direct Pantheon RN.';
}

function phptest6_compare_table(
    string $table,
    array $metadata,
    array $pairs,
    int $trackedUserId
): array {
    $differences = [];
    $summary = [
        'table' => $table,
        'matched_records' => 0,
        'missing_from_enalog' => 0,
        'missing_from_pantheon' => 0,
        'relevant_differences' => 0,
    ];

    foreach ($pairs as $pair) {
        $enalogRow = $pair['enalog'];
        $pantheonRow = $pair['pantheon'];

        if ($enalogRow === null) {
            $summary['missing_from_enalog']++;
        } elseif ($pantheonRow === null) {
            $summary['missing_from_pantheon']++;
        } else {
            $summary['matched_records']++;
        }

        $columns = array_keys($metadata);
        if ($enalogRow !== null || $pantheonRow !== null) {
            $columns = array_values(array_unique(array_merge(
                $columns,
                array_keys($enalogRow ?? []),
                array_keys($pantheonRow ?? [])
            )));
        }

        foreach ($columns as $column) {
            if (!phptest6_is_relevant_column($column)) {
                continue;
            }

            $enalogValue = $enalogRow[$column] ?? null;
            $pantheonValue = $pantheonRow[$column] ?? null;

            if ($enalogRow !== null && $pantheonRow !== null && !phptest6_values_differ($enalogValue, $pantheonValue)) {
                continue;
            }

            $summary['relevant_differences']++;
            $columnMeta = $metadata[$column] ?? ['type' => '(missing)', 'nullable' => '(missing)', 'computed' => '(missing)'];
            $differences[] = [
                'table' => $table,
                'record' => (string) ($pair['record'] ?? ''),
                'column' => $column,
                'eNalog value' => phptest6_value($enalogValue),
                'Pantheon value' => phptest6_value($pantheonValue),
                'data type' => $columnMeta['type'],
                'nullable' => $columnMeta['nullable'],
                'computed' => $columnMeta['computed'],
                'warning' => phptest6_warning($column, $enalogRow, $pantheonRow, $trackedUserId),
            ];
        }
    }

    return [$summary, $differences];
}

function phptest6_render_heading(string $text, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(12, strlen($text))) . PHP_EOL . $text . PHP_EOL . str_repeat('=', max(12, strlen($text))) . PHP_EOL;
        return;
    }

    $tag = 'h' . min(6, max(1, $level));
    echo '<' . $tag . '>' . phptest6_h($text) . '</' . $tag . '>';
}

function phptest6_render_rows(string $title, array $rows): void
{
    phptest6_render_heading($title, 3);

    if ($rows === []) {
        echo PHP_SAPI === 'cli' ? "No rows.\n" : '<p class="muted">No rows.</p>';
        return;
    }

    $columns = array_keys($rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL . str_repeat('-', 180) . PHP_EOL;
        foreach ($rows as $row) {
            echo implode(' | ', array_map(static fn ($column) => phptest6_value($row[$column] ?? null), $columns)) . PHP_EOL;
        }
        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $column) {
        echo '<th>' . phptest6_h((string) $column) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            echo '<td>' . phptest6_h(phptest6_value($row[$column] ?? null)) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest6_render_selection_form(string $enalogInput, string $pantheonInput, int $trackedUserId): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    echo '<form class="comparison-form" method="get">';
    echo '<div class="comparison-form__field"><label for="enalog-rn">eNalog RN</label>';
    echo '<input id="enalog-rn" name="enalog" value="' . phptest6_h($enalogInput) . '" placeholder="npr. 26-6000-003617" autocomplete="off">';
    echo '</div>';
    echo '<div class="comparison-form__field"><label for="pantheon-rn">Pantheon RN</label>';
    echo '<input id="pantheon-rn" name="pantheon" value="' . phptest6_h($pantheonInput) . '" placeholder="npr. 26-6000-000279" autocomplete="off">';
    echo '</div>';
    echo '<div class="comparison-form__field comparison-form__field--user"><label for="tracked-user">Pantheon korisnik</label>';
    echo '<input id="tracked-user" type="number" min="1" step="1" name="user_id" value="' . $trackedUserId . '">';
    echo '</div>';
    echo '<div class="comparison-form__actions"><button type="submit">Uporedi RN</button><a href="?">Automatski odabir</a></div>';
    echo '</form>';
    echo '<p class="muted">Unesite oba RN broja za određeni par, ili ostavite prazno za najnoviji uporedivi eNalog/Pantheon par.</p>';
}

$schema = phptest6_identifier(phptest6_option('schema', (string) phptest6_env('DB_SCHEMA', 'dbo')), 'dbo');
$enalogInput = phptest6_option('enalog', phptest6_option('rn1'));
$pantheonInput = phptest6_option('pantheon', phptest6_option('rn2'));
$trackedUserId = max(1, (int) phptest6_option('user_id', '57'));
$connection = null;

try {
    [$connection, $database] = phptest6_connect();
    $trackedUser = phptest6_fetch_one(
        $connection,
        'SELECT anUserId, acUserId, acTitle, acActive FROM ' . phptest6_table($schema, 'tPA_User') . ' WHERE anUserId = ?',
        [$trackedUserId]
    );

    $pantheonHeader = $pantheonInput !== ''
        ? phptest6_find_work_order($connection, $schema, $pantheonInput, false)
        : null;
    if ($pantheonInput !== '' && $pantheonHeader === null) {
        phptest6_fail('Direct Pantheon comparison work order was not found. Pass pantheon=RN_NUMBER.');
    }

    $enalogHeader = $enalogInput !== ''
        ? phptest6_find_work_order($connection, $schema, $enalogInput, true)
        : ($pantheonHeader !== null
            ? phptest6_find_enalog_match($connection, $schema, $pantheonHeader)
            : phptest6_find_enalog_with_direct_match($connection, $schema));
    if ($enalogHeader === null) {
        phptest6_fail('Comparable eNalog work order was not found. Pass enalog=RN_NUMBER.');
    }

    $pantheonHeader ??= phptest6_find_direct_match($connection, $schema, $enalogHeader);
    if ($pantheonHeader === null) {
        phptest6_fail('Direct Pantheon comparison work order was not found. Pass pantheon=RN_NUMBER.');
    }

    $enalogKey = trim((string) ($enalogHeader['acKey'] ?? ''));
    $pantheonKey = trim((string) ($pantheonHeader['acKey'] ?? ''));
    $tables = [
        'tHF_WOEx' => ['keys' => ['__comparison_header']],
        'tHF_WOExItem' => ['keys' => ['anNo', 'anVariant', 'acIdent']],
        'tHF_WOExItemResources' => ['keys' => ['__parent_item_record', 'anPriority', 'acResursID', 'acResType']],
        'tHF_WOExItemTools' => ['keys' => ['__parent_resource_record', 'anSubNo', 'acToolID']],
        'tHF_WOExItemToolsSml' => ['keys' => ['__parent_resource_record', 'anSubNo', 'acToolSml']],
        'tHF_WOExItemRules' => ['keys' => ['__parent_item_record', 'anSubNo', 'acRuleID']],
        'tHF_LinkWOExOrderItem' => ['keys' => ['acLnkKey', 'anLnkNo', 'anNo']],
    ];
    $metadata = [];
    $summaryRows = [];
    $differenceRows = [];

    foreach ($tables as $table => $definition) {
        $metadata[$table] = phptest6_column_metadata($connection, $schema, $table);
    }

    $enalogItems = phptest6_fetch_related_rows($connection, $schema, 'tHF_WOExItem', $metadata['tHF_WOExItem'], $enalogKey);
    $pantheonItems = phptest6_fetch_related_rows($connection, $schema, 'tHF_WOExItem', $metadata['tHF_WOExItem'], $pantheonKey);
    $itemPairs = phptest6_pair_rows($enalogItems, $pantheonItems, $tables['tHF_WOExItem']['keys'], 'item');
    $enalogItemRecords = [];
    $pantheonItemRecords = [];

    foreach ($itemPairs as $pair) {
        $leftQid = (int) ($pair['enalog']['anQId'] ?? 0);
        $rightQid = (int) ($pair['pantheon']['anQId'] ?? 0);
        $record = (string) ($pair['record'] ?? '');
        if ($leftQid > 0) {
            $enalogItemRecords[$leftQid] = $record;
        }
        if ($rightQid > 0) {
            $pantheonItemRecords[$rightQid] = $record;
        }
    }

    $relatedByTable = [
        'tHF_WOEx' => [[array_merge($enalogHeader, ['__comparison_header' => 'header'])], [array_merge($pantheonHeader, ['__comparison_header' => 'header'])]],
        'tHF_WOExItem' => [$enalogItems, $pantheonItems],
    ];

    $fetchByIds = static function (string $table, string $column, array $ids) use ($connection, $schema, $metadata): array {
        $resolvedColumn = phptest6_metadata_column($metadata[$table] ?? [], $column);
        if ($ids === [] || $resolvedColumn === null) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        return phptest6_fetch_all($connection, 'SELECT * FROM ' . phptest6_table($schema, $table) . " WHERE {$resolvedColumn} IN ({$placeholders})", $ids);
    };
    $enalogItemQids = array_values(array_keys($enalogItemRecords));
    $pantheonItemQids = array_values(array_keys($pantheonItemRecords));
    $enalogResources = $fetchByIds('tHF_WOExItemResources', 'anWOExItemQId', $enalogItemQids);
    $pantheonResources = $fetchByIds('tHF_WOExItemResources', 'anWOExItemQId', $pantheonItemQids);
    $enalogResources = array_map(static function (array $row) use ($enalogItemRecords): array {
        $row['__parent_item_record'] = $enalogItemRecords[(int) phptest6_row_value($row, 'anWOExItemQId')] ?? 'unmatched parent item';
        return $row;
    }, $enalogResources);
    $pantheonResources = array_map(static function (array $row) use ($pantheonItemRecords): array {
        $row['__parent_item_record'] = $pantheonItemRecords[(int) phptest6_row_value($row, 'anWOExItemQId')] ?? 'unmatched parent item';
        return $row;
    }, $pantheonResources);
    $relatedByTable['tHF_WOExItemResources'] = [$enalogResources, $pantheonResources];

    $resourcePairs = phptest6_pair_rows($enalogResources, $pantheonResources, $tables['tHF_WOExItemResources']['keys'], 'resource');
    $enalogResourceRecords = [];
    $pantheonResourceRecords = [];
    foreach ($resourcePairs as $pair) {
        $leftQid = (int) ($pair['enalog']['anQId'] ?? 0);
        $rightQid = (int) ($pair['pantheon']['anQId'] ?? 0);
        $record = (string) ($pair['record'] ?? '');
        if ($leftQid > 0) {
            $enalogResourceRecords[$leftQid] = $record;
        }
        if ($rightQid > 0) {
            $pantheonResourceRecords[$rightQid] = $record;
        }
    }

    foreach (['tHF_WOExItemTools', 'tHF_WOExItemToolsSml'] as $table) {
        $enalogTools = $fetchByIds($table, 'anResursQID', array_values(array_keys($enalogResourceRecords)));
        $pantheonTools = $fetchByIds($table, 'anResursQID', array_values(array_keys($pantheonResourceRecords)));
        $enalogTools = array_map(static function (array $row) use ($enalogResourceRecords): array {
            $row['__parent_resource_record'] = $enalogResourceRecords[(int) phptest6_row_value($row, 'anResursQID')] ?? 'unmatched parent resource';
            return $row;
        }, $enalogTools);
        $pantheonTools = array_map(static function (array $row) use ($pantheonResourceRecords): array {
            $row['__parent_resource_record'] = $pantheonResourceRecords[(int) phptest6_row_value($row, 'anResursQID')] ?? 'unmatched parent resource';
            return $row;
        }, $pantheonTools);
        $relatedByTable[$table] = [$enalogTools, $pantheonTools];
    }

    $enalogRules = $fetchByIds('tHF_WOExItemRules', 'anWOExItemQId', $enalogItemQids);
    $pantheonRules = $fetchByIds('tHF_WOExItemRules', 'anWOExItemQId', $pantheonItemQids);
    $enalogRules = array_map(static function (array $row) use ($enalogItemRecords): array {
        $row['__parent_item_record'] = $enalogItemRecords[(int) phptest6_row_value($row, 'anWOExItemQId')] ?? 'unmatched parent item';
        return $row;
    }, $enalogRules);
    $pantheonRules = array_map(static function (array $row) use ($pantheonItemRecords): array {
        $row['__parent_item_record'] = $pantheonItemRecords[(int) phptest6_row_value($row, 'anWOExItemQId')] ?? 'unmatched parent item';
        return $row;
    }, $pantheonRules);
    $relatedByTable['tHF_WOExItemRules'] = [$enalogRules, $pantheonRules];
    $relatedByTable['tHF_LinkWOExOrderItem'] = [
        phptest6_fetch_related_rows($connection, $schema, 'tHF_LinkWOExOrderItem', $metadata['tHF_LinkWOExOrderItem'], $enalogKey),
        phptest6_fetch_related_rows($connection, $schema, 'tHF_LinkWOExOrderItem', $metadata['tHF_LinkWOExOrderItem'], $pantheonKey),
    ];

    foreach ($tables as $table => $definition) {
        [$leftRows, $rightRows] = $relatedByTable[$table] ?? [[], []];
        $pairs = phptest6_pair_rows($leftRows, $rightRows, $definition['keys'], strtolower($table));
        [$summary, $differences] = phptest6_compare_table($table, $metadata[$table], $pairs, $trackedUserId);
        $summaryRows[] = $summary;
        $differenceRows = array_merge($differenceRows, $differences);
    }

    // Include audit matches even when there is no value difference. This makes
    // a supplied Pantheon user ID (for example 46) visible as an explicit
    // writer check, rather than only as a side-effect of a value comparison.
    $trackedUserRows = [];
    foreach ($tables as $table => $definition) {
        foreach (['eNalog' => $relatedByTable[$table][0] ?? [], 'Pantheon' => $relatedByTable[$table][1] ?? []] as $source => $rows) {
            foreach ($rows as $index => $row) {
                $insertedBy = phptest6_row_value($row, 'anUserIns');
                $changedBy = phptest6_row_value($row, 'anUserChg');

                if ((int) $insertedBy !== $trackedUserId && (int) $changedBy !== $trackedUserId) {
                    continue;
                }

                $trackedUserRows[] = [
                    'source' => $source,
                    'table' => $table,
                    'record' => phptest6_record_key($row, $definition['keys'], strtolower($table) . '-' . strtolower($source) . '-' . $index),
                    'anUserIns' => phptest6_value($insertedBy),
                    'adTimeIns' => phptest6_value(phptest6_row_value($row, 'adTimeIns')),
                    'anUserChg' => phptest6_value($changedBy),
                    'adTimeChg' => phptest6_value(phptest6_row_value($row, 'adTimeChg')),
                ];
            }
        }
    }

    $bomRows = phptest6_fetch_all(
        $connection,
        'SELECT * FROM ' . phptest6_table($schema, 'tHF_SetPrSt') . ' WHERE acIdent = ? ORDER BY anNo, anQId',
        [trim((string) ($enalogHeader['acIdent'] ?? ''))]
    );
    $bomRelevantRows = array_map(static function (array $row): array {
        return array_filter($row, static function ($value, $column): bool {
            return in_array($column, ['anNo', 'anQId', 'acIdentChild', 'acOperationType', 'acUM'], true)
                || phptest6_is_relevant_column((string) $column);
        }, ARRAY_FILTER_USE_BOTH);
    }, $bomRows);

    if (PHP_SAPI !== 'cli') {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>RN quantity comparator</title>';
        echo '<style>body{font:14px/1.45 Arial,sans-serif;margin:24px;color:#1f2937;background:#f7f8fb}h1{margin:0 0 8px}.note{padding:10px 12px;background:#fff7dd;border:1px solid #edcf70;border-radius:6px;margin:10px 0}.meta{padding:10px 12px;background:#eaf3ff;border:1px solid #b6d5fb;border-radius:6px;margin:10px 0}.comparison-form{display:flex;flex-wrap:wrap;align-items:end;gap:12px;padding:16px;background:#fff;border:1px solid #d9e1eb;border-radius:8px;margin:16px 0 8px}.comparison-form__field{display:grid;gap:5px;min-width:220px}.comparison-form__field--user{min-width:150px;max-width:180px}.comparison-form label{font-weight:600;color:#334155}.comparison-form input{height:36px;padding:0 10px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;color:#1f2937;font:inherit;box-sizing:border-box}.comparison-form input:focus{outline:2px solid #93c5fd;border-color:#2563eb}.comparison-form__actions{display:flex;align-items:center;gap:12px;height:36px}.comparison-form button{height:36px;padding:0 14px;border:0;border-radius:6px;background:#2563eb;color:#fff;font:600 14px Arial,sans-serif;cursor:pointer}.comparison-form button:hover{background:#1d4ed8}.comparison-form a{color:#2563eb;text-decoration:none;font-weight:600}.comparison-form a:hover{text-decoration:underline}.table-wrap{overflow:auto;background:#fff;border:1px solid #d9e1eb;border-radius:6px;margin:8px 0 24px}table{border-collapse:collapse;min-width:100%;font-size:12px}th,td{padding:6px 8px;border-right:1px solid #e6ebf1;border-bottom:1px solid #e0e6ee;text-align:left;vertical-align:top;white-space:nowrap}th{background:#edf2f8;position:sticky;top:0}td:last-child{white-space:normal;min-width:330px}.muted{color:#64748b}@media(max-width:680px){body{margin:14px}.comparison-form{align-items:stretch}.comparison-form__field,.comparison-form__field--user{width:100%;max-width:none}.comparison-form__actions{justify-content:space-between}}</style></head><body>';
    }

    phptest6_render_heading('eNalog / Pantheon RN quantity comparator', 1);
    phptest6_render_rows('Selected work orders', [[
        'database (locked)' => $database,
        'tracked user ID' => $trackedUserId,
        'eNalog RN' => phptest6_value($enalogHeader['acKeyView'] ?? $enalogHeader['acKey'] ?? null),
        'eNalog key' => $enalogKey,
        'Pantheon RN' => phptest6_value($pantheonHeader['acKeyView'] ?? $pantheonHeader['acKey'] ?? null),
        'Pantheon key' => $pantheonKey,
        'eNalog product' => phptest6_value($enalogHeader['acIdent'] ?? null),
        'Pantheon product' => phptest6_value($pantheonHeader['acIdent'] ?? null),
        'same finished product' => trim((string) ($enalogHeader['acIdent'] ?? '')) === trim((string) ($pantheonHeader['acIdent'] ?? '')) ? 'YES' : 'NO - choose a matching RN pair for a meaningful comparison',
    ]]);
    phptest6_render_rows('Tracked Pantheon user', [[
        'anUserId' => $trackedUserId,
        'acUserId' => phptest6_value($trackedUser['acUserId'] ?? null),
        'acTitle' => phptest6_value($trackedUser['acTitle'] ?? null),
        'acActive' => phptest6_value($trackedUser['acActive'] ?? null),
        'found' => $trackedUser === null ? 'NO' : 'YES',
    ]]);

    if (PHP_SAPI !== 'cli') {
        echo '<div class="note">Read-only: this script connects only to BA_TRENDY_TESTNA and performs no INSERT, UPDATE, DELETE, or procedure calls.</div>';
        phptest6_render_selection_form($enalogInput, $pantheonInput, $trackedUserId);
    }

    phptest6_render_rows('Difference summary by table', $summaryRows);
    phptest6_render_rows('Relevant differences grouped by table and record', $differenceRows);
    phptest6_render_rows('Rows written or last changed by tracked user ' . $trackedUserId, $trackedUserRows);
    phptest6_render_rows('Current product BOM baseline (context only)', $bomRelevantRows);

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }

    sqlsrv_close($connection);
} catch (Throwable $exception) {
    if (is_resource($connection)) {
        sqlsrv_close($connection);
    }

    phptest6_fail($exception);
}
