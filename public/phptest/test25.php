<?php

/*
 * test25.php
 * Read-only Pantheon order-line inspector for dbo.tHE_OrderItem.
 *
 * Browser examples:
 * - /phptest/test25.php
 * - /phptest/test25.php?order=26-0110-001609
 * - /phptest/test25.php?order=4512125894
 * - /phptest/test25.php?product=6345894
 * - /phptest/test25.php?vat_code=I0
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest25_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest25_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Pantheon order-line inspector</title></head><body>';
    echo '<pre>' . phptest25_h($message) . '</pre></body></html>';
    exit;
}

function phptest25_option(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    if (is_array($value)) {
        $value = end($value);
    }

    return trim((string) $value);
}

function phptest25_identifier(string $value, string $fallback): string
{
    return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1 ? $value : $fallback;
}

function phptest25_fetch_all($conn, string $sql, array $params = []): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => 60]);

    if (!$stmt) {
        phptest25_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest25_value($value, int $maxLength = 5000): string
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

function phptest25_filters(string $order, string $product, string $vatCode): array
{
    $where = [];
    $params = [];

    if ($order !== '') {
        $needle = '%' . $order . '%';
        $normalized = preg_replace('/[^A-Za-z0-9]+/', '', $order) ?? '';
        $orderParts = [
            'CONVERT(nvarchar(255), o.acKey) LIKE ?',
            'CONVERT(nvarchar(255), o.acKeyView) LIKE ?',
            'CONVERT(nvarchar(255), o.acDoc1) LIKE ?',
        ];
        array_push($params, $needle, $needle, $needle);

        if ($normalized !== '') {
            $orderParts[] = "REPLACE(REPLACE(REPLACE(CONVERT(nvarchar(255), o.acKey), '-', ''), ' ', ''), '/', '') = ?";
            $orderParts[] = "REPLACE(REPLACE(REPLACE(CONVERT(nvarchar(255), o.acKeyView), '-', ''), ' ', ''), '/', '') = ?";
            $orderParts[] = "REPLACE(REPLACE(REPLACE(CONVERT(nvarchar(255), o.acDoc1), '-', ''), ' ', ''), '/', '') = ?";
            array_push($params, $normalized, $normalized, $normalized);
        }

        $where[] = '(' . implode(' OR ', $orderParts) . ')';
    }

    if ($product !== '') {
        $where[] = "(
            LTRIM(RTRIM(ISNULL(i.acIdent, ''))) = ?
            OR LTRIM(RTRIM(ISNULL(p.acIdent, ''))) = ?
            OR LTRIM(RTRIM(ISNULL(p.acCode, ''))) = ?
        )";
        array_push($params, $product, $product, $product);
    }

    if ($vatCode !== '') {
        $where[] = "LTRIM(RTRIM(ISNULL(i.acVATCode, ''))) = ?";
        $params[] = strtoupper($vatCode);
    }

    return [
        $where === [] ? '' : 'WHERE ' . implode(' AND ', $where),
        $params,
    ];
}

function phptest25_fetch_focused_rows(
    $conn,
    string $schema,
    string $order,
    string $product,
    string $vatCode,
    int $limit
): array {
    [$whereSql, $params] = phptest25_filters($order, $product, $vatCode);
    $safeLimit = max(1, min($limit, 200));

    return phptest25_fetch_all(
        $conn,
        "
            SELECT TOP ({$safeLimit})
                o.acKeyView AS order_number,
                o.acKey AS order_key,
                o.acDocType AS order_document_type,
                o.acConsignee AS order_supplier,
                o.acDoc1 AS source_document_number,
                o.acCurrency AS order_currency,
                o.anVAT AS order_vat_total,
                o.anForPay AS order_grand_total,
                i.anNo AS line_number,
                i.anQId AS order_item_qid,
                i.anOrderQId AS order_qid,
                i.anIdentQId AS product_qid_on_line,
                i.acIdent AS product_code,
                i.acName AS product_name,
                i.anQty AS quantity,
                i.acUM AS unit,
                i.anPrice AS unit_price,
                i.anRebate AS discount_percent,
                i.acVATCode AS line_vat_code,
                i.anVAT AS line_vat_rate,
                i.anPVValue AS line_net_value,
                i.anPVDiscount AS line_discount_value,
                i.anPVVATBase AS line_vat_base,
                i.anPVVAT AS line_vat_value,
                i.anPVForPay AS line_gross_value,
                i.anPVOCValue AS line_oc_net_value,
                i.anPVOCVATBase AS line_oc_vat_base,
                i.anPVOCVAT AS line_oc_vat_value,
                i.anPVOCForPay AS line_oc_gross_value,
                i.acNote AS line_note,
                i.anUserIns AS line_inserted_by,
                i.adTimeIns AS line_inserted_at,
                p.anQId AS catalog_product_qid,
                p.acVATCode AS product_vat_code,
                p.acVATCodeLow AS product_vat_code_low,
                p.acVATCodeReceive AS product_vat_code_receive,
                p.anVAT AS product_vat_rate,
                p.anVATReceive AS product_vat_receive_rate,
                tax.acName AS vat_definition_name,
                tax.anVAT AS vat_definition_rate,
                tax.acActive AS vat_definition_active
            FROM [{$schema}].[tHE_OrderItem] i
            INNER JOIN [{$schema}].[tHE_Order] o
                ON o.acKey = i.acKey
            LEFT JOIN [{$schema}].[tHE_SetItem] p
                ON LTRIM(RTRIM(p.acIdent)) = LTRIM(RTRIM(i.acIdent))
            LEFT JOIN [{$schema}].[tHE_SetTax] tax
                ON LTRIM(RTRIM(tax.acVATCode)) = LTRIM(RTRIM(i.acVATCode))
            {$whereSql}
            ORDER BY i.adTimeIns DESC, i.anQId DESC
        ",
        $params
    );
}

function phptest25_fetch_raw_rows(
    $conn,
    string $schema,
    string $order,
    string $product,
    string $vatCode,
    int $limit
): array {
    [$whereSql, $params] = phptest25_filters($order, $product, $vatCode);
    $safeLimit = max(1, min($limit, 200));

    return phptest25_fetch_all(
        $conn,
        "
            SELECT TOP ({$safeLimit}) i.*
            FROM [{$schema}].[tHE_OrderItem] i
            INNER JOIN [{$schema}].[tHE_Order] o
                ON o.acKey = i.acKey
            LEFT JOIN [{$schema}].[tHE_SetItem] p
                ON LTRIM(RTRIM(p.acIdent)) = LTRIM(RTRIM(i.acIdent))
            {$whereSql}
            ORDER BY i.adTimeIns DESC, i.anQId DESC
        ",
        $params
    );
}

function phptest25_render_cli(string $title, array $rows): void
{
    echo PHP_EOL . $title . PHP_EOL;
    echo str_repeat('=', max(20, strlen($title))) . PHP_EOL;

    if ($rows === []) {
        echo 'No rows.' . PHP_EOL;
        return;
    }

    foreach ($rows as $index => $row) {
        echo PHP_EOL . 'ROW ' . ($index + 1) . PHP_EOL;

        foreach ($row as $column => $value) {
            echo $column . ': ' . phptest25_value($value, 500) . PHP_EOL;
        }
    }
}

function phptest25_render_table(string $title, array $rows, array $wideColumns = []): void
{
    echo '<section class="card"><div class="section-head"><h2>' . phptest25_h($title) . '</h2><span>' . count($rows) . ' rows</span></div>';

    if ($rows === []) {
        echo '<p class="empty">No matching rows.</p></section>';
        return;
    }

    $columns = array_keys($rows[0]);
    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest25_h($column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            $class = in_array($column, $wideColumns, true) ? ' class="wide"' : '';
            echo '<td' . $class . '>' . phptest25_h(phptest25_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div></section>';
}

function phptest25_render_raw_details(array $rows): void
{
    echo '<section class="card"><div class="section-head"><h2>Complete raw tHE_OrderItem records</h2><span>' . count($rows) . ' rows</span></div>';

    if ($rows === []) {
        echo '<p class="empty">No matching rows.</p></section>';
        return;
    }

    foreach ($rows as $index => $row) {
        $title = 'Line ' . phptest25_value($row['anNo'] ?? null)
            . ' | ' . phptest25_value($row['acIdent'] ?? null)
            . ' | QID ' . phptest25_value($row['anQId'] ?? null);
        echo '<details' . ($index === 0 ? ' open' : '') . '><summary>' . phptest25_h($title) . '</summary>';
        echo '<div class="raw-grid">';

        foreach ($row as $column => $value) {
            $displayValue = phptest25_value($value);
            $state = $value === null ? 'null-value' : ($displayValue === '' ? 'empty-value' : '');
            echo '<div class="raw-field ' . $state . '"><div class="raw-label">' . phptest25_h($column) . '</div>';
            echo '<div class="raw-value">' . phptest25_h($displayValue) . '</div></div>';
        }

        echo '</div></details>';
    }

    echo '</section>';
}

$schema = phptest25_identifier(
    phptest25_option('schema', $defaultSchema ?: 'dbo'),
    $defaultSchema ?: 'dbo'
);
$order = phptest25_option('order', '26-0110-001609');
$product = phptest25_option('product');
$vatCode = strtoupper(phptest25_option('vat_code'));
$limit = max(1, min((int) phptest25_option('limit', '50'), 200));

try {
    $focusedRows = phptest25_fetch_focused_rows($conn, $schema, $order, $product, $vatCode, $limit);
    $rawRows = phptest25_fetch_raw_rows($conn, $schema, $order, $product, $vatCode, $limit);

    if (PHP_SAPI === 'cli') {
        echo 'TABLE: ' . $schema . '.tHE_OrderItem' . PHP_EOL;
        echo 'ORDER: ' . ($order !== '' ? $order : '-') . PHP_EOL;
        echo 'PRODUCT: ' . ($product !== '' ? $product : '-') . PHP_EOL;
        echo 'VAT CODE: ' . ($vatCode !== '' ? $vatCode : '-') . PHP_EOL;
        phptest25_render_cli('Focused order-line data', $focusedRows);
        phptest25_render_cli('Complete raw order-line data', $rawRows);
        sqlsrv_close($conn);
        exit;
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Pantheon order-line inspector</title>';
    echo '<style>
        :root{--ink:#162033;--muted:#667085;--line:#d5dce8;--accent:#1d4ed8;--teal:#0f766e}
        *{box-sizing:border-box}
        body{margin:0;padding:26px;font:14px/1.45 "Segoe UI",sans-serif;color:var(--ink);background:linear-gradient(145deg,#eff6ff,#f8fafc 48%,#ecfdf5) fixed}
        main{max-width:1900px;margin:auto}
        h1,h2{margin:0}
        h1{font-size:29px}
        h2{font-size:18px}
        .intro{margin:7px 0 20px;color:var(--muted)}
        .card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 8px 28px rgba(15,23,42,.07);padding:18px;margin-bottom:20px}
        form{display:grid;grid-template-columns:repeat(4,minmax(180px,1fr)) auto;gap:12px;align-items:end}
        label{display:block;font-weight:700;margin-bottom:5px}
        input{width:100%;padding:10px 11px;border:1px solid #bac5d4;border-radius:8px}
        button{padding:11px 18px;border:0;border-radius:8px;background:var(--accent);color:#fff;font-weight:700;cursor:pointer}
        .meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
        .pill{padding:5px 9px;border-radius:999px;background:#eaf2ff;border:1px solid #bfd2ff;color:#1e40af}
        .section-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
        .section-head span{color:var(--muted)}
        .table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}
        table{border-collapse:collapse;min-width:2200px;width:100%}
        th,td{padding:8px 9px;border-bottom:1px solid #e5e9f0;text-align:left;vertical-align:top;white-space:nowrap}
        th{position:sticky;top:0;background:#edf2f8;z-index:1}
        td.wide{white-space:pre-wrap;min-width:280px;max-width:560px;word-break:break-word}
        details{border:1px solid var(--line);border-radius:10px;margin:10px 0;background:#fbfcfe;overflow:hidden}
        summary{padding:11px 13px;font-weight:800;cursor:pointer;background:#f1f5f9}
        .raw-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1px;background:var(--line)}
        .raw-field{background:#fff;padding:9px 11px;min-height:62px}
        .raw-label{font:12px Consolas,"Courier New",monospace;color:var(--teal);font-weight:800}
        .raw-value{margin-top:4px;white-space:pre-wrap;word-break:break-word}
        .null-value{background:#f3f4f6;color:#6b7280}
        .empty-value{background:#fff7ed;color:#9a3412}
        .empty{color:var(--muted)}
        code{background:#eef2f7;padding:2px 5px;border-radius:5px}
        @media(max-width:1100px){body{padding:14px}form{grid-template-columns:1fr 1fr}.action{grid-column:1/-1}}
        @media(max-width:600px){form{grid-template-columns:1fr}}
    </style></head><body><main>';
    echo '<h1>Pantheon order-line inspector</h1>';
    echo '<p class="intro">Read-only view of <code>' . phptest25_h($schema) . '.tHE_OrderItem</code>, joined to the order header, product catalog and VAT definition.</p>';
    echo '<section class="card"><form method="get">';
    echo '<div><label for="order">Pantheon order or source document</label><input id="order" name="order" value="' . phptest25_h($order) . '" placeholder="26-0110-001609"></div>';
    echo '<div><label for="product">Product code</label><input id="product" name="product" value="' . phptest25_h($product) . '" placeholder="6345894"></div>';
    echo '<div><label for="vat_code">Line VAT code</label><input id="vat_code" name="vat_code" value="' . phptest25_h($vatCode) . '" placeholder="I0 or P1"></div>';
    echo '<div><label for="limit">Maximum rows</label><input id="limit" name="limit" type="number" min="1" max="200" value="' . $limit . '"></div>';
    echo '<div class="action"><button type="submit">Load lines</button></div>';
    echo '</form><div class="meta">';
    echo '<span class="pill">Order lines: ' . count($focusedRows) . '</span>';
    echo '<span class="pill">0110 expected: I0 / 0%</span>';
    echo '<span class="pill">0200 expected: P1 / domestic rate</span>';
    echo '<span class="pill">Read only</span>';
    echo '</div></section>';

    phptest25_render_table('VAT and accounting fields with product comparison', $focusedRows, [
        'product_name',
        'line_note',
    ]);
    phptest25_render_raw_details($rawRows);

    echo '</main></body></html>';
    sqlsrv_close($conn);
} catch (Throwable $exception) {
    if (is_resource($conn)) {
        sqlsrv_close($conn);
    }

    phptest25_fail($exception);
}
