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
        return self::sourceSchema() . '.' . self::itemsTable();
    }

    public static function scannerList(string $search = '', int $limit = 100, string $operationsSet = 'OPR'): array
    {
        $resolvedLimit = self::resolveScannerLimit($limit);
        $itemsTable = self::sourceSchema() . '.' . self::itemsTable() . ' as i';
        $stockTable = self::sourceSchema() . '.' . self::stockTable() . ' as s';

        $query = DB::table($itemsTable)
            ->leftJoin($stockTable, 's.acIdent', '=', 'i.acIdent')
            ->whereRaw(
                "LTRIM(RTRIM(ISNULL(i.acSetOfItem, ''))) = ?",
                [trim($operationsSet)]
            )
            ->whereRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) <> ''");

        self::applyLikeAny($query, ['i.acIdent', 'i.acName'], $search);

        $rows = $query
            ->selectRaw("LTRIM(RTRIM(i.acIdent)) as operation_code")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acName, ''))) as operation_name")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acUM, ''))) as operation_um")
            ->selectRaw("COALESCE(SUM(CAST(ISNULL(s.anStock, 0) as float)), 0) as operation_qty")
            ->groupBy('i.acIdent', 'i.acName', 'i.acUM')
            ->orderBy('i.acIdent')
            ->limit($resolvedLimit)
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();

        $operations = [];

        foreach ($rows as $row) {
            $operationCode = trim((string) ($row['operation_code'] ?? ''));
            $operationName = trim((string) ($row['operation_name'] ?? ''));

            if ($operationCode === '' && $operationName === '') {
                continue;
            }
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
