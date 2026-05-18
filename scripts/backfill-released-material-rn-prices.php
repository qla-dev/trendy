#!/usr/bin/env php
<?php

/*
 * Backfills RN unit prices for released-material documents (doc type 6400).
 *
 * Purpose:
 *   - reads existing 6400 rows from dbo.tHE_Move / dbo.tHE_MoveItem
 *   - resolves the expected RN unit price from the item catalog buy price
 *     (`tHE_SetItem.anBuyPrice`) using `mi.anIdentQId`, or `mi.acIdent` as a fallback
 *   - compares that expected unit price with the stored `mi.anWOPrice`
 *   - optionally updates only the rows where the stored and expected values differ
 *
 * This exists because Pantheon reads the RN unit price from `tHE_MoveItem.anWOPrice`,
 * and linked-document value is derived from quantity * RN unit price. If older 6400
 * rows have the wrong unit price stored, this script brings them back in sync.
 *
 * Dry run for everything in scope:
 *   php scripts/backfill-released-material-rn-prices.php --verbose
 *
 * Dry run for a date window:
 *   php scripts/backfill-released-material-rn-prices.php --from=2026-05-01 --to=2026-05-18 --verbose
 *
 * Dry run for a single RN / document / material:
 *   php scripts/backfill-released-material-rn-prices.php --rn=26-6000-002775 --verbose
 *   php scripts/backfill-released-material-rn-prices.php --document=26-6400-000123 --verbose
 *   php scripts/backfill-released-material-rn-prices.php --material=PLOPLAZMA --verbose
 *
 * Execute updates after reviewing the dry-run output:
 *   php scripts/backfill-released-material-rn-prices.php --from=2026-05-01 --to=2026-05-18 --execute
 *
 * Useful options:
 *   --search=...
 *   --document=...
 *   --rn=...
 *   --material=...
 *   --name=...
 *   --from=YYYY-MM-DD
 *   --to=YYYY-MM-DD
 *   --limit=100
 *   --user-id=123
 *   --connection=sqlsrv
 *
 * Defaults:
 *   - no writes unless --execute is passed
 *   - only released-material documents with `acDocType = 6400` are considered
 *   - rows without a catalog match are skipped
 *   - only rows whose stored `anWOPrice` differs from the expected catalog price are updated
 */

use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class ReleasedMaterialRnPriceBackfill
{
    private ConnectionInterface $db;
    private array $options;
    private bool $execute;
    private bool $verbose;
    private ?int $limit;
    private int $userId;
    private string $schema;
    private Carbon $now;

    public function __construct(array $options)
    {
        $this->options = $options;
        $connectionName = $this->optionString('connection', 'sqlsrv');
        $this->prepareSqlServerCliConnection($connectionName);
        $this->db = DB::connection($connectionName);
        $this->execute = array_key_exists('execute', $options) && !$this->optionBool('dry-run', false);
        $this->verbose = $this->optionBool('verbose', false);
        $this->limit = $this->optionNullableInt('limit');
        $this->userId = $this->optionNullableInt('user-id') ?? 0;
        $this->schema = (string) config('workorders.schema', 'dbo');
        $this->now = Carbon::now();
    }

    private function prepareSqlServerCliConnection(string $connectionName): void
    {
        $connectionConfig = config('database.connections.' . $connectionName);

        if (!is_array($connectionConfig) || (string) ($connectionConfig['driver'] ?? '') !== 'sqlsrv') {
            return;
        }

        config([
            'database.connections.' . $connectionName . '.encrypt' => 'no',
            'database.connections.' . $connectionName . '.trust_server_certificate' => 'yes',
        ]);
    }

    public function run(): int
    {
        $this->printHeader();

        $rows = $this->candidateRows();
        $stats = [
            'total_rows' => count($rows),
            'would_update' => 0,
            'updated' => 0,
            'already_synced' => 0,
            'missing_catalog' => 0,
            'failed' => 0,
        ];

        foreach ($rows as $row) {
            if (!$this->hasCatalogMatch($row)) {
                $stats['missing_catalog']++;
                // We cannot safely backfill the RN price if the released-material item
                // cannot be matched to a catalog row.
                $this->printRow('SKIP no catalog', $row);
                continue;
            }

            if (!$this->needsUpdate($row)) {
                $stats['already_synced']++;
                if ($this->verbose) {
                    $this->printRow('OK synced', $row);
                }
                continue;
            }

            $stats['would_update']++;

            if (!$this->execute) {
                $this->printRow('DRY update', $row);
                continue;
            }

            try {
                // Keep Pantheon's RN unit-price field in sync with the current expected
                // catalog buy price for the released-material item.
                $affected = $this->db
                    ->table($this->qualified('tHE_MoveItem'))
                    ->where('anQId', (int) ($row['move_item_qid'] ?? 0))
                    ->update([
                        'anWOPrice' => (float) ($row['expected_rn_price'] ?? 0),
                        'adTimeChg' => $this->now,
                        'anUserChg' => $this->userId,
                    ]);

                if ($affected < 1) {
                    throw new RuntimeException('No row updated for move item QId ' . (string) ($row['move_item_qid'] ?? ''));
                }

                $stats['updated']++;
                $this->printRow('UPDATED', $row);
            } catch (Throwable $exception) {
                $stats['failed']++;
                fwrite(
                    STDERR,
                    'FAILED ' . $this->rowLabel($row) . ': ' . $exception->getMessage() . PHP_EOL
                );
            }
        }

        $this->printStats($stats);

        if (!$this->execute) {
            echo PHP_EOL . 'Dry run only. Add --execute to write RN unit prices into tHE_MoveItem.anWOPrice.' . PHP_EOL;
        }

        return $stats['failed'] > 0 ? 1 : 0;
    }

    private function printHeader(): void
    {
        echo 'Released material RN unit-price backfill' . PHP_EOL;
        echo 'Mode: ' . ($this->execute ? 'EXECUTE' : 'DRY RUN') . PHP_EOL;
        echo 'Connection: ' . $this->db->getName() . PHP_EOL;
        echo 'Move table: ' . $this->qualified('tHE_Move') . PHP_EOL;
        echo 'Move item table: ' . $this->qualified('tHE_MoveItem') . PHP_EOL;
        echo 'Catalog table: ' . $this->qualified('tHE_SetItem') . PHP_EOL;
        echo 'Filter search: ' . $this->optionString('search', '-') . PHP_EOL;
        echo 'Filter document: ' . $this->optionString('document', '-') . PHP_EOL;
        echo 'Filter RN: ' . $this->optionString('rn', '-') . PHP_EOL;
        echo 'Filter material: ' . $this->optionString('material', '-') . PHP_EOL;
        echo 'Filter name: ' . $this->optionString('name', '-') . PHP_EOL;
        echo 'Filter from: ' . $this->optionString('from', '-') . PHP_EOL;
        echo 'Filter to: ' . $this->optionString('to', '-') . PHP_EOL;
        echo 'Limit: ' . ($this->limit === null ? 'ALL' : (string) $this->limit) . PHP_EOL;
        echo PHP_EOL;
    }

    private function candidateRows(): array
    {
        $dateTimeExpr = 'CASE WHEN m.adTimeIns IS NOT NULL THEN m.adTimeIns ELSE CAST(m.adDate AS datetime) END';
        $trimMoveKeyExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acKey), '')))";
        $trimMoveKeyViewExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acKeyView), '')))";
        $trimWorkOrderExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), move_wo.acLnkKey), '')))";
        $trimWorkOrderViewExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), wo.acKeyView), '')))";
        $trimMaterialCodeExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acIdent), '')))";
        $trimMaterialNameExpr = "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), mi.acName), '')))";
        $catalogBuyPriceExpr = 'COALESCE(CAST(catalog_qid.anBuyPrice as float), CAST(catalog_code.anBuyPrice as float), 0)';
        $expectedRnPriceExpr = $catalogBuyPriceExpr;
        $storedRnPriceExpr = 'CAST(ISNULL(mi.anWOPrice, 0) as float)';
        $priceDiffExpr = 'ROUND(' . $storedRnPriceExpr . ' - ' . $expectedRnPriceExpr . ', 4)';
        $catalogMatchExpr = 'CASE WHEN catalog_qid.anQId IS NULL AND catalog_code.anQId IS NULL THEN 0 ELSE 1 END';

        // Read released-material document items, calculate the expected RN unit price
        // from the catalog, and compare it with the currently stored mi.anWOPrice value.
        $query = $this->db
            ->table($this->qualified('tHE_Move') . ' as m')
            ->join($this->qualified('tHE_MoveItem') . ' as mi', 'mi.acKey', '=', 'm.acKey')
            ->leftJoin($this->qualified('tHF_LinkMoveWOEx') . ' as move_wo', 'move_wo.acKey', '=', 'm.acKey')
            ->leftJoin($this->qualified('tHF_WOEx') . ' as wo', 'wo.acKey', '=', 'move_wo.acLnkKey')
            ->leftJoin($this->qualified('tHE_SetItem') . ' as catalog_qid', 'catalog_qid.anQId', '=', 'mi.anIdentQId')
            ->leftJoin($this->qualified('tHE_SetItem') . ' as catalog_code', function ($join) use ($trimMaterialCodeExpr) {
                $join->whereRaw('catalog_qid.anQId IS NULL')
                    ->whereRaw("LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), catalog_code.acIdent), ''))) = {$trimMaterialCodeExpr}");
            })
            ->where('m.acDocType', '6400')
            ->selectRaw('CAST(mi.anQId as bigint) as move_item_qid')
            ->selectRaw($trimMoveKeyExpr . ' as document_key')
            ->selectRaw("COALESCE(NULLIF({$trimMoveKeyViewExpr}, ''), {$trimMoveKeyExpr}) as document_number")
            ->selectRaw('CONVERT(varchar(19), ' . $dateTimeExpr . ', 120) as document_date')
            ->selectRaw("COALESCE(NULLIF({$trimWorkOrderExpr}, ''), NULLIF({$trimWorkOrderViewExpr}, ''), NULLIF(LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acDoc2), ''))), ''), '') as rn_number")
            ->selectRaw('CAST(ISNULL(mi.anNo, 0) as int) as line_no')
            ->selectRaw($trimMaterialCodeExpr . ' as material_code')
            ->selectRaw($trimMaterialNameExpr . ' as material_name')
            ->selectRaw('CAST(ISNULL(mi.anQty, 0) as float) as quantity')
            ->selectRaw("CAST({$catalogBuyPriceExpr} as float) as buy_price")
            ->selectRaw("CAST({$storedRnPriceExpr} as float) as stored_rn_price")
            ->selectRaw("CAST({$expectedRnPriceExpr} as float) as expected_rn_price")
            ->selectRaw("CAST({$priceDiffExpr} as float) as price_diff")
            ->selectRaw("CAST({$catalogMatchExpr} as int) as has_catalog_match");

        $this->applyLikeFilter($query, [
            $trimMoveKeyExpr,
            $trimMoveKeyViewExpr,
            $trimWorkOrderExpr,
            $trimWorkOrderViewExpr,
            $trimMaterialCodeExpr,
            $trimMaterialNameExpr,
        ], $this->optionString('search', ''));

        $this->applyLikeFilter($query, [$trimMoveKeyExpr, $trimMoveKeyViewExpr], $this->optionString('document', ''));
        $this->applyLikeFilter($query, [$trimWorkOrderExpr, $trimWorkOrderViewExpr, "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(255), m.acDoc2), '')))"], $this->optionString('rn', ''));
        $this->applyLikeFilter($query, [$trimMaterialCodeExpr], $this->optionString('material', ''));
        $this->applyLikeFilter($query, [$trimMaterialNameExpr], $this->optionString('name', ''));

        $dateFrom = $this->optionString('from', '');
        if ($dateFrom !== '') {
            $query->whereRaw('CAST(' . $dateTimeExpr . ' AS date) >= ?', [$dateFrom]);
        }

        $dateTo = $this->optionString('to', '');
        if ($dateTo !== '') {
            $query->whereRaw('CAST(' . $dateTimeExpr . ' AS date) <= ?', [$dateTo]);
        }

        $query
            ->orderByRaw($dateTimeExpr . ' DESC')
            ->orderBy('m.acKey', 'desc')
            ->orderBy('mi.anNo', 'asc');

        if ($this->limit !== null && $this->limit > 0) {
            $query->limit($this->limit);
        }

        return $query
            ->get()
            ->map(static fn ($row) => (array) $row)
            ->all();
    }

    private function applyLikeFilter(Builder $query, array $expressions, string $value): void
    {
        $value = trim($value);

        if ($value === '') {
            return;
        }

        $needle = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value) . '%';

        $query->where(function (Builder $subQuery) use ($expressions, $needle): void {
            foreach ($expressions as $index => $expression) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $subQuery->{$method}($expression . ' LIKE ?', [$needle]);
            }
        });
    }

    private function hasCatalogMatch(array $row): bool
    {
        return (int) ($row['has_catalog_match'] ?? 0) === 1;
    }

    private function needsUpdate(array $row): bool
    {
        $diff = (float) ($row['price_diff'] ?? 0);

        return abs($diff) > 0.0001;
    }

    private function printRow(string $action, array $row): void
    {
        echo sprintf(
            '%s | %s | line=%s | %s | qty=%s | buy=%s | stored=%s | expected=%s | diff=%s',
            $action,
            $this->rowLabel($row),
            (string) ($row['line_no'] ?? ''),
            trim((string) ($row['material_code'] ?? '')),
            $this->formatNumber($row['quantity'] ?? null),
            $this->formatNumber($row['buy_price'] ?? null),
            $this->formatNumber($row['stored_rn_price'] ?? null),
            $this->formatNumber($row['expected_rn_price'] ?? null),
            $this->formatNumber($row['price_diff'] ?? null)
        ) . PHP_EOL;
    }

    private function rowLabel(array $row): string
    {
        $documentNumber = trim((string) ($row['document_number'] ?? $row['document_key'] ?? ''));
        $rnNumber = trim((string) ($row['rn_number'] ?? ''));

        if ($rnNumber !== '') {
            return $documentNumber . ' / RN ' . $rnNumber;
        }

        return $documentNumber;
    }

    private function printStats(array $stats): void
    {
        echo PHP_EOL;
        echo 'Summary' . PHP_EOL;
        echo '-------' . PHP_EOL;

        foreach ($stats as $key => $value) {
            echo $key . ': ' . $value . PHP_EOL;
        }
    }

    private function formatNumber($value): string
    {
        if ($value === null || !is_numeric((string) $value)) {
            return 'NULL';
        }

        return number_format((float) $value, 4, '.', '');
    }

    private function qualified(string $table): string
    {
        return $this->schema . '.' . $table;
    }

    private function optionString(string $name, string $default = ''): string
    {
        $value = $this->options[$name] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    private function optionBool(string $name, bool $default = false): bool
    {
        if (!array_key_exists($name, $this->options)) {
            return $default;
        }

        $value = $this->options[$name];

        if ($value === false || $value === null || $value === '') {
            return true;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'da'], true);
    }

    private function optionNullableInt(string $name): ?int
    {
        if (!array_key_exists($name, $this->options)) {
            return null;
        }

        $value = trim((string) $this->options[$name]);

        if ($value === '' || !preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }
}

$options = getopt('', [
    'connection::',
    'execute',
    'dry-run',
    'verbose',
    'search::',
    'document::',
    'rn::',
    'material::',
    'name::',
    'from::',
    'to::',
    'limit::',
    'user-id::',
]);

exit((new ReleasedMaterialRnPriceBackfill(is_array($options) ? $options : []))->run());
