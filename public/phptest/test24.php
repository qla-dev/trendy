<?php

/*
 * test24.php
 * Read-only viewer for note fields on one Pantheon product.
 *
 * Browser examples:
 * - /phptest/test24.php
 * - /phptest/test24.php?product=12312312
 * - /phptest/test24.php?product=6501766
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest24_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest24_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Pantheon product notes</title></head><body>';
    echo '<pre>' . phptest24_h($message) . '</pre></body></html>';
    exit;
}

function phptest24_option(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    if (is_array($value)) {
        $value = end($value);
    }

    return trim((string) $value);
}

function phptest24_identifier(string $value, string $fallback): string
{
    return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1 ? $value : $fallback;
}

function phptest24_fetch_product($conn, string $schema, string $productCode): array
{
    $stmt = sqlsrv_query(
        $conn,
        "
            SELECT TOP (1)
                anQId,
                acIdent,
                acCode,
                acName,
                acDescr,
                acClassif,
                acClassif2,
                acSetOfItem,
                acActive,
                acUM,
                acUM2,
                acCurrency,
                acPurchCurr,
                acVATCode,
                anVAT,
                acCustTariff,
                acDocTypeProd,
                anUserIns,
                adTimeIns,
                anUserChg,
                adTimeChg,
                acNote,
                acDMNote,
                acFieldSE
            FROM [{$schema}].[tHE_SetItem]
            WHERE LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?
               OR LTRIM(RTRIM(ISNULL(acCode, ''))) = ?
            ORDER BY CASE
                WHEN LTRIM(RTRIM(ISNULL(acIdent, ''))) = ? THEN 0
                ELSE 1
            END, anQId DESC
        ",
        [$productCode, $productCode, $productCode],
        ['QueryTimeout' => 60]
    );

    if (!$stmt) {
        phptest24_fail(sqlsrv_errors());
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    return is_array($row) ? $row : [];
}

function phptest24_value($value): string
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

    return trim((string) $value);
}

function phptest24_note_status($value): string
{
    if ($value === null) {
        return 'NULL';
    }

    return trim((string) $value) === '' ? 'EMPTY' : 'FILLED';
}

$schema = phptest24_identifier(
    phptest24_option('schema', $defaultSchema ?: 'dbo'),
    $defaultSchema ?: 'dbo'
);
$productCode = phptest24_option('product', '12312312');

try {
    $product = $productCode !== ''
        ? phptest24_fetch_product($conn, $schema, $productCode)
        : [];

    if (PHP_SAPI === 'cli') {
        echo 'TABLE: ' . $schema . '.tHE_SetItem' . PHP_EOL;
        echo 'PRODUCT: ' . ($productCode !== '' ? $productCode : '-') . PHP_EOL;
        echo 'FOUND: ' . ($product === [] ? 'NO' : 'YES') . PHP_EOL;

        if ($product !== []) {
            foreach ($product as $column => $value) {
                echo $column . ': ' . phptest24_value($value) . PHP_EOL;
            }

            echo PHP_EOL . 'NOTE STATUS' . PHP_EOL;
            foreach (['acNote', 'acDMNote', 'acFieldSE'] as $column) {
                echo $column . ': ' . phptest24_note_status($product[$column] ?? null) . PHP_EOL;
            }
        }

        sqlsrv_close($conn);
        exit;
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Pantheon product notes</title>';
    echo '<style>
        :root{--ink:#1c2536;--muted:#667085;--line:#d6dde8;--accent:#9a3412}
        *{box-sizing:border-box}
        body{margin:0;padding:28px;font:14px/1.5 "Segoe UI",sans-serif;color:var(--ink);background:radial-gradient(circle at 85% 0,#ffedd5 0,transparent 30%),#f5f7fa}
        main{max-width:1300px;margin:auto}
        h1,h2{margin:0}
        h1{font-size:29px}
        .intro{margin:7px 0 20px;color:var(--muted)}
        .card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 8px 28px rgba(15,23,42,.07);padding:18px;margin-bottom:20px}
        form{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:12px;align-items:end}
        label{display:block;font-weight:700;margin-bottom:5px}
        input{width:100%;padding:11px;border:1px solid #bac5d4;border-radius:8px}
        button{padding:11px 18px;border:0;border-radius:8px;background:var(--accent);color:#fff;font-weight:700;cursor:pointer}
        .meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
        .pill{padding:5px 9px;border-radius:999px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412}
        .product-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:16px}
        .product-name{font-size:22px;font-weight:800}
        .product-code{color:var(--muted)}
        .facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1px;background:var(--line);border:1px solid var(--line);border-radius:10px;overflow:hidden;margin-bottom:18px}
        .fact{background:#fff;padding:10px 12px;min-height:64px}
        .fact-label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
        .fact-value{font-weight:650;margin-top:3px;word-break:break-word}
        .notes{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
        .note-box{border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}
        .note-head{display:flex;justify-content:space-between;gap:10px;padding:9px 11px;background:#f3f5f8;border-bottom:1px solid var(--line);font-weight:800}
        .status{padding:2px 7px;border-radius:999px;font-size:12px}
        .filled{background:#dcfce7;color:#166534}
        .empty{background:#fee2e2;color:#991b1b}
        .null{background:#e5e7eb;color:#374151}
        pre{margin:0;padding:13px;white-space:pre-wrap;word-break:break-word;min-height:120px;font:14px/1.5 Consolas,"Courier New",monospace}
        .not-found{color:#991b1b;background:#fff1f2;border-color:#fecdd3}
        @media(max-width:850px){body{padding:14px}.notes{grid-template-columns:1fr}}
        @media(max-width:540px){form{grid-template-columns:1fr}}
    </style></head><body><main>';
    echo '<h1>Pantheon product notes</h1>';
    echo '<p class="intro">Read-only lookup of one product in <strong>' . phptest24_h($schema) . '.tHE_SetItem</strong>.</p>';
    echo '<section class="card"><form method="get">';
    echo '<div><label for="product">Product code (acIdent or acCode)</label><input id="product" name="product" value="' . phptest24_h($productCode) . '" placeholder="12312312"></div>';
    echo '<div><button type="submit">Show product</button></div>';
    echo '</form><div class="meta"><span class="pill">Table: ' . phptest24_h($schema) . '.tHE_SetItem</span><span class="pill">Read only</span></div></section>';

    if ($product === []) {
        echo '<section class="card not-found">Product <strong>' . phptest24_h($productCode) . '</strong> was not found.</section>';
    } else {
        echo '<section class="card">';
        echo '<div class="product-head"><div><div class="product-name">' . phptest24_h(phptest24_value($product['acName'] ?? null)) . '</div>';
        echo '<div class="product-code">acIdent: ' . phptest24_h(phptest24_value($product['acIdent'] ?? null)) . ' | acCode: ' . phptest24_h(phptest24_value($product['acCode'] ?? null)) . '</div></div></div>';

        $facts = [
            'anQId' => $product['anQId'] ?? null,
            'Description' => $product['acDescr'] ?? null,
            'Classification' => $product['acClassif'] ?? null,
            'Classification 2' => $product['acClassif2'] ?? null,
            'Set of item' => $product['acSetOfItem'] ?? null,
            'Active' => $product['acActive'] ?? null,
            'Unit' => $product['acUM'] ?? null,
            'Unit 2' => $product['acUM2'] ?? null,
            'Currency' => $product['acCurrency'] ?? null,
            'Purchase currency' => $product['acPurchCurr'] ?? null,
            'VAT code' => $product['acVATCode'] ?? null,
            'VAT' => $product['anVAT'] ?? null,
            'Tariff' => $product['acCustTariff'] ?? null,
            'Production document type' => $product['acDocTypeProd'] ?? null,
            'Inserted by' => $product['anUserIns'] ?? null,
            'Inserted at' => $product['adTimeIns'] ?? null,
            'Changed by' => $product['anUserChg'] ?? null,
            'Changed at' => $product['adTimeChg'] ?? null,
        ];

        echo '<div class="facts">';
        foreach ($facts as $label => $value) {
            echo '<div class="fact"><div class="fact-label">' . phptest24_h($label) . '</div><div class="fact-value">' . phptest24_h(phptest24_value($value)) . '</div></div>';
        }
        echo '</div><div class="notes">';

        foreach ([
            'acNote' => 'Product note',
            'acDMNote' => 'DM note',
            'acFieldSE' => 'Custom field SE',
        ] as $column => $label) {
            $status = phptest24_note_status($product[$column] ?? null);
            echo '<div class="note-box"><div class="note-head"><span>' . phptest24_h($column . ' - ' . $label) . '</span>';
            echo '<span class="status ' . strtolower($status) . '">' . $status . '</span></div>';
            echo '<pre>' . phptest24_h(phptest24_value($product[$column] ?? null)) . '</pre></div>';
        }

        echo '</div></section>';
    }

    echo '</main></body></html>';
    sqlsrv_close($conn);
} catch (Throwable $exception) {
    if (is_resource($conn)) {
        sqlsrv_close($conn);
    }

    phptest24_fail($exception);
}
