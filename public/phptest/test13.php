<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

$root = dirname(__DIR__, 2);

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require __DIR__ . '/_conn.php';

use Carbon\Carbon;

function phptest13_h(mixed $value): string
{
    if ($value instanceof DateTimeInterface) {
        $value = $value->format('Y-m-d H:i:s');
    } elseif (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    } elseif ($value === null) {
        $value = '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function phptest13_string(mixed $value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_array($value) || is_object($value)) {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if ($value === null) {
        return '';
    }

    return (string) $value;
}

function phptest13_bool(bool $value): string
{
    return $value ? 'DA' : 'NE';
}

function phptest13_identifier(string $value, string $fallback): string
{
    $value = trim($value);

    if ($value === '') {
        return $fallback;
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        return $fallback;
    }

    return $value;
}

function phptest13_fetch_all($stmt): array
{
    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    return $rows;
}

function phptest13_schema_metadata($conn, string $schema, string $table): array
{
    $sql = "
        SELECT
            COLUMN_NAME,
            DATA_TYPE,
            CHARACTER_MAXIMUM_LENGTH,
            NUMERIC_PRECISION,
            NUMERIC_SCALE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ";

    $stmt = sqlsrv_query($conn, $sql, [$schema, $table], ['QueryTimeout' => 30]);

    if (!$stmt) {
        throw new RuntimeException('Greska pri citanju INFORMATION_SCHEMA.COLUMNS za ' . $schema . '.' . $table);
    }

    $metadata = [];

    foreach (phptest13_fetch_all($stmt) as $row) {
        $columnName = trim((string) ($row['COLUMN_NAME'] ?? ''));

        if ($columnName === '') {
            continue;
        }

        $metadata[$columnName] = [
            'data_type' => trim((string) ($row['DATA_TYPE'] ?? '')),
            'length' => $row['CHARACTER_MAXIMUM_LENGTH'] !== null ? (int) $row['CHARACTER_MAXIMUM_LENGTH'] : null,
            'precision' => $row['NUMERIC_PRECISION'] !== null ? (int) $row['NUMERIC_PRECISION'] : null,
            'scale' => $row['NUMERIC_SCALE'] !== null ? (int) $row['NUMERIC_SCALE'] : null,
        ];
    }

    return $metadata;
}

function phptest13_non_insertable_columns($conn, string $schema, string $table): array
{
    $sql = "
        SELECT c.name
        FROM sys.columns AS c
        INNER JOIN sys.tables AS t ON t.object_id = c.object_id
        INNER JOIN sys.schemas AS s ON s.schema_id = t.schema_id
        WHERE s.name = ? AND t.name = ? AND (c.is_identity = 1 OR c.is_computed = 1)
        ORDER BY c.column_id
    ";

    $stmt = sqlsrv_query($conn, $sql, [$schema, $table], ['QueryTimeout' => 30]);

    if (!$stmt) {
        throw new RuntimeException('Greska pri citanju non-insertable kolona za ' . $schema . '.' . $table);
    }

    $columns = [];
    foreach (phptest13_fetch_all($stmt) as $row) {
        $columns[] = trim((string) ($row['name'] ?? ''));
    }

    return array_values(array_filter($columns));
}

function phptest13_pick_material($conn, string $schema, string $catalogTable, string $requestedCode = ''): array
{
    $requestedCode = trim($requestedCode);
    $qualified = '[' . $schema . '].[' . $catalogTable . ']';
    $sql = "
        SELECT TOP (10)
            LTRIM(RTRIM(ISNULL(acIdent, ''))) AS material_code,
            LTRIM(RTRIM(ISNULL(acName, ''))) AS material_name,
            LTRIM(RTRIM(ISNULL(acUM, ''))) AS material_um,
            CAST(ISNULL(anQId, 0) AS bigint) AS material_qid
        FROM {$qualified}
        WHERE LTRIM(RTRIM(ISNULL(acIdent, ''))) <> ''
          AND LTRIM(RTRIM(ISNULL(acIdent, ''))) <> '0'
          AND LTRIM(RTRIM(ISNULL(acName, ''))) <> ''
    ";

    $params = [];

    if ($requestedCode !== '') {
        $sql .= " AND (LTRIM(RTRIM(ISNULL(acIdent, ''))) = ? OR LTRIM(RTRIM(ISNULL(acIdent, ''))) LIKE ?)";
        $params[] = $requestedCode;
        $params[] = '%' . $requestedCode . '%';
    }

    $sql .= " ORDER BY UPPER(LTRIM(RTRIM(ISNULL(acIdent, '')))) ASC";

    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => 30]);

    if (!$stmt) {
        throw new RuntimeException('Greska pri trazenju test materijala.');
    }

    $rows = phptest13_fetch_all($stmt);

    if ($requestedCode !== '') {
        foreach ($rows as $row) {
            if (strcasecmp(trim((string) ($row['material_code'] ?? '')), $requestedCode) === 0) {
                return $row;
            }
        }
    }

    if (!empty($rows)) {
        return $rows[0];
    }

    throw new RuntimeException('Nije pronadjen nijedan materijal za test.');
}

function phptest13_extract_payload_columns(string $source, string $startMarker, string $endMarker): array
{
    $start = strpos($source, $startMarker);

    if ($start === false) {
        return [];
    }

    $end = strpos($source, $endMarker, $start);
    $segment = $end === false ? substr($source, $start) : substr($source, $start, $end - $start);

    preg_match_all("/\\\$payload\\['([^']+)'\\]\\s*=/", $segment, $matches);

    $columns = array_values(array_unique(array_map('strval', $matches[1] ?? [])));
    sort($columns, SORT_STRING);

    return $columns;
}

function phptest13_status_note_rows(array $metadata, array $insertableColumns, array $payloadColumns, array $previewValues = [], array $sourceRules = []): array
{
    $insertableMap = array_fill_keys($insertableColumns, true);
    $payloadMap = array_fill_keys($payloadColumns, true);
    $rows = [];

    foreach ($metadata as $column => $meta) {
        $normalized = strtolower((string) $column);

        if (!str_contains($normalized, 'status') && !str_contains($normalized, 'note')) {
            continue;
        }

        $rows[] = [
            'column' => (string) $column,
            'data_type' => (string) ($meta['data_type'] ?? ''),
            'length' => $meta['length'] ?? null,
            'insertable' => isset($insertableMap[$column]),
            'in_payload' => isset($payloadMap[$column]),
            'preview_value' => $previewValues[$column] ?? '',
            'source_rule' => $sourceRules[$column] ?? '',
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        return strcmp((string) $a['column'], (string) $b['column']);
    });

    return $rows;
}

function phptest13_payload_rows(array $payloadColumns, array $metadata, array $insertableColumns, array $previewValues = [], array $sourceRules = []): array
{
    $insertableMap = array_fill_keys($insertableColumns, true);
    $rows = [];

    foreach ($payloadColumns as $column) {
        $meta = $metadata[$column] ?? [];

        $rows[] = [
            'column' => (string) $column,
            'exists_in_table' => array_key_exists($column, $metadata),
            'data_type' => (string) ($meta['data_type'] ?? ''),
            'length' => $meta['length'] ?? null,
            'insertable' => isset($insertableMap[$column]),
            'preview_value' => $previewValues[$column] ?? '',
            'source_rule' => $sourceRules[$column] ?? '',
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        return strcmp((string) $a['column'], (string) $b['column']);
    });

    return $rows;
}

function phptest13_render_table(string $title, array $headers, array $rows): void
{
    echo '<h3>' . phptest13_h($title) . '</h3>';

    if (empty($rows)) {
        echo "<p class='note warn'>Nema redova za prikaz.</p>";
        return;
    }

    echo "<div class='table-wrap'><table><thead><tr>";
    foreach ($headers as $header) {
        echo '<th>' . phptest13_h($header) . '</th>';
    }
    echo "</tr></thead><tbody>";

    foreach ($rows as $row) {
        echo '<tr>';
        foreach (array_keys($headers) as $key) {
            $value = $row[$key] ?? '';
            $class = '';

            if (in_array($key, ['insertable', 'in_payload', 'exists_in_table'], true)) {
                $class = !empty($value) ? 'ok' : 'bad';
                $value = phptest13_bool((bool) $value);
            }

            echo '<td class="' . $class . '">' . phptest13_h($value) . '</td>';
        }
        echo '</tr>';
    }

    echo "</tbody></table></div>";
}

$schema = phptest13_identifier((string) config('workorders.schema', $defaultSchema ?: 'dbo'), $defaultSchema ?: 'dbo');
$orderTable = phptest13_identifier((string) config('workorders.orders_table', 'tHE_Order'), 'tHE_Order');
$orderItemTable = phptest13_identifier((string) config('workorders.order_items_table', 'tHE_OrderItem'), 'tHE_OrderItem');
$catalogTable = phptest13_identifier((string) config('workorders.catalog_items_table', 'tHE_SetItem'), 'tHE_SetItem');
$materialCode = trim((string) ($_GET['code'] ?? ''));

$qty = max(0.001, (float) ($_GET['qty'] ?? 2));
$unitPrice = max(0, (float) ($_GET['unit_price'] ?? 170.70));
$lineTotal = (float) ($_GET['line_total'] ?? round($qty * $unitPrice, 4));
$docNo = trim((string) ($_GET['doc_no'] ?? 'AI-TEST-STATUS-NOTE'));
$orderNote = trim((string) ($_GET['order_note'] ?? 'TEST NOTE HEADER - provjera status/note kolona'));
$itemNote = trim((string) ($_GET['item_note'] ?? 'TEST NOTE ITEM - provjera note kolona na stavci'));

$serviceSource = file_get_contents($root . '/app/Services/OrderAi/PantheonOrderTransferService.php') ?: '';
$headerPayloadColumns = phptest13_extract_payload_columns($serviceSource, 'private function buildHeaderPayload', 'private function buildItemPayload');
$itemPayloadColumns = phptest13_extract_payload_columns($serviceSource, 'private function buildItemPayload', 'private function buildHeaderNote');

$selectedMaterial = phptest13_pick_material($conn, $schema, $catalogTable, $materialCode);
$resolvedCode = trim((string) ($selectedMaterial['material_code'] ?? ''));
$resolvedName = trim((string) ($selectedMaterial['material_name'] ?? ''));
$resolvedUnit = strtoupper(substr(trim((string) ($selectedMaterial['material_um'] ?? config('ai-order-scan.default_unit', 'KO'))), 0, 3));

$headerPreviewValues = [
    'acStatus' => '1',
    'acNote' => '',
    'acInternalNote' => 'Kreirano iz AI skena narudzbe preko eNalog.app',
    'acDoc1' => $docNo,
    'acConsignee' => trim((string) ($_GET['supplier_name'] ?? 'GROB-WERKE')),
    'acReceiver' => trim((string) ($_GET['supplier_name'] ?? 'GROB-WERKE')),
];

$itemPreviewValues = [
    'acIdent' => $resolvedCode,
    'acName' => $resolvedName,
    'acUM' => $resolvedUnit,
    'anQty' => $qty,
    'anPrice' => $unitPrice,
    'acNote' => $itemNote,
];

$headerSourceRules = [
    'acStatus' => "Hardcoded u servisu: '1'",
    'acNote' => 'buildHeaderNote(): uvijek prazan string',
    'acInternalNote' => 'buildInternalNote(): AI interni trag',
];

$itemSourceRules = [
    'acNote' => 'mergePantheonTextParts(): overflow naziva + item note',
    'acIdent' => 'product_code iz pripremljene stavke',
    'acName' => 'product_name iz pripremljene stavke',
];

$orderMetadata = phptest13_schema_metadata($conn, $schema, $orderTable);
$orderItemMetadata = phptest13_schema_metadata($conn, $schema, $orderItemTable);
$orderColumns = array_keys($orderMetadata);
$orderItemColumns = array_keys($orderItemMetadata);

$orderNonInsertable = phptest13_non_insertable_columns($conn, $schema, $orderTable);
$orderItemNonInsertable = phptest13_non_insertable_columns($conn, $schema, $orderItemTable);

$orderInsertable = array_values(array_diff($orderColumns, $orderNonInsertable));
$orderItemInsertable = array_values(array_diff($orderItemColumns, $orderItemNonInsertable));

$headerStatusNoteRows = phptest13_status_note_rows($orderMetadata, $orderInsertable, $headerPayloadColumns, $headerPreviewValues, $headerSourceRules);
$itemStatusNoteRows = phptest13_status_note_rows($orderItemMetadata, $orderItemInsertable, $itemPayloadColumns, $itemPreviewValues, $itemSourceRules);
$headerPayloadRows = phptest13_payload_rows($headerPayloadColumns, $orderMetadata, $orderInsertable, $headerPreviewValues, $headerSourceRules);
$itemPayloadRows = phptest13_payload_rows($itemPayloadColumns, $orderItemMetadata, $orderItemInsertable, $itemPreviewValues, $itemSourceRules);

$summaryRows = [
    [
        'scope' => 'Header (narudzba)',
        'table' => $schema . '.' . $orderTable,
        'status_columns' => implode(', ', array_values(array_filter($orderColumns, static fn ($column) => str_contains(strtolower((string) $column), 'status')))) ?: '-',
        'note_columns' => implode(', ', array_values(array_filter($orderColumns, static fn ($column) => str_contains(strtolower((string) $column), 'note')))) ?: '-',
    ],
    [
        'scope' => 'Item (stavka)',
        'table' => $schema . '.' . $orderItemTable,
        'status_columns' => implode(', ', array_values(array_filter($orderItemColumns, static fn ($column) => str_contains(strtolower((string) $column), 'status')))) ?: '-',
        'note_columns' => implode(', ', array_values(array_filter($orderItemColumns, static fn ($column) => str_contains(strtolower((string) $column), 'note')))) ?: '-',
    ],
];

if (PHP_SAPI === 'cli') {
    echo "=== ORDER AI INSERT MAP TEST ===" . PHP_EOL;
    echo "Order table: {$schema}.{$orderTable}" . PHP_EOL;
    echo "Order item table: {$schema}.{$orderItemTable}" . PHP_EOL;
    echo "Material: {$resolvedCode} | {$resolvedName}" . PHP_EOL;
    echo PHP_EOL . "Header status/note:" . PHP_EOL;
    foreach ($headerStatusNoteRows as $row) {
        echo " - {$row['column']} | insertable=" . phptest13_bool((bool) $row['insertable']) . " | in_payload=" . phptest13_bool((bool) $row['in_payload']) . " | source={$row['source_rule']} | preview={$row['preview_value']}" . PHP_EOL;
    }
    echo PHP_EOL . "Item status/note:" . PHP_EOL;
    foreach ($itemStatusNoteRows as $row) {
        echo " - {$row['column']} | insertable=" . phptest13_bool((bool) $row['insertable']) . " | in_payload=" . phptest13_bool((bool) $row['in_payload']) . " | source={$row['source_rule']} | preview={$row['preview_value']}" . PHP_EOL;
    }
    echo PHP_EOL . "Header payload columns: " . implode(', ', $headerPayloadColumns) . PHP_EOL;
    echo "Item payload columns: " . implode(', ', $itemPayloadColumns) . PHP_EOL;
    sqlsrv_close($conn);
    exit(0);
}

echo <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI Order Insert Map Test</title>
  <style>
    body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fb;color:#18324a;margin:0;padding:24px}
    h1,h2,h3{margin:0 0 12px}
    h1{font-size:28px}
    h2{font-size:20px;margin-top:28px}
    h3{font-size:16px;margin-top:18px}
    .meta,.note{padding:12px 14px;border-radius:12px;border:1px solid #d7e2ee;background:#fff;margin:0 0 16px}
    .note{background:#eef8ff}
    .warn{background:#fff6df;border-color:#f1d67c}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:0 0 20px}
    .card{background:#fff;border:1px solid #d7e2ee;border-radius:14px;padding:14px;box-shadow:0 10px 24px rgba(14,30,52,.06)}
    .label{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#68809a;margin-bottom:6px}
    .value{font-size:18px;font-weight:700}
    .table-wrap{overflow:auto;border:1px solid #d7e2ee;border-radius:14px;background:#fff}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px 12px;border-bottom:1px solid #e8eef5;text-align:left;vertical-align:top;font-size:13px}
    th{background:#f3f7fb;color:#5e7389;font-size:12px;text-transform:uppercase;letter-spacing:.05em;position:sticky;top:0}
    tr:last-child td{border-bottom:0}
    .ok{color:#0a7d4f;font-weight:700}
    .bad{color:#9a2133;font-weight:700}
    pre{white-space:pre-wrap;word-break:break-word;background:#0f172a;color:#d8e2f1;padding:14px;border-radius:12px;overflow:auto}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    @media (max-width: 1000px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
HTML;

echo '<h1>AI Order Insert Map Test</h1>';
echo '<p class="note">Ovaj test ne upisuje nista u Pantheon. Pokazuje koje kolone servis priprema za upis na osnovu koda u <b>PantheonOrderTransferService</b>, plus da li te kolone stvarno postoje u baznim tabelama i da li su insertable.</p>';

echo '<div class="cards">';
echo '<div class="card"><span class="label">Order table</span><div class="value">' . phptest13_h($schema . '.' . $orderTable) . '</div></div>';
echo '<div class="card"><span class="label">Order item table</span><div class="value">' . phptest13_h($schema . '.' . $orderItemTable) . '</div></div>';
echo '<div class="card"><span class="label">Header payload fields</span><div class="value">' . phptest13_h((string) count($headerPayloadColumns)) . '</div></div>';
echo '<div class="card"><span class="label">Item payload fields</span><div class="value">' . phptest13_h((string) count($itemPayloadColumns)) . '</div></div>';
echo '</div>';

echo '<div class="meta">';
echo '<b>Test material:</b> ' . phptest13_h($resolvedCode) . ' - ' . phptest13_h($resolvedName) . '<br>';
echo '<b>Sample preview:</b> qty ' . phptest13_h((string) $qty) . ', unit price ' . phptest13_h((string) $unitPrice) . ', line total ' . phptest13_h((string) $lineTotal) . '<br>';
echo '<b>Header note sample:</b> [empty by policy]<br>';
echo '<b>Item note sample:</b> ' . phptest13_h($itemNote);
echo '</div>';

phptest13_render_table(
    'Status / note column summary',
    [
        'scope' => 'Scope',
        'table' => 'Table',
        'status_columns' => 'Status columns',
        'note_columns' => 'Note columns',
    ],
    $summaryRows
);

phptest13_render_table(
    'Header status / note columns',
    [
        'column' => 'Column',
        'data_type' => 'Type',
        'length' => 'Length',
        'insertable' => 'Insertable',
        'in_payload' => 'In payload',
        'preview_value' => 'Sample value',
        'source_rule' => 'Source rule',
    ],
    $headerStatusNoteRows
);

phptest13_render_table(
    'Item status / note columns',
    [
        'column' => 'Column',
        'data_type' => 'Type',
        'length' => 'Length',
        'insertable' => 'Insertable',
        'in_payload' => 'In payload',
        'preview_value' => 'Sample value',
        'source_rule' => 'Source rule',
    ],
    $itemStatusNoteRows
);

echo '<div class="grid">';
echo '<div>';
phptest13_render_table(
    'Header payload columns from service code',
    [
        'column' => 'Column',
        'exists_in_table' => 'Exists in table',
        'data_type' => 'Type',
        'length' => 'Length',
        'insertable' => 'Insertable',
        'preview_value' => 'Sample value',
        'source_rule' => 'Source rule',
    ],
    $headerPayloadRows
);
echo '</div>';

echo '<div>';
phptest13_render_table(
    'Item payload columns from service code',
    [
        'column' => 'Column',
        'exists_in_table' => 'Exists in table',
        'data_type' => 'Type',
        'length' => 'Length',
        'insertable' => 'Insertable',
        'preview_value' => 'Sample value',
        'source_rule' => 'Source rule',
    ],
    $itemPayloadRows
);
echo '</div>';
echo '</div>';

echo '<h2>Quick conclusions</h2>';
echo '<pre>' . phptest13_h(json_encode([
    'header_status_columns_found' => array_values(array_filter(array_map(static fn ($row) => (string) ($row['column'] ?? ''), $headerStatusNoteRows))),
    'item_status_columns_found' => array_values(array_filter(array_map(static fn ($row) => (string) ($row['column'] ?? ''), $itemStatusNoteRows))),
    'service_writes_header_status' => in_array('acStatus', $headerPayloadColumns, true),
    'service_writes_header_note' => in_array('acNote', $headerPayloadColumns, true),
    'header_note_value' => '',
    'service_writes_header_internal_note' => in_array('acInternalNote', $headerPayloadColumns, true),
    'service_writes_item_note' => in_array('acNote', $itemPayloadColumns, true),
    'service_writes_item_status' => (bool) array_values(array_filter($itemPayloadColumns, static fn ($column) => str_contains(strtolower((string) $column), 'status'))),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . '</pre>';

echo '</body></html>';

sqlsrv_close($conn);
