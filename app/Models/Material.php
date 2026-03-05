<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'position',
        'material_code',
        'name',
        'quantity',
        'unit',
        'note',
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
        $materialColumn = self::firstExistingColumn($columns, ['acIdent']);
        $nameColumn = self::firstExistingColumn($columns, ['acDescr', 'acName']);
        $unitColumn = self::firstExistingColumn($columns, ['acUM']);
        $qtyColumn = self::firstExistingColumn($columns, ['anQty', 'anPlanQty', 'anQtyBase']);
        $operationTypeColumn = self::firstExistingColumn($columns, ['acOperationType']);

        if ($materialColumn === null && $nameColumn === null) {
            return [];
        }

        $query = DB::table(self::scannerSourceTable());

        if ($operationTypeColumn !== null) {
            $query->whereRaw("LTRIM(RTRIM(ISNULL({$operationTypeColumn}, ''))) = ''");
        }

        if ($materialColumn !== null) {
            $query->whereRaw("LTRIM(RTRIM(ISNULL({$materialColumn}, ''))) <> ''");
        }

        self::applyLikeAny(
            $query,
            array_values(array_filter([$materialColumn, $nameColumn])),
            $search
        );

        $selectColumns = [];

        if ($materialColumn !== null) {
            $selectColumns[] = $materialColumn . ' as material_code';
        }
        if ($nameColumn !== null) {
            $selectColumns[] = $nameColumn . ' as material_name';
        }
        if ($unitColumn !== null) {
            $selectColumns[] = $unitColumn . ' as material_um';
        }
        if ($qtyColumn !== null) {
            $selectColumns[] = $qtyColumn . ' as material_qty';
        }

        if (empty($selectColumns)) {
            return [];
        }

        $rows = $query
            ->select($selectColumns)
            ->orderBy($materialColumn ?? $nameColumn ?? 'anNo')
            ->limit(max($resolvedLimit * 4, $resolvedLimit))
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();

        $unique = [];
        $materials = [];

        foreach ($rows as $row) {
            $materialCode = trim((string) ($row['material_code'] ?? ''));
            $materialName = trim((string) ($row['material_name'] ?? ''));

            if ($materialCode === '' && $materialName === '') {
                continue;
            }

            $key = strtolower($materialCode !== '' ? $materialCode : $materialName);

            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = true;
            $rawQty = $row['material_qty'] ?? null;
            $parsedQty = is_numeric((string) $rawQty) ? (float) $rawQty : 0.0;

            $materials[] = [
                'anNo' => count($materials) + 1,
                'acIdentChild' => $materialCode !== '' ? $materialCode : $materialName,
                'acDescr' => $materialName !== '' ? $materialName : ($materialCode !== '' ? $materialCode : '-'),
                'acUM' => strtoupper(substr(trim((string) ($row['material_um'] ?? '')), 0, 3)),
                'anGrossQty' => $parsedQty,
                'acOperationType' => 'M',
            ];

            if (count($materials) >= $resolvedLimit) {
                break;
            }
        }

        return $materials;
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
