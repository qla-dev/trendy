<?php

/*
 * test29.php
 * Read-only AI order transfer payload trace for BA_TRENDY_TESTNA.
 *
 * Shows every scalar value from a transfer payload and where that value lands
 * or is used in the Pantheon target database.
 *
 * Browser examples:
 * - /phptest/test29.php
 * - /phptest/test29.php?key=2601100001708
 *
 * CLI examples:
 * - php public/phptest/test29.php "key=2601100001708"
 * - php public/phptest/test29.php "format=json&payload_file=storage/app/payload.json"
 */

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest29_env(string $key, ?string $default = null): ?string
{
    $root = dirname(__DIR__, 2);
    $path = $root . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($path) || !is_readable($path)) {
        return $default;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return $default;
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$candidateKey, $value] = explode('=', $line, 2);

        if (trim($candidateKey) !== $key) {
            continue;
        }

        $value = trim($value);

        if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    return $default;
}

function phptest29_bool_env(string $key, bool $default): bool
{
    $value = strtolower(trim((string) phptest29_env($key, $default ? 'true' : 'false')));

    if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function phptest29_option(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;

    if (is_array($value)) {
        $value = end($value);
    }

    return trim((string) $value);
}

function phptest29_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest29_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>AI payload target DB trace</title></head><body>';
    echo '<pre>' . phptest29_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest29_identifier(string $value, string $fallback): string
{
    return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1 ? $value : $fallback;
}

function phptest29_quote_identifier(string $identifier): string
{
    return '[' . str_replace(']', ']]', $identifier) . ']';
}

function phptest29_table(string $schema, string $table): string
{
    return phptest29_quote_identifier($schema) . '.' . phptest29_quote_identifier($table);
}

function phptest29_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest29_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest29_fetch_one($conn, string $sql, array $params = [], int $timeout = 60): ?array
{
    $rows = phptest29_fetch_all($conn, $sql, $params, $timeout);

    return $rows[0] ?? null;
}

function phptest29_table_exists($conn, string $schema, string $table): bool
{
    $row = phptest29_fetch_one($conn, "
        SELECT 1 AS found
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
    ", [$schema, $table]);

    return $row !== null;
}

function phptest29_columns($conn, string $schema, string $table): array
{
    $rows = phptest29_fetch_all($conn, "
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ", [$schema, $table]);

    return array_values(array_map(static function (array $row): string {
        return (string) ($row['COLUMN_NAME'] ?? '');
    }, $rows));
}

function phptest29_select_existing(array $columns, array $wanted): string
{
    $selected = [];

    foreach ($wanted as $column) {
        if (in_array($column, $columns, true)) {
            $selected[] = phptest29_quote_identifier($column);
        }
    }

    return $selected !== [] ? implode(', ', $selected) : '*';
}

function phptest29_is_list_array(array $value): bool
{
    if ($value === []) {
        return true;
    }

    return array_keys($value) === range(0, count($value) - 1);
}

function phptest29_flatten_payload($value, string $path = ''): array
{
    if (!is_array($value)) {
        return [[
            'path' => $path,
            'value' => $value,
        ]];
    }

    if ($value === []) {
        return [[
            'path' => $path,
            'value' => [],
        ]];
    }

    $rows = [];
    $isList = phptest29_is_list_array($value);

    foreach ($value as $key => $child) {
        $childPath = $isList
            ? $path . '[' . (int) $key . ']'
            : ($path === '' ? (string) $key : $path . '.' . (string) $key);
        $rows = array_merge($rows, phptest29_flatten_payload($child, $childPath));
    }

    return $rows;
}

function phptest29_value($value, int $maxLength = 500): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_array($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '[]' : $encoded;
    }

    if (is_float($value) || is_int($value)) {
        $formatted = number_format((float) $value, 8, '.', '');
        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    $string = trim((string) $value);

    if (function_exists('mb_strlen') && mb_strlen($string, 'UTF-8') > $maxLength) {
        return mb_substr($string, 0, $maxLength, 'UTF-8') . '...';
    }

    if (strlen($string) > $maxLength) {
        return substr($string, 0, $maxLength) . '...';
    }

    return $string;
}

function phptest29_scalar_compare($expected, $actual): string
{
    if ($expected instanceof DateTimeInterface) {
        $expected = $expected->format('Y-m-d H:i:s');
    }

    if ($actual instanceof DateTimeInterface) {
        $actualString = $actual->format('Y-m-d H:i:s');
        $actualDate = $actual->format('Y-m-d');
    } else {
        $actualString = trim((string) ($actual ?? ''));
        $actualDate = $actualString;
    }

    if ($expected === null) {
        return $actual === null || $actualString === '' ? 'matched' : 'differs';
    }

    if (is_bool($expected)) {
        $actualBool = in_array(strtolower($actualString), ['1', 'true', 't', 'yes'], true);
        return $expected === $actualBool ? 'matched' : 'differs';
    }

    $expectedIsNumericType = is_int($expected) || is_float($expected);
    $actualIsNumericType = is_int($actual) || is_float($actual);

    if (($expectedIsNumericType || $actualIsNumericType)
        && is_numeric((string) $expected)
        && $actual !== null
        && is_numeric((string) $actual)
    ) {
        return abs((float) $expected - (float) $actual) < 0.0001 ? 'matched' : 'differs';
    }

    $expectedString = trim((string) $expected);

    if ($expectedString === '') {
        return $actualString === '' ? 'matched' : 'differs';
    }

    $expectedDate = phptest29_parse_date($expectedString);
    if ($expectedDate !== null) {
        return $expectedDate === $actualDate ? 'matched' : 'differs';
    }

    return $expectedString === $actualString ? 'matched' : 'differs';
}

function phptest29_parse_date(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d\TH:i:s.u\Z',
        'Y-m-d\TH:i:s.uP',
        'Y-m-d',
        'd.m.Y',
        'd.m.y',
    ];

    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

function phptest29_location_row(
    string $path,
    $payloadValue,
    string $database,
    string $schema,
    string $table,
    string $column,
    string $selector,
    ?array $row,
    string $note = '',
    bool $compare = true
): array {
    $hasColumn = $row !== null && array_key_exists($column, $row);
    $actual = $hasColumn ? $row[$column] : null;
    $status = 'row missing';

    if ($row !== null && !$hasColumn) {
        $status = 'column missing';
    } elseif ($hasColumn) {
        $status = $compare ? phptest29_scalar_compare($payloadValue, $actual) : 'informational';
    }

    return [
        'payload_path' => $path,
        'payload_value' => phptest29_value($payloadValue),
        'database_location' => $database . '.' . $schema . '.' . $table . '.' . $column,
        'row_selector' => $selector,
        'stored_value' => $hasColumn ? phptest29_value($actual) : '',
        'status' => $status,
        'note' => $note,
    ];
}

function phptest29_info_row(string $path, $payloadValue, string $location, string $status, string $note): array
{
    return [
        'payload_path' => $path,
        'payload_value' => phptest29_value($payloadValue),
        'database_location' => $location,
        'row_selector' => '',
        'stored_value' => '',
        'status' => $status,
        'note' => $note,
    ];
}

function phptest29_contains_row(
    string $path,
    $payloadValue,
    string $database,
    string $schema,
    string $table,
    string $column,
    string $selector,
    ?array $row,
    string $note = ''
): array {
    $hasColumn = $row !== null && array_key_exists($column, $row);
    $actual = $hasColumn ? $row[$column] : null;
    $status = 'row missing';

    if ($row !== null && !$hasColumn) {
        $status = 'column missing';
    } elseif ($hasColumn) {
        $status = str_contains((string) $actual, (string) $payloadValue) ? 'matched' : 'differs';
    }

    return [
        'payload_path' => $path,
        'payload_value' => phptest29_value($payloadValue),
        'database_location' => $database . '.' . $schema . '.' . $table . '.' . $column,
        'row_selector' => $selector,
        'stored_value' => $hasColumn ? phptest29_value($actual) : '',
        'status' => $status,
        'note' => $note,
    ];
}

function phptest29_item_row_for_index(array $itemRows, int $index, array $payload = []): ?array
{
    $itemPayloads = is_array($payload['item_payloads'] ?? null) ? array_values($payload['item_payloads']) : [];
    $expectedNo = null;

    if (isset($itemPayloads[$index]) && is_array($itemPayloads[$index]) && is_numeric((string) ($itemPayloads[$index]['anNo'] ?? ''))) {
        $expectedNo = (int) $itemPayloads[$index]['anNo'];
    }

    if ($expectedNo !== null) {
        foreach ($itemRows as $row) {
            if ((int) ($row['anNo'] ?? 0) === $expectedNo) {
                return $row;
            }
        }
    }

    return $itemRows[$index] ?? null;
}

function phptest29_product_code_for_item(array $payload, int $index): string
{
    $items = is_array($payload['payload']['items'] ?? null) ? array_values($payload['payload']['items']) : [];
    $itemPayloads = is_array($payload['item_payloads'] ?? null) ? array_values($payload['item_payloads']) : [];

    if (isset($items[$index]) && is_array($items[$index])) {
        $code = trim((string) ($items[$index]['product_code'] ?? ''));
        if ($code !== '') {
            return $code;
        }
    }

    if (isset($itemPayloads[$index]) && is_array($itemPayloads[$index])) {
        return trim((string) ($itemPayloads[$index]['acIdent'] ?? ''));
    }

    return '';
}

function phptest29_payload_locations(
    string $path,
    $value,
    array $payload,
    array $context
): array {
    $database = $context['database'];
    $schema = $context['schema'];
    $orderTable = $context['order_table'];
    $orderItemTable = $context['order_item_table'];
    $catalogTable = $context['catalog_table'];
    $subjectTable = $context['subject_table'];
    $unitTable = $context['unit_table'];
    $contactTable = $context['contact_table'];
    $headerRow = $context['header_row'];
    $itemRows = $context['item_rows'];
    $catalogRows = $context['catalog_rows'];
    $contactRow = $context['contact_row'];
    $selector = 'acKey=' . ($context['order_key'] ?: '(blank)');
    $rows = [];

    if (preg_match('/^header_payload\.([A-Za-z0-9_]+)$/', $path, $matches) === 1) {
        return [phptest29_location_row($path, $value, $database, $schema, $orderTable, $matches[1], $selector, $headerRow)];
    }

    if (preg_match('/^item_payloads\[(\d+)\]\.([A-Za-z0-9_]+)$/', $path, $matches) === 1) {
        $index = (int) $matches[1];
        $itemRow = phptest29_item_row_for_index($itemRows, $index, $payload);
        $itemSelector = $selector . ', item_index=' . $index;

        if ($itemRow !== null && array_key_exists('anNo', $itemRow)) {
            $itemSelector .= ', anNo=' . phptest29_value($itemRow['anNo']);
        }

        return [phptest29_location_row($path, $value, $database, $schema, $orderItemTable, $matches[2], $itemSelector, $itemRow)];
    }

    if (preg_match('/^payload\.warnings\[(\d+)\]$/', $path) === 1) {
        return [phptest29_contains_row(
            $path,
            $value,
            $database,
            $schema,
            $orderTable,
            'acInternalNote',
            $selector,
            $headerRow,
            'AI warnings are folded into the final order internal note.'
        )];
    }

    if (preg_match('/^payload\.items\[(\d+)\]\.([A-Za-z0-9_]+)$/', $path, $matches) === 1) {
        $index = (int) $matches[1];
        $field = $matches[2];
        $itemRow = phptest29_item_row_for_index($itemRows, $index, $payload);
        $itemSelector = $selector . ', item_index=' . $index;
        $productCode = phptest29_product_code_for_item($payload, $index);
        $catalogRow = $productCode !== '' ? ($catalogRows[$productCode] ?? null) : null;

        if ($itemRow !== null && array_key_exists('anNo', $itemRow)) {
            $itemSelector .= ', anNo=' . phptest29_value($itemRow['anNo']);
        }

        $map = [
            'product_code' => ['acIdent'],
            'product_name' => ['acName'],
            'quantity' => ['anQty', 'anQtyConverted'],
            'unit' => ['acUM', 'acUMConverted'],
            'unit_price' => ['anPrice', 'anPriceCurrency'],
            'line_total' => ['anPVValue'],
            'vat_rate' => ['anVAT'],
            'vat_code' => ['acVATCode'],
            'discount_percent' => ['anRebate'],
            'priority' => ['acPriority'],
            'note' => ['acNote'],
            'product_qid' => ['anIdentQId'],
            'base_value' => ['anPVValue', 'anPVVATBase', 'anPVOCValue', 'anPVOCVATBase'],
            'vat_value' => ['anPVVAT', 'anPVOCVAT'],
            'grand_total' => ['anPVForPay', 'anPVOCForPay'],
        ];

        if ($field === 'delivery_deadline') {
            return [
                phptest29_location_row(
                    $path,
                    $value,
                    $database,
                    $schema,
                    $orderItemTable,
                    'adDeliveryDate',
                    $itemSelector,
                    $itemRow,
                    'Rok isporuke should be populated from the item delivery deadline.'
                ),
                phptest29_location_row(
                    $path,
                    $value,
                    $database,
                    $schema,
                    $orderItemTable,
                    'adDeliveryDeadline',
                    $itemSelector,
                    $itemRow,
                    'Rok otpreme should also be populated from the item delivery deadline.'
                ),
            ];
        }

        if (array_key_exists($field, $map)) {
            foreach ($map[$field] as $column) {
                $rows[] = phptest29_location_row($path, $value, $database, $schema, $orderItemTable, $column, $itemSelector, $itemRow);
            }

            if ($field === 'product_code' && $productCode !== '') {
                $rows[] = phptest29_location_row(
                    $path,
                    $value,
                    $database,
                    $schema,
                    $catalogTable,
                    'acIdent',
                    'acIdent=' . $productCode,
                    $catalogRow,
                    'Catalog lookup used during transfer.'
                );
            }

            return $rows;
        }

        if (in_array($field, ['catalog_item_exists', 'catalog_item_missing', 'catalog_item_auto_create', 'catalog_item_created', 'catalog_item_status', 'catalog_item_notice', 'catalog_unit_hint'], true)) {
            return [phptest29_info_row(
                $path,
                $value,
                $database . '.' . $schema . '.' . $catalogTable,
                'lookup',
                'Catalog status fields describe lookup/creation behavior. The target evidence is the catalog row for acIdent=' . ($productCode !== '' ? $productCode : '(blank)') . '.'
            )];
        }

        if ($field === 'line_number') {
            return [phptest29_info_row(
                $path,
                $value,
                $database . '.' . $schema . '.' . $orderItemTable . '.anNo',
                'informational',
                'Source document line number is not inserted directly; target order lines are numbered by transfer order.'
            )];
        }

        return [phptest29_info_row(
            $path,
            $value,
            'AI scan payload / transfer preparation',
            'not a direct target column',
            'This source item field is retained in the transfer payload or used for lookup/validation rather than inserted as its own order column.'
        )];
    }

    $headerMap = [
        'external_document_number' => ['acDoc1'],
        'external_document_date' => ['adDateDoc1'],
        'requester_code' => ['acConsignee', 'acDoc2'],
        'document_type' => ['acDocType'],
        'currency' => ['acCurrency'],
        'contact_name' => ['acContactPrsn', 'acContactPrsn3'],
        'way_of_sale' => ['acWayOfSale'],
        'subtotal' => ['anValue'],
        'vat_total' => ['anVAT'],
        'grand_total' => ['anForPay', 'anCurrValue'],
    ];

    if (preg_match('/^payload\.([A-Za-z0-9_]+)$/', $path, $matches) === 1) {
        $field = $matches[1];

        if ($field === 'delivery_deadline') {
            return [phptest29_info_row(
                $path,
                $value,
                $database . '.' . $schema . '.' . $orderTable . '.adDeliveryDeadline',
                'not written from payload',
                'Header delivery deadline is not sourced from payload.delivery_deadline. Header external document date is traced on payload.external_document_date; item delivery dates are traced on payload.items[*].delivery_deadline.'
            )];
        }

        if (array_key_exists($field, $headerMap)) {
            foreach ($headerMap[$field] as $column) {
                $rows[] = phptest29_location_row($path, $value, $database, $schema, $orderTable, $column, $selector, $headerRow);
            }

            return $rows;
        }

        if (in_array($field, ['customer_name', 'supplier_name', 'receiver_name'], true)) {
            return [phptest29_info_row(
                $path,
                $value,
                $database . '.' . $schema . '.' . $orderTable . '.acConsignee/acReceiver/anConsigneeQId/anReceiverQId',
                'derived',
                'Party fields are transformed before insert and validated against ' . $database . '.' . $schema . '.' . $subjectTable . '.'
            )];
        }

        if ($field === 'note') {
            return [phptest29_info_row(
                $path,
                $value,
                'AI scan payload / transfer preparation',
                'not a direct target column',
                'The current transfer service does not insert the source order note as a standalone target column.'
            )];
        }
    }

    $topLevelMap = [
        'pantheon_order_key' => [['table' => $orderTable, 'column' => 'acKey'], ['table' => $orderItemTable, 'column' => 'acKey']],
        'pantheon_order_view' => [['table' => $orderTable, 'column' => 'acKeyView']],
        'pantheon_order_qid' => [['table' => $orderTable, 'column' => 'anQId']],
        'referent_id' => [
            ['table' => $orderTable, 'column' => 'anClerk'],
            ['table' => $orderTable, 'column' => 'anNoteClerk'],
            ['table' => $orderTable, 'column' => 'anUserIns'],
            ['table' => $orderTable, 'column' => 'anUserChg'],
            ['table' => $contactTable, 'column' => 'anUserID'],
        ],
        'referent_user_code' => [['table' => $contactTable, 'column' => 'acUserId']],
    ];

    if (array_key_exists($path, $topLevelMap)) {
        foreach ($topLevelMap[$path] as $target) {
            $row = $target['table'] === $orderItemTable ? ($itemRows[0] ?? null) : ($target['table'] === $contactTable ? $contactRow : $headerRow);
            $targetSelector = $target['table'] === $orderItemTable ? $selector . ', first item row' : $selector;
            if ($target['table'] === $contactTable) {
                $targetSelector = 'anUserID=' . phptest29_value($payload['referent_id'] ?? '');
            }

            $rows[] = phptest29_location_row(
                $path,
                $value,
                $database,
                $schema,
                $target['table'],
                $target['column'],
                $targetSelector,
                $row
            );
        }

        return $rows;
    }

    if ($path === 'item_count') {
        $actualCount = count($itemRows);
        return [[
            'payload_path' => $path,
            'payload_value' => phptest29_value($value),
            'database_location' => $database . '.' . $schema . '.' . $orderItemTable,
            'row_selector' => $selector,
            'stored_value' => (string) $actualCount,
            'status' => phptest29_scalar_compare($value, $actualCount),
            'note' => 'Count of item rows for this order key.',
        ]];
    }

    if ($path === 'referent') {
        $actualName = '';
        if ($contactRow !== null) {
            $actualName = trim((string) ($contactRow['acContact'] ?? ''));
            if ($actualName === '') {
                $actualName = trim(trim((string) ($contactRow['acName'] ?? '')) . ' ' . trim((string) ($contactRow['acSurname'] ?? '')));
            }
        }

        return [[
            'payload_path' => $path,
            'payload_value' => phptest29_value($value),
            'database_location' => $database . '.' . $schema . '.' . $contactTable . '.acContact/acName/acSurname',
            'row_selector' => 'anUserID=' . phptest29_value($payload['referent_id'] ?? ''),
            'stored_value' => $actualName,
            'status' => $actualName === trim((string) $value) ? 'matched' : 'informational',
            'note' => 'Display name is resolved from contact columns.',
        ]];
    }

    if ($path === 'created_catalog_items') {
        return [phptest29_info_row(
            $path,
            $value,
            $database . '.' . $schema . '.' . $catalogTable,
            empty($value) ? 'matched' : 'check rows below',
            empty($value) ? 'No catalog rows were created by this transfer.' : 'Created catalog items should exist in the catalog table.'
        )];
    }

    if (str_starts_with($path, 'created_catalog_items[')) {
        return [phptest29_info_row(
            $path,
            $value,
            $database . '.' . $schema . '.' . $catalogTable,
            'check catalog',
            'Created catalog item detail should be verified against the catalog row.'
        )];
    }

    return [phptest29_info_row(
        $path,
        $value,
        'AI scan history / transfer response metadata',
        'not target business data',
        'This value is part of the transfer response or scan history, not a standalone target database column.'
    )];
}

function phptest29_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min(6, $level));
    echo '<' . $tag . '>' . phptest29_h($title) . '</' . $tag . '>';
}

function phptest29_render_table(string $title, array $rows): void
{
    phptest29_render_heading($title, 2);

    if ($rows === []) {
        if (PHP_SAPI === 'cli') {
            echo "No rows.\n";
        } else {
            echo '<div class="note">No rows.</div>';
        }
        return;
    }

    $columns = array_keys($rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 220) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest29_value($row[$column] ?? null, 1200);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest29_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $status = strtolower((string) ($row['status'] ?? ''));
        $class = str_replace(' ', '-', $status);
        echo '<tr class="status-' . phptest29_h($class) . '">';

        foreach ($columns as $column) {
            $wide = in_array($column, ['payload_path', 'payload_value', 'database_location', 'stored_value', 'note'], true);
            echo '<td' . ($wide ? ' class="wide"' : '') . '>' . phptest29_h(phptest29_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest29_default_payload_json(): string
{
    $json = <<<'JSON'
{"payload":{"customer_name":"Trendy d.o.o.","supplier_name":"GROB-WERKE","requester_code":"043","receiver_name":"Trendy d.o.o.","contact_name":"","external_document_number":"4512132032","document_type":"0110","currency":"EUR","delivery_deadline":"","note":"","way_of_sale":"D","warnings":["GROB obrada stavki je ograni\u010dena do ACHTUNG reda."],"subtotal":149.4,"vat_total":0,"grand_total":149.4,"items":[{"line_number":10,"product_code":"5803379","product_name":"Stift GM5335\/11-01-100\/1-23","drawing_reference":"","material_hint":"16MnCr5SVA","quantity":2,"unit":"KO","delivery_deadline":"09.09.2026","unit_price":74.7,"line_total":149.4,"vat_rate":0,"vat_code":"I0","discount_percent":0,"priority":"","note":"","primary_classification":"\u010cELIK","catalog_item_exists":true,"catalog_item_missing":false,"catalog_item_auto_create":false,"catalog_item_created":false,"catalog_item_status":"matched","catalog_item_notice":"","catalog_unit_hint":"KO","product_qid":13167,"source_product_code":"5803379","source_product_name":"Stift GM5335\/11-01-100\/1-23","base_value":149.4,"vat_value":0,"grand_total":149.4}]},"pantheon_order_key":"2601100001708","pantheon_order_view":"26-0110-0001708","pantheon_order_qid":null,"item_count":1,"referent":"Lejla Krnji\u0107","referent_id":46,"referent_user_code":"","header_payload":{"acStatus":"1","acPriceRate":"1","acPayMethod":"2","anDaysForValid":5,"anDaysForPayment":"45","acDoc2":"043","adDateDoc1":"2026-07-07 00:00:00","anBnkAcctNo":"1","acCode1":"02","acCode2":"30","acCode3":"11","anFgnBankNo":"0","anNoteClerk":46,"anRoundItem":".0100","anRoundValue":".0100","acRoundVATOnDoc":"F","anSigner1":"0","anSigner2":"0","anSigner3":"0","anFieldNA":".000000","anFieldNB":".000000","anFieldNC":".000000","anFieldND":".000000","anFieldNE":".000000","anFieldNF":".000000","anFieldNG":".000000","anFieldNH":".000000","anFieldNI":".000000","anFieldNJ":".000000","anCurrValue":149.4,"acTriangTrans":"0","acUPNPrint":"F","anRoundItemFC":".0100","anRoundPrice":".0001","anRoundValueOC":".0100","anOurBankAcctNo":"0","anOurBankAcctNoFgn":"0","acRetailSale":"F","anFXRate":"1.955830","acInsertedFrom":"P","acFiscStatus":"0","anDeliveryPriority":"5","anDeliveryDays":"0","anDeptQId":"1","anTransporterQId":"1","anWarehouseQId":"1","acKey":"2601100001708","acDocType":"0110","acRefNo1":"99","adDate":"2026-07-10T00:00:00.000000Z","adDateValid":"2026-07-15T00:00:00.000000Z","acConsignee":"043","acReceiver":"GROB-WERKE","acCurrency":"EUR","acWayOfSale":"D","acWarehouse":"Veleprodajno skladi\u0139\u02c7te","acDoc1":"4512132032","anValue":149.4,"anDiscount":0,"anVAT":0,"anForPay":149.4,"acInternalNote":"Kreirano iz AI skena narud\u017ebe preko eNalog.app | AI napomene: GROB obrada stavki je ograni\u010dena do ACHTUNG reda.","adTimeIns":"2026-07-10T08:23:56.059919Z","adTimeChg":"2026-07-10T08:23:56.059919Z","anUserIns":46,"anUserChg":46,"anClerk":46,"anBuyerCostCenterIdDef":0,"anBuyerIdDef":0,"anConsigneeQId":255,"anReceiverQId":255},"item_payloads":[{"anExcise":"0.0","anExciseP":"0.0","anSalePrice":".0000","anPackQty":"1.000000","anVariant":"0","anDimVolume":"0.0","anDimWeight":"10.300000000000001","anDimWeightBrutto":"10.300000000000001","anRebate1":"0.0","anRebate2":"0.0","anRebate3":"0.0","anRetailPrice":"142.9000","anPriceCurrency":74.7,"anRTPrice":"142.9000","anReserved":".000000","anExciseInc":"0.0","anExciseNotInc":"0.0","anExciseIncP":"0.0","anExciseNotIncP":"0.0","anLastprice":"371.5","anUMDecPlaces":".0000","anRound":"0","anRTPriceConverted":"0.0","acWeighed":"F","anColorCode":"0","anPrstTime":"0.0","acPrstUMTime":"H","anCostDrvQId":"1","anDeptQId":"1","acKey":"2601100001708","anNo":1,"acIdent":"5803379","acName":"Stift GM5335\/11-01-100\/1-23","anQty":2,"anQtyDispDoc":0,"acUM":"KO","anPrice":74.7,"anRebate":0,"acVATCode":"I0","anVAT":0,"anLnkNo":0,"adTimeIns":"2026-07-10T08:23:56.706458Z","adTimeChg":"2026-07-10T08:23:56.706458Z","anPVValue":149.4,"anPVDiscount":0,"anPVExcise":0,"anPVVATBase":149.4,"anPVVAT":0,"anPVForPay":149.4,"anPVOCValue":149.4,"anPVOCDiscount":0,"anPVOCExcise":0,"anPVOCVATBase":149.4,"anPVOCVAT":0,"anPVOCForPay":149.4,"anQtyConverted":2,"acUMConverted":"KO","adDeliveryDate":"2026-09-09T00:00:00.000000Z","adDeliveryDeadline":"2026-09-09T00:00:00.000000Z","anUserIns":1,"anUserChg":1,"anIdentQId":13167}],"created_catalog_items":[],"created_catalog_item_count":0}
JSON;

    $payload = json_decode($json, true);

    if (is_array($payload)) {
        $payload['payload']['external_document_date'] = '09.07.2026';
        $payload['header_payload']['adDateDoc1'] = '2026-07-09 00:00:00';
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (is_string($encoded) && $encoded !== '') {
            return $encoded;
        }
    }

    return $json;
}

function phptest29_payload_json(): string
{
    $payload = phptest29_option('payload');

    if ($payload !== '') {
        return $payload;
    }

    $payloadFile = phptest29_option('payload_file');

    if ($payloadFile !== '') {
        $root = dirname(__DIR__, 2);
        $candidate = $payloadFile;

        if (!preg_match('/^[A-Za-z]:[\/\\\\]/', $candidate) && !str_starts_with($candidate, DIRECTORY_SEPARATOR)) {
            $candidate = $root . DIRECTORY_SEPARATOR . $candidate;
        }

        $realRoot = realpath($root);
        $realFile = realpath($candidate);

        if ($realRoot !== false && $realFile !== false && str_starts_with($realFile, $realRoot) && is_file($realFile)) {
            $contents = file_get_contents($realFile);
            if (is_string($contents) && trim($contents) !== '') {
                return $contents;
            }
        }
    }

    return phptest29_default_payload_json();
}

function phptest29_connect(): array
{
    $host = (string) (phptest29_env('AI_ORDER_TARGET_DB_HOST') ?: phptest29_env('DB_HOST', ''));
    $port = (string) (phptest29_env('AI_ORDER_TARGET_DB_PORT') ?: phptest29_env('DB_PORT', '1433'));
    $database = (string) (phptest29_env('AI_ORDER_TARGET_DB_DATABASE') ?: 'BA_TRENDY_TESTNA');
    $username = (string) (phptest29_env('AI_ORDER_TARGET_DB_USERNAME') ?: phptest29_env('DB_USERNAME', ''));
    $password = (string) (phptest29_env('AI_ORDER_TARGET_DB_PASSWORD') ?: phptest29_env('DB_PASSWORD', ''));

    if ($host === '' || $username === '') {
        phptest29_fail('Missing SQL Server host or username. Set AI_ORDER_TARGET_DB_* or DB_* values in .env.');
    }

    $server = $host . ($port !== '' ? ',' . $port : '');
    $conn = sqlsrv_connect($server, [
        'Database' => $database,
        'UID' => $username,
        'PWD' => $password,
        'CharacterSet' => 'UTF-8',
        'Encrypt' => phptest29_bool_env('AI_ORDER_TARGET_DB_ENCRYPT', false),
        'TrustServerCertificate' => phptest29_bool_env('AI_ORDER_TARGET_DB_TRUST_SERVER_CERTIFICATE', true),
        'LoginTimeout' => 10,
    ]);

    if (!$conn) {
        phptest29_fail(sqlsrv_errors());
    }

    return [$conn, $database];
}

$payloadJson = phptest29_payload_json();
$payload = json_decode($payloadJson, true);

if (!is_array($payload)) {
    phptest29_fail('Payload JSON could not be decoded: ' . json_last_error_msg());
}

[$conn, $database] = phptest29_connect();

$schema = phptest29_identifier(phptest29_option('schema', (string) phptest29_env('DB_SCHEMA', 'dbo')), 'dbo');
$orderTable = phptest29_identifier((string) phptest29_env('WORK_ORDER_ORDERS_TABLE', 'tHE_Order'), 'tHE_Order');
$orderItemTable = phptest29_identifier((string) phptest29_env('WORK_ORDER_ORDER_ITEMS_TABLE', 'tHE_OrderItem'), 'tHE_OrderItem');
$catalogTable = phptest29_identifier((string) phptest29_env('WORK_ORDER_CATALOG_ITEMS_TABLE', 'tHE_SetItem'), 'tHE_SetItem');
$subjectTable = 'tHE_SetSubj';
$unitTable = 'tHE_SetUM';
$contactTable = 'tHE_SetSubjContact';
$orderKey = phptest29_option('key');

if ($orderKey === '') {
    $orderKey = trim((string) ($payload['pantheon_order_key'] ?? ($payload['header_payload']['acKey'] ?? '')));
}

$headerRow = null;
$itemRows = [];
$catalogRows = [];
$unitRows = [];
$subjectRows = [];
$contactRow = null;

if ($orderKey !== '' && phptest29_table_exists($conn, $schema, $orderTable)) {
    $headerRow = phptest29_fetch_one($conn, "
        SELECT TOP 1 *
        FROM " . phptest29_table($schema, $orderTable) . "
        WHERE LTRIM(RTRIM(ISNULL(acKey, ''))) = ?
    ", [$orderKey]);
}

if ($orderKey !== '' && phptest29_table_exists($conn, $schema, $orderItemTable)) {
    $itemRows = phptest29_fetch_all($conn, "
        SELECT *
        FROM " . phptest29_table($schema, $orderItemTable) . "
        WHERE LTRIM(RTRIM(ISNULL(acKey, ''))) = ?
        ORDER BY anNo, anQId
    ", [$orderKey]);
}

$productCodes = [];
foreach ((array) ($payload['payload']['items'] ?? []) as $item) {
    if (is_array($item)) {
        $code = trim((string) ($item['product_code'] ?? ''));
        if ($code !== '') {
            $productCodes[$code] = $code;
        }
    }
}
foreach ((array) ($payload['item_payloads'] ?? []) as $item) {
    if (is_array($item)) {
        $code = trim((string) ($item['acIdent'] ?? ''));
        if ($code !== '') {
            $productCodes[$code] = $code;
        }
    }
}

if ($productCodes !== [] && phptest29_table_exists($conn, $schema, $catalogTable)) {
    $placeholders = implode(', ', array_fill(0, count($productCodes), '?'));
    $codes = array_values($productCodes);
    $catalogRowsRaw = phptest29_fetch_all($conn, "
        SELECT *
        FROM " . phptest29_table($schema, $catalogTable) . "
        WHERE LTRIM(RTRIM(CONVERT(nvarchar(255), acIdent))) IN ({$placeholders})
    ", $codes);

    foreach ($catalogRowsRaw as $row) {
        $code = trim((string) ($row['acIdent'] ?? ''));
        if ($code !== '') {
            $catalogRows[$code] = $row;
        }
    }
}

$subjectIds = [];
foreach (['anConsigneeQId', 'anReceiverQId'] as $column) {
    if ($headerRow !== null && is_numeric((string) ($headerRow[$column] ?? ''))) {
        $subjectIds[(int) $headerRow[$column]] = true;
    }
}

if ($subjectIds !== [] && phptest29_table_exists($conn, $schema, $subjectTable)) {
    $placeholders = implode(', ', array_fill(0, count($subjectIds), '?'));
    $subjectIdValues = array_map('strval', array_keys($subjectIds));
    $subjectRows = phptest29_fetch_all($conn, "
        SELECT *
        FROM " . phptest29_table($schema, $subjectTable) . "
        WHERE LTRIM(RTRIM(CONVERT(nvarchar(255), anQId))) IN ({$placeholders})
        ORDER BY anQId
    ", $subjectIdValues);
}

$units = [];
foreach ($itemRows as $row) {
    foreach (['acUM', 'acUMConverted'] as $column) {
        $unit = trim((string) ($row[$column] ?? ''));
        if ($unit !== '') {
            $units[$unit] = true;
        }
    }
}

if ($units !== [] && phptest29_table_exists($conn, $schema, $unitTable)) {
    $placeholders = implode(', ', array_fill(0, count($units), '?'));
    $unitRows = phptest29_fetch_all($conn, "
        SELECT *
        FROM " . phptest29_table($schema, $unitTable) . "
        WHERE LTRIM(RTRIM(ISNULL(acUM, ''))) IN ({$placeholders})
        ORDER BY acUM
    ", array_keys($units));
}

if (is_numeric((string) ($payload['referent_id'] ?? '')) && phptest29_table_exists($conn, $schema, $contactTable)) {
    $contactRow = phptest29_fetch_one($conn, "
        SELECT TOP 1 *
        FROM " . phptest29_table($schema, $contactTable) . "
        WHERE LTRIM(RTRIM(CONVERT(nvarchar(255), anUserID))) = ?
    ", [(string) ((int) $payload['referent_id'])]);
}

$context = [
    'database' => $database,
    'schema' => $schema,
    'order_table' => $orderTable,
    'order_item_table' => $orderItemTable,
    'catalog_table' => $catalogTable,
    'subject_table' => $subjectTable,
    'unit_table' => $unitTable,
    'contact_table' => $contactTable,
    'order_key' => $orderKey,
    'header_row' => $headerRow,
    'item_rows' => $itemRows,
    'catalog_rows' => $catalogRows,
    'contact_row' => $contactRow,
];

$traceRows = [];
foreach (phptest29_flatten_payload($payload) as $leaf) {
    $traceRows = array_merge(
        $traceRows,
        phptest29_payload_locations((string) $leaf['path'], $leaf['value'], $payload, $context)
    );
}

$summaryCounts = array_count_values(array_map(static function (array $row): string {
    return (string) ($row['status'] ?? '');
}, $traceRows));

$summaryRows = [[
    'database' => $database,
    'schema' => $schema,
    'order_table' => $schema . '.' . $orderTable,
    'item_table' => $schema . '.' . $orderItemTable,
    'order_key' => $orderKey,
    'header_found' => $headerRow !== null ? 'yes' : 'no',
    'item_rows_found' => count($itemRows),
    'payload_leaf_values' => count(phptest29_flatten_payload($payload)),
    'matched' => (int) ($summaryCounts['matched'] ?? 0),
    'differs' => (int) ($summaryCounts['differs'] ?? 0),
    'row_missing' => (int) ($summaryCounts['row missing'] ?? 0),
    'column_missing' => (int) ($summaryCounts['column missing'] ?? 0),
]];

$headerSummaryRows = [];
if ($headerRow !== null) {
    foreach (['acKey', 'acKeyView', 'acDocType', 'acDoc1', 'adDateDoc1', 'acDoc2', 'acConsignee', 'acReceiver', 'acCurrency', 'acWayOfSale', 'acWarehouse', 'anValue', 'anVAT', 'anForPay', 'anClerk', 'anNoteClerk', 'anUserIns', 'anUserChg', 'anConsigneeQId', 'anReceiverQId'] as $column) {
        if (array_key_exists($column, $headerRow)) {
            $headerSummaryRows[] = [
                'table' => $database . '.' . $schema . '.' . $orderTable,
                'column' => $column,
                'value' => phptest29_value($headerRow[$column]),
            ];
        }
    }
}

$itemSummaryRows = [];
foreach ($itemRows as $row) {
    $itemSummaryRows[] = [
        'table' => $database . '.' . $schema . '.' . $orderItemTable,
        'acKey' => phptest29_value($row['acKey'] ?? ''),
        'anNo' => phptest29_value($row['anNo'] ?? ''),
        'acIdent' => phptest29_value($row['acIdent'] ?? ''),
        'acName' => phptest29_value($row['acName'] ?? ''),
        'anQty' => phptest29_value($row['anQty'] ?? ''),
        'acUM' => phptest29_value($row['acUM'] ?? ''),
        'anPrice' => phptest29_value($row['anPrice'] ?? ''),
        'acVATCode' => phptest29_value($row['acVATCode'] ?? ''),
        'anPVForPay' => phptest29_value($row['anPVForPay'] ?? ''),
        'rok_isporuke_adDeliveryDate' => phptest29_value($row['adDeliveryDate'] ?? ''),
        'rok_otpreme_adDeliveryDeadline' => phptest29_value($row['adDeliveryDeadline'] ?? ''),
        'anIdentQId' => phptest29_value($row['anIdentQId'] ?? ''),
    ];
}

$lookupRows = [];
foreach ($catalogRows as $code => $row) {
    $lookupRows[] = [
        'lookup' => 'catalog item',
        'table' => $database . '.' . $schema . '.' . $catalogTable,
        'selector' => 'acIdent=' . $code,
        'qid' => phptest29_value($row['anQId'] ?? ''),
        'code' => phptest29_value($row['acIdent'] ?? ''),
        'name' => phptest29_value($row['acName'] ?? ''),
        'unit' => phptest29_value($row['acUM'] ?? ''),
    ];
}
foreach ($subjectRows as $row) {
    $lookupRows[] = [
        'lookup' => 'subject',
        'table' => $database . '.' . $schema . '.' . $subjectTable,
        'selector' => 'anQId=' . phptest29_value($row['anQId'] ?? ''),
        'qid' => phptest29_value($row['anQId'] ?? ''),
        'code' => phptest29_value($row['acSubject'] ?? ''),
        'name' => phptest29_value($row['acName2'] ?? ($row['acName'] ?? '')),
        'unit' => '',
    ];
}
foreach ($unitRows as $row) {
    $lookupRows[] = [
        'lookup' => 'unit',
        'table' => $database . '.' . $schema . '.' . $unitTable,
        'selector' => 'acUM=' . phptest29_value($row['acUM'] ?? ''),
        'qid' => '',
        'code' => phptest29_value($row['acUM'] ?? ''),
        'name' => phptest29_value($row['acName'] ?? ''),
        'unit' => '',
    ];
}

if (phptest29_option('format') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'summary' => $summaryRows[0],
        'field_trace' => $traceRows,
        'header_summary' => $headerSummaryRows,
        'item_summary' => $itemSummaryRows,
        'lookup_summary' => $lookupRows,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    sqlsrv_close($conn);
    exit;
}

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>AI payload target DB trace</title>';
    echo '<style>
        body{font:14px/1.45 Arial,sans-serif;margin:24px;background:#f6f8fb;color:#182231}
        h1{font-size:25px;margin:0 0 10px}
        h2{margin:24px 0 8px;font-size:18px}
        .note{background:#eef5ff;border:1px solid #c8dcff;border-radius:6px;padding:10px 12px;margin:10px 0 14px}
        form{background:#fff;border:1px solid #d8e0ea;border-radius:8px;padding:14px;margin:14px 0;display:grid;gap:10px}
        textarea{width:100%;min-height:160px;font:12px/1.35 Consolas,monospace;border:1px solid #bac6d3;border-radius:5px;padding:8px}
        input{padding:7px 8px;border:1px solid #bac6d3;border-radius:5px}
        button{justify-self:start;padding:8px 13px;border:0;border-radius:5px;background:#1d4ed8;color:#fff;font-weight:700}
        .row{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
        label{font-weight:700}
        label span{display:block;margin-bottom:4px}
        .table-wrap{overflow:auto;background:#fff;border:1px solid #d8e0ea;border-radius:8px;margin-bottom:18px}
        table{border-collapse:collapse;min-width:100%;font-size:12px}
        th,td{padding:7px 8px;border-right:1px solid #edf1f5;border-bottom:1px solid #e5eaf1;text-align:left;vertical-align:top;white-space:nowrap}
        th{position:sticky;top:0;background:#edf2f8;color:#465469}
        td.wide{white-space:pre-wrap;min-width:220px;max-width:560px;word-break:break-word}
        .status-matched td{background:#f4fff6}
        .status-differs td{background:#fff3f0}
        .status-row-missing td,.status-column-missing td{background:#fff7df}
        .status-not-a-direct-target-column td,.status-not-target-business-data td,.status-derived td,.status-informational td,.status-lookup td{background:#f8fafc}
    </style></head><body>';
}

phptest29_render_heading('AI payload target DB trace', 1);

if (PHP_SAPI !== 'cli') {
    echo '<div class="note">Read-only diagnostic. This connects to the configured AI order target database, defaulting to <code>BA_TRENDY_TESTNA</code>, and traces the transfer payload into the target Pantheon tables.</div>';
    echo '<form method="post">';
    echo '<div class="row">';
    echo '<label><span>Order key</span><input name="key" value="' . phptest29_h($orderKey) . '" placeholder="2601100001708"></label>';
    echo '<label><span>Schema</span><input name="schema" value="' . phptest29_h($schema) . '"></label>';
    echo '</div>';
    echo '<label><span>Transfer payload JSON</span><textarea name="payload">' . phptest29_h($payloadJson) . '</textarea></label>';
    echo '<button type="submit">Trace payload</button>';
    echo '</form>';
}

phptest29_render_table('Connection and order summary', $summaryRows);
phptest29_render_table('Payload field locations', $traceRows);
phptest29_render_table('Target order header snapshot', $headerSummaryRows);
phptest29_render_table('Target order item snapshot', $itemSummaryRows);
phptest29_render_table('Target lookup rows used by transfer', $lookupRows);

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}

sqlsrv_close($conn);
