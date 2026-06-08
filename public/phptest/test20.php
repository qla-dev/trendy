<?php

/*
 * test20.php
 * Raw pregled tabela i kolona vezanih za artikal.
 *
 * Parametri:
 * - article_code=0251-012-075
 * - schema=dbo
 * - catalog_table=tHE_SetItem
 * - sample_limit=3
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest20_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest20_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Article schema raw test</title></head><body>';
    echo '<pre>' . phptest20_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest20_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest20_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest20_identifier(string $value, string $fallback): string
{
    $trimmed = trim($value);

    if ($trimmed === '' || preg_match('/^[A-Za-z0-9_]+$/', $trimmed) !== 1) {
        return $fallback;
    }

    return $trimmed;
}

function phptest20_quote_identifier(string $identifier): string
{
    return '[' . str_replace(']', ']]', $identifier) . ']';
}

function phptest20_qualify_table(string $schema, string $table): string
{
    return phptest20_quote_identifier($schema) . '.' . phptest20_quote_identifier($table);
}

function phptest20_format_number($value, int $scale = 4): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest20_format_value($value): string
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
        return phptest20_format_number($value, 6);
    }

    $stringValue = trim((string) $value);

    if ($stringValue === '') {
        return '';
    }

    if (function_exists('mb_strlen') && mb_strlen($stringValue, 'UTF-8') > 1200) {
        return mb_substr($stringValue, 0, 1200, 'UTF-8') . '...';
    }

    return $stringValue;
}

function phptest20_logical_value($value): string
{
    if ($value === null) {
        return '';
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_float($value) || is_int($value) || (is_string($value) && is_numeric($value))) {
        return phptest20_format_number($value, 6);
    }

    return trim((string) $value);
}

function phptest20_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest20_h($title) . '</' . $tag . '>';
}

function phptest20_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest20_h($class) . '">' . phptest20_h($text) . '</div>';
}

function phptest20_render_table(string $title, array $rows): void
{
    phptest20_render_heading($title, 3);

    if (empty($rows)) {
        phptest20_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 220) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest20_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest20_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest20_h(phptest20_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest20_string_type(string $type): bool
{
    return in_array(strtolower($type), ['char', 'nchar', 'varchar', 'nvarchar', 'text', 'ntext'], true);
}

function phptest20_numeric_type(string $type): bool
{
    return in_array(strtolower($type), ['bigint', 'int', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'real', 'money', 'smallmoney'], true);
}

function phptest20_binary_type(string $type): bool
{
    return in_array(strtolower($type), ['binary', 'varbinary', 'image', 'timestamp', 'rowversion'], true);
}

function phptest20_is_interesting_column(string $columnName): bool
{
    static $fragments = [
        'note',
        'napom',
        'remark',
        'descr',
        'opis',
        'classif',
        'surface',
        'protect',
        'zastit',
        'coat',
        'finish',
        'field',
        'werkstoff',
        'mater',
        'obrada',
        'anod',
        'galv',
        'cink',
        'paint',
        'boja',
    ];

    $normalized = strtolower(trim($columnName));

    foreach ($fragments as $fragment) {
        if (str_contains($normalized, $fragment)) {
            return true;
        }
    }

    return false;
}

function phptest20_is_focused_candidate(string $columnName): bool
{
    static $fragments = [
        'note',
        'napom',
        'remark',
        'surface',
        'protect',
        'zastit',
        'coat',
        'finish',
        'classif',
        'field',
        'werkstoff',
        'mater',
        'obrada',
        'paint',
        'boja',
    ];

    $normalized = strtolower(trim($columnName));

    foreach ($fragments as $fragment) {
        if (str_contains($normalized, $fragment)) {
            return true;
        }
    }

    return false;
}

function phptest20_is_article_context_table(string $tableName): bool
{
    $normalized = strtolower($tableName);

    if (str_starts_with($normalized, 'the_stock')) {
        return true;
    }

    foreach ([
        'setitem',
        'prodst',
        'orderitem',
        'moveitem',
        'woexitem',
        'linkmoveitem',
        'linkwoexitem',
        'stockres',
        'stockserial',
    ] as $fragment) {
        if (str_contains($normalized, $fragment)) {
            return true;
        }
    }

    return false;
}

function phptest20_has_operational_prefix(string $tableName): bool
{
    $normalized = strtolower($tableName);

    foreach (['the_', 'thf_', 'mhe_', 'mhf_', 'tpa_', 'hef', 'proiz'] as $prefix) {
        if (str_starts_with($normalized, $prefix)) {
            return true;
        }
    }

    return false;
}

function phptest20_should_inspect_matches(string $tableName): bool
{
    return phptest20_has_operational_prefix($tableName) || phptest20_is_article_context_table($tableName);
}

function phptest20_table_priority(string $tableName): int
{
    $normalized = strtolower($tableName);

    if (str_starts_with($normalized, 'the_setitem')) {
        return 0;
    }

    if (str_starts_with($normalized, 'the_')) {
        return 1;
    }

    if (str_starts_with($normalized, 'thf_')) {
        return 2;
    }

    if (str_contains($normalized, 'setitem')) {
        return 3;
    }

    if (str_contains($normalized, 'remark') || str_contains($normalized, 'note')) {
        return 4;
    }

    return 10;
}

function phptest20_positive_integer($value): ?int
{
    if (!is_numeric((string) $value)) {
        return null;
    }

    $integer = (int) $value;

    return $integer > 0 ? $integer : null;
}

function phptest20_extract_fields(array $row, bool $empty, bool $interestingOnly = false): array
{
    $result = [];

    foreach ($row as $column => $value) {
        if ($interestingOnly && !phptest20_is_interesting_column((string) $column)) {
            continue;
        }

        $normalized = phptest20_logical_value($value);
        $isEmpty = $normalized === '';

        if ($empty !== $isEmpty) {
            continue;
        }

        $result[] = [
                'column' => (string) $column,
                'value' => $normalized,
            ];
    }

    return $result;
}

function phptest20_load_metadata($conn, string $schema): array
{
    $rows = phptest20_fetch_all(
        $conn,
        "
            SELECT
                c.TABLE_NAME,
                c.COLUMN_NAME,
                c.DATA_TYPE,
                c.CHARACTER_MAXIMUM_LENGTH,
                c.IS_NULLABLE,
                c.ORDINAL_POSITION
            FROM INFORMATION_SCHEMA.COLUMNS c
            INNER JOIN INFORMATION_SCHEMA.TABLES t
                ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
               AND t.TABLE_NAME = c.TABLE_NAME
            WHERE c.TABLE_SCHEMA = ?
              AND t.TABLE_TYPE = 'BASE TABLE'
            ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION
        ",
        [$schema]
    );

    $tables = [];

    foreach ($rows as $row) {
        $tableName = trim((string) ($row['TABLE_NAME'] ?? ''));

        if ($tableName === '' || str_starts_with($tableName, '_')) {
            continue;
        }

        if (!isset($tables[$tableName])) {
            $tables[$tableName] = [
                'table_name' => $tableName,
                'columns' => [],
                'matchable_string_columns' => [],
                'matchable_numeric_columns' => [],
                'interesting_columns' => [],
                'has_matchable_reference' => false,
                'is_article_context' => false,
            ];
        }

        $columnName = trim((string) ($row['COLUMN_NAME'] ?? ''));
        $type = strtolower(trim((string) ($row['DATA_TYPE'] ?? '')));
        $normalizedColumn = strtolower($columnName);

        $column = [
            'name' => $columnName,
            'type' => $type,
            'max_length' => is_numeric((string) ($row['CHARACTER_MAXIMUM_LENGTH'] ?? null))
                ? (int) $row['CHARACTER_MAXIMUM_LENGTH']
                : null,
            'nullable' => strtoupper((string) ($row['IS_NULLABLE'] ?? 'YES')) === 'YES',
            'ordinal' => is_numeric((string) ($row['ORDINAL_POSITION'] ?? null))
                ? (int) $row['ORDINAL_POSITION']
                : null,
            'is_string' => phptest20_string_type($type),
            'is_numeric' => phptest20_numeric_type($type),
            'is_binary' => phptest20_binary_type($type),
            'is_reference' => false,
            'is_interesting' => phptest20_is_interesting_column($columnName),
        ];

        if ($column['is_string'] && in_array($normalizedColumn, ['acident', 'acbarcode', 'acplu', 'acidentchild', 'acidentparent', 'acidentprod'], true)) {
            $column['is_reference'] = true;
            $tables[$tableName]['matchable_string_columns'][] = $columnName;
            $tables[$tableName]['has_matchable_reference'] = true;
        }

        if ($column['is_numeric'] && in_array($normalizedColumn, ['anidentqid', 'anitemqid', 'analtidentqid'], true)) {
            $column['is_reference'] = true;
            $tables[$tableName]['matchable_numeric_columns'][] = $columnName;
            $tables[$tableName]['has_matchable_reference'] = true;
        }

        if ($column['is_interesting']) {
            $tables[$tableName]['interesting_columns'][] = $columnName;
        }

        $tables[$tableName]['columns'][] = $column;
    }

    foreach ($tables as $tableName => $tableMeta) {
        $tables[$tableName]['is_article_context'] = phptest20_is_article_context_table($tableName)
            || (!empty($tableMeta['has_matchable_reference']) && phptest20_has_operational_prefix($tableName));
    }

    return $tables;
}

function phptest20_load_catalog_row($conn, string $schema, string $catalogTable, string $articleCode, array $columns): array
{
    if (empty($columns)) {
        return [];
    }

    $availableColumns = array_map(static fn (array $column): string => (string) ($column['name'] ?? ''), $columns);
    $selectableColumns = array_values(array_filter($columns, static fn (array $column): bool => empty($column['is_binary'])));

    if (empty($selectableColumns)) {
        return [];
    }

    $where = [];
    $params = [];

    foreach (['acIdent', 'acCode'] as $columnName) {
        if (!in_array($columnName, $availableColumns, true)) {
            continue;
        }

        $where[] = "LTRIM(RTRIM(ISNULL(" . phptest20_quote_identifier($columnName) . ", ''))) = ?";
        $params[] = $articleCode;
    }

    if (empty($where)) {
        return [];
    }

    $selectList = implode(', ', array_map(
        static fn (array $column): string => phptest20_quote_identifier((string) ($column['name'] ?? '')),
        $selectableColumns
    ));

    $rows = phptest20_fetch_all(
        $conn,
        "SELECT TOP 1 {$selectList} FROM " . phptest20_qualify_table($schema, $catalogTable) . ' WHERE ' . implode(' OR ', $where),
        $params
    );

    return empty($rows) ? [] : $rows[0];
}

function phptest20_build_match_sql(array $referenceColumns, array $qidColumns, string $articleCode, ?int $catalogQid): array
{
    $where = [];
    $params = [];

    foreach ($referenceColumns as $columnName) {
        $where[] = "LTRIM(RTRIM(ISNULL(" . phptest20_quote_identifier($columnName) . ", ''))) = ?";
        $params[] = $articleCode;
    }

    foreach ($qidColumns as $columnName) {
        if ($catalogQid === null) {
            continue;
        }

        $where[] = phptest20_quote_identifier($columnName) . ' = ?';
        $params[] = $catalogQid;
    }

    return [implode(' OR ', $where), $params];
}

function phptest20_load_matched_counts($conn, string $schema, array $candidates, string $articleCode, ?int $catalogQid): array
{
    $selects = [];
    $params = [];

    foreach ($candidates as $tableName => $candidate) {
        [$whereSql, $whereParams] = phptest20_build_match_sql(
            $candidate['reference_columns'] ?? [],
            $candidate['qid_columns'] ?? [],
            $articleCode,
            $catalogQid
        );

        if ($whereSql === '') {
            continue;
        }

        $selects[] = 'SELECT ? AS table_name, COUNT_BIG(1) AS match_count FROM '
            . phptest20_qualify_table($schema, $tableName)
            . ' WHERE ' . $whereSql
            . ' HAVING COUNT_BIG(1) > 0';
        $params[] = $tableName;
        array_push($params, ...$whereParams);
    }

    if (empty($selects)) {
        return [];
    }

    $rows = phptest20_fetch_all($conn, implode(' UNION ALL ', $selects), $params, 120);
    $counts = [];

    foreach ($rows as $row) {
        $counts[(string) ($row['table_name'] ?? '')] = (int) ($row['match_count'] ?? 0);
    }

    return $counts;
}

function phptest20_find_matched_tables($conn, string $schema, array $metadata, string $articleCode, ?int $catalogQid, int $sampleLimit): array
{
    $candidates = [];

    foreach ($metadata as $tableName => $tableMeta) {
        if (!phptest20_should_inspect_matches($tableName)) {
            continue;
        }

        $referenceColumns = array_values(array_filter(
            $tableMeta['matchable_string_columns'] ?? [],
            static fn (string $columnName): bool => $columnName !== 'acCode'
        ));
        $qidColumns = $catalogQid !== null
            ? array_values($tableMeta['matchable_numeric_columns'] ?? [])
            : [];

        if (empty($referenceColumns) && empty($qidColumns)) {
            continue;
        }

        $candidates[$tableName] = [
            'reference_columns' => $referenceColumns,
            'qid_columns' => $qidColumns,
        ];
    }

    $counts = phptest20_load_matched_counts($conn, $schema, $candidates, $articleCode, $catalogQid);
    $results = [];

    foreach ($counts as $tableName => $matchCount) {
        $tableMeta = $metadata[$tableName] ?? null;

        if (!is_array($tableMeta) || $matchCount < 1) {
            continue;
        }

        [$whereSql, $params] = phptest20_build_match_sql(
            $candidates[$tableName]['reference_columns'] ?? [],
            $candidates[$tableName]['qid_columns'] ?? [],
            $articleCode,
            $catalogQid
        );

        if ($whereSql === '') {
            continue;
        }

        $sampleColumns = array_values(array_filter(
            $tableMeta['columns'] ?? [],
            static fn (array $column): bool => empty($column['is_binary'])
        ));
        $sampleSelect = implode(', ', array_map(
            static fn (array $column): string => phptest20_quote_identifier((string) ($column['name'] ?? '')),
            $sampleColumns
        ));
        $sampleRows = phptest20_fetch_all(
            $conn,
            "SELECT TOP ({$sampleLimit}) {$sampleSelect} FROM " . phptest20_qualify_table($schema, $tableName) . ' WHERE ' . $whereSql,
            $params,
            120
        );

        $results[] = [
            'table_name' => $tableName,
            'match_count' => $matchCount,
            'reference_columns_checked' => $candidates[$tableName]['reference_columns'] ?? [],
            'qid_columns_checked' => $candidates[$tableName]['qid_columns'] ?? [],
            'columns' => $tableMeta['columns'] ?? [],
            'sample_rows' => $sampleRows,
        ];
    }

    usort($results, static function (array $left, array $right): int {
        $leftTable = (string) ($left['table_name'] ?? '');
        $rightTable = (string) ($right['table_name'] ?? '');
        $leftScore = phptest20_table_priority($leftTable);
        $rightScore = phptest20_table_priority($rightTable);

        if ($leftScore !== $rightScore) {
            return $leftScore <=> $rightScore;
        }

        $leftCount = (int) ($left['match_count'] ?? 0);
        $rightCount = (int) ($right['match_count'] ?? 0);

        if ($leftCount !== $rightCount) {
            return $rightCount <=> $leftCount;
        }

        return strcmp($leftTable, $rightTable);
    });

    return $results;
}

function phptest20_find_interesting_columns(array $metadata, string $catalogTable, array $catalogRow): array
{
    $results = [];

    foreach ($metadata as $tableName => $tableMeta) {
        if ($tableName !== $catalogTable && empty($tableMeta['is_article_context'])) {
            continue;
        }

        foreach (($tableMeta['columns'] ?? []) as $column) {
            if (empty($column['is_interesting'])) {
                continue;
            }

            $columnName = (string) ($column['name'] ?? '');
            if (!empty($column['is_binary'])) {
                continue;
            }

            $catalogValue = $tableName === $catalogTable
                ? phptest20_logical_value($catalogRow[$columnName] ?? null)
                : null;

            $results[] = [
                'table_name' => $tableName,
                'column_name' => $columnName,
                'type' => (string) ($column['type'] ?? ''),
                'catalog_value' => $catalogValue,
            ];
        }
    }

    usort($results, static function (array $left, array $right) use ($catalogTable): int {
        $leftCatalog = (string) ($left['table_name'] ?? '') === $catalogTable;
        $rightCatalog = (string) ($right['table_name'] ?? '') === $catalogTable;

        if ($leftCatalog !== $rightCatalog) {
            return $leftCatalog ? -1 : 1;
        }

        $tableCompare = strcmp((string) ($left['table_name'] ?? ''), (string) ($right['table_name'] ?? ''));

        if ($tableCompare !== 0) {
            return $tableCompare;
        }

        return strcmp((string) ($left['column_name'] ?? ''), (string) ($right['column_name'] ?? ''));
    });

    return $results;
}

$schema = phptest20_identifier((string) ($_GET['schema'] ?? $defaultSchema ?: 'dbo'), $defaultSchema ?: 'dbo');
$catalogTable = phptest20_identifier((string) ($_GET['catalog_table'] ?? 'tHE_SetItem'), 'tHE_SetItem');
$articleCode = trim((string) ($_GET['article_code'] ?? ''));
$sampleLimit = max(1, min((int) ($_GET['sample_limit'] ?? 3), 10));
$report = null;

if ($articleCode !== '') {
    $startedAt = microtime(true);
    $metadata = phptest20_load_metadata($conn, $schema);
    $catalogRow = phptest20_load_catalog_row($conn, $schema, $catalogTable, $articleCode, $metadata[$catalogTable]['columns'] ?? []);
    $catalogQid = phptest20_positive_integer($catalogRow['anQId'] ?? null);
    $matchedTables = phptest20_find_matched_tables($conn, $schema, $metadata, $articleCode, $catalogQid, $sampleLimit);
    $interestingColumns = phptest20_find_interesting_columns($metadata, $catalogTable, $catalogRow);
    $focusedCandidates = array_values(array_filter($interestingColumns, static function (array $column): bool {
        return phptest20_is_focused_candidate((string) ($column['column_name'] ?? ''));
    }));

    $report = [
        'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'catalog_row' => $catalogRow,
        'catalog_qid' => $catalogQid,
        'matched_tables' => $matchedTables,
        'interesting_columns' => $interestingColumns,
        'focused_candidates' => $focusedCandidates,
        'catalog_non_empty_fields' => phptest20_extract_fields($catalogRow, false),
        'catalog_empty_interesting_fields' => phptest20_extract_fields($catalogRow, true, true),
        'catalog_non_empty_interesting_fields' => phptest20_extract_fields($catalogRow, false, true),
    ];
}

if (PHP_SAPI === 'cli') {
    echo 'ARTICLE CODE: ' . ($articleCode !== '' ? $articleCode : '-') . PHP_EOL;
    echo 'SCHEMA: ' . $schema . PHP_EOL;
    echo 'CATALOG TABLE: ' . $catalogTable . PHP_EOL;
    echo 'SAMPLE LIMIT: ' . $sampleLimit . PHP_EOL;

    if ($articleCode === '') {
        echo 'Run example: php public/phptest/test20.php "article_code=0251-012-075"' . PHP_EOL;
        exit;
    }

    $summaryRows = [[
        'article_code' => $articleCode,
        'catalog_row_found' => !empty($report['catalog_row']) ? 'YES' : 'NO',
        'catalog_qid' => $report['catalog_qid'] !== null ? (string) $report['catalog_qid'] : '-',
        'matched_tables' => count($report['matched_tables'] ?? []),
        'interesting_columns' => count($report['interesting_columns'] ?? []),
        'focused_candidates' => count($report['focused_candidates'] ?? []),
        'elapsed_ms' => $report['elapsed_ms'] ?? 0,
    ]];

    phptest20_render_table('Summary', $summaryRows);
    phptest20_render_table('Catalog interesting non-empty fields', $report['catalog_non_empty_interesting_fields'] ?? []);
    phptest20_render_table('Catalog empty interesting fields', $report['catalog_empty_interesting_fields'] ?? []);
    phptest20_render_table('Focused candidate columns', $report['focused_candidates'] ?? []);
    phptest20_render_table('All interesting columns', $report['interesting_columns'] ?? []);

    foreach (($report['matched_tables'] ?? []) as $table) {
        phptest20_render_table(
            'Matched table: ' . (string) ($table['table_name'] ?? ''),
            [[
                'table_name' => $table['table_name'] ?? '',
                'match_count' => $table['match_count'] ?? 0,
                'reference_columns_checked' => implode(', ', $table['reference_columns_checked'] ?? []),
                'qid_columns_checked' => implode(', ', $table['qid_columns_checked'] ?? []),
            ]]
        );
        phptest20_render_table('Columns of ' . (string) ($table['table_name'] ?? ''), $table['columns'] ?? []);
        phptest20_render_table('Sample rows of ' . (string) ($table['table_name'] ?? ''), $table['sample_rows'] ?? []);
    }

    exit;
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>Article schema raw test</title>';
echo '<style>
body{font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.45;margin:20px;background:#f5f6fa;color:#1f2937}
h1,h2,h3{margin:0 0 12px}
.card{background:#fff;border:1px solid #d7dce5;border-radius:10px;padding:16px 18px;margin-bottom:18px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.note{padding:10px 12px;border-radius:8px;background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;margin:10px 0}
.muted{color:#6b7280}
.mono{font-family:Consolas,"Courier New",monospace}
.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.summary .box{padding:12px;border:1px solid #dbe3f3;border-radius:8px;background:#f8fbff}
form.grid{display:grid;grid-template-columns:2fr 1fr 1fr 120px;gap:12px;align-items:end}
label{display:block;font-weight:700;margin-bottom:6px}
input{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
button{padding:10px 14px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #d7dce5;padding:7px 8px;vertical-align:top;text-align:left}
th{background:#f1f5f9}
.table-wrap{overflow:auto}
details{border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;background:#fcfcfd;margin:10px 0}
summary{cursor:pointer;font-weight:700}
.chips{display:flex;flex-wrap:wrap;gap:8px}
.chip{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe}
@media (max-width: 900px){form.grid{grid-template-columns:1fr}}
</style>';
echo '</head><body>';

echo '<div class="card">';
echo '<h1>Article Schema Raw Test</h1>';
echo '<p class="muted">Unesi sifru artikla i skripta ce prikazati tabele, kolone i kandidate za napomenu, zastitu, klasifikaciju ili slicna polja.</p>';
echo '<form method="get" class="grid">';
echo '<div><label for="article_code">Sifra artikla</label><input id="article_code" name="article_code" value="' . phptest20_h($articleCode) . '" class="mono" placeholder="npr. 0251-012-075"></div>';
echo '<div><label for="schema">Schema</label><input id="schema" name="schema" value="' . phptest20_h($schema) . '"></div>';
echo '<div><label for="catalog_table">Katalog tabela</label><input id="catalog_table" name="catalog_table" value="' . phptest20_h($catalogTable) . '"></div>';
echo '<div><label for="sample_limit">Sample rows</label><input id="sample_limit" name="sample_limit" value="' . phptest20_h((string) $sampleLimit) . '"></div>';
echo '<div><button type="submit">Pokreni</button></div>';
echo '</form>';
echo '</div>';

if ($articleCode === '') {
    echo '<div class="card"><div class="note">Primjer: /phptest/test20.php?article_code=0251-012-075</div></div>';
    echo '</body></html>';
    exit;
}

echo '<div class="card">';
echo '<div class="summary">';
echo '<div class="box"><div class="muted">Sifra</div><div class="mono">' . phptest20_h($articleCode) . '</div></div>';
echo '<div class="box"><div class="muted">Catalog row</div><div>' . (!empty($report['catalog_row']) ? 'FOUND' : 'NOT FOUND') . '</div></div>';
echo '<div class="box"><div class="muted">Catalog QID</div><div>' . phptest20_h($report['catalog_qid'] !== null ? (string) $report['catalog_qid'] : '-') . '</div></div>';
echo '<div class="box"><div class="muted">Matched tables</div><div>' . phptest20_h((string) count($report['matched_tables'] ?? [])) . '</div></div>';
echo '<div class="box"><div class="muted">Interesting columns</div><div>' . phptest20_h((string) count($report['interesting_columns'] ?? [])) . '</div></div>';
echo '<div class="box"><div class="muted">Elapsed</div><div>' . phptest20_h((string) ($report['elapsed_ms'] ?? 0)) . ' ms</div></div>';
echo '</div>';
echo '</div>';

echo '<div class="card">';
phptest20_render_table('Catalog interesting non-empty fields', $report['catalog_non_empty_interesting_fields'] ?? []);
phptest20_render_table('Catalog empty interesting fields', $report['catalog_empty_interesting_fields'] ?? []);
phptest20_render_table('Catalog all non-empty fields', $report['catalog_non_empty_fields'] ?? []);
echo '</div>';

echo '<div class="card">';
phptest20_render_table('Focused candidate columns', $report['focused_candidates'] ?? []);
phptest20_render_table('All interesting columns', $report['interesting_columns'] ?? []);
echo '</div>';

echo '<div class="card">';
phptest20_render_heading('Matched tables', 2);

if (empty($report['matched_tables'])) {
    phptest20_render_note('No direct article matches found.');
} else {
    foreach (($report['matched_tables'] ?? []) as $table) {
        echo '<details' . ((string) ($table['table_name'] ?? '') === $catalogTable ? ' open' : '') . '>';
        echo '<summary>' . phptest20_h((string) ($table['table_name'] ?? '')) . ' | rows: ' . phptest20_h((string) ($table['match_count'] ?? 0)) . '</summary>';
        echo '<div class="chips" style="margin:10px 0">';
        foreach (($table['reference_columns_checked'] ?? []) as $columnName) {
            echo '<span class="chip mono">' . phptest20_h((string) $columnName) . '</span>';
        }
        foreach (($table['qid_columns_checked'] ?? []) as $columnName) {
            echo '<span class="chip mono">' . phptest20_h((string) $columnName) . '</span>';
        }
        echo '</div>';
        phptest20_render_table('Columns of ' . (string) ($table['table_name'] ?? ''), $table['columns'] ?? []);
        phptest20_render_table('Sample rows of ' . (string) ($table['table_name'] ?? ''), $table['sample_rows'] ?? []);
        echo '</details>';
    }
}

echo '</div>';
echo '</body></html>';
