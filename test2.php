<?php

$server = "hostBApa1.datalab.ba,50387";
$database = "BA_TRENDY";
$username = "SQLTREN_ADM2";
$password = "#4^Sdgfx3VHy5G";

function request_value(string $key, ?string $default = null): ?string
{
    if (PHP_SAPI === 'cli') {
        global $argv;

        foreach (array_slice($argv ?? [], 1) as $argument) {
            if (!str_contains($argument, '=')) {
                continue;
            }

            [$argKey, $argValue] = explode('=', $argument, 2);

            if ($argKey === $key) {
                return trim($argValue);
            }
        }
    }

    if (isset($_GET[$key])) {
        return trim((string) $_GET[$key]);
    }

    return $default;
}

function escape_html(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalize_identifier(string $value, string $fallback): string
{
    $trimmed = trim($value);

    if ($trimmed === '' || !preg_match('/^[A-Za-z0-9_]+$/', $trimmed)) {
        return $fallback;
    }

    return $trimmed;
}

function format_cell_value(mixed $value): string
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

    return (string) $value;
}

$schema = normalize_identifier((string) request_value('schema', 'dbo'), 'dbo');
$table = normalize_identifier((string) request_value('table', 'tHE_Order'), 'tHE_Order');
$top = max(1, min(50, (int) request_value('top', '10')));
$requestedOrderBy = normalize_identifier((string) request_value('order_by', 'adDate'), 'adDate');
$columnFilter = trim((string) request_value('columns', ''));
$filterNeedle = strtolower($columnFilter);

$conn = sqlsrv_connect($server, [
    'Database' => $database,
    'UID' => $username,
    'PWD' => $password,
    'CharacterSet' => 'UTF-8',
]);

if (!$conn) {
    header('Content-Type: text/plain; charset=UTF-8');
    die(print_r(sqlsrv_errors(), true));
}

$columnsSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION";
$columnsStmt = sqlsrv_query($conn, $columnsSql, [$schema, $table]);

if (!$columnsStmt) {
    header('Content-Type: text/plain; charset=UTF-8');
    die(print_r(sqlsrv_errors(), true));
}

$allColumns = [];

while ($row = sqlsrv_fetch_array($columnsStmt, SQLSRV_FETCH_ASSOC)) {
    $columnName = trim((string) ($row['COLUMN_NAME'] ?? ''));

    if ($columnName !== '') {
        $allColumns[] = $columnName;
    }
}

if (empty($allColumns)) {
    header('Content-Type: text/plain; charset=UTF-8');
    die("Tabela {$schema}.{$table} nije pronađena ili nema dostupne kolone.");
}

$displayColumns = array_values(array_filter($allColumns, function (string $columnName) use ($filterNeedle) {
    return $filterNeedle === '' || str_contains(strtolower($columnName), $filterNeedle);
}));

if (empty($displayColumns)) {
    $displayColumns = $allColumns;
}

$orderByColumn = in_array($requestedOrderBy, $allColumns, true) ? $requestedOrderBy : $allColumns[0];
$wrappedColumns = implode(', ', array_map(function (string $columnName) {
    return '[' . $columnName . ']';
}, $displayColumns));
$dataSql = "SELECT TOP {$top} {$wrappedColumns} FROM [{$schema}].[{$table}] ORDER BY [{$orderByColumn}] DESC";
$dataStmt = sqlsrv_query($conn, $dataSql);

if (!$dataStmt) {
    header('Content-Type: text/plain; charset=UTF-8');
    die(print_r(sqlsrv_errors(), true));
}

$rows = [];

while ($row = sqlsrv_fetch_array($dataStmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Table Inspector</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f7fb;
            color: #1f2937;
        }

        form, .meta, .columns {
            background: #fff;
            border: 1px solid #d8dee9;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }

        label {
            display: inline-block;
            margin-right: 12px;
            margin-bottom: 8px;
        }

        input {
            padding: 6px 8px;
            min-width: 140px;
        }

        button {
            padding: 7px 12px;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th, td {
            border: 1px solid #d8dee9;
            padding: 8px;
            text-align: left;
            vertical-align: top;
            white-space: nowrap;
        }

        th {
            position: sticky;
            top: 0;
            background: #e8eefc;
        }

        .table-wrap {
            overflow: auto;
            max-height: 75vh;
            border: 1px solid #d8dee9;
            border-radius: 8px;
            background: #fff;
        }

        .columns code {
            display: inline-block;
            margin: 4px 6px 0 0;
            padding: 4px 6px;
            background: #eef2ff;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>SQL Table Inspector</h1>

    <form method="get">
        <label>Schema
            <input type="text" name="schema" value="<?= escape_html($schema) ?>">
        </label>
        <label>Table
            <input type="text" name="table" value="<?= escape_html($table) ?>">
        </label>
        <label>Top
            <input type="number" name="top" min="1" max="50" value="<?= escape_html((string) $top) ?>">
        </label>
        <label>Order by
            <input type="text" name="order_by" value="<?= escape_html($orderByColumn) ?>">
        </label>
        <label>Columns contains
            <input type="text" name="columns" value="<?= escape_html($columnFilter) ?>" placeholder="qty, plan, ident">
        </label>
        <button type="submit">Load</button>
    </form>

    <div class="meta">
        <strong>Table:</strong> <?= escape_html($schema . '.' . $table) ?><br>
        <strong>Rows shown:</strong> <?= escape_html((string) count($rows)) ?><br>
        <strong>Order:</strong> <?= escape_html($orderByColumn) ?> DESC
    </div>

    <div class="columns">
        <strong>Available columns (<?= escape_html((string) count($allColumns)) ?>):</strong><br>
        <?php foreach ($allColumns as $columnName): ?>
            <code><?= escape_html($columnName) ?></code>
        <?php endforeach; ?>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <?php foreach ($displayColumns as $columnName): ?>
                        <th><?= escape_html($columnName) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($displayColumns as $columnName): ?>
                            <td><?= escape_html(format_cell_value($row[$columnName] ?? null)) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php

sqlsrv_close($conn);
