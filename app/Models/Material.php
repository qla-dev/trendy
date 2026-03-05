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
        return self::sourceSchema() . '.' . self::itemsTable();
    }

    public static function scannerList(
        string $search = '',
        int $limit = 100,
        array $materialsSets = [],
        int $offset = 0
    ): array
    {
        $resolvedLimit = self::resolveScannerLimit($limit);
        $resolvedOffset = max(0, (int) $offset);
        $normalizedSets = array_values(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $materialsSets), function ($value) {
            return $value !== '';
        }));
        $stockTable = self::sourceSchema() . '.' . self::stockTable() . ' as s';
        $itemsTable = self::sourceSchema() . '.' . self::itemsTable() . ' as i';
        $query = DB::table($stockTable)
            ->join($itemsTable, function ($join) {
                $join->whereRaw("LTRIM(RTRIM(ISNULL(s.acIdent, ''))) = LTRIM(RTRIM(ISNULL(i.acIdent, '')))");
            })
            ->whereRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) <> ''");

        if (!empty($normalizedSets)) {
            $query->whereIn(
                DB::raw("LTRIM(RTRIM(ISNULL(i.acSetOfItem, '')))"),
                $normalizedSets
            );
        }

        self::applyLikeAny($query, ['i.acIdent', 'i.acName'], $search);

        $codeExpr = "LTRIM(RTRIM(ISNULL(i.acIdent, '')))";
        $rows = $query
            ->selectRaw("$codeExpr as material_code")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acName, ''))) as material_name")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acUM, ''))) as material_um")
            ->selectRaw("COALESCE(SUM(CAST(ISNULL(s.anStock, 0) as float)), 0) as material_qty")
            ->groupBy('i.acIdent', 'i.acName', 'i.acUM')
            ->havingRaw("COALESCE(SUM(CAST(ISNULL(s.anStock, 0) as float)), 0) <> 0")
            ->orderByRaw("CASE WHEN LEFT($codeExpr, 1) LIKE '[A-Za-z]' THEN 0 WHEN LEFT($codeExpr, 1) LIKE '[0-9]' THEN 2 ELSE 1 END ASC")
            ->orderByRaw("UPPER($codeExpr) ASC")
            ->offset($resolvedOffset)
            ->limit($resolvedLimit)
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();

        $materials = [];

        foreach ($rows as $row) {
            $materialCode = trim((string) ($row['material_code'] ?? ''));
            $materialName = trim((string) ($row['material_name'] ?? ''));

            if ($materialCode === '' && $materialName === '') {
                continue;
            }
            $rawQty = $row['material_qty'] ?? null;
            $parsedQty = is_numeric((string) $rawQty) ? (float) $rawQty : 0.0;

            $materials[] = [
                'anNo' => $resolvedOffset + count($materials) + 1,
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

    private static function itemsTable(): string
    {
        return (string) config('workorders.catalog_items_table', 'tHE_SetItem');
    }

    private static function stockTable(): string
    {
        return (string) config('workorders.stock_table', 'tHE_Stock');
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
}
