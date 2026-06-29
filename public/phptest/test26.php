<?php

/*
 * test26.php
 * Read-only Pantheon verifier for AI order transfer fields changed for GROB/Ekg.
 *
 * Shows the exact database values for:
 * - tHE_Order.acConsignee          requester_code / Narucitelj
 * - tHE_Order.adDeliveryDeadline   header delivery deadline
 * - tHE_Order.acNote               header note
 * - tHE_OrderItem.adDeliveryDeadline item delivery deadline
 * - tHE_OrderItem.adDeliveryDate     item delivery date
 * - tHE_OrderItem.acNote             item note
 *
 * Browser examples:
 * - /phptest/test26.php
 * - /phptest/test26.php?requester=040
 * - /phptest/test26.php?requester=40
 * - /phptest/test26.php?order=26-0110-001609
 * - /phptest/test26.php?search=4512126028&requester=
 *
 * CLI examples:
 * - php public/phptest/test26.php "requester=040&limit=5"
 * - php public/phptest/test26.php "requester=40&limit=5"
 * - php public/phptest/test26.php "order=26-0110-001609&requester="
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest26_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest26_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Pantheon AI field verifier</title></head><body>';
    echo '<pre>' . phptest26_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest26_option(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    if (is_array($value)) {
        $value = end($value);
    }

    return trim((string) $value);
}

function phptest26_identifier(string $value, string $fallback): string
{
    return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1 ? $value : $fallback;
}

function phptest26_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest26_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest26_value($value, int $maxLength = 5000): string
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

    $string = trim((string) $value);

    if (function_exists('mb_strlen') && mb_strlen($string, 'UTF-8') > $maxLength) {
        return mb_substr($string, 0, $maxLength, 'UTF-8') . '...';
    }

    return $string;
}

function phptest26_note_status($value): string
{
    if ($value === null) {
        return 'NULL';
    }

    return trim((string) $value) === '' ? 'EMPTY' : 'FILLED';
}

function phptest26_normalized(string $value): string
{
    return preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? '';
}

function phptest26_order_conditions(string $order, string $search, string $requester): array
{
    $where = [];
    $params = [];

    if ($order !== '') {
        $needle = '%' . $order . '%';
        $normalized = phptest26_normalized($order);
        $parts = [
            'CONVERT(nvarchar(255), o.acKey) LIKE ?',
            'CONVERT(nvarchar(255), o.acKeyView) LIKE ?',
            'CONVERT(nvarchar(255), o.acDoc1) LIKE ?',
        ];
        array_push($params, $needle, $needle, $needle);

        if ($normalized !== '') {
            $parts[] = "REPLACE(REPLACE(REPLACE(CONVERT(nvarchar(255), o.acKey), '-', ''), ' ', ''), '/', '') = ?";
            $parts[] = "REPLACE(REPLACE(REPLACE(CONVERT(nvarchar(255), o.acKeyView), '-', ''), ' ', ''), '/', '') = ?";
            $parts[] = "REPLACE(REPLACE(REPLACE(CONVERT(nvarchar(255), o.acDoc1), '-', ''), ' ', ''), '/', '') = ?";
            array_push($params, $normalized, $normalized, $normalized);
        }

        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    if ($search !== '') {
        $needle = '%' . $search . '%';
        $parts = [
            'CONVERT(nvarchar(255), o.acKey) LIKE ?',
            'CONVERT(nvarchar(255), o.acKeyView) LIKE ?',
            'CONVERT(nvarchar(255), o.acDoc1) LIKE ?',
            'CONVERT(nvarchar(255), o.acConsignee) LIKE ?',
            'CONVERT(nvarchar(max), o.acNote) LIKE ?',
            'CONVERT(nvarchar(max), o.acInternalNote) LIKE ?',
        ];
        array_push($params, $needle, $needle, $needle, $needle, $needle, $needle);

        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    if ($requester !== '') {
        $where[] = "(
            LTRIM(RTRIM(ISNULL(o.acConsignee, ''))) = ?
            OR TRY_CONVERT(bigint, LTRIM(RTRIM(ISNULL(o.acConsignee, '')))) = TRY_CONVERT(bigint, ?)
        )";
        array_push($params, $requester, $requester);
    }

    return [
        $where === [] ? '' : 'WHERE ' . implode(' AND ', $where),
        $params,
    ];
}

function phptest26_fetch_orders(
    $conn,
    string $schema,
    string $order,
    string $search,
    string $requester,
    int $limit
): array {
    [$whereSql, $params] = phptest26_order_conditions($order, $search, $requester);
    $safeLimit = max(1, min($limit, 100));

    return phptest26_fetch_all(
        $conn,
        "
            SELECT TOP ({$safeLimit})
                o.anQId AS order_qid,
                o.acKey AS order_key,
                o.acKeyView AS order_number,
                o.acDocType AS document_type,
                o.acStatus AS status,
                o.acDoc1 AS source_document_number,
                o.adDate AS order_date,
                o.adDateDoc1 AS source_document_date,
                o.adDeliveryDeadline AS order_delivery_deadline,
                o.acConsignee AS requester_code,
                o.acReceiver AS receiver,
                o.acContactPrsn AS contact_person,
                o.acCurrency AS currency,
                o.anValue AS net_value,
                o.anVAT AS vat_value,
                o.anForPay AS total_value,
                o.acNote AS order_note,
                o.acInternalNote AS order_internal_note,
                o.anUserIns AS inserted_by,
                o.adTimeIns AS inserted_at,
                o.anUserChg AS changed_by,
                o.adTimeChg AS changed_at,
                item_counts.item_count,
                item_counts.items_with_delivery_deadline,
                item_counts.items_with_note
            FROM [{$schema}].[tHE_Order] o
            OUTER APPLY (
                SELECT
                    COUNT(*) AS item_count,
                    SUM(CASE WHEN i.adDeliveryDeadline IS NULL THEN 0 ELSE 1 END) AS items_with_delivery_deadline,
                    SUM(CASE WHEN NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(max), ISNULL(i.acNote, '')))), '') IS NULL THEN 0 ELSE 1 END) AS items_with_note
                FROM [{$schema}].[tHE_OrderItem] i
                WHERE i.acKey = o.acKey
            ) item_counts
            {$whereSql}
            ORDER BY o.adTimeIns DESC, o.anQId DESC
        ",
        $params
    );
}

function phptest26_fetch_items($conn, string $schema, array $orderKeys): array
{
    $keys = array_values(array_unique(array_filter(array_map('strval', $orderKeys))));

    if ($keys === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($keys), '?'));

    return phptest26_fetch_all(
        $conn,
        "
            SELECT
                i.acKey AS order_key,
                i.anQId AS item_qid,
                i.anNo AS line_number,
                i.acIdent AS product_code,
                i.acName AS product_name,
                i.anQty AS quantity,
                i.acUM AS unit,
                i.anPrice AS unit_price,
                i.anRebate AS discount_percent,
                i.acVATCode AS vat_code,
                i.anVAT AS vat_rate,
                i.anPVValue AS line_net_value,
                i.anPVVAT AS line_vat_value,
                i.anPVForPay AS line_total_value,
                i.adDeliveryDeadline AS item_delivery_deadline,
                i.adDeliveryDate AS item_delivery_date,
                i.acNote AS item_note,
                i.anUserIns AS inserted_by,
                i.adTimeIns AS inserted_at,
                i.anUserChg AS changed_by,
                i.adTimeChg AS changed_at
            FROM [{$schema}].[tHE_OrderItem] i
            WHERE i.acKey IN ({$placeholders})
            ORDER BY i.acKey, i.anNo, i.anQId
        ",
        $keys
    );
}

function phptest26_group_by_order_key(array $items): array
{
    $grouped = [];

    foreach ($items as $item) {
        $key = (string) ($item['order_key'] ?? '');
        $grouped[$key][] = $item;
    }

    return $grouped;
}

function phptest26_render_cli(array $orders, array $itemsByOrderKey): void
{
    if ($orders === []) {
        echo 'No matching orders.' . PHP_EOL;
        return;
    }

    foreach ($orders as $orderIndex => $order) {
        $orderKey = (string) ($order['order_key'] ?? '');
        $items = $itemsByOrderKey[$orderKey] ?? [];

        echo PHP_EOL . 'ORDER ' . ($orderIndex + 1) . PHP_EOL;
        echo str_repeat('=', 90) . PHP_EOL;
        echo 'order_key: ' . phptest26_value($order['order_key'] ?? null) . PHP_EOL;
        echo 'order_number: ' . phptest26_value($order['order_number'] ?? null) . PHP_EOL;
        echo 'source_document_number: ' . phptest26_value($order['source_document_number'] ?? null) . PHP_EOL;
        echo 'requester_code / tHE_Order.acConsignee: ' . phptest26_value($order['requester_code'] ?? null) . PHP_EOL;
        echo 'order_delivery_deadline / tHE_Order.adDeliveryDeadline: ' . phptest26_value($order['order_delivery_deadline'] ?? null) . PHP_EOL;
        echo 'order_note status / tHE_Order.acNote: ' . phptest26_note_status($order['order_note'] ?? null) . PHP_EOL;
        echo 'order_note value: ' . phptest26_value($order['order_note'] ?? null, 2000) . PHP_EOL;
        echo 'order_internal_note / tHE_Order.acInternalNote: ' . phptest26_value($order['order_internal_note'] ?? null, 2000) . PHP_EOL;
        echo 'inserted_at / tHE_Order.adTimeIns: ' . phptest26_value($order['inserted_at'] ?? null) . PHP_EOL;
        echo 'changed_at / tHE_Order.adTimeChg: ' . phptest26_value($order['changed_at'] ?? null) . PHP_EOL;
        echo 'item_count: ' . count($items) . PHP_EOL;

        foreach ($items as $itemIndex => $item) {
            echo PHP_EOL . '  ITEM ' . ($itemIndex + 1) . PHP_EOL;
            echo '  line_number: ' . phptest26_value($item['line_number'] ?? null) . PHP_EOL;
            echo '  product_code: ' . phptest26_value($item['product_code'] ?? null) . PHP_EOL;
            echo '  product_name: ' . phptest26_value($item['product_name'] ?? null, 300) . PHP_EOL;
            echo '  item_delivery_deadline / tHE_OrderItem.adDeliveryDeadline: ' . phptest26_value($item['item_delivery_deadline'] ?? null) . PHP_EOL;
            echo '  item_delivery_date / tHE_OrderItem.adDeliveryDate: ' . phptest26_value($item['item_delivery_date'] ?? null) . PHP_EOL;
            echo '  item_note status / tHE_OrderItem.acNote: ' . phptest26_note_status($item['item_note'] ?? null) . PHP_EOL;
            echo '  item_note value: ' . phptest26_value($item['item_note'] ?? null, 1000) . PHP_EOL;
        }
    }
}

function phptest26_render_fact(string $label, $value, string $class = ''): void
{
    $classes = trim('fact ' . $class);
    echo '<div class="' . phptest26_h($classes) . '"><div class="fact-label">' . phptest26_h($label) . '</div>';
    echo '<div class="fact-value">' . phptest26_h(phptest26_value($value)) . '</div></div>';
}

$schema = phptest26_identifier(
    phptest26_option('schema', $defaultSchema ?: 'dbo'),
    $defaultSchema ?: 'dbo'
);
$order = phptest26_option('order');
$search = phptest26_option('search');
$requester = phptest26_option('requester');
$limit = max(1, min((int) phptest26_option('limit', '20'), 100));

try {
    $orders = phptest26_fetch_orders($conn, $schema, $order, $search, $requester, $limit);
    $items = phptest26_fetch_items($conn, $schema, array_column($orders, 'order_key'));
    $itemsByOrderKey = phptest26_group_by_order_key($items);

    if (PHP_SAPI === 'cli') {
        echo 'TABLES: ' . $schema . '.tHE_Order + ' . $schema . '.tHE_OrderItem' . PHP_EOL;
        echo 'ORDER FILTER: ' . ($order !== '' ? $order : '-') . PHP_EOL;
        echo 'SEARCH: ' . ($search !== '' ? $search : '-') . PHP_EOL;
        echo 'REQUESTER CODE FILTER: ' . ($requester !== '' ? $requester : '-') . PHP_EOL;
        echo 'ORDER ROWS: ' . count($orders) . PHP_EOL;
        echo 'ITEM ROWS: ' . count($items) . PHP_EOL;
        phptest26_render_cli($orders, $itemsByOrderKey);
        sqlsrv_close($conn);
        exit;
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Pantheon AI field verifier</title>';
    echo '<style>
        :root{--ink:#172033;--muted:#657083;--line:#d6dde8;--accent:#0f766e;--accent-2:#1d4ed8;--paper:#fff}
        *{box-sizing:border-box}
        body{margin:0;padding:26px;font:14px/1.48 "Segoe UI",Arial,sans-serif;color:var(--ink);background:#f4f7fb}
        main{max-width:1900px;margin:auto}
        h1,h2,h3{margin:0}
        h1{font-size:29px}
        h2{font-size:20px}
        h3{font-size:16px}
        .intro{margin:7px 0 20px;color:var(--muted)}
        .panel,.order{background:var(--paper);border:1px solid var(--line);border-radius:10px;box-shadow:0 8px 24px rgba(15,23,42,.06);padding:18px;margin-bottom:18px}
        form{display:grid;grid-template-columns:minmax(210px,1fr) minmax(210px,1fr) 150px 115px auto;gap:12px;align-items:end}
        label{display:block;font-weight:700;margin-bottom:5px}
        input{width:100%;padding:10px 11px;border:1px solid #b9c4d3;border-radius:7px;background:#fff}
        button{padding:11px 18px;border:0;border-radius:7px;background:var(--accent);color:#fff;font-weight:800;cursor:pointer}
        .meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
        .pill{padding:5px 9px;border:1px solid #a7d8d2;border-radius:999px;background:#e9f8f6;color:#115e59}
        code{background:#edf2f7;border-radius:5px;padding:2px 5px}
        .order-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:14px}
        .order-title{font-size:20px;font-weight:850}
        .source{color:var(--muted)}
        .status{display:inline-flex;align-items:center;min-height:28px;padding:4px 9px;border-radius:999px;font-weight:850;white-space:nowrap}
        .filled{background:#dcfce7;color:#166534}
        .empty{background:#fee2e2;color:#991b1b}
        .null{background:#e5e7eb;color:#374151}
        .facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:1px;background:var(--line);border:1px solid var(--line);border-radius:8px;overflow:hidden;margin-bottom:16px}
        .fact{background:#fff;padding:10px 12px;min-height:64px}
        .fact.key-field{background:#eff6ff}
        .fact.note-field{background:#fff7ed}
        .fact-label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
        .fact-value{font-weight:700;margin-top:3px;white-space:pre-wrap;word-break:break-word}
        .notes{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:17px}
        .note-box{border:1px solid #d9d4c3;border-radius:8px;background:#fffef8;overflow:hidden}
        .note-label{display:flex;justify-content:space-between;gap:10px;padding:9px 11px;background:#f3efdf;font-weight:850;border-bottom:1px solid #d9d4c3}
        pre{margin:0;padding:12px;white-space:pre-wrap;word-break:break-word;font:13px/1.48 Consolas,"Courier New",monospace;max-height:360px;overflow:auto}
        .table-wrap{overflow:auto;border:1px solid var(--line);border-radius:8px}
        table{border-collapse:collapse;min-width:1500px;width:100%;background:#fff}
        th,td{padding:8px 9px;border-bottom:1px solid #e6ebf2;text-align:left;vertical-align:top;white-space:nowrap}
        th{position:sticky;top:0;background:#edf2f8;color:#445064;z-index:1}
        td.wide{white-space:pre-wrap;min-width:250px;max-width:620px;word-break:break-word}
        .no-results{color:#6b7280}
        @media(max-width:1150px){body{padding:14px}form{grid-template-columns:1fr 1fr}.action{grid-column:1/-1}.notes{grid-template-columns:1fr}}
        @media(max-width:620px){form{grid-template-columns:1fr}.order-head{display:block}.status{margin-top:10px}}
    </style></head><body><main>';

    echo '<h1>Pantheon AI field verifier</h1>';
    echo '<p class="intro">Read-only database view of the edited AI transfer fields in <code>' . phptest26_h($schema) . '.tHE_Order</code> and <code>' . phptest26_h($schema) . '.tHE_OrderItem</code>. Requester filtering is optional; numeric requester values match with or without leading zeros.</p>';
    echo '<section class="panel"><form method="get">';
    echo '<div><label for="order">Pantheon order or source document</label><input id="order" name="order" value="' . phptest26_h($order) . '" placeholder="26-0110-001609 or 4512126028"></div>';
    echo '<div><label for="search">Search text</label><input id="search" name="search" value="' . phptest26_h($search) . '" placeholder="Optional broad search"></div>';
    echo '<div><label for="requester">Requester code</label><input id="requester" name="requester" value="' . phptest26_h($requester) . '" placeholder="040"></div>';
    echo '<div><label for="limit">Limit</label><input id="limit" name="limit" type="number" min="1" max="100" value="' . $limit . '"></div>';
    echo '<div class="action"><button type="submit">Show database values</button></div>';
    echo '</form><div class="meta">';
    echo '<span class="pill">Order rows: ' . count($orders) . '</span>';
    echo '<span class="pill">Item rows: ' . count($items) . '</span>';
    echo '<span class="pill">Read only</span>';
    echo '</div></section>';

    if ($orders === []) {
        echo '<section class="panel no-results">No matching orders were found. Clear the requester filter or enter a specific order/source document.</section>';
    }

    foreach ($orders as $orderRow) {
        $orderKey = (string) ($orderRow['order_key'] ?? '');
        $orderItems = $itemsByOrderKey[$orderKey] ?? [];
        $headerNoteStatus = phptest26_note_status($orderRow['order_note'] ?? null);
        $internalNoteStatus = phptest26_note_status($orderRow['order_internal_note'] ?? null);

        echo '<article class="order">';
        echo '<div class="order-head"><div>';
        echo '<div class="order-title">' . phptest26_h(phptest26_value($orderRow['order_number'] ?? null)) . '</div>';
        echo '<div class="source">Source document: ' . phptest26_h(phptest26_value($orderRow['source_document_number'] ?? null)) . ' | Key: ' . phptest26_h(phptest26_value($orderRow['order_key'] ?? null)) . '</div>';
        echo '</div><span class="status ' . strtolower($headerNoteStatus) . '">' . phptest26_h($headerNoteStatus) . ' tHE_Order.acNote</span></div>';

        echo '<div class="facts">';
        phptest26_render_fact('requester_code -> tHE_Order.acConsignee', $orderRow['requester_code'] ?? null, 'key-field');
        phptest26_render_fact('header deadline -> tHE_Order.adDeliveryDeadline', $orderRow['order_delivery_deadline'] ?? null, 'key-field');
        phptest26_render_fact('header note status', $headerNoteStatus, 'note-field');
        phptest26_render_fact('items with deadline', ($orderRow['items_with_delivery_deadline'] ?? 0) . ' / ' . ($orderRow['item_count'] ?? 0));
        phptest26_render_fact('items with note', ($orderRow['items_with_note'] ?? 0) . ' / ' . ($orderRow['item_count'] ?? 0), 'note-field');
        phptest26_render_fact('document type', $orderRow['document_type'] ?? null);
        phptest26_render_fact('status', $orderRow['status'] ?? null);
        phptest26_render_fact('order date', $orderRow['order_date'] ?? null);
        phptest26_render_fact('source date', $orderRow['source_document_date'] ?? null);
        phptest26_render_fact('receiver', $orderRow['receiver'] ?? null);
        phptest26_render_fact('contact person', $orderRow['contact_person'] ?? null);
        phptest26_render_fact('currency', $orderRow['currency'] ?? null);
        phptest26_render_fact('net value', $orderRow['net_value'] ?? null);
        phptest26_render_fact('vat value', $orderRow['vat_value'] ?? null);
        phptest26_render_fact('total value', $orderRow['total_value'] ?? null);
        phptest26_render_fact('inserted at', $orderRow['inserted_at'] ?? null);
        phptest26_render_fact('changed at', $orderRow['changed_at'] ?? null);
        echo '</div>';

        echo '<div class="notes">';
        echo '<div class="note-box"><div class="note-label"><span>tHE_Order.acNote - header note</span><span class="status ' . strtolower($headerNoteStatus) . '">' . phptest26_h($headerNoteStatus) . '</span></div>';
        echo '<pre>' . phptest26_h(phptest26_value($orderRow['order_note'] ?? null)) . '</pre></div>';
        echo '<div class="note-box"><div class="note-label"><span>tHE_Order.acInternalNote - internal trace</span><span class="status ' . strtolower($internalNoteStatus) . '">' . phptest26_h($internalNoteStatus) . '</span></div>';
        echo '<pre>' . phptest26_h(phptest26_value($orderRow['order_internal_note'] ?? null)) . '</pre></div>';
        echo '</div>';

        echo '<h3>Order item database values</h3>';

        if ($orderItems === []) {
            echo '<p class="no-results">No items found for this order key.</p>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr>';
            foreach ([
                'line_number' => 'Line',
                'product_code' => 'Product code',
                'product_name' => 'Product name',
                'quantity' => 'Qty',
                'unit' => 'Unit',
                'unit_price' => 'Price',
                'vat_code' => 'VAT code',
                'vat_rate' => 'VAT rate',
                'item_delivery_deadline' => 'tHE_OrderItem.adDeliveryDeadline',
                'item_delivery_date' => 'tHE_OrderItem.adDeliveryDate',
                'item_note_status' => 'Note status',
                'item_note' => 'tHE_OrderItem.acNote',
                'inserted_at' => 'Inserted at',
                'changed_at' => 'Changed at',
            ] as $label) {
                echo '<th>' . phptest26_h($label) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($orderItems as $item) {
                $itemNoteStatus = phptest26_note_status($item['item_note'] ?? null);
                $cells = [
                    'line_number' => $item['line_number'] ?? null,
                    'product_code' => $item['product_code'] ?? null,
                    'product_name' => $item['product_name'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                    'unit' => $item['unit'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'vat_code' => $item['vat_code'] ?? null,
                    'vat_rate' => $item['vat_rate'] ?? null,
                    'item_delivery_deadline' => $item['item_delivery_deadline'] ?? null,
                    'item_delivery_date' => $item['item_delivery_date'] ?? null,
                    'item_note_status' => $itemNoteStatus,
                    'item_note' => $item['item_note'] ?? null,
                    'inserted_at' => $item['inserted_at'] ?? null,
                    'changed_at' => $item['changed_at'] ?? null,
                ];

                echo '<tr>';
                foreach ($cells as $column => $value) {
                    $class = in_array($column, ['product_name', 'item_note'], true) ? ' class="wide"' : '';
                    echo '<td' . $class . '>' . phptest26_h(phptest26_value($value)) . '</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        }

        echo '</article>';
    }

    echo '</main></body></html>';
    sqlsrv_close($conn);
} catch (Throwable $exception) {
    if (is_resource($conn)) {
        sqlsrv_close($conn);
    }

    phptest26_fail($exception);
}
