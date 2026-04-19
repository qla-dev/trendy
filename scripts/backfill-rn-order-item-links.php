#!/usr/bin/env php
<?php

/*
 * Backfills missing RN -> order item links for app-created work orders.
 *
 * Dry run first:
 *   php scripts/backfill-rn-order-item-links.php --from=2026-04-11 --to=2026-04-18 --verbose
 *
 * Insert missing links after reviewing the dry-run summary:
 *   php scripts/backfill-rn-order-item-links.php --from=2026-04-11 --to=2026-04-18 --execute
 *
 * Defaults:
 *   - no writes unless --execute is passed
 *   - date window is the previous 7 days if --from/--to are omitted
 *   - only RNs with adSchedStartTime at 08:00 are considered
 *   - RNs that already have a link are skipped
 */

use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class RnOrderItemLinkBackfill
{
    private ConnectionInterface $db;
    private array $options;
    private string $schema;
    private string $workOrderTable;
    private string $orderTable;
    private string $orderItemTable;
    private string $linkReadTable;
    private string $linkInsertTable;
    private array $columnsCache = [];
    private int $plannedStartHour;
    private int $plannedStartMinute;
    private bool $execute;
    private bool $verbose;
    private ?int $limit;
    private ?int $userId;
    private string $userLabel;
    private Carbon $from;
    private Carbon $to;
    private string $dateColumn;

    public function __construct(array $options)
    {
        $this->options = $options;
        $connectionName = $this->optionString('connection', (string) config('database.default'));
        $this->db = DB::connection($connectionName);
        $this->schema = $this->optionString('schema', (string) config('workorders.schema', 'dbo'));
        $this->workOrderTable = (string) config('workorders.table', 'tHF_WOEx');
        $this->orderTable = (string) config('workorders.orders_table', 'tHE_Order');
        $this->orderItemTable = (string) config('workorders.order_items_table', 'tHE_OrderItem');
        $this->linkReadTable = $this->resolveLinkReadTable();
        $this->linkInsertTable = $this->resolveLinkInsertTable();
        $this->plannedStartHour = $this->optionInt('hour', 8);
        $this->plannedStartMinute = $this->optionInt('minute', 0);
        $this->execute = array_key_exists('execute', $options) && !$this->optionBool('dry-run', false);
        $this->verbose = $this->optionBool('verbose', false);
        $this->limit = $this->optionNullableInt('limit');
        $this->userId = $this->optionNullableInt('user-id');
        $this->userLabel = $this->optionString('user-label', 'rn-link-backfill');
        [$this->from, $this->to] = $this->resolveDateWindow();
        $this->dateColumn = $this->resolveDateColumn();
    }

    public function run(): int
    {
        $this->printHeader();

        $rows = $this->candidateWorkOrders();
        $stats = [
            'candidates' => count($rows),
            'inserted' => 0,
            'would_insert' => 0,
            'skipped_existing_link' => 0,
            'skipped_missing_key' => 0,
            'skipped_missing_order' => 0,
            'skipped_missing_position' => 0,
            'skipped_missing_order_item_qid' => 0,
            'skipped_empty_payload' => 0,
            'failed' => 0,
        ];

        foreach ($rows as $row) {
            $row = (array) $row;
            $workOrderKey = trim((string) $this->value($row, ['acKey'], ''));
            $workOrderNumber = trim((string) $this->value($row, ['acKeyView', 'acRefNo1', 'acKey'], $workOrderKey));

            if ($workOrderKey === '') {
                $stats['skipped_missing_key']++;
                $this->printRow('SKIP missing RN key', $workOrderNumber, $row);
                continue;
            }

            if ($this->linkExistsForWorkOrder($workOrderKey)) {
                $stats['skipped_existing_link']++;
                $this->printRow('SKIP existing link', $workOrderNumber, $row);
                continue;
            }

            $context = $this->orderContextFromWorkOrder($row);

            if (trim((string) ($context['order_key'] ?? '')) === '') {
                $stats['skipped_missing_order']++;
                $this->printRow('SKIP missing order', $workOrderNumber, $row);
                continue;
            }

            if ((int) ($context['order_position'] ?? 0) < 1) {
                $stats['skipped_missing_position']++;
                $this->printRow('SKIP missing position', $workOrderNumber, $row);
                continue;
            }

            $orderItemRow = $this->findOrderItemRow($context);
            $orderItemQId = $this->intOrNull($orderItemRow['anQId'] ?? null);

            if ($this->linkInsertRequiresOrderItemQId() && $orderItemQId === null) {
                $stats['skipped_missing_order_item_qid']++;
                $this->printRow('SKIP missing order item QID', $workOrderNumber, $row);
                continue;
            }

            $payload = $this->buildLinkPayload($workOrderKey, $context, $orderItemQId);

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
                $this->db->transaction(function () use ($workOrderKey, $payload): void {
                    if (!$this->linkExistsForWorkOrder($workOrderKey)) {
                        $this->db->table($this->qualified($this->linkInsertTable))->insert($payload);
                    }
                });

                $stats['inserted']++;
                $this->printRow('INSERTED', $workOrderNumber, $row, $payload);
            } catch (Throwable $exception) {
                $stats['failed']++;
                fwrite(STDERR, 'FAILED ' . $workOrderNumber . ': ' . $exception->getMessage() . PHP_EOL);
            }
        }

        $this->printStats($stats);

        if (!$this->execute) {
            echo PHP_EOL . 'Dry run only. Add --execute to insert the missing links.' . PHP_EOL;
        }

        return $stats['failed'] > 0 ? 1 : 0;
    }

    private function printHeader(): void
    {
        echo 'RN order-item link backfill' . PHP_EOL;
        echo 'Mode: ' . ($this->execute ? 'EXECUTE' : 'DRY RUN') . PHP_EOL;
        echo 'Connection: ' . $this->db->getName() . PHP_EOL;
        echo 'Work orders: ' . $this->qualified($this->workOrderTable) . PHP_EOL;
        echo 'Orders: ' . $this->qualified($this->orderTable) . PHP_EOL;
        echo 'Order items: ' . $this->qualified($this->orderItemTable) . PHP_EOL;
        echo 'Link read table: ' . $this->qualified($this->linkReadTable) . PHP_EOL;
        echo 'Link insert table: ' . $this->qualified($this->linkInsertTable) . PHP_EOL;
        echo 'Created date column: ' . $this->dateColumn . PHP_EOL;
        echo 'Created window: ' . $this->from->format('Y-m-d H:i:s') . ' - ' . $this->to->format('Y-m-d H:i:s') . PHP_EOL;
        echo 'Planned start time filter: ' . sprintf('%02d:%02d', $this->plannedStartHour, $this->plannedStartMinute) . PHP_EOL;
        echo PHP_EOL;
    }

    private function candidateWorkOrders(): array
    {
        $columns = $this->columns($this->workOrderTable);
        $plannedStartColumn = $this->firstExisting($columns, ['adSchedStartTime']);
        $keyColumn = $this->firstExisting($columns, ['acKey']);

        if ($plannedStartColumn === null || $keyColumn === null || !in_array($this->dateColumn, $columns, true)) {
            throw new RuntimeException('Required work-order columns are missing.');
        }

        $selectColumns = $this->existingColumns($columns, [
            'acKey',
            'acKeyView',
            'acRefNo1',
            'acLnkKey',
            'acLnkKeyView',
            'anLnkNo',
            'acIdent',
            'acCode',
            'product_code',
            'adSchedStartTime',
            'adDate',
            'adDateIns',
            'adTimeIns',
        ]);
        $query = $this->db->table($this->qualified($this->workOrderTable));
        $wrappedPlannedStart = $query->getGrammar()->wrap($plannedStartColumn);

        $query
            ->whereBetween($this->dateColumn, [
                $this->from->format('Y-m-d H:i:s'),
                $this->to->format('Y-m-d H:i:s'),
            ])
            ->whereNotNull($plannedStartColumn)
            ->whereRaw("DATEPART(HOUR, $wrappedPlannedStart) = ?", [$this->plannedStartHour])
            ->whereRaw("DATEPART(MINUTE, $wrappedPlannedStart) = ?", [$this->plannedStartMinute]);

        foreach (array_unique([$this->dateColumn, 'adTimeIns', 'adDateIns', 'adDate', 'acKey']) as $orderByColumn) {
            if (in_array($orderByColumn, $columns, true)) {
                $query->orderByDesc($orderByColumn);
            }
        }

        if ($this->limit !== null && $this->limit > 0) {
            $query->limit($this->limit);
        }

        return (empty($selectColumns) ? $query->get() : $query->get($selectColumns))
            ->map(static fn ($row): array => (array) $row)
            ->values()
            ->all();
    }

    private function orderContextFromWorkOrder(array $workOrder): array
    {
        $orderKey = trim((string) $this->value($workOrder, ['acLnkKey'], ''));
        $orderNumber = trim((string) $this->value($workOrder, ['acLnkKeyView'], ''));
        $orderPosition = (int) $this->value($workOrder, ['anLnkNo'], 0);
        $productCode = trim((string) $this->value($workOrder, ['acIdent', 'acCode', 'product_code'], ''));

        if ($orderKey === '' && $orderNumber !== '') {
            $order = $this->findOrderByNumber($orderNumber);
            if ($order !== null) {
                $orderKey = trim((string) $this->value($order, ['acKey'], ''));
                $orderNumber = $this->resolveOrderDisplayNumber($order, $orderNumber);
            }
        }

        return [
            'order_key' => $orderKey,
            'order_number' => $orderNumber,
            'order_position' => $orderPosition,
            'product_code' => $productCode,
        ];
    }

    private function findOrderByNumber(string $orderNumber): ?array
    {
        $normalizedOrderNumber = $this->normalizeOrderDisplayNumber($orderNumber);

        if ($normalizedOrderNumber === '') {
            return null;
        }

        $columns = $this->columns($this->orderTable);
        $numberColumns = $this->existingColumns($columns, ['acKeyView', 'acRefNo1', 'acKey']);

        if (empty($numberColumns)) {
            return null;
        }

        $selectColumns = $this->existingColumns($columns, [
            'acKey',
            'acKeyView',
            'acRefNo1',
            'adDate',
            'adDateIns',
            'adTimeIns',
        ]);
        $query = $this->db->table($this->qualified($this->orderTable));
        $query->where(function (Builder $numberQuery) use ($numberColumns, $normalizedOrderNumber): void {
            foreach ($numberColumns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $numberQuery->{$method}($this->orderDisplayIdentifierExpression($numberQuery, $column) . ' = ?', [$normalizedOrderNumber]);
            }
        });

        foreach (['adDate', 'adDateIns', 'adTimeIns', 'acKey'] as $orderByColumn) {
            if (in_array($orderByColumn, $columns, true)) {
                $query->orderByDesc($orderByColumn);
            }
        }

        $row = empty($selectColumns) ? $query->first() : $query->first($selectColumns);

        return $row ? (array) $row : null;
    }

    private function findOrderItemRow(array $context): ?array
    {
        $columns = $this->columns($this->orderItemTable);
        $orderKeyColumns = $this->existingColumns($columns, ['acKey', 'acLnkKey', 'acOrderKey', 'order_key']);
        $orderPositionColumns = $this->existingColumns($columns, ['anNo', 'anLineNo', 'anItemNo', 'anPosition', 'anPos', 'anPosNo']);
        $productColumns = $this->existingColumns($columns, ['acIdent', 'product_code', 'acCode']);
        $orderKey = $this->normalizeIdentifier((string) ($context['order_key'] ?? ''));
        $orderPosition = (int) ($context['order_position'] ?? 0);
        $productCode = trim((string) ($context['product_code'] ?? ''));

        if ($orderKey === '' || empty($orderKeyColumns)) {
            return null;
        }

        $selectColumns = $this->existingColumns($columns, [
            'anQId',
            'acKey',
            'anNo',
            'anLineNo',
            'anItemNo',
            'acIdent',
            'product_code',
            'acCode',
        ]);
        $query = $this->db->table($this->qualified($this->orderItemTable));
        $query->where(function (Builder $keyQuery) use ($orderKeyColumns, $orderKey): void {
            foreach ($orderKeyColumns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $keyQuery->{$method}($this->normalizedIdentifierExpression($keyQuery, $column) . ' = ?', [$orderKey]);
            }
        });

        if ($orderPosition > 0 && !empty($orderPositionColumns)) {
            $query->where(function (Builder $positionQuery) use ($orderPositionColumns, $orderPosition): void {
                foreach ($orderPositionColumns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $positionQuery->{$method}($column, $orderPosition);
                }
            });
        }

        if ($productCode !== '' && !empty($productColumns)) {
            $exactProductCode = strtoupper(trim($productCode));
            $query->where(function (Builder $productQuery) use ($productColumns, $exactProductCode): void {
                foreach ($productColumns as $index => $column) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $productQuery->{$method}($this->productCodeExpression($productQuery, $column) . ' = ?', [$exactProductCode]);
                }
            });
        }

        foreach (['adDate', 'adDateIns', 'adTimeIns', 'anNo', 'anQId'] as $orderByColumn) {
            if (in_array($orderByColumn, $columns, true)) {
                $query->orderByDesc($orderByColumn);
            }
        }

        $row = empty($selectColumns) ? $query->first() : $query->first($selectColumns);

        return $row ? (array) $row : null;
    }

    private function buildLinkPayload(string $workOrderKey, array $context, ?int $orderItemQId): array
    {
        $columns = $this->columns($this->linkInsertTable);
        $payload = [];
        $orderKey = trim((string) ($context['order_key'] ?? ''));
        $orderPosition = (int) ($context['order_position'] ?? 0);

        if ($workOrderKey === '' || $orderKey === '' || empty($columns)) {
            return [];
        }

        $now = Carbon::now();

        if (in_array('acKey', $columns, true)) {
            $payload['acKey'] = $workOrderKey;
        }
        if (in_array('acLnkKey', $columns, true)) {
            $payload['acLnkKey'] = $orderKey;
        }
        if (in_array('anLnkNo', $columns, true)) {
            $payload['anLnkNo'] = $orderPosition;
        }
        if ($orderItemQId !== null && in_array('anOrderItemQId', $columns, true)) {
            $payload['anOrderItemQId'] = $orderItemQId;
        }
        if (in_array('acType', $columns, true)) {
            $payload['acType'] = 'DK';
        }
        if (in_array('adTimeIns', $columns, true)) {
            $payload['adTimeIns'] = $now;
        }
        if (in_array('adTimeChg', $columns, true)) {
            $payload['adTimeChg'] = $now;
        }
        if ($this->userId !== null && $this->userId > 0) {
            if (in_array('anUserId', $columns, true)) {
                $payload['anUserId'] = $this->userId;
            }
            if (in_array('anUserIns', $columns, true)) {
                $payload['anUserIns'] = $this->userId;
            }
            if (in_array('anUserChg', $columns, true)) {
                $payload['anUserChg'] = $this->userId;
            }
        }
        if ($this->userLabel !== '' && in_array('acValue', $columns, true)) {
            $payload['acValue'] = substr($this->userLabel, 0, 35);
        }
        if (in_array('anNo', $columns, true)) {
            $payload['anNo'] = 0;
        }

        return $payload;
    }

    private function linkInsertRequiresOrderItemQId(): bool
    {
        return in_array('anOrderItemQId', $this->columns($this->linkInsertTable), true);
    }

    private function linkExistsForWorkOrder(string $workOrderKey): bool
    {
        $normalizedWorkOrderKey = $this->normalizeIdentifier($workOrderKey);

        if ($normalizedWorkOrderKey === '') {
            return false;
        }

        foreach ([$this->linkReadTable, $this->linkInsertTable] as $table) {
            $columns = $this->columns($table);

            if (!in_array('acKey', $columns, true)) {
                continue;
            }

            $query = $this->db->table($this->qualified($table));
            $query->whereRaw($this->normalizedIdentifierExpression($query, 'acKey') . ' = ?', [$normalizedWorkOrderKey]);

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    private function resolveLinkReadTable(): string
    {
        $configuredTable = trim((string) config('workorders.work_order_order_item_link_table', 'vHF_LinkWOExOrderItem'));

        foreach (array_values(array_unique(array_filter([
            $configuredTable,
            'vHF_LinkWOExOrderItem',
            'tHF_LinkWOExOrderItem',
        ]))) as $candidate) {
            if ($this->tableHasColumns($candidate)) {
                return $candidate;
            }
        }

        return $configuredTable !== '' ? $configuredTable : 'tHF_LinkWOExOrderItem';
    }

    private function resolveLinkInsertTable(): string
    {
        $configuredTable = trim((string) config('workorders.work_order_order_item_link_insert_table', 'tHF_LinkWOExOrderItem'));
        $readTable = trim((string) config('workorders.work_order_order_item_link_table', 'vHF_LinkWOExOrderItem'));
        $derivedTable = preg_match('/^v(.+)$/', $readTable, $matches) === 1 ? 't' . $matches[1] : '';

        foreach (array_values(array_unique(array_filter([
            $configuredTable,
            $derivedTable,
            'tHF_LinkWOExOrderItem',
            $readTable,
        ]))) as $candidate) {
            if ($this->baseTableExists($candidate)) {
                return $candidate;
            }
        }

        return $configuredTable !== '' ? $configuredTable : 'tHF_LinkWOExOrderItem';
    }

    private function resolveDateColumn(): string
    {
        $columns = $this->columns($this->workOrderTable);
        $requested = $this->optionString('date-column', '');

        if ($requested !== '') {
            if (!in_array($requested, $columns, true)) {
                throw new InvalidArgumentException("Date column does not exist on work-order table: $requested");
            }

            return $requested;
        }

        $resolved = $this->firstExisting($columns, ['adTimeIns', 'adDateIns', 'adDate']);

        if ($resolved === null) {
            throw new RuntimeException('Could not resolve a work-order created date column.');
        }

        return $resolved;
    }

    private function resolveDateWindow(): array
    {
        $fromOption = $this->optionString('from', '');
        $toOption = $this->optionString('to', '');
        $days = max(1, $this->optionInt('days', 7));

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

    private function tableHasColumns(string $table): bool
    {
        return $this->db
            ->table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->schema)
            ->where('TABLE_NAME', $table)
            ->exists();
    }

    private function baseTableExists(string $table): bool
    {
        return $this->db
            ->table('INFORMATION_SCHEMA.TABLES')
            ->where('TABLE_SCHEMA', $this->schema)
            ->where('TABLE_NAME', $table)
            ->where('TABLE_TYPE', 'BASE TABLE')
            ->exists();
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

    private function normalizedIdentifierExpression(Builder $query, string $columnIdentifier): string
    {
        $wrappedColumn = $query->getGrammar()->wrap($columnIdentifier);

        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(CAST($wrappedColumn AS NVARCHAR(64)), ''), '-', ''), ' ', ''), '/', ''), '.', ''), '_', ''))";
    }

    private function orderDisplayIdentifierExpression(Builder $query, string $columnIdentifier): string
    {
        $normalizedExpression = $this->normalizedIdentifierExpression($query, $columnIdentifier);

        return "CASE WHEN LEN($normalizedExpression) = 13 AND SUBSTRING($normalizedExpression, 7, 1) = '0' THEN STUFF($normalizedExpression, 7, 1, '') ELSE $normalizedExpression END";
    }

    private function productCodeExpression(Builder $query, string $columnIdentifier): string
    {
        $wrappedColumn = $query->getGrammar()->wrap($columnIdentifier);

        return "UPPER(LTRIM(RTRIM(COALESCE(CAST($wrappedColumn AS NVARCHAR(64)), ''))))";
    }

    private function normalizeIdentifier(string $value): string
    {
        $normalized = preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value)));

        return is_string($normalized) ? $normalized : '';
    }

    private function normalizeOrderDisplayNumber(string $value): string
    {
        $normalized = $this->normalizeIdentifier($value);

        if (preg_match('/^\d{13}$/', $normalized) === 1 && substr($normalized, 6, 1) === '0') {
            return substr($normalized, 0, 6) . substr($normalized, 7);
        }

        return $normalized;
    }

    private function resolveOrderDisplayNumber(array $row, string $fallback): string
    {
        $displayNumber = trim((string) $this->value($row, ['acKeyView', 'acRefNo1', 'acKey'], ''));

        return $displayNumber !== '' ? $displayNumber : $fallback;
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

    private function printRow(string $status, string $workOrderNumber, array $row, array $payload = []): void
    {
        if (!$this->verbose && !in_array($status, ['INSERTED', 'FAILED'], true)) {
            return;
        }

        $parts = [
            $status,
            'RN=' . ($workOrderNumber !== '' ? $workOrderNumber : '-'),
            'order=' . trim((string) $this->value($row, ['acLnkKeyView', 'acLnkKey'], '-')),
            'pos=' . trim((string) $this->value($row, ['anLnkNo'], '-')),
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

    private function optionInt(string $key, int $default): int
    {
        $value = $this->optionString($key, '');

        return $value !== '' && is_numeric($value) ? (int) $value : $default;
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
    'days::',
    'from::',
    'to::',
    'date-column::',
    'hour::',
    'minute::',
    'limit::',
    'user-id::',
    'user-label::',
    'verbose',
]);

try {
    exit((new RnOrderItemLinkBackfill(is_array($options) ? $options : []))->run());
} catch (Throwable $exception) {
    fwrite(STDERR, 'Backfill failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
