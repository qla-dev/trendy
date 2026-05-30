<?php

/*
 * test17.php
 * Pantheon RN trace za materijalno izdavanje.
 *
 * Pokazuje gdje su upisani podaci koje Pantheon koristi oko otvorenog RN-a:
 * - tHF_WOEx
 * - tHF_WOExItem
 * - tHF_SetPrSt
 * - tHF_WOExItemResources
 * - tHF_LinkMoveItemWOExItem + tHE_MoveItem + tHE_Move
 *
 * Korisno za provjeru otvorenih RN-ova kreiranih kroz eNalog app i poređenje
 * sa "standardnim" RN-ovima kada treba vidjeti zasto Pantheon ne nudi Izdavanje.
 *
 * Parametri:
 * - rn=26-6000-003019
 */

require __DIR__ . '/_conn.php';

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

function phptest17_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function phptest17_fail($error): void
{
    $message = $error instanceof Throwable
        ? ($error->getMessage() . "\n" . $error->getTraceAsString())
        : print_r($error, true);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN material issue trace</title></head><body>';
    echo '<pre>' . phptest17_h($message) . '</pre>';
    echo '</body></html>';
    exit;
}

function phptest17_fetch_all($conn, string $sql, array $params = [], int $timeout = 60): array
{
    $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => $timeout]);

    if (!$stmt) {
        phptest17_fail(sqlsrv_errors());
    }

    $rows = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);

    return $rows;
}

function phptest17_norm(string $value): string
{
    $normalized = preg_replace('/\D+/', '', $value);

    return is_string($normalized) ? $normalized : '';
}

function phptest17_candidates(string $value): array
{
    $normalized = phptest17_norm($value);

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

    return array_values(array_unique(array_filter($candidates, static function ($candidate) {
        return $candidate !== '';
    })));
}

function phptest17_locate_work_order($conn, string $schema, string $input): array
{
    $trimmedInput = trim($input);
    $candidates = phptest17_candidates($trimmedInput);

    if ($trimmedInput === '' || empty($candidates)) {
        return [
            'input' => $trimmedInput,
            'normalized' => phptest17_norm($trimmedInput),
            'candidates' => $candidates,
            'row' => null,
        ];
    }

    $where = [];
    $params = [];

    foreach ($candidates as $candidate) {
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKey), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
        $where[] = "REPLACE(REPLACE(CONVERT(nvarchar(255), acKeyView), '-', ''), ' ', '') = ?";
        $params[] = $candidate;
    }

    $sql = "
        SELECT TOP 1 *
        FROM [{$schema}].[tHF_WOEx]
        WHERE " . implode(' OR ', $where) . "
        ORDER BY adTimeIns DESC, acKey DESC
    ";

    $rows = phptest17_fetch_all($conn, $sql, $params);

    return [
        'input' => $trimmedInput,
        'normalized' => phptest17_norm($trimmedInput),
        'candidates' => $candidates,
        'row' => $rows[0] ?? null,
    ];
}

function phptest17_placeholder_list(int $count): string
{
    return implode(', ', array_fill(0, $count, '?'));
}

function phptest17_format_number($value, int $scale = 4): string
{
    if ($value === null || !is_numeric((string) $value)) {
        return '';
    }

    $formatted = number_format((float) $value, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

function phptest17_format_value($value): string
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
        return phptest17_format_number($value);
    }

    return trim((string) $value);
}

function phptest17_render_heading(string $title, int $level = 2): void
{
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', max(10, strlen($title))) . PHP_EOL;
        return;
    }

    $tag = 'h' . max(1, min($level, 6));
    echo '<' . $tag . '>' . phptest17_h($title) . '</' . $tag . '>';
}

function phptest17_render_note(string $text, string $class = 'note'): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
        return;
    }

    echo '<div class="' . phptest17_h($class) . '">' . phptest17_h($text) . '</div>';
}

function phptest17_render_table(string $title, array $rows): void
{
    phptest17_render_heading($title, 3);

    if (empty($rows)) {
        phptest17_render_note('No rows.');
        return;
    }

    $columns = array_keys((array) $rows[0]);

    if (PHP_SAPI === 'cli') {
        echo implode(' | ', $columns) . PHP_EOL;
        echo str_repeat('-', 220) . PHP_EOL;

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = phptest17_format_value($row[$column] ?? null);
            }

            echo implode(' | ', $values) . PHP_EOL;
        }

        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . phptest17_h((string) $column) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<td>' . phptest17_h(phptest17_format_value($row[$column] ?? null)) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function phptest17_source_group(array $workOrderRow): string
{
    $note = trim((string) ($workOrderRow['acNote'] ?? ''));

    return stripos($note, 'eNalog.app') !== false ? 'enalog' : 'other';
}

function phptest17_is_positive($value): bool
{
    return is_numeric((string) $value) && abs((float) $value) > 0.000001;
}

function phptest17_build_summary(array $workOrderRow, array $items, array $resources, array $moveLinks): array
{
    $operationsTotal = 0;
    $operationsO = 0;
    $operationsD = 0;
    $operationsOther = 0;
    $materialsTotal = 0;
    $materialsPositivePlanQty = 0;
    $materialsPositiveActualQty = 0;
    $materialsWithResource = 0;
    $materialsWithMoveLink = 0;
    $operationsWithResource = 0;
    $opTypeMismatchVsBom = 0;

    foreach ($items as $item) {
        $catalogSet = strtoupper(trim((string) ($item['catalog_set'] ?? '')));
        $itemOpType = strtoupper(trim((string) ($item['wo_operation_type'] ?? '')));
        $bomOpType = strtoupper(trim((string) ($item['bom_operation_type'] ?? '')));
        $hasResource = (int) ($item['has_resource_row'] ?? 0) > 0;
        $hasMoveLink = (int) ($item['has_move_link'] ?? 0) > 0;

        if ($catalogSet === 'OPR') {
            $operationsTotal++;

            if ($itemOpType === 'O') {
                $operationsO++;
            } elseif ($itemOpType === 'D') {
                $operationsD++;
            } else {
                $operationsOther++;
            }

            if ($hasResource) {
                $operationsWithResource++;
            }
        } else {
            $materialsTotal++;

            if (phptest17_is_positive($item['wo_plan_qty'] ?? null)) {
                $materialsPositivePlanQty++;
            }

            if (phptest17_is_positive($item['wo_qty'] ?? null)) {
                $materialsPositiveActualQty++;
            }

            if ($hasResource) {
                $materialsWithResource++;
            }

            if ($hasMoveLink) {
                $materialsWithMoveLink++;
            }
        }

        if ($bomOpType !== '' && $itemOpType !== '' && $bomOpType !== $itemOpType) {
            $opTypeMismatchVsBom++;
        }
    }

    return [[
        'rn' => $workOrderRow['acKeyView'] ?? ($workOrderRow['acKey'] ?? ''),
        'source_group' => phptest17_source_group($workOrderRow),
        'status_mf' => $workOrderRow['acStatusMF'] ?? '',
        'status' => $workOrderRow['acStatus'] ?? '',
        'product_ident' => $workOrderRow['acIdent'] ?? '',
        'order_number' => $workOrderRow['acLnkKeyView'] ?? ($workOrderRow['acLnkKey'] ?? ''),
        'header_plan_qty' => $workOrderRow['anPlanQty'] ?? null,
        'header_produced_qty' => $workOrderRow['anProducedQty'] ?? null,
        'operations_total' => $operationsTotal,
        'operations_optype_O' => $operationsO,
        'operations_optype_D' => $operationsD,
        'operations_optype_other' => $operationsOther,
        'operations_with_resource' => $operationsWithResource,
        'materials_total' => $materialsTotal,
        'materials_positive_plan_qty' => $materialsPositivePlanQty,
        'materials_positive_actual_qty' => $materialsPositiveActualQty,
        'materials_with_resource' => $materialsWithResource,
        'materials_with_move_link' => $materialsWithMoveLink,
        'resource_rows_total' => count($resources),
        'move_links_total' => count($moveLinks),
        'item_vs_bom_optype_mismatches' => $opTypeMismatchVsBom,
    ]];
}

$rn = trim((string) ($_GET['rn'] ?? '26-6000-003020'));

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>RN material issue trace</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;font-size:14px;line-height:1.4;margin:20px;color:#222}
        h1,h2,h3{margin:16px 0 8px}
        .meta,.note{padding:10px 12px;border:1px solid #d7d7d7;background:#fafafa;margin:8px 0 16px}
        .note{background:#fff8df;border-color:#f0d67b}
        .table-wrap{overflow-x:auto;margin:8px 0 22px}
        table{border-collapse:collapse;min-width:1100px;background:#fff}
        th,td{border:1px solid #d8d8d8;padding:4px 6px;vertical-align:top;white-space:nowrap}
        th{background:#f1f3f5;text-align:left}
    </style></head><body>';
}

phptest17_render_heading('Pantheon RN material issue trace', 1);
phptest17_render_note(
    'Parametar: rn. Fokus je na otvorenom RN-u i mjestima gdje su upisani podaci za materijalno izdavanje.',
    'meta'
);

$lookup = phptest17_locate_work_order($conn, $defaultSchema, $rn);
$workOrder = is_array($lookup['row'] ?? null) ? $lookup['row'] : [];

phptest17_render_table('Located work order', [[
    'input' => $lookup['input'] ?? '',
    'normalized' => $lookup['normalized'] ?? '',
    'candidates' => implode(', ', (array) ($lookup['candidates'] ?? [])),
    'found' => empty($workOrder) ? 'NO' : 'YES',
    'acKey' => $workOrder['acKey'] ?? '',
    'acKeyView' => $workOrder['acKeyView'] ?? '',
    'acIdent' => $workOrder['acIdent'] ?? '',
    'acName' => $workOrder['acName'] ?? '',
    'acStatusMF' => $workOrder['acStatusMF'] ?? '',
    'acStatus' => $workOrder['acStatus'] ?? '',
    'acLnkKeyView' => $workOrder['acLnkKeyView'] ?? '',
    'adTimeIns' => $workOrder['adTimeIns'] ?? null,
]]);

if (empty($workOrder)) {
    phptest17_render_note('Work order was not found.');
    sqlsrv_close($conn);

    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }

    exit;
}

$workOrderKey = trim((string) ($workOrder['acKey'] ?? ''));
$productIdent = trim((string) ($workOrder['acIdent'] ?? ''));

$itemTraceSql = "
    SELECT
        i.anQId AS wo_item_qid,
        i.anNo AS wo_no,
        i.acIdent AS wo_ident,
        i.acDescr AS wo_descr,
        i.acUM AS wo_um,
        i.acOperationType AS wo_operation_type,
        i.acDelayType AS wo_delay_type,
        i.anPlanQty AS wo_plan_qty,
        i.anQty AS wo_qty,
        i.anQty1 AS wo_qty1,
        i.acIssueFinished AS wo_issue_finished,
        i.anIssuePerc AS wo_issue_perc,
        si.acSetOfItem AS catalog_set,
        bom.anNo AS bom_no,
        bom.acOperationType AS bom_operation_type,
        bom.acDelayType AS bom_delay_type,
        bom.anGrossQty AS bom_gross_qty,
        bom.anNetQty AS bom_net_qty,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM [{$defaultSchema}].[tHF_WOExItemResources] AS r
                WHERE r.anWOExItemQId = i.anQId
            ) THEN 1
            ELSE 0
        END AS has_resource_row,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM [{$defaultSchema}].[tHF_LinkMoveItemWOExItem] AS l
                WHERE l.anWOExItemQId = i.anQId
                    AND l.acType = 'PP'
            ) THEN 1
            ELSE 0
        END AS has_move_link
    FROM [{$defaultSchema}].[tHF_WOExItem] AS i
    LEFT JOIN [{$defaultSchema}].[tHE_SetItem] AS si
        ON si.acIdent = i.acIdent
    LEFT JOIN [{$defaultSchema}].[tHF_SetPrSt] AS bom
        ON bom.acIdent = ?
        AND bom.acIdentChild = i.acIdent
    WHERE i.acKey = ?
    ORDER BY i.anNo, bom.anNo, i.anQId
";
$itemTraceRows = phptest17_fetch_all($conn, $itemTraceSql, [$productIdent, $workOrderKey]);

$itemQids = [];
foreach ($itemTraceRows as $row) {
    $qid = (int) ($row['wo_item_qid'] ?? 0);
    if ($qid > 0) {
        $itemQids[] = $qid;
    }
}
$itemQids = array_values(array_unique($itemQids));

$resourceRows = [];
if (!empty($itemQids)) {
    $resourceSql = "
        SELECT
            r.anWOExItemQId,
            i.anNo AS wo_item_no,
            i.acIdent AS wo_item_ident,
            i.acOperationType AS wo_item_operation_type,
            r.anQId AS resource_qid,
            r.anPlanQty,
            r.anQty,
            r.anQty1,
            r.acIssueFinished,
            r.anExecutionPerc,
            r.adTimeIns,
            r.adTimeChg
        FROM [{$defaultSchema}].[tHF_WOExItemResources] AS r
        LEFT JOIN [{$defaultSchema}].[tHF_WOExItem] AS i
            ON i.anQId = r.anWOExItemQId
        WHERE r.anWOExItemQId IN (" . phptest17_placeholder_list(count($itemQids)) . ")
        ORDER BY i.anNo, r.anQId
    ";
    $resourceRows = phptest17_fetch_all($conn, $resourceSql, $itemQids);
}

$moveLinkRows = [];
if (!empty($itemQids)) {
    $moveLinkSql = "
        SELECT
            l.anWOExItemQId,
            i.anNo AS wo_item_no,
            i.acIdent AS wo_item_ident,
            i.acOperationType AS wo_item_operation_type,
            l.anQId AS link_qid,
            l.acType AS link_type,
            l.adTimeIns AS link_time_ins,
            mi.anQId AS move_item_qid,
            mi.anNo AS move_line_no,
            mi.acIdent AS move_ident,
            mi.acName AS move_name,
            mi.anQty AS move_qty,
            mi.acUM AS move_um,
            mi.anWOPrice,
            mi.anPrice,
            m.acKey AS move_key,
            m.acKeyView AS move_key_view,
            m.acDocType,
            m.adDate AS move_date
        FROM [{$defaultSchema}].[tHF_LinkMoveItemWOExItem] AS l
        INNER JOIN [{$defaultSchema}].[tHE_MoveItem] AS mi
            ON mi.anQId = l.anMoveItemQId
        INNER JOIN [{$defaultSchema}].[tHE_Move] AS m
            ON m.acKey = mi.acKey
        INNER JOIN [{$defaultSchema}].[tHF_WOExItem] AS i
            ON i.anQId = l.anWOExItemQId
        WHERE l.anWOExItemQId IN (" . phptest17_placeholder_list(count($itemQids)) . ")
        ORDER BY l.adTimeIns DESC, i.anNo, mi.anNo
    ";
    $moveLinkRows = phptest17_fetch_all($conn, $moveLinkSql, $itemQids);
}

$bomRows = [];
if ($productIdent !== '') {
    $bomRows = phptest17_fetch_all(
        $conn,
        "
            SELECT
                anNo,
                acIdentChild,
                acDescr,
                acUM,
                acOperationType,
                acDelayType,
                anGrossQty,
                anNetQty,
                anQId,
                adTimeIns,
                adTimeChg
            FROM [{$defaultSchema}].[tHF_SetPrSt]
            WHERE acIdent = ?
            ORDER BY anNo, anQId
        ",
        [$productIdent]
    );
}

phptest17_render_table('Summary', phptest17_build_summary($workOrder, $itemTraceRows, $resourceRows, $moveLinkRows));
phptest17_render_table('tHF_WOEx header', [$workOrder]);
phptest17_render_table('WO items vs BOM rows', $itemTraceRows);
phptest17_render_table('tHF_WOExItemResources', $resourceRows);
phptest17_render_table('tHF_LinkMoveItemWOExItem + Move docs', $moveLinkRows);
phptest17_render_table('tHF_SetPrSt for product', $bomRows);

sqlsrv_close($conn);

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}
