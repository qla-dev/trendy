<?php

/*
 * test23.php
 * Read-only Pantheon order-header note viewer for dbo.tHE_Order.
 *
 * Browser examples:
 * - /phptest/test23.php
 * - /phptest/test23.php?search=4512126028
 * - /phptest/test23.php?search=26-0110-001601
 * - /phptest/test23.php?search=Eilbestellung
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest23_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest23_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Pantheon document notes</title></head><body>';
    echo '<pre>' . phptest23_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest23_option(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    if (is_array($value)) {
        $value = end($value);
    }

    return trim((string) $value);
}

function phptest23_identifier(string $value, string $fallback): string
{
    return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1 ? $value : $fallback;
}

function phptest23_fetch_all($conn, string $sql, array $params = []): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => 60]);

    if (!$stmt) {
        phptest23_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest23_value($value): string
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

function phptest23_note_status($value): string
{
    return trim((string) $value) === '' ? 'EMPTY' : 'FILLED';
}

function phptest23_fetch_orders(
    $conn,
    string $schema,
    string $search,
    string $noteFilter,
    int $limit
): array {
    $where = [];
    $params = [];

    if ($search !== '') {
        $needle = '%' . $search . '%';

        foreach (['acKey', 'acKeyView', 'acDoc1', 'acConsignee', 'acNote', 'acInternalNote'] as $column) {
            $where[] = 'CONVERT(nvarchar(max), [' . $column . ']) LIKE ?';
            $params[] = $needle;
        }
    }

    $hasNote = "NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(max), [acNote]))), '') IS NOT NULL";

    if ($noteFilter === 'filled') {
        $where[] = $hasNote;
    } elseif ($noteFilter === 'empty') {
        $where[] = 'NOT (' . $hasNote . ')';
    }

    $whereSql = $where === [] ? '' : 'WHERE (' . implode(' OR ', array_slice($where, 0, $search !== '' ? 6 : 0)) . ')';

    if ($search === '') {
        $whereSql = '';
    }

    if ($noteFilter !== 'all') {
        $noteSql = end($where);
        $whereSql .= ($whereSql === '' ? 'WHERE ' : ' AND ') . $noteSql;
    }

    $safeLimit = max(1, min($limit, 200));

    return phptest23_fetch_all(
        $conn,
        "
            SELECT TOP ({$safeLimit})
                anQId,
                acKey,
                acKeyView,
                acDocType,
                adDate,
                acStatus,
                acConsignee,
                acReceiver,
                acContactPrsn,
                acDoc1,
                acDoc2,
                adDateDoc1,
                acCurrency,
                anValue,
                anVAT,
                anForPay,
                anUserIns,
                adTimeIns,
                anUserChg,
                adTimeChg,
                acNote,
                acInternalNote
            FROM [{$schema}].[tHE_Order]
            {$whereSql}
            ORDER BY adTimeIns DESC, anQId DESC
        ",
        $params
    );
}

function phptest23_render_cli(array $rows): void
{
    foreach ($rows as $index => $row) {
        echo PHP_EOL . 'ORDER ' . ($index + 1) . PHP_EOL;
        echo str_repeat('=', 80) . PHP_EOL;

        foreach ($row as $column => $value) {
            echo $column . ': ' . phptest23_value($value) . PHP_EOL;
        }
    }

    if ($rows === []) {
        echo 'No rows.' . PHP_EOL;
    }
}

$schema = phptest23_identifier(phptest23_option('schema', $defaultSchema ?: 'dbo'), $defaultSchema ?: 'dbo');
$search = phptest23_option('search', '4512126028');
$noteFilter = strtolower(phptest23_option('note_filter', 'all'));
$noteFilter = in_array($noteFilter, ['all', 'filled', 'empty'], true) ? $noteFilter : 'all';
$limit = max(1, min((int) phptest23_option('limit', '20'), 200));

try {
    $rows = phptest23_fetch_orders($conn, $schema, $search, $noteFilter, $limit);

    if (PHP_SAPI === 'cli') {
        echo 'TABLE: ' . $schema . '.tHE_Order' . PHP_EOL;
        echo 'SEARCH: ' . ($search !== '' ? $search : '[latest]') . PHP_EOL;
        echo 'ROWS: ' . count($rows) . PHP_EOL;
        phptest23_render_cli($rows);
        sqlsrv_close($conn);
        exit;
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Pantheon document notes</title>';
    echo '<style>
        :root{--ink:#172033;--muted:#667085;--line:#d5dce8;--accent:#164e63;--paper:#fffef8}
        *{box-sizing:border-box}
        body{margin:0;padding:28px;font:14px/1.5 "Segoe UI",sans-serif;color:var(--ink);background:radial-gradient(circle at top left,#dff5f3 0,transparent 35%),#f3f6f9}
        main{max-width:1800px;margin:auto}
        h1{margin:0;font-size:29px}
        .intro{margin:7px 0 20px;color:var(--muted)}
        .panel,.order{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 8px 28px rgba(15,23,42,.07);padding:18px;margin-bottom:20px}
        form{display:grid;grid-template-columns:minmax(280px,2fr) minmax(170px,1fr) 130px auto;gap:12px;align-items:end}
        label{display:block;font-weight:700;margin-bottom:5px}
        input,select{width:100%;padding:10px 11px;border:1px solid #b9c5d4;border-radius:8px;background:#fff}
        button{padding:11px 18px;border:0;border-radius:8px;background:var(--accent);color:#fff;font-weight:700;cursor:pointer}
        .meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
        .pill{padding:5px 9px;border:1px solid #a5d1cf;border-radius:999px;background:#eaf8f6;color:#115e59}
        .order-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:14px}
        .order-title{font-size:20px;font-weight:800}
        .source{color:var(--muted)}
        .status{padding:5px 9px;border-radius:999px;font-weight:800}
        .filled{background:#dcfce7;color:#166534}
        .empty{background:#fee2e2;color:#991b1b}
        .facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1px;background:var(--line);border:1px solid var(--line);border-radius:10px;overflow:hidden;margin-bottom:15px}
        .fact{background:#fff;padding:10px 12px;min-height:64px}
        .fact-label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
        .fact-value{font-weight:650;margin-top:3px;word-break:break-word}
        .notes{display:grid;grid-template-columns:2fr 1fr;gap:14px}
        .note-box{border:1px solid #d9d4c3;border-radius:10px;background:var(--paper);overflow:hidden}
        .note-label{padding:8px 11px;background:#f3efdf;font-weight:800;border-bottom:1px solid #d9d4c3}
        pre{margin:0;padding:13px;white-space:pre-wrap;word-break:break-word;font:14px/1.55 Consolas,"Courier New",monospace;max-height:620px;overflow:auto}
        .no-results{color:var(--muted)}
        @media(max-width:900px){body{padding:14px}form{grid-template-columns:1fr 1fr}.notes{grid-template-columns:1fr}.action{grid-column:1/-1}}
        @media(max-width:560px){form{grid-template-columns:1fr}}
    </style></head><body><main>';
    echo '<h1>Pantheon document notes</h1>';
    echo '<p class="intro">Read-only view of <strong>' . phptest23_h($schema) . '.tHE_Order</strong>. Search covers Pantheon order number, original document number, customer and note text.</p>';
    echo '<section class="panel"><form method="get">';
    echo '<div><label for="search">Order, source document, customer or note text</label><input id="search" name="search" value="' . phptest23_h($search) . '" placeholder="4512126028"></div>';
    echo '<div><label for="note_filter">Document note</label><select id="note_filter" name="note_filter">';

    foreach (['all' => 'All', 'filled' => 'Only filled', 'empty' => 'Only empty'] as $value => $label) {
        echo '<option value="' . $value . '"' . ($noteFilter === $value ? ' selected' : '') . '>' . phptest23_h($label) . '</option>';
    }

    echo '</select></div>';
    echo '<div><label for="limit">Limit</label><input id="limit" name="limit" type="number" min="1" max="200" value="' . $limit . '"></div>';
    echo '<div class="action"><button type="submit">Show orders</button></div>';
    echo '</form><div class="meta">';
    echo '<span class="pill">Table: ' . phptest23_h($schema) . '.tHE_Order</span>';
    echo '<span class="pill">Rows: ' . count($rows) . '</span>';
    echo '<span class="pill">Read only</span>';
    echo '</div></section>';

    if ($rows === []) {
        echo '<section class="panel no-results">No matching orders were found.</section>';
    }

    foreach ($rows as $row) {
        $noteStatus = phptest23_note_status($row['acNote'] ?? null);
        echo '<article class="order">';
        echo '<div class="order-head"><div>';
        echo '<div class="order-title">' . phptest23_h(phptest23_value($row['acKeyView'] ?? null)) . '</div>';
        echo '<div class="source">Original document: ' . phptest23_h(phptest23_value($row['acDoc1'] ?? null)) . '</div>';
        echo '</div><span class="status ' . strtolower($noteStatus) . '">' . $noteStatus . ' acNote</span></div>';

        $facts = [
            'anQId' => $row['anQId'] ?? null,
            'acKey' => $row['acKey'] ?? null,
            'Document type' => $row['acDocType'] ?? null,
            'Document date' => $row['adDate'] ?? null,
            'Status' => $row['acStatus'] ?? null,
            'Customer' => $row['acConsignee'] ?? null,
            'Receiver' => $row['acReceiver'] ?? null,
            'Contact' => $row['acContactPrsn'] ?? null,
            'Source position' => $row['acDoc2'] ?? null,
            'Source date' => $row['adDateDoc1'] ?? null,
            'Currency' => $row['acCurrency'] ?? null,
            'Net value' => $row['anValue'] ?? null,
            'VAT' => $row['anVAT'] ?? null,
            'Total' => $row['anForPay'] ?? null,
            'Inserted by' => $row['anUserIns'] ?? null,
            'Inserted at' => $row['adTimeIns'] ?? null,
            'Changed by' => $row['anUserChg'] ?? null,
            'Changed at' => $row['adTimeChg'] ?? null,
        ];

        echo '<div class="facts">';

        foreach ($facts as $label => $value) {
            echo '<div class="fact"><div class="fact-label">' . phptest23_h($label) . '</div>';
            echo '<div class="fact-value">' . phptest23_h(phptest23_value($value)) . '</div></div>';
        }

        echo '</div><div class="notes">';
        echo '<div class="note-box"><div class="note-label">acNote - Napomena na dokumentu</div><pre>' . phptest23_h(phptest23_value($row['acNote'] ?? null)) . '</pre></div>';
        echo '<div class="note-box"><div class="note-label">acInternalNote - Internal processing note</div><pre>' . phptest23_h(phptest23_value($row['acInternalNote'] ?? null)) . '</pre></div>';
        echo '</div></article>';
    }

    echo '</main></body></html>';
    sqlsrv_close($conn);
} catch (Throwable $exception) {
    if (is_resource($conn)) {
        sqlsrv_close($conn);
    }

    phptest23_fail($exception);
}
