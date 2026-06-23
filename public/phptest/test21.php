<?php

/*
 * test21.php
 * Compare two catalog items with focus on tariff-related save issues.
 *
 * Query parameters:
 * - left=6345894
 * - right=12312312
 * - left_label=candidate
 * - right_label=known eNalog.app
 * - orders=5
 * - schema=dbo
 * - catalog_table=tHE_SetItem
 * - order_item_table=tHE_OrderItem
 * - defaults_table=mHE_SetItemDefVal
 * - tariff_table=tHE_SetCustTariff
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest21_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest21_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Product tariff compare</title></head><body>';
    echo '<pre>' . phptest21_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest21_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest21_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest21_fetch_one($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $rows = phptest21_fetch_all($conn, $sql, $params, $timeout);

    return $rows[0] ?? [];
}

function phptest21_identifier(string $value, string $fallback): string
{
    $trimmed = trim($value);

    if ($trimmed === '' || preg_match('/^[A-Za-z0-9_]+$/', $trimmed) !== 1) {
        return $fallback;
    }

    return $trimmed;
}

function phptest21_quote_identifier(string $identifier): string
{
    return '[' . str_replace(']', ']]', $identifier) . ']';
}

function phptest21_qualify_table(string $schema, string $table): string
{
    return phptest21_quote_identifier($schema) . '.' . phptest21_quote_identifier($table);
}

function phptest21_option_string(string $key, string $default = ''): string
{
    if (!array_key_exists($key, $_GET)) {
        return $default;
    }

    $value = $_GET[$key];

    if (is_array($value)) {
        $value = end($value);
    }

    return trim((string) $value);
}

function phptest21_option_int(string $key, int $default = 0): int
{
    $value = phptest21_option_string($key, (string) $default);

    return is_numeric($value) ? (int) $value : $default;
}

function phptest21_format_number($value, int $scale = 6): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest21_value_string($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_float($value) || is_int($value) || (is_string($value) && is_numeric($value))) {
        return phptest21_format_number($value, 6);
    }

    return trim((string) $value);
}

function phptest21_display_value($value, int $maxLength = 28): string
{
    $string = phptest21_value_string($value);

    if ($string === '') {
        return $value === null ? '[null]' : '[empty]';
    }

    $string = preg_replace('/\s+/u', ' ', $string) ?? $string;

    if (function_exists('mb_strlen') && mb_strlen($string, 'UTF-8') > $maxLength) {
        return mb_substr($string, 0, $maxLength - 3, 'UTF-8') . '...';
    }

    return $string;
}

function phptest21_normalize_for_compare($value): string
{
    if ($value === null) {
        return '<null>';
    }

    return phptest21_value_string($value);
}

function phptest21_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . $title . PHP_EOL;
        echo str_repeat('=', max(12, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest21_h($title) . '</' . $tag . '>';
}

function phptest21_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest21_h($class) . '">' . phptest21_h($text) . '</div>';
}

function phptest21_render_table(string $title, array $rows): void
{
    phptest21_render_heading($title, 3);

    if ($rows === []) {
        phptest21_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 160) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest21_display_value($row[$column] ?? null, 40);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest21_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest21_h(phptest21_display_value($row[$column] ?? null, 120)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest21_render_page_start(string $title): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>' . phptest21_h($title) . '</title>';
    echo '<style>';
    echo 'body{font-family:Arial,sans-serif;margin:24px;background:#f7f7fb;color:#1f2937}';
    echo 'h1,h2,h3{margin:0 0 12px}';
    echo '.note,.warn{padding:12px 14px;border-radius:8px;margin:10px 0 16px}';
    echo '.note{background:#eef6ff;border:1px solid #c7def7}';
    echo '.warn{background:#fff4e5;border:1px solid #f0c98a}';
    echo '.table-wrap{overflow:auto;margin:10px 0 24px;background:#fff;border:1px solid #d9deea;border-radius:10px}';
    echo 'table{border-collapse:collapse;width:100%;font-size:14px}';
    echo 'th,td{padding:10px 12px;border-bottom:1px solid #e6eaf2;text-align:left;vertical-align:top;white-space:nowrap}';
    echo 'th{background:#f1f4fa;font-weight:700}';
    echo 'tr:last-child td{border-bottom:none}';
    echo 'form{background:#fff;border:1px solid #d9deea;border-radius:10px;padding:16px;margin:0 0 20px}';
    echo '.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}';
    echo 'label{display:block;font-size:13px;font-weight:700;margin-bottom:4px}';
    echo 'input{width:100%;padding:9px 10px;border:1px solid #c7cfdd;border-radius:8px;box-sizing:border-box}';
    echo '.actions{margin-top:14px;display:flex;gap:10px;flex-wrap:wrap}';
    echo 'button,a.btn{display:inline-block;background:#1f6feb;color:#fff;text-decoration:none;border:none;border-radius:8px;padding:10px 14px;cursor:pointer}';
    echo 'a.btn.secondary{background:#6b7280}';
    echo 'code{background:#eef2f7;padding:2px 6px;border-radius:6px}';
    echo 'ul{margin:8px 0 24px;padding-left:20px}';
    echo '</style></head><body>';
    echo '<h1>' . phptest21_h($title) . '</h1>';
}

function phptest21_render_page_end(): void
{
    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
}

function phptest21_render_form(array $state): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    echo '<form method="get">';
    echo '<div class="grid">';

    $fields = [
        'left' => 'Left product code',
        'left_label' => 'Left label',
        'right' => 'Right product code',
        'right_label' => 'Right label',
        'orders' => 'Recent order rows',
    ];

    foreach ($fields as $name => $label) {
        echo '<div>';
        echo '<label for="' . phptest21_h($name) . '">' . phptest21_h($label) . '</label>';
        echo '<input id="' . phptest21_h($name) . '" name="' . phptest21_h($name) . '" value="' . phptest21_h((string) ($state[$name] ?? '')) . '">';
        echo '</div>';
    }

    echo '</div>';
    echo '<div class="actions">';
    echo '<button type="submit">Compare products</button>';
    echo '<a class="btn secondary" href="?left=6345894&right=12312312&left_label=candidate&right_label=known+eNalog.app&orders=5">Reset to defaults</a>';
    echo '</div>';
    echo '</form>';
}

function phptest21_table_exists($conn, string $schema, string $table): bool
{
    $row = phptest21_fetch_one(
        $conn,
        "
            SELECT TOP (1) 1 AS exists_flag
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ",
        [$schema, $table]
    );

    return !empty($row);
}

function phptest21_column_names($conn, string $schema, string $table): array
{
    $rows = phptest21_fetch_all(
        $conn,
        "
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ",
        [$schema, $table]
    );

    return array_values(array_filter(array_map(function ($row) {
        return trim((string) ($row['COLUMN_NAME'] ?? ''));
    }, $rows)));
}

function phptest21_fetch_product_row($conn, string $schema, string $table, string $productCode): array
{
    if ($productCode === '' || !phptest21_table_exists($conn, $schema, $table)) {
        return [];
    }

    $qualified = phptest21_qualify_table($schema, $table);

    return phptest21_fetch_one(
        $conn,
        "
            SELECT TOP (1) *
            FROM {$qualified}
            WHERE LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?
        ",
        [$productCode]
    );
}

function phptest21_fetch_tariff_lookup_row($conn, string $schema, string $table, string $tariffCode): array
{
    if ($tariffCode === '' || !phptest21_table_exists($conn, $schema, $table)) {
        return [];
    }

    $qualified = phptest21_qualify_table($schema, $table);

    return phptest21_fetch_one(
        $conn,
        "
            SELECT TOP (1) *
            FROM {$qualified}
            WHERE LTRIM(RTRIM(ISNULL(acCustTariff, ''))) = ?
        ",
        [$tariffCode]
    );
}

function phptest21_count_order_items($conn, string $schema, string $table, string $productCode): int
{
    if ($productCode === '' || !phptest21_table_exists($conn, $schema, $table)) {
        return 0;
    }

    $qualified = phptest21_qualify_table($schema, $table);
    $row = phptest21_fetch_one(
        $conn,
        "
            SELECT COUNT(*) AS item_count
            FROM {$qualified}
            WHERE LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?
        ",
        [$productCode]
    );

    return is_numeric((string) ($row['item_count'] ?? null)) ? (int) $row['item_count'] : 0;
}

function phptest21_fetch_recent_order_items($conn, string $schema, string $table, string $productCode, int $limit): array
{
    if ($productCode === '' || !phptest21_table_exists($conn, $schema, $table)) {
        return [];
    }

    $qualified = phptest21_qualify_table($schema, $table);
    $safeLimit = max(1, $limit);

    return phptest21_fetch_all(
        $conn,
        "
            SELECT TOP ({$safeLimit})
                acKey,
                acIdent,
                acName,
                acUM,
                acVATCode,
                anIdentQId,
                anQId
            FROM {$qualified}
            WHERE LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?
            ORDER BY anQId DESC
        ",
        [$productCode]
    );
}

function phptest21_tariff_status(bool $exists, string $tariffCode, array $lookupRow): string
{
    if (!$exists) {
        return 'missing_product';
    }

    if ($tariffCode === '') {
        return 'missing';
    }

    return $lookupRow === [] ? 'invalid_lookup' : 'valid_lookup';
}

function phptest21_build_issues(bool $exists, array $catalogRow, array $defaultsRow, string $tariffStatus): array
{
    if (!$exists) {
        return ['Product does not exist in the catalog table.'];
    }

    $issues = [];

    if ($tariffStatus === 'missing') {
        $issues[] = 'acCustTariff is blank in the catalog item.';
    } elseif ($tariffStatus === 'invalid_lookup') {
        $issues[] = 'acCustTariff is set, but no matching row exists in the tariff lookup table.';
    }

    foreach ([
        'acVATCode' => 'Catalog VAT code is blank.',
        'acCurrency' => 'Catalog currency is blank.',
        'acPurchCurr' => 'Catalog purchase currency is blank.',
        'acDocTypeProd' => 'Catalog production doc type is blank.',
    ] as $column => $message) {
        if (trim((string) ($catalogRow[$column] ?? '')) === '') {
            $issues[] = $message;
        }
    }

    if ($defaultsRow === []) {
        $issues[] = 'No matching row exists in the default-value mirror table.';
    } elseif (trim((string) ($defaultsRow['acCustTariff'] ?? '')) === '') {
        $issues[] = 'Default-value mirror also has a blank acCustTariff.';
    }

    return array_values(array_unique($issues));
}

function phptest21_build_report(
    $conn,
    string $schema,
    string $catalogTable,
    string $orderItemTable,
    string $defaultsTable,
    string $tariffTable,
    string $code,
    string $label,
    int $orderLimit
): array {
    $catalogRow = phptest21_fetch_product_row($conn, $schema, $catalogTable, $code);
    $defaultsRow = phptest21_fetch_product_row($conn, $schema, $defaultsTable, $code);
    $exists = $catalogRow !== [];
    $tariffCode = trim((string) ($catalogRow['acCustTariff'] ?? ''));
    $tariffLookup = phptest21_fetch_tariff_lookup_row($conn, $schema, $tariffTable, $tariffCode);
    $tariffName = trim((string) ($tariffLookup['acName'] ?? ''));
    $tariffStatus = phptest21_tariff_status($exists, $tariffCode, $tariffLookup);

    return [
        'code' => $code,
        'label' => $label,
        'exists' => $exists,
        'catalog_row' => $catalogRow,
        'defaults_row' => $defaultsRow,
        'summary' => [
            'Exists' => $exists ? 'yes' : 'no',
            'Name' => $catalogRow['acName'] ?? null,
            'Tariff code' => $tariffCode,
            'Tariff name' => $tariffName,
            'Tariff status' => $tariffStatus,
            'Pantheon edit risk' => in_array($tariffStatus, ['missing', 'invalid_lookup'], true) ? 'high' : 'low',
            'VAT code' => $catalogRow['acVATCode'] ?? null,
            'Unit' => $catalogRow['acUM'] ?? null,
            'Set of item' => $catalogRow['acSetOfItem'] ?? null,
            'Classification' => $catalogRow['acClassif'] ?? null,
            'Currency' => $catalogRow['acCurrency'] ?? null,
            'Purchase currency' => $catalogRow['acPurchCurr'] ?? null,
            'Production doc type' => $catalogRow['acDocTypeProd'] ?? null,
            'Catalog QID' => $catalogRow['anQId'] ?? null,
            'Inserted at' => $catalogRow['adTimeIns'] ?? null,
            'Inserted by user' => $catalogRow['anUserIns'] ?? null,
        ],
        'defaults_summary' => [
            'Exists' => $defaultsRow !== [] ? 'yes' : 'no',
            'Name' => $defaultsRow['acName'] ?? null,
            'Tariff code' => $defaultsRow['acCustTariff'] ?? null,
            'VAT code' => $defaultsRow['acVATCode'] ?? null,
            'VAT receive' => $defaultsRow['acVatCodeReceive'] ?? null,
            'Unit' => $defaultsRow['acUM'] ?? null,
            'Set of item' => $defaultsRow['acSetOfItem'] ?? null,
            'Type' => $defaultsRow['acType'] ?? null,
            'Currency' => $defaultsRow['acCurrency'] ?? null,
            'Purchase currency' => $defaultsRow['acPurchCurr'] ?? null,
        ],
        'order_item_count' => $exists ? phptest21_count_order_items($conn, $schema, $orderItemTable, $code) : 0,
        'recent_order_items' => $exists ? phptest21_fetch_recent_order_items($conn, $schema, $orderItemTable, $code, $orderLimit) : [],
        'issues' => phptest21_build_issues($exists, $catalogRow, $defaultsRow, $tariffStatus),
    ];
}

function phptest21_diff_rows(array $leftRow, array $rightRow, array $columns): array
{
    $rows = [];

    foreach ($columns as $column) {
        $leftValue = $leftRow[$column] ?? null;
        $rightValue = $rightRow[$column] ?? null;

        if (phptest21_normalize_for_compare($leftValue) === phptest21_normalize_for_compare($rightValue)) {
            continue;
        }

        $rows[] = [
            'Column' => $column,
            'Left' => phptest21_display_value($leftValue, 80),
            'Right' => phptest21_display_value($rightValue, 80),
        ];
    }

    return $rows;
}

$schema = phptest21_identifier(phptest21_option_string('schema', $defaultSchema), $defaultSchema);
$catalogTable = phptest21_identifier(phptest21_option_string('catalog_table', 'tHE_SetItem'), 'tHE_SetItem');
$orderItemTable = phptest21_identifier(phptest21_option_string('order_item_table', 'tHE_OrderItem'), 'tHE_OrderItem');
$defaultsTable = phptest21_identifier(phptest21_option_string('defaults_table', 'mHE_SetItemDefVal'), 'mHE_SetItemDefVal');
$tariffTable = phptest21_identifier(phptest21_option_string('tariff_table', 'tHE_SetCustTariff'), 'tHE_SetCustTariff');
$leftCode = phptest21_option_string('left', '6345894');
$rightCode = phptest21_option_string('right', '12312312');
$leftLabel = phptest21_option_string('left_label', 'candidate');
$rightLabel = phptest21_option_string('right_label', 'known eNalog.app');
$orderLimit = max(1, phptest21_option_int('orders', 5));

try {
    $catalogColumns = phptest21_column_names($conn, $schema, $catalogTable);
    $leftReport = phptest21_build_report($conn, $schema, $catalogTable, $orderItemTable, $defaultsTable, $tariffTable, $leftCode, $leftLabel, $orderLimit);
    $rightReport = phptest21_build_report($conn, $schema, $catalogTable, $orderItemTable, $defaultsTable, $tariffTable, $rightCode, $rightLabel, $orderLimit);

    phptest21_render_page_start('Product tariff comparison');

    phptest21_render_note(
        'Open this test directly in the browser and change the compared article codes with the form below. '
        . 'Example URL params: left=6345894&right=12312312'
    );

    phptest21_render_form([
        'left' => $leftCode,
        'left_label' => $leftLabel,
        'right' => $rightCode,
        'right_label' => $rightLabel,
        'orders' => (string) $orderLimit,
    ]);

    phptest21_render_table('Comparison context', [[
        'Connection host' => $server,
        'Database' => $database,
        'Schema' => $schema,
        'Catalog table' => $catalogTable,
        'Defaults table' => $defaultsTable,
        'Tariff table' => $tariffTable,
        'Left' => $leftCode . ' [' . $leftLabel . ']',
        'Right' => $rightCode . ' [' . $rightLabel . ']',
    ]]);

    $summaryRows = [];
    foreach ($leftReport['summary'] as $field => $leftValue) {
        $summaryRows[] = [
            'Field' => $field,
            'Left' => phptest21_display_value($leftValue, 80),
            'Right' => phptest21_display_value($rightReport['summary'][$field] ?? null, 80),
        ];
    }
    phptest21_render_table('Summary', $summaryRows);

    $diffRows = phptest21_diff_rows(
        $leftReport['catalog_row'],
        $rightReport['catalog_row'],
        array_values(array_unique(array_merge(
            $catalogColumns,
            array_keys($leftReport['catalog_row']),
            array_keys($rightReport['catalog_row'])
        )))
    );
    phptest21_render_table('Differing catalog columns', $diffRows);

    phptest21_render_table('Recent order-item usage: ' . $leftCode . ' [' . $leftLabel . ']', $leftReport['recent_order_items']);
    phptest21_render_table('Recent order-item usage: ' . $rightCode . ' [' . $rightLabel . ']', $rightReport['recent_order_items']);

    $defaultsRows = [];
    foreach ($leftReport['defaults_summary'] as $field => $leftValue) {
        $defaultsRows[] = [
            'Field' => $field,
            'Left' => phptest21_display_value($leftValue, 80),
            'Right' => phptest21_display_value($rightReport['defaults_summary'][$field] ?? null, 80),
        ];
    }
    phptest21_render_table('Default-value mirror comparison', $defaultsRows);

    phptest21_render_heading('Diagnostic hints', 3);

    foreach ([$leftReport, $rightReport] as $report) {
        if (PHP_SAPI === 'cli') {
            echo $report['code'] . ' [' . $report['label'] . ']' . PHP_EOL;
            foreach ($report['issues'] as $issue) {
                echo ' - ' . $issue . PHP_EOL;
            }
            if ($report['issues'] === []) {
                echo ' - no obvious tariff-related warnings found' . PHP_EOL;
            }
            continue;
        }

        echo '<div class="' . ($report['issues'] === [] ? 'note' : 'warn') . '">';
        echo '<strong>' . phptest21_h($report['code'] . ' [' . $report['label'] . ']') . '</strong>';
        echo '<ul>';
        if ($report['issues'] === []) {
            echo '<li>no obvious tariff-related warnings found</li>';
        } else {
            foreach ($report['issues'] as $issue) {
                echo '<li>' . phptest21_h($issue) . '</li>';
            }
        }
        echo '</ul>';
        echo '</div>';
    }

    phptest21_render_page_end();
} catch (Throwable $exception) {
    phptest21_fail($exception);
}
