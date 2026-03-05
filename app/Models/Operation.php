<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class Operation extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'alternative',
        'position',
        'operation_code',
        'name',
        'note',
        'unit',
        'unit_value',
        'normative',
        'va',
        'primary_class',
        'secondary_class',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public static function scannerSourceTable(): string
    {
        return self::sourceSchema() . '.' . self::sourceTable();
    }

    public static function scannerList(string $search = '', int $limit = 100): array
    {
        $resolvedLimit = self::resolveScannerLimit($limit);
        $columns = self::sourceColumns();
        $operationColumn = self::firstExistingColumn($columns, ['acIdent']);
        $nameColumn = self::firstExistingColumn($columns, ['acDescr', 'acName']);
        $unitColumn = self::firstExistingColumn($columns, ['acUM', 'acUMTime']);
        $qtyColumn = self::firstExistingColumn($columns, ['anQty', 'anQtyBase', 'anPlanQty']);
        $operationTypeColumn = self::firstExistingColumn($columns, ['acOperationType']);

        if ($operationColumn === null && $nameColumn === null) {
            return [];
        }

        $query = DB::table(self::scannerSourceTable());

        if ($operationTypeColumn !== null) {
            $query->whereRaw("LTRIM(RTRIM(ISNULL({$operationTypeColumn}, ''))) <> ''");
        }

        if ($operationColumn !== null) {
            $query->whereRaw("LTRIM(RTRIM(ISNULL({$operationColumn}, ''))) <> ''");
        }

        self::applyLikeAny(
            $query,
            array_values(array_filter([$operationColumn, $nameColumn])),
            $search
        );

        $selectColumns = [];

        if ($operationColumn !== null) {
            $selectColumns[] = $operationColumn . ' as operation_code';
        }
        if ($nameColumn !== null) {
            $selectColumns[] = $nameColumn . ' as operation_name';
        }
        if ($unitColumn !== null) {
            $selectColumns[] = $unitColumn . ' as operation_um';
        }
        if ($qtyColumn !== null) {
            $selectColumns[] = $qtyColumn . ' as operation_qty';
        }

        if (empty($selectColumns)) {
            return [];
        }

        $rows = $query
            ->select($selectColumns)
            ->orderBy($operationColumn ?? $nameColumn ?? 'anNo')
            ->limit(max($resolvedLimit * 4, $resolvedLimit))
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();

        $unique = [];
        $operations = [];

        foreach ($rows as $row) {
            $operationCode = trim((string) ($row['operation_code'] ?? ''));
            $operationName = trim((string) ($row['operation_name'] ?? ''));

            if ($operationCode === '' && $operationName === '') {
                continue;
            }

            $key = strtolower($operationCode !== '' ? $operationCode : $operationName);

            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = true;
            $rawQty = $row['operation_qty'] ?? null;
            $parsedQty = is_numeric((string) $rawQty) ? (float) $rawQty : 0.0;

            $operations[] = [
                'anNo' => count($operations) + 1,
                'acIdentChild' => $operationCode !== '' ? $operationCode : $operationName,
                'acDescr' => $operationName !== '' ? $operationName : ($operationCode !== '' ? $operationCode : '-'),
                'acUM' => strtoupper(substr(trim((string) ($row['operation_um'] ?? '')), 0, 3)),
                'anGrossQty' => $parsedQty,
                'acOperationType' => 'O',
            ];

            if (count($operations) >= $resolvedLimit) {
                break;
            }
        }

        return $operations;
    }

    private static function applyLikeAny(Builder $query, array $columns, string $value): void
    {
        $value = trim($value);

        if ($value === '' || empty($columns)) {
            return;
        }

        $query->where(function (Builder $likeQuery) use ($columns, $value) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $likeQuery->where($column, 'like', '%' . $value . '%');
                    continue;
                }

                $likeQuery->orWhere($column, 'like', '%' . $value . '%');
            }
        });
    }

    private static function sourceColumns(): array
    {
        return DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', self::sourceSchema())
            ->where('TABLE_NAME', self::sourceTable())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();
    }

    private static function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function resolveScannerLimit(?int $requestedLimit = null): int
    {
        $limit = (int) ($requestedLimit ?? 100);

        if ($limit < 1) {
            return 100;
        }

        return min($limit, 100);
    }

    private static function sourceSchema(): string
    {
        return (string) config('workorders.schema', 'dbo');
    }

    private static function sourceTable(): string
    {
        return (string) config('workorders.items_table', 'tHF_WOExItem');
    }
}
