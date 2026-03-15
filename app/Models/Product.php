<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'position',
        'product_code',
        'name',
        'unit',
        'note',
    ];

    public static function scannerSourceTable(): string
    {
        return self::sourceSchema() . '.' . self::itemsTable();
    }

    public static function structureSourceTable(): string
    {
        return self::sourceSchema() . '.' . self::productStructureTable();
    }

    public static function scannerList(string $search = '', int $limit = 100, string $selectedIdent = ''): array
    {
        $resolvedLimit = self::resolveScannerLimit($limit);
        $productsByKey = [];

        if (trim($selectedIdent) !== '') {
            $selectedProduct = self::findCatalogProduct($selectedIdent);

            if ($selectedProduct !== null) {
                self::addProduct($productsByKey, $selectedProduct);
            }
        }

        $rows = self::baseScannerQuery($search)
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) as product_code")
            ->selectRaw("LTRIM(RTRIM(ISNULL(MAX(i.acName), ''))) as product_name")
            ->selectRaw("UPPER(LEFT(LTRIM(RTRIM(ISNULL(MAX(i.acUM), ''))), 3)) as product_um")
            ->selectRaw("LTRIM(RTRIM(ISNULL(MAX(i.acSetOfItem), ''))) as product_set")
            ->selectRaw("COUNT(*) as bom_count")
            ->groupBy('i.acIdent')
            ->orderByRaw("CASE WHEN LEFT(LTRIM(RTRIM(ISNULL(i.acIdent, ''))), 1) LIKE '[A-Za-z]' THEN 0 WHEN LEFT(LTRIM(RTRIM(ISNULL(i.acIdent, ''))), 1) LIKE '[0-9]' THEN 2 ELSE 1 END ASC")
            ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(i.acIdent, '')))) ASC")
            ->limit($resolvedLimit)
            ->get()
            ->map(function ($row) {
                return self::mapScannerRow((array) $row);
            })
            ->values()
            ->all();

        foreach ($rows as $row) {
            self::addProduct($productsByKey, $row);
        }

        return array_values($productsByKey);
    }

    public static function findCatalogProduct(string $ident): ?array
    {
        $normalizedIdent = trim($ident);

        if ($normalizedIdent === '') {
            return null;
        }

        $row = self::baseScannerQuery()
            ->whereRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) = ?", [$normalizedIdent])
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) as product_code")
            ->selectRaw("LTRIM(RTRIM(ISNULL(MAX(i.acName), ''))) as product_name")
            ->selectRaw("UPPER(LEFT(LTRIM(RTRIM(ISNULL(MAX(i.acUM), ''))), 3)) as product_um")
            ->selectRaw("LTRIM(RTRIM(ISNULL(MAX(i.acSetOfItem), ''))) as product_set")
            ->selectRaw("COUNT(*) as bom_count")
            ->groupBy('i.acIdent')
            ->first();

        return $row === null ? null : self::mapScannerRow((array) $row, 'selected');
    }

    private static function baseScannerQuery(string $search = ''): Builder
    {
        $itemsTable = self::sourceSchema() . '.' . self::itemsTable() . ' as i';
        $productStructureTable = self::sourceSchema() . '.' . self::productStructureTable() . ' as ps';

        $query = DB::table($itemsTable)
            ->join($productStructureTable, function ($join) {
                $join->whereRaw("LTRIM(RTRIM(ISNULL(ps.acIdent, ''))) = LTRIM(RTRIM(ISNULL(i.acIdent, '')))");
            })
            ->whereRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) <> ''")
            ->whereRaw("LTRIM(RTRIM(ISNULL(ps.acIdent, ''))) <> ''")
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(i.acSetOfItem, '')))) <> 'OPR'");

        self::applyLikeAny($query, ['i.acIdent', 'i.acName'], $search);

        return $query;
    }

    private static function mapScannerRow(array $row, string $source = 'catalogue'): array
    {
        $productCode = trim((string) ($row['product_code'] ?? ''));
        $productName = trim((string) ($row['product_name'] ?? ''));
        $productUm = strtoupper(substr(trim((string) ($row['product_um'] ?? '')), 0, 3));
        $productSet = strtoupper(trim((string) ($row['product_set'] ?? '')));
        $bomCount = is_numeric((string) ($row['bom_count'] ?? null))
            ? (int) $row['bom_count']
            : 0;

        return [
            'acIdent' => $productCode,
            'acIdentTrimmed' => $productCode,
            'acName' => $productName,
            'acUM' => $productUm,
            'acSetOfItem' => $productSet,
            'label' => $productName !== '' ? ($productCode . ' - ' . $productName) : $productCode,
            'source' => $source,
            'bom_count' => $bomCount,
        ];
    }

    private static function addProduct(array &$productsByKey, array $product): void
    {
        $productCode = trim((string) ($product['acIdent'] ?? ''));

        if ($productCode === '') {
            return;
        }

        $productKey = strtolower($productCode);

        if (!array_key_exists($productKey, $productsByKey)) {
            $productsByKey[$productKey] = $product;
            return;
        }

        $existingName = trim((string) ($productsByKey[$productKey]['acName'] ?? ''));
        $incomingName = trim((string) ($product['acName'] ?? ''));

        if ($existingName === '' && $incomingName !== '') {
            $productsByKey[$productKey] = $product;
        }
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

    private static function productStructureTable(): string
    {
        return (string) config('workorders.product_structure_table', 'tHF_SetPrSt');
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
