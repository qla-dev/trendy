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
        $normalizedSets = self::normalizeMaterialsSets($materialsSets);
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

    public static function barcodeGeneratorList(
        string $search = '',
        int $limit = 25,
        array $materialsSets = [],
        int $offset = 0,
        string $sortBy = 'material_code',
        string $sortDir = 'asc'
    ): array {
        $resolvedLimit = self::resolveScannerLimit($limit);
        $resolvedOffset = max(0, (int) $offset);
        $resolvedSortDir = strtolower(trim($sortDir)) === 'desc' ? 'desc' : 'asc';
        $query = DB::query()->fromSub(
            self::barcodeGeneratorBaseQuery($search, $materialsSets),
            'm'
        );

        switch (trim($sortBy)) {
            case 'material_name':
                $query
                    ->orderByRaw("CASE WHEN LTRIM(RTRIM(ISNULL(m.material_name, ''))) = '' THEN 1 ELSE 0 END ASC")
                    ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(m.material_name, '')))) {$resolvedSortDir}")
                    ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(m.material_code, '')))) ASC");
                break;
            case 'material_um':
                $query
                    ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(m.material_um, '')))) {$resolvedSortDir}")
                    ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(m.material_code, '')))) ASC");
                break;
            case 'material_qty':
                $query
                    ->orderBy('material_qty', $resolvedSortDir)
                    ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(m.material_code, '')))) ASC");
                break;
            case 'material_code':
            default:
                $query
                    ->orderByRaw("CASE WHEN LEFT(LTRIM(RTRIM(ISNULL(m.material_code, ''))), 1) LIKE '[A-Za-z]' THEN 0 WHEN LEFT(LTRIM(RTRIM(ISNULL(m.material_code, ''))), 1) LIKE '[0-9]' THEN 2 ELSE 1 END ASC")
                    ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(m.material_code, '')))) {$resolvedSortDir}");
                break;
        }

        return $query
            ->offset($resolvedOffset)
            ->limit($resolvedLimit)
            ->get()
            ->map(function ($row) {
                $materialQty = is_numeric((string) ($row->material_qty ?? null))
                    ? (float) $row->material_qty
                    : 0.0;

                return [
                    'material_code' => trim((string) ($row->material_code ?? '')),
                    'material_name' => trim((string) ($row->material_name ?? '')),
                    'material_um' => strtoupper(substr(trim((string) ($row->material_um ?? '')), 0, 3)),
                    'material_qty' => $materialQty,
                    'barcode_value' => trim((string) ($row->material_code ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    public static function barcodeGeneratorTotalCount(array $materialsSets = []): int
    {
        return (int) DB::query()
            ->fromSub(self::barcodeGeneratorBaseQuery('', $materialsSets), 'm')
            ->count();
    }

    public static function barcodeGeneratorFilteredCount(
        string $search = '',
        array $materialsSets = []
    ): int {
        return (int) DB::query()
            ->fromSub(self::barcodeGeneratorBaseQuery($search, $materialsSets), 'm')
            ->count();
    }

    public static function scannerFindByBarcode(string $barcode, array $materialsSets = []): ?array
    {
        $normalizedBarcode = self::normalizeScannerBarcode($barcode);

        if ($normalizedBarcode === '') {
            return null;
        }

        $normalizedSets = self::normalizeMaterialsSets($materialsSets);
        $stockTable = self::sourceSchema() . '.' . self::stockTable() . ' as s';
        $itemsTable = self::sourceSchema() . '.' . self::itemsTable() . ' as i';
        $query = DB::table($itemsTable)
            ->leftJoin($stockTable, function ($join) {
                $join->whereRaw("LTRIM(RTRIM(ISNULL(s.acIdent, ''))) = LTRIM(RTRIM(ISNULL(i.acIdent, '')))");
            })
            ->whereRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) <> ''");

        if (!empty($normalizedSets)) {
            $query->whereIn(
                DB::raw("LTRIM(RTRIM(ISNULL(i.acSetOfItem, '')))"),
                $normalizedSets
            );
        }

        $query->where(function (Builder $barcodeQuery) use ($normalizedBarcode) {
            $barcodeQuery
                ->whereRaw(self::normalizedBarcodeSql('i.acIdent') . ' = ?', [$normalizedBarcode])
                ->orWhereRaw(self::normalizedBarcodeSql('i.acCode') . ' = ?', [$normalizedBarcode]);

            if (ctype_digit($normalizedBarcode)) {
                $numericBarcode = ltrim($normalizedBarcode, '0');
                if ($numericBarcode === '') {
                    $numericBarcode = '0';
                }

                $barcodeQuery
                    ->orWhereRaw("CAST(ISNULL(i.anPLUCode, 0) as varchar(64)) = ?", [$numericBarcode])
                    ->orWhereRaw("CAST(ISNULL(i.anPLUCode2, 0) as varchar(64)) = ?", [$numericBarcode])
                    ->orWhereRaw("CAST(ISNULL(i.anQId, 0) as varchar(64)) = ?", [$numericBarcode]);
            }
        });

        $row = $query
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) as material_code")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acName, ''))) as material_name")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acUM, ''))) as material_um")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acCode, ''))) as material_code_alt")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acSetOfItem, ''))) as material_set")
            ->selectRaw("CAST(ISNULL(i.anQId, 0) as bigint) as material_qid")
            ->selectRaw("COALESCE(SUM(CAST(ISNULL(s.anStock, 0) as float)), 0) as material_qty")
            ->groupBy('i.acIdent', 'i.acName', 'i.acUM', 'i.acCode', 'i.acSetOfItem', 'i.anQId')
            ->orderByRaw("CASE WHEN " . self::normalizedBarcodeSql('i.acIdent') . " = ? THEN 0 ELSE 1 END", [$normalizedBarcode])
            ->orderByRaw("CASE WHEN " . self::normalizedBarcodeSql('i.acCode') . " = ? THEN 0 ELSE 1 END", [$normalizedBarcode])
            ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(i.acIdent, '')))) ASC")
            ->first();

        if ($row === null) {
            return null;
        }

        $materialQty = is_numeric((string) ($row->material_qty ?? null))
            ? (float) $row->material_qty
            : 0.0;

        return [
            'barcode' => trim((string) ($row->material_code ?? '')),
            'barcode_field' => 'acIdent',
            'material_code' => trim((string) ($row->material_code ?? '')),
            'material_name' => trim((string) ($row->material_name ?? '')),
            'material_um' => strtoupper(substr(trim((string) ($row->material_um ?? '')), 0, 3)),
            'material_code_alt' => trim((string) ($row->material_code_alt ?? '')),
            'material_set' => strtoupper(trim((string) ($row->material_set ?? ''))),
            'material_qid' => is_numeric((string) ($row->material_qid ?? null))
                ? (int) $row->material_qid
                : null,
            'material_qty' => $materialQty,
        ];
    }

    public static function scannerTotalCount(array $materialsSets = []): int
    {
        $normalizedSets = self::normalizeMaterialsSets($materialsSets);

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

        $grouped = $query
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) as material_code")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acName, ''))) as material_name")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acUM, ''))) as material_um")
            ->groupBy('i.acIdent', 'i.acName', 'i.acUM')
            ->havingRaw("COALESCE(SUM(CAST(ISNULL(s.anStock, 0) as float)), 0) <> 0");

        return (int) DB::query()->fromSub($grouped, 'm')->count();
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

    private static function barcodeGeneratorBaseQuery(
        string $search = '',
        array $materialsSets = []
    ): Builder {
        $normalizedSets = self::normalizeMaterialsSets($materialsSets);
        $stockTable = self::sourceSchema() . '.' . self::stockTable() . ' as s';
        $itemsTable = self::sourceSchema() . '.' . self::itemsTable() . ' as i';
        $query = DB::table($itemsTable)
            ->leftJoin($stockTable, function ($join) {
                $join->whereRaw("LTRIM(RTRIM(ISNULL(s.acIdent, ''))) = LTRIM(RTRIM(ISNULL(i.acIdent, '')))");
            })
            ->whereRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) <> ''");

        if (!empty($normalizedSets)) {
            $query->whereIn(
                DB::raw("LTRIM(RTRIM(ISNULL(i.acSetOfItem, '')))"),
                $normalizedSets
            );
        }

        self::applyLikeAny($query, ['i.acIdent', 'i.acName', 'i.acCode'], $search);

        return $query
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) as material_code")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acName, ''))) as material_name")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acUM, ''))) as material_um")
            ->selectRaw("COALESCE(SUM(CAST(ISNULL(s.anStock, 0) as float)), 0) as material_qty")
            ->groupBy('i.acIdent', 'i.acName', 'i.acUM');
    }

    private static function normalizeMaterialsSets(array $materialsSets = []): array
    {
        return array_values(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $materialsSets), function ($value) {
            return $value !== '';
        }));
    }

    private static function normalizeScannerBarcode(string $barcode): string
    {
        $value = strtoupper(trim($barcode));

        if ($value === '') {
            return '';
        }

        return preg_replace('/[^0-9A-Z]/', '', $value) ?? '';
    }

    private static function normalizedBarcodeSql(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(UPPER(LTRIM(RTRIM(ISNULL($column, '')))), '-', ''), ' ', ''), '/', '')";
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
