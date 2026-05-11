#!/usr/bin/env php
<?php

/*
 * Backfills missing tHF_WOExItemResources rows for RN operation items.
 *
 * Dry run for the whole database scope:
 *   php scripts/backfill-rn-item-resources.php --verbose
 *
 * Dry run for a date window:
 *   php scripts/backfill-rn-item-resources.php --from=2026-04-06 --to=2026-04-28
 *
 * Dry run for specific RN values:
 *   php scripts/backfill-rn-item-resources.php --rn=26-6000-002732 --rn=26-6000-002733 --verbose
 *
 * Insert missing resource rows after reviewing the dry-run output:
 *   php scripts/backfill-rn-item-resources.php --execute
 *
 * Defaults:
 *   - no writes unless --execute is passed
 *   - no date filter unless --from/--to/--days are provided
 *   - only operation-like RN items are considered
 *   - only items without a matching tHF_WOExItemResources row are considered
 */

use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class RnItemResourceBackfill
{
    private ConnectionInterface $db;
    private array $options;
    private string $schema;
    private string $workOrderTable;
    private string $itemTable;
    private string $resourceTable;
    private string $catalogTable;
    private array $columnsCache = [];
    private array $identityColumnsCache = [];
    private bool $execute;
    private bool $verbose;
    private ?int $limit;
    private ?int $userId;
    private ?Carbon $from;
    private ?Carbon $to;
    private ?string $dateColumn;
    /** @var string[] */
    private array $workOrderFilters;
    private Carbon $now;

    public function __construct(array $options)
    {
        $this->options = $options;
        $connectionName = $this->optionString('connection', (string) config('database.default'));
        $this->prepareSqlServerCliConnection($connectionName);
        $this->db = DB::connection($connectionName);
        $this->schema = $this->optionString('schema', (string) config('workorders.schema', 'dbo'));
        $this->workOrderTable = (string) config('workorders.table', 'tHF_WOEx');
        $this->itemTable = (string) config('workorders.items_table', 'tHF_WOExItem');
        $this->resourceTable = (string) config('workorders.item_resources_table', 'tHF_WOExItemResources');
        $this->catalogTable = (string) config('workorders.catalog_items_table', 'tHE_SetItem');
        $this->execute = array_key_exists('execute', $options) && !$this->optionBool('dry-run', false);
        $this->verbose = $this->optionBool('verbose', false);
        $this->limit = $this->optionNullableInt('limit');
        $this->userId = $this->optionNullableInt('user-id');
        [$this->from, $this->to] = $this->resolveDateWindow();
        $this->dateColumn = $this->resolveDateColumn();
        $this->workOrderFilters = $this->resolveWorkOrderFilters();
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

        $rows = $this->candidateItems();
        $groupedWorkOrders = $this->groupCandidatesByWorkOrder($rows);
        $stats = [
            'candidate_rows' => count($rows),
            'affected_work_orders' => count($groupedWorkOrders),
            'work_order_filter_count' => count($this->workOrderFilters),
            'would_insert' => 0,
            'inserted' => 0,
            'skipped_existing_resource' => 0,
            'skipped_empty_payload' => 0,
            'failed' => 0,
        ];

        foreach ($rows as $row) {
            $row = (array) $row;
            $workOrderNumber = $this->workOrderDisplayNumber($row);
            $itemQId = $this->intOrNull($row['item_anQId'] ?? null);

            if ($itemQId === null) {
                $stats['skipped_empty_payload']++;
                $this->printRow('SKIP missing item QID', $workOrderNumber, $row);
                continue;
            }

            if ($this->resourceExistsForItemQId($itemQId)) {
                $stats['skipped_existing_resource']++;
                $this->printRow('SKIP existing resource', $workOrderNumber, $row);
                continue;
            }

            $payload = $this->buildResourcePayload($row);

            if (empty($payload)) {
                $stats['skipped_empty_payload']++;
                $this->printRow('SKIP empty payload', $workOrderNumber, $row);
                continue;
            }

            if (!$this->execute) {
                $stats['would_insert']++;
                $this->printRow('DRY insert', $workOrderNumber, $row, $payload);
                continue;
            }

            try {
                $this->db->transaction(function () use ($itemQId, $payload): void {
                    if (!$this->resourceExistsForItemQId((int) $itemQId)) {
                        $this->db->table($this->qualified($this->resourceTable))->insert($payload);
                    }
                });

                $stats['inserted']++;
                $this->printRow('INSERTED', $workOrderNumber, $row, $payload);
            } catch (Throwable $exception) {
                $stats['failed']++;
                fwrite(STDERR, 'FAILED ' . $workOrderNumber . ' / itemQId=' . $itemQId . ': ' . $exception->getMessage() . PHP_EOL);
            }
        }

        $this->printStats($stats);
        $this->printAffectedWorkOrders($groupedWorkOrders);

        if (!$this->execute) {
            echo PHP_EOL . 'Dry run only. Add --execute to insert the missing resource rows.' . PHP_EOL;
        }

        return $stats['failed'] > 0 ? 1 : 0;
    }

    private function printHeader(): void
    {
        echo 'RN item-resources backfill' . PHP_EOL;
        echo 'Mode: ' . ($this->execute ? 'EXECUTE' : 'DRY RUN') . PHP_EOL;
        echo 'Connection: ' . $this->db->getName() . PHP_EOL;
        echo 'Work orders: ' . $this->qualified($this->workOrderTable) . PHP_EOL;
        echo 'Items: ' . $this->qualified($this->itemTable) . PHP_EOL;
        echo 'Resources: ' . $this->qualified($this->resourceTable) . PHP_EOL;
        echo 'Catalog: ' . $this->qualified($this->catalogTable) . PHP_EOL;
        echo 'Date column: ' . ($this->dateColumn ?? '-') . PHP_EOL;
        echo 'Date filter: ' . $this->dateWindowLabel() . PHP_EOL;
        echo 'RN filters: ' . count($this->workOrderFilters) . PHP_EOL;
        echo PHP_EOL;
    }

    private function candidateItems(): array
    {
        $workOrderColumns = $this->columns($this->workOrderTable);
        $itemColumns = $this->columns($this->itemTable);
        $resourceColumns = $this->columns($this->resourceTable);

        $workOrderKeyColumn = $this->firstExisting($workOrderColumns, ['acKey']);
        $itemWorkOrderKeyColumn = $this->firstExisting($itemColumns, ['acKey']);
        $itemQIdColumn = $this->firstExisting($itemColumns, ['anQId']);
        $itemOperationTypeColumn = $this->firstExisting($itemColumns, ['acOperationType']);
        $resourceLinkColumn = $this->resourceLinkColumn($resourceColumns);

        if (
            $workOrderKeyColumn === null
            || $itemWorkOrderKeyColumn === null
            || $itemQIdColumn === null
            || $itemOperationTypeColumn === null
            || $resourceLinkColumn === null
        ) {
            throw new RuntimeException('Required columns for RN item resources backfill are missing.');
        }

        $query = $this->db
            ->table($this->qualified($this->itemTable) . ' as i')
            ->join(
                $this->qualified($this->workOrderTable) . ' as wo',
                'wo.' . $workOrderKeyColumn,
                '=',
                'i.' . $itemWorkOrderKeyColumn
            )
            ->leftJoin(
                $this->qualified($this->resourceTable) . ' as r',
                'r.' . $resourceLinkColumn,
                '=',
                'i.' . $itemQIdColumn
            )
            ->whereNull('r.' . $resourceLinkColumn);

        $wrappedOperationType = $query->getGrammar()->wrap('i.' . $itemOperationTypeColumn);
        $query->whereRaw(
            "UPPER(LTRIM(RTRIM(COALESCE(CAST($wrappedOperationType AS NVARCHAR(16)), '')))) <> 'M'"
        )->whereRaw(
            "UPPER(LTRIM(RTRIM(COALESCE(CAST($wrappedOperationType AS NVARCHAR(16)), '')))) <> ''"
        );

        if ($this->dateColumn !== null && $this->from !== null && $this->to !== null) {
            $query->whereBetween('wo.' . $this->dateColumn, [
                $this->from->format('Y-m-d H:i:s'),
                $this->to->format('Y-m-d H:i:s'),
            ]);
        }

        if (!empty($this->workOrderFilters)) {
            $this->applyWorkOrderFilter($query, $workOrderColumns, $this->workOrderFilters);
        }

        $selectColumns = [
            'i.' . $itemQIdColumn . ' as item_anQId',
        ];

        foreach ([
            'anNo' => 'item_anNo',
            'anVariant' => 'item_anVariant',
            'acIdent' => 'item_acIdent',
            'acDescr' => 'item_acDescr',
            'acOperationType' => 'item_acOperationType',
            'anPlanQty' => 'item_anPlanQty',
            'anQty' => 'item_anQty',
            'anQty1' => 'item_anQty1',
            'acIssueFinished' => 'item_acIssueFinished',
            'anUserIns' => 'item_anUserIns',
            'anUserChg' => 'item_anUserChg',
            'adTimeIns' => 'item_adTimeIns',
            'adTimeChg' => 'item_adTimeChg',
        ] as $column => $alias) {
            if (in_array($column, $itemColumns, true)) {
                $selectColumns[] = 'i.' . $column . ' as ' . $alias;
            }
        }

        foreach ([
            'acKey' => 'wo_acKey',
            'acKeyView' => 'wo_acKeyView',
            'acRefNo1' => 'wo_acRefNo1',
            'acLnkKey' => 'wo_acLnkKey',
            'acLnkKeyView' => 'wo_acLnkKeyView',
            'anLnkNo' => 'wo_anLnkNo',
            'acIdent' => 'wo_acIdent',
            'acCode' => 'wo_acCode',
            'product_code' => 'wo_product_code',
            'adDate' => 'wo_adDate',
            'adDateIns' => 'wo_adDateIns',
            'adTimeIns' => 'wo_adTimeIns',
            'adTimeChg' => 'wo_adTimeChg',
        ] as $column => $alias) {
            if (in_array($column, $workOrderColumns, true)) {
                $selectColumns[] = 'wo.' . $column . ' as ' . $alias;
            }
        }

        $query->select($selectColumns);

        foreach (['adTimeIns', 'adDateIns', 'adDate', 'acKey'] as $orderByColumn) {
            if (in_array($orderByColumn, $workOrderColumns, true)) {
                $query->orderByDesc('wo.' . $orderByColumn);
            }
        }

        if (in_array('anNo', $itemColumns, true)) {
            $query->orderBy('i.anNo');
        }

        if ($this->limit !== null && $this->limit > 0) {
            $query->limit($this->limit);
        }

        return $query->get()
            ->map(static fn ($row): array => (array) $row)
            ->values()
            ->all();
    }

    private function buildResourcePayload(array $row): array
    {
        $columns = $this->columns($this->resourceTable);
        $identityColumns = $this->identityColumns($this->resourceTable);
        $itemQIdColumn = $this->resourceLinkColumn($columns);
        $itemQId = $this->intOrNull($row['item_anQId'] ?? null);

        if ($itemQIdColumn === null || $itemQId === null || $itemQId < 1) {
            return [];
        }

        $workOrderKey = trim((string) ($row['wo_acKey'] ?? ''));
        $itemNo = $this->intOrZero($row['item_anNo'] ?? null);
        $variant = $this->intOrZero($row['item_anVariant'] ?? null);
        $planQty = $this->floatOrZero($row['item_anPlanQty'] ?? null);
        $qty = $this->floatOrZero($row['item_anQty'] ?? null);
        $qty1 = $this->floatOrZero($row['item_anQty1'] ?? null);
        $issueFinished = strtoupper(trim((string) ($row['item_acIssueFinished'] ?? 'N')));
        $issueFinished = $issueFinished !== '' ? substr($issueFinished, 0, 1) : 'N';
        $userId = $this->resolvedUserId($row);

        $payload = [
            $itemQIdColumn => $itemQId,
        ];

        if ($workOrderKey !== '') {
            foreach (['acKey', 'acWOKey', 'acDocKey', 'acLnkKey'] as $column) {
                if (in_array($column, $columns, true)) {
                    $payload[$column] = $workOrderKey;
                }
            }
        }

        foreach (['anNo', 'anLineNo', 'anResNo'] as $column) {
            if (in_array($column, $columns, true)) {
                $payload[$column] = $itemNo;
            }
        }

        if (in_array('anVariant', $columns, true)) {
            $payload['anVariant'] = $variant;
        }

        if (in_array('anPriority', $columns, true)) {
            $payload['anPriority'] = 0;
        }

        if (in_array('acPriority', $columns, true)) {
            $payload['acPriority'] = '0';
        }

        if (in_array('priority', $columns, true)) {
            $payload['priority'] = '0';
        }

        foreach (['acResursID', 'acResType', 'acETAdditive', 'acIncomeGrp', 'acQtyFormula', 'acSubContractor'] as $column) {
            if (in_array($column, $columns, true)) {
                $payload[$column] = '';
            }
        }

        if (in_array('anPlanQty', $columns, true)) {
            $payload['anPlanQty'] = $planQty;
        }

        if (in_array('anQty', $columns, true)) {
            $payload['anQty'] = $qty;
        }

        if (in_array('anQty1', $columns, true)) {
            $payload['anQty1'] = $qty1;
        }

        foreach (['anShift', 'anPlanArea', 'anArea', 'anQty2'] as $column) {
            if (in_array($column, $columns, true)) {
                $payload[$column] = 0;
            }
        }

        foreach (['anBatch', 'anNoOfWorkers'] as $column) {
            if (in_array($column, $columns, true)) {
                $payload[$column] = 1;
            }
        }

        if (in_array('acIssueFinished', $columns, true)) {
            $payload['acIssueFinished'] = in_array($issueFinished, ['Y', 'N'], true) ? $issueFinished : 'N';
        }

        if (in_array('anExecutionPerc', $columns, true)) {
            $payload['anExecutionPerc'] = 100.0;
        }

        if (in_array('adDateIns', $columns, true)) {
            $payload['adDateIns'] = $this->now;
        }

        if (in_array('adTimeIns', $columns, true)) {
            $payload['adTimeIns'] = $this->now;
        }

        if (in_array('adTimeChg', $columns, true)) {
            $payload['adTimeChg'] = $this->now;
        }

        if ($userId !== null && in_array('anUserIns', $columns, true)) {
            $payload['anUserIns'] = $userId;
        }

        if ($userId !== null && in_array('anUserChg', $columns, true)) {
            $payload['anUserChg'] = $userId;
        }

        if (in_array('anQId', $columns, true) && !in_array('anQId', $identityColumns, true)) {
            $payload['anQId'] = ((int) ($this->db->table($this->qualified($this->resourceTable))->max('anQId') ?? 0)) + 1;
        }

        return $payload;
    }

    private function resourceExistsForItemQId(int $itemQId): bool
    {
        $columns = $this->columns($this->resourceTable);
        $resourceLinkColumn = $this->resourceLinkColumn($columns);

        if ($resourceLinkColumn === null || $itemQId < 1) {
            return false;
        }

        return $this->db
            ->table($this->qualified($this->resourceTable))
            ->where($resourceLinkColumn, $itemQId)
            ->exists();
    }

    private function groupCandidatesByWorkOrder(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $workOrderNumber = $this->workOrderDisplayNumber($row);
            $key = $workOrderNumber !== '' ? $workOrderNumber : trim((string) ($row['wo_acKey'] ?? ''));

            if ($key === '') {
                $key = '__missing__';
            }

            if (!array_key_exists($key, $grouped)) {
                $grouped[$key] = [
                    'work_order' => $workOrderNumber !== '' ? $workOrderNumber : (string) ($row['wo_acKey'] ?? ''),
                    'order' => trim((string) $this->value($row, ['wo_acLnkKeyView', 'wo_acLnkKey'], '')),
                    'position' => trim((string) $this->value($row, ['wo_anLnkNo'], '')),
                    'missing_rows' => 0,
                ];
            }

            $grouped[$key]['missing_rows']++;
        }

        uasort($grouped, static function (array $first, array $second): int {
            return strcmp((string) ($first['work_order'] ?? ''), (string) ($second['work_order'] ?? ''));
        });

        return array_values($grouped);
    }

    private function printAffectedWorkOrders(array $groupedWorkOrders): void
    {
        if (empty($groupedWorkOrders)) {
            echo PHP_EOL . 'Affected work orders: none' . PHP_EOL;
            return;
        }

        echo PHP_EOL . 'Affected work orders' . PHP_EOL;

        foreach ($groupedWorkOrders as $group) {
            echo implode(' | ', [
                'RN=' . ((string) ($group['work_order'] ?? '') !== '' ? (string) $group['work_order'] : '-'),
                'order=' . ((string) ($group['order'] ?? '') !== '' ? (string) $group['order'] : '-'),
                'pos=' . ((string) ($group['position'] ?? '') !== '' ? (string) $group['position'] : '-'),
                'missing_rows=' . (string) ($group['missing_rows'] ?? 0),
            ]) . PHP_EOL;
        }
    }

    private function printRow(string $status, string $workOrderNumber, array $row, array $payload = []): void
    {
        if (!$this->verbose && !in_array($status, ['INSERTED', 'FAILED'], true)) {
            return;
        }

        $parts = [
            $status,
            'RN=' . ($workOrderNumber !== '' ? $workOrderNumber : '-'),
            'order=' . trim((string) $this->value($row, ['wo_acLnkKeyView', 'wo_acLnkKey'], '-')),
            'pos=' . trim((string) $this->value($row, ['wo_anLnkNo'], '-')),
            'itemQId=' . trim((string) $this->value($row, ['item_anQId'], '-')),
            'ident=' . trim((string) $this->value($row, ['item_acIdent'], '-')),
            'op=' . trim((string) $this->value($row, ['item_acOperationType'], '-')),
        ];

        if (!empty($payload)) {
            $parts[] = 'payload=' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        echo implode(' | ', $parts) . PHP_EOL;
    }

    private function printStats(array $stats): void
    {
        echo PHP_EOL . 'Summary' . PHP_EOL;

        foreach ($stats as $key => $value) {
            echo str_pad($key . ':', 32) . $value . PHP_EOL;
        }
    }

    private function workOrderDisplayNumber(array $row): string
    {
        return trim((string) $this->value($row, ['wo_acKeyView', 'wo_acRefNo1', 'wo_acKey'], ''));
    }

    private function resourceLinkColumn(array $resourceColumns): ?string
    {
        return $this->firstExisting($resourceColumns, ['anWOExItemQId', 'anItemQId', 'anQIdItem']);
    }

    private function applyWorkOrderFilter(Builder $query, array $workOrderColumns, array $filters): void
    {
        $comparisonValues = [];

        foreach ($filters as $filter) {
            foreach ($this->workOrderIdentifierCandidates($filter) as $candidate) {
                $comparisonValues[$candidate] = true;
            }
        }

        $comparisonValues = array_values(array_keys($comparisonValues));

        if (empty($comparisonValues)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $identifierColumns = $this->existingColumns($workOrderColumns, ['acKeyView', 'acRefNo1', 'acKey']);
        if (empty($identifierColumns)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($comparisonValues), '?'));

        $query->where(function (Builder $identifierQuery) use ($identifierColumns, $comparisonValues, $placeholders): void {
            foreach ($identifierColumns as $index => $column) {
                $normalizedExpression = $this->workOrderDisplayIdentifierExpression($identifierQuery, 'wo.' . $column);
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $identifierQuery->{$method}("$normalizedExpression IN ($placeholders)", $comparisonValues);
            }
        });
    }

    private function resolveWorkOrderFilters(): array
    {
        $filters = [];

        foreach ($this->optionStringList('rn') as $value) {
            foreach (preg_split('/[,\r\n]+/', $value) ?: [] as $part) {
                $part = trim((string) $part);

                if ($part !== '') {
                    $filters[] = $part;
                }
            }
        }

        $unique = [];
        foreach ($filters as $filter) {
            $normalized = trim((string) $filter);

            if ($normalized === '') {
                continue;
            }

            $unique[$normalized] = $normalized;
        }

        return array_values($unique);
    }

    private function resolveDateWindow(): array
    {
        $fromOption = $this->optionString('from', '');
        $toOption = $this->optionString('to', '');
        $daysOption = $this->optionString('days', '');

        if ($fromOption === '' && $toOption === '' && $daysOption === '') {
            return [null, null];
        }

        $days = $daysOption !== '' && is_numeric($daysOption) ? max((int) $daysOption, 0) : 0;
        $from = $fromOption !== ''
            ? Carbon::parse($fromOption)->startOfDay()
            : Carbon::now()->subDays($days)->startOfDay();
        $to = $toOption !== ''
            ? Carbon::parse($toOption)->endOfDay()
            : Carbon::now()->endOfDay();

        if ($from->gt($to)) {
            throw new InvalidArgumentException('The --from date must be before --to.');
        }

        return [$from, $to];
    }

    private function resolveDateColumn(): ?string
    {
        $columns = $this->columns($this->workOrderTable);
        $preferred = $this->optionString('date-column', '');

        if ($preferred !== '') {
            return in_array($preferred, $columns, true) ? $preferred : null;
        }

        foreach (['adTimeIns', 'adDateIns', 'adDate'] as $column) {
            if (in_array($column, $columns, true)) {
                return $column;
            }
        }

        return null;
    }

    private function dateWindowLabel(): string
    {
        if ($this->from === null || $this->to === null || $this->dateColumn === null) {
            return 'none';
        }

        return $this->from->format('Y-m-d H:i:s') . ' - ' . $this->to->format('Y-m-d H:i:s');
    }

    private function workOrderIdentifierCandidates(string $value): array
    {
        $normalized = $this->normalizeIdentifier($value);

        if ($normalized === '') {
            return [];
        }

        $candidates = [$normalized];

        if (preg_match('/^\d{12}$/', $normalized) === 1) {
            $candidates[] = substr($normalized, 0, 6) . '0' . substr($normalized, 6);
        }

        if (preg_match('/^\d{13}$/', $normalized) === 1 && substr($normalized, 6, 1) === '0') {
            $candidates[] = substr($normalized, 0, 6) . substr($normalized, 7);
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $candidate): bool => $candidate !== '')));
    }

    private function workOrderDisplayIdentifierExpression(Builder $query, string $columnIdentifier): string
    {
        $normalizedExpression = $this->normalizedIdentifierExpression($query, $columnIdentifier);

        return "CASE WHEN LEN($normalizedExpression) = 13 AND SUBSTRING($normalizedExpression, 7, 1) = '0' THEN STUFF($normalizedExpression, 7, 1, '') ELSE $normalizedExpression END";
    }

    private function normalizedIdentifierExpression(Builder $query, string $columnIdentifier): string
    {
        $wrappedColumn = $query->getGrammar()->wrap($columnIdentifier);

        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(CAST($wrappedColumn AS NVARCHAR(64)), ''), '-', ''), ' ', ''), '/', ''), '.', ''), '_', ''))";
    }

    private function normalizeIdentifier(string $value): string
    {
        $normalized = preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value)));

        return is_string($normalized) ? $normalized : '';
    }

    private function columns(string $table): array
    {
        if (array_key_exists($table, $this->columnsCache)) {
            return $this->columnsCache[$table];
        }

        $this->columnsCache[$table] = $this->db
            ->table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->schema)
            ->where('TABLE_NAME', $table)
            ->pluck('COLUMN_NAME')
            ->map(static fn ($column): string => (string) $column)
            ->values()
            ->all();

        return $this->columnsCache[$table];
    }

    private function identityColumns(string $table): array
    {
        if (array_key_exists($table, $this->identityColumnsCache)) {
            return $this->identityColumnsCache[$table];
        }

        if ($this->db->getDriverName() !== 'sqlsrv') {
            return $this->identityColumnsCache[$table] = [];
        }

        $this->identityColumnsCache[$table] = $this->db
            ->table('sys.columns as c')
            ->join('sys.tables as t', 'c.object_id', '=', 't.object_id')
            ->join('sys.schemas as s', 't.schema_id', '=', 's.schema_id')
            ->where('s.name', $this->schema)
            ->where('t.name', $table)
            ->where('c.is_identity', 1)
            ->pluck('c.name')
            ->map(static fn ($column): string => (string) $column)
            ->values()
            ->all();

        return $this->identityColumnsCache[$table];
    }

    private function qualified(string $table): string
    {
        return $this->schema . '.' . $table;
    }

    private function firstExisting(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function existingColumns(array $columns, array $candidates): array
    {
        return array_values(array_filter($candidates, fn (string $candidate): bool => in_array($candidate, $columns, true)));
    }

    private function value(array $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return $default;
    }

    private function floatOrZero(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (int) (float) $normalized : null;
    }

    private function intOrZero(mixed $value): int
    {
        return $this->intOrNull($value) ?? 0;
    }

    private function resolvedUserId(array $row): ?int
    {
        if ($this->userId !== null && $this->userId > 0) {
            return $this->userId;
        }

        foreach (['item_anUserChg', 'item_anUserIns'] as $column) {
            $userId = $this->intOrNull($row[$column] ?? null);
            if ($userId !== null && $userId > 0) {
                return $userId;
            }
        }

        return null;
    }

    private function optionString(string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $this->options)) {
            return $default;
        }

        $value = $this->options[$key];

        if (is_array($value)) {
            $value = end($value);
        }

        if ($value === false) {
            return $default;
        }

        return trim((string) $value);
    }

    private function optionStringList(string $key): array
    {
        if (!array_key_exists($key, $this->options)) {
            return [];
        }

        $value = $this->options[$key];
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn ($entry): string => trim((string) $entry), $value), static fn (string $entry): bool => $entry !== ''));
        }

        $stringValue = trim((string) $value);

        return $stringValue !== '' ? [$stringValue] : [];
    }

    private function optionNullableInt(string $key): ?int
    {
        $value = $this->optionString($key, '');

        return $value !== '' && is_numeric($value) ? (int) $value : null;
    }

    private function optionBool(string $key, bool $default): bool
    {
        if (!array_key_exists($key, $this->options)) {
            return $default;
        }

        $value = $this->options[$key];

        if ($value === false) {
            return true;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y'], true);
    }
}

$options = getopt('', [
    'execute',
    'dry-run::',
    'connection::',
    'schema::',
    'from::',
    'to::',
    'days::',
    'date-column::',
    'limit::',
    'user-id::',
    'rn::',
    'verbose',
]);

try {
    exit((new RnItemResourceBackfill(is_array($options) ? $options : []))->run());
} catch (Throwable $exception) {
    fwrite(STDERR, 'Backfill failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
