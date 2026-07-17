<?php

/*
 * Read-only Pantheon WO document-link inspector.
 *
 * Browser: /phptest/test34.php?rn=26-6000-003620
 * CLI:     php public/phptest/test34.php "rn=26-6000-003620"
 *
 * This script is deliberately hard-locked to BA_TRENDY_TESTNA and allows
 * SELECT statements only. It never changes Pantheon data.
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

const PHPTEST34_DATABASE = 'BA_TRENDY_TESTNA';

function phptest34_env(string $key, ?string $default = null): ?string
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
        return (string) preg_replace_callback('/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/', function (array $matches) use (&$resolve, &$values, $stack): string {
            $referenced = $matches[1];
            if (!array_key_exists($referenced, $values) || in_array($referenced, $stack, true)) {
                return $matches[0];
            }

            return $resolve($values[$referenced], [...$stack, $referenced]);
        }, $value);
    };

    return $resolved[$key] = $resolve($values[$key], [$key]);
}

function phptest34_h(mixed $value): string
{
    return htmlspecialchars(phptest34_value($value), ENT_QUOTES, 'UTF-8');
}

function phptest34_value(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    return trim((string) $value);
}

function phptest34_fail(string $message): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    http_response_code(400);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>WO link inspector</title></head><body><pre>'
        . phptest34_h($message) . '</pre></body></html>';
    exit;
}

function phptest34_connect()
{
    $host = (string) phptest34_env('WORK_ORDER_TARGET_DB_HOST', phptest34_env('DB_HOST', ''));
    $port = (string) phptest34_env('WORK_ORDER_TARGET_DB_PORT', phptest34_env('DB_PORT', '1433'));
    $username = (string) phptest34_env('WORK_ORDER_TARGET_DB_USERNAME', phptest34_env('DB_USERNAME', ''));
    $password = (string) phptest34_env('WORK_ORDER_TARGET_DB_PASSWORD', phptest34_env('DB_PASSWORD', ''));

    $connection = sqlsrv_connect($host . ($port !== '' ? ',' . $port : ''), [
        'Database' => PHPTEST34_DATABASE,
        'UID' => $username,
        'PWD' => $password,
        'CharacterSet' => 'UTF-8',
        'Encrypt' => strtolower((string) phptest34_env('WORK_ORDER_TARGET_DB_ENCRYPT', 'true')) !== 'false',
        'TrustServerCertificate' => strtolower((string) phptest34_env('WORK_ORDER_TARGET_DB_TRUST_SERVER_CERTIFICATE', 'true')) !== 'false',
        'LoginTimeout' => 10,
    ]);

    if (!$connection) {
        phptest34_fail('Could not connect to ' . PHPTEST34_DATABASE . ': ' . print_r(sqlsrv_errors(), true));
    }

    return $connection;
}

function phptest34_fetch_all($connection, string $sql, array $params = []): array
{
    if (preg_match('/^\s*(SELECT|WITH)\b/i', $sql) !== 1) {
        phptest34_fail('Blocked non-read-only SQL.');
    }

    $statement = sqlsrv_query($connection, $sql, $params, ['QueryTimeout' => 60]);
    if (!$statement) {
        phptest34_fail('Query failed: ' . print_r(sqlsrv_errors(), true));
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($statement);

    return $rows;
}

function phptest34_rn_candidates(string $rn): array
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

function phptest34_menu_path(string $documentType, string $linkType): string
{
    return match ([$documentType, $linkType]) {
        ['6400', 'P'] => 'Izdavanja → Materijali',
        ['6100', 'M'] => 'Izdavanja → Operacije',
        ['6600', 'P'] => 'Prijemi',
        default => 'No confirmed menu mapping for this type combination',
    };
}

function phptest34_render_table(array $rows): void
{
    if ($rows === []) {
        echo PHP_SAPI === 'cli' ? "No rows found.\n" : '<p class="muted">No rows found.</p>';
        return;
    }

    $columns = array_keys($rows[0]);
    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL . str_repeat('-', 180) . PHP_EOL;
        foreach ($rows as $row) {
            echo implode(' | ', array_map(fn ($column) => phptest34_value($row[$column] ?? null), $columns)) . PHP_EOL;
        }
        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $column) {
        echo '<th>' . phptest34_h($column) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            echo '<td>' . phptest34_h($row[$column] ?? null) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

$rn = trim((string) ($_GET['rn'] ?? '26-6000-003620'));
$candidates = phptest34_rn_candidates($rn);
if ($candidates === []) {
    phptest34_fail('Enter a valid work-order number.');
}

$connection = phptest34_connect();
$placeholders = implode(', ', array_fill(0, count($candidates), '?'));
$workOrders = phptest34_fetch_all($connection, "
    SELECT acKey, acKeyView, acStatus, acStatusMF, acLnkKey, anLnkNo, adDate, adTimeIns, anUserIns
    FROM dbo.tHF_WOEx
    WHERE REPLACE(REPLACE(LTRIM(RTRIM(acKey)), '-', ''), ' ', '') IN ({$placeholders})
       OR REPLACE(REPLACE(LTRIM(RTRIM(acKeyView)), '-', ''), ' ', '') IN ({$placeholders})
    ORDER BY adTimeIns DESC, acKey DESC
", [...$candidates, ...$candidates]);

if ($workOrders === []) {
    phptest34_fail('Work order not found: ' . $rn);
}

$workOrder = $workOrders[0];
$workOrderKey = phptest34_value($workOrder['acKey'] ?? '');
$workOrderNumber = phptest34_value($workOrder['acKeyView'] ?? '');

$directLinks = phptest34_fetch_all($connection, '
    SELECT
        m.acKeyView AS document_number,
        m.acDocType AS document_type,
        dt.acName AS document_type_name,
        m.acKey AS document_key,
        l.anNo AS link_no,
        l.anQId AS link_qid,
        l.acLnkKey AS linked_work_order_key,
        l.acType AS link_type,
        l.acTypeA AS link_type_a,
        l.acTypeB AS link_type_b,
        l.anMoveQId AS move_qid,
        m.acDoc2 AS document_work_order_reference,
        m.adTimeIns AS document_created_at
    FROM dbo.tHF_LinkMoveWOEx AS l
    INNER JOIN dbo.tHE_Move AS m ON m.acKey = l.acKey
    INNER JOIN dbo.tPA_SetDocType AS dt ON dt.acDocType = m.acDocType
    WHERE LTRIM(RTRIM(l.acLnkKey)) = ?
      AND m.acDocType IN (\'6400\', \'6100\', \'6600\')
    ORDER BY m.acDocType, m.acKeyView
', [$workOrderKey]);

$headerReferences = phptest34_fetch_all($connection, '
    SELECT
        m.acKeyView AS document_number,
        m.acDocType AS document_type,
        dt.acName AS document_type_name,
        m.acKey AS document_key,
        m.acDoc2 AS document_work_order_reference,
        m.anQId AS document_move_qid,
        m.adTimeIns AS document_created_at,
        CASE WHEN l.acKey IS NULL THEN \'NO\' ELSE \'YES\' END AS has_direct_wo_link,
        l.acType AS link_type,
        l.acLnkKey AS linked_work_order_key,
        l.anMoveQId AS link_move_qid
    FROM dbo.tHE_Move AS m
    INNER JOIN dbo.tPA_SetDocType AS dt ON dt.acDocType = m.acDocType
    LEFT JOIN dbo.tHF_LinkMoveWOEx AS l
        ON l.acKey = m.acKey AND LTRIM(RTRIM(l.acLnkKey)) = ?
    WHERE LTRIM(RTRIM(m.acDoc2)) = ?
      AND m.acDocType IN (\'6400\', \'6100\', \'6600\')
    ORDER BY m.acDocType, m.acKeyView
', [$workOrderKey, $workOrderNumber]);

$pantheonRelatedDocuments = phptest34_fetch_all($connection, '
    SELECT
        m.acKeyView AS document_number,
        m.acDocType AS document_type,
        dt.acName AS document_type_name,
        m.acKey AS document_key,
        l.acType AS link_type,
        r.acWhereKey AS pantheon_related_document_key,
        CASE WHEN r.acWhereKey IS NULL THEN \'NO\' ELSE \'YES\' END AS present_in_pantheon_related_documents_view
    FROM dbo.tHF_LinkMoveWOEx AS l
    INNER JOIN dbo.tHE_Move AS m ON m.acKey = l.acKey
    INNER JOIN dbo.tPA_SetDocType AS dt ON dt.acDocType = m.acDocType
    LEFT JOIN dbo.vHE_ViewDocWOEx AS r
        ON r.acKey = l.acLnkKey AND r.acWhereKey = m.acKey
    WHERE LTRIM(RTRIM(l.acLnkKey)) = ?
      AND m.acDocType IN (\'6400\', \'6100\', \'6600\')
    ORDER BY m.acDocType, m.acKeyView
', [$workOrderKey]);

$workOrderItems = phptest34_fetch_all($connection, '
    SELECT anQId AS work_order_item_qid, anNo AS position, acIdent AS code,
           acOperationType AS operation_type, anQty AS quantity, acIssueFinished AS issue_finished
    FROM dbo.tHF_WOExItem
    WHERE acKey = ?
    ORDER BY anNo
', [$workOrderKey]);

$documentItemLinks = phptest34_fetch_all($connection, '
    SELECT
        m.acKeyView AS document_number,
        m.acDocType AS document_type,
        mi.anNo AS document_line,
        mi.acIdent AS item_code,
        mi.anQId AS document_item_qid,
        CASE WHEN il.anQId IS NULL THEN \'NO\' ELSE \'YES\' END AS has_wo_item_link,
        il.anWOExItemQid AS linked_wo_item_qid,
        CASE WHEN wi.anQId IS NULL THEN \'NO\' ELSE \'YES\' END AS has_worker_work_record
    FROM dbo.tHF_LinkMoveWOEx AS hl
    INNER JOIN dbo.tHE_Move AS m ON m.acKey = hl.acKey AND m.acDocType IN (\'6400\', \'6600\')
    INNER JOIN dbo.tHE_MoveItem AS mi ON mi.anMoveQId = m.anQId
    LEFT JOIN dbo.tHF_LinkMoveItemWOExItem AS il ON il.anMoveItemQId = mi.anQId
    LEFT JOIN dbo.tHF_WOExItemWork AS wi ON wi.anMoveItemQId = mi.anQId
    WHERE LTRIM(RTRIM(hl.acLnkKey)) = ?
    ORDER BY m.acKeyView, mi.anNo
', [$workOrderKey]);

if (PHP_SAPI === 'cli') {
    echo 'Work order:' . PHP_EOL;
    phptest34_render_table([$workOrder]);
    echo PHP_EOL . 'Direct tHF_LinkMoveWOEx links:' . PHP_EOL;
    phptest34_render_table($directLinks);
    echo PHP_EOL . 'Document header references (tHE_Move.acDoc2):' . PHP_EOL;
    phptest34_render_table($headerReferences);
    echo PHP_EOL . 'Pantheon related-documents view (dbo.vHE_ViewDocWOEx):' . PHP_EOL;
    phptest34_render_table($pantheonRelatedDocuments);
    echo PHP_EOL . 'WO positions (dbo.tHF_WOExItem):' . PHP_EOL;
    phptest34_render_table($workOrderItems);
    echo PHP_EOL . '6400/6600 document item associations:' . PHP_EOL;
    phptest34_render_table($documentItemLinks);
    exit;
}
?>
<!doctype html>
<html lang="bs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pantheon WO document-link inspector</title>
  <style>
    body { margin: 0; background: #f5f7fb; color: #1d2939; font: 14px/1.45 system-ui, sans-serif; }
    main { max-width: 1500px; margin: 28px auto; padding: 0 20px 32px; }
    .card { background: #fff; border: 1px solid #dfe5ef; border-radius: 10px; padding: 20px; margin-top: 16px; box-shadow: 0 1px 2px rgb(16 24 40 / 4%); }
    h1, h2 { margin: 0 0 10px; } h1 { font-size: 22px; } h2 { font-size: 17px; }
    p { margin: 8px 0; } .muted { color: #667085; }
    form { display: flex; align-items: end; gap: 10px; flex-wrap: wrap; }
    label { display: grid; gap: 4px; font-weight: 600; } input { width: 220px; padding: 8px 10px; border: 1px solid #98a2b3; border-radius: 6px; font: inherit; }
    button { padding: 9px 13px; background: #175cd3; color: #fff; border: 0; border-radius: 6px; font: inherit; cursor: pointer; }
    .table-wrap { overflow: auto; } table { border-collapse: collapse; min-width: 100%; } th, td { padding: 9px 10px; border: 1px solid #dfe5ef; text-align: left; white-space: nowrap; vertical-align: top; } th { background: #eef4ff; } td { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    code { background: #eff2f6; padding: 2px 4px; border-radius: 3px; } ul { margin: 8px 0 0; padding-left: 20px; }
  </style>
</head>
<body>
<main>
  <h1>Pantheon WO document-link inspector</h1>
  <p class="muted">Read-only diagnostic for <code>BA_TRENDY_TESTNA</code>. It does not insert, update, or delete records.</p>
  <div class="card">
    <form method="get">
      <label>Radni nalog<input name="rn" value="<?= phptest34_h($rn) ?>" autocomplete="off"></label>
      <button type="submit">Prikaži linkove</button>
    </form>
  </div>
  <div class="card"><h2>WO header</h2><?php phptest34_render_table([$workOrder]); ?></div>
  <div class="card">
    <h2>Direct Pantheon links: dbo.tHF_LinkMoveWOEx</h2>
    <p class="muted">Raw work-order/document link rows. <code>link_type</code> is the raw <code>acType</code> value.</p>
    <?php phptest34_render_table($directLinks); ?>
  </div>
  <div class="card">
    <h2>Header reference cross-check: dbo.tHE_Move.acDoc2</h2>
    <p class="muted">A document can name a WO in its header but still be missing the direct link above; <code>has_direct_wo_link</code> exposes that difference.</p>
    <?php phptest34_render_table($headerReferences); ?>
  </div>
  <div class="card">
    <h2>Pantheon related-documents source: dbo.vHE_ViewDocWOEx</h2>
    <p class="muted">This is Pantheon&rsquo;s database view for work-order related documents. <code>YES</code> confirms that the database-level related-documents source returns the document.</p>
    <?php phptest34_render_table($pantheonRelatedDocuments); ?>
  </div>
  <div class="card">
    <h2>WO positions: dbo.tHF_WOExItem</h2>
    <p class="muted">For empty WOs closed with the current eNalog flow, manually entered operations and materials create positions here (1, 2, &hellip;) before 6400/6600 documents are made. Older closings created before this change can correctly show no rows.</p>
    <?php phptest34_render_table($workOrderItems); ?>
  </div>
  <div class="card">
    <h2>6400/6600 document item associations</h2>
    <p class="muted">After closing an empty WO, each manually entered operation and material should show <code>YES</code> for the WO item link. A 6600 operation additionally has its worker-work record.</p>
    <?php phptest34_render_table($documentItemLinks); ?>
  </div>
  <div class="card">
    <h2>Previously requested menu mapping</h2>
    <ul>
      <li><code>6400 + P</code> → Izdavanja → Materijali</li>
      <li><code>6100 + M</code> → Izdavanja → Operacije</li>
      <li><code>6600 + P</code> → Prijemi</li>
    </ul>
  </div>
</main>
</body>
</html>
