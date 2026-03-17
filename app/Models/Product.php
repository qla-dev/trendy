<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    private static ?array $catalogItemColumnsCache = null;
    private static ?array $catalogItemNonInsertableColumnsCache = null;
    private static ?array $productStructureColumnsCache = null;
    private static ?array $productStructureNonInsertableColumnsCache = null;

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

    public static function ensureCatalogProduct(array $attributes, int $userId = 0): array
    {
        $productCode = trim((string) ($attributes['product_code'] ?? $attributes['acIdent'] ?? ''));

        if ($productCode === '') {
            throw new \InvalidArgumentException('Product code is required.');
        }

        $productName = trim((string) ($attributes['product_name'] ?? $attributes['acName'] ?? ''));
        $productUnit = strtoupper(substr(trim((string) ($attributes['product_um'] ?? $attributes['acUM'] ?? '')), 0, 3));
        $productSet = strtoupper(trim((string) ($attributes['product_set'] ?? $attributes['acSetOfItem'] ?? '')));
        $now = now();
        $itemsTable = self::sourceSchema() . '.' . self::itemsTable();
        $existingRow = DB::table($itemsTable)
            ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$productCode])
            ->first();

        if ($existingRow !== null) {
            return [
                'created' => false,
                'row' => (array) $existingRow,
            ];
        }

        $catalogColumns = self::catalogItemColumns();
        $nonInsertableColumns = self::catalogItemNonInsertableColumns();

        if (empty($catalogColumns)) {
            throw new \RuntimeException('Catalog item columns could not be resolved.');
        }

        $nextQId = array_key_exists('anQId', $catalogColumns)
            ? ((int) (DB::table($itemsTable)->max('anQId') ?? 0)) + 1
            : null;
        $preferredValues = [
            'acIdent' => $productCode,
            'acCode' => $productCode,
            'acName' => $productName !== '' ? $productName : $productCode,
            'acUM' => $productUnit,
            'acSetOfItem' => $productSet,
            'anQId' => $nextQId,
            'anPLUCode' => 0,
            'anPLUCode2' => 0,
            'anBuyPrice' => 0,
            'anPrice' => 0,
            'anVAT' => 0,
            'anDeliveryDeadline' => 0,
            'anUserIns' => $userId > 0 ? $userId : 0,
            'anUserChg' => $userId > 0 ? $userId : 0,
            'adTimeIns' => $now,
            'adTimeChg' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $insertPayload = [];

        foreach ($catalogColumns as $columnName => $columnMeta) {
            if (isset($nonInsertableColumns[$columnName])) {
                continue;
            }

            if (array_key_exists($columnName, $preferredValues)) {
                $insertValue = $preferredValues[$columnName];
            } elseif (($columnMeta['nullable'] ?? true) || ($columnMeta['default'] ?? null) !== null) {
                continue;
            } else {
                $insertValue = self::defaultValueForColumnMeta($columnMeta, $now, $userId);
            }

            if (is_string($insertValue)) {
                $maxLength = (int) ($columnMeta['max_length'] ?? 0);

                if ($maxLength > 0) {
                    $insertValue = mb_substr($insertValue, 0, $maxLength);
                }
            }

            $insertPayload[$columnName] = $insertValue;
        }

        DB::table($itemsTable)->insert($insertPayload);

        $createdRow = DB::table($itemsTable)
            ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$productCode])
            ->first();

        return [
            'created' => true,
            'row' => $createdRow !== null ? (array) $createdRow : $insertPayload,
        ];
    }

    public static function ensureCatalogProductStructure(
        string $productCode,
        array $components,
        float $quantityFactor = 1.0,
        int $userId = 0
    ): array {
        $productCode = trim($productCode);

        if ($productCode === '') {
            throw new \InvalidArgumentException('Product code is required for structure creation.');
        }

        $normalizedComponents = array_values(array_filter(array_map(function ($component, $index) use ($quantityFactor) {
            if (!is_array($component)) {
                return null;
            }

            $componentCode = trim((string) ($component['acIdentChild'] ?? ''));
            if ($componentCode === '') {
                return null;
            }

            $lineNo = self::toIntOrNull($component['anNo'] ?? null);
            $plannedQty = self::toFloatOrNull($component['anPlanQty'] ?? null);
            $resolvedComponentQty = $plannedQty !== null
                ? max(0.0, $plannedQty)
                : max(0.0, $quantityFactor);
            $grossQty = $quantityFactor > 0
                ? ($resolvedComponentQty / $quantityFactor)
                : $resolvedComponentQty;

            return [
                'acIdentChild' => $componentCode,
                'acDescr' => trim((string) ($component['acDescr'] ?? '')),
                'acUM' => strtoupper(substr(trim((string) ($component['acUM'] ?? '')), 0, 3)),
                'acOperationType' => strtoupper(substr(trim((string) ($component['acOperationType'] ?? '')), 0, 1)),
                'anNo' => $lineNo !== null && $lineNo > 0 ? $lineNo : ($index + 1),
                'anGrossQty' => $grossQty,
            ];
        }, $components, array_keys($components)), static function ($component) {
            return is_array($component);
        }));

        if (empty($normalizedComponents)) {
            return [
                'created' => false,
                'count' => 0,
            ];
        }

        $structureTable = self::sourceSchema() . '.' . self::productStructureTable();
        $structureColumns = self::productStructureColumns();
        $nonInsertableColumns = self::productStructureNonInsertableColumns();

        if (empty($structureColumns)) {
            throw new \RuntimeException('Product structure columns could not be resolved.');
        }

        return DB::transaction(function () use (
            $structureTable,
            $structureColumns,
            $nonInsertableColumns,
            $productCode,
            $normalizedComponents,
            $userId
        ) {
            $exists = DB::table($structureTable)
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$productCode])
                ->exists();

            if ($exists) {
                return [
                    'created' => false,
                    'count' => 0,
                ];
            }

            $now = now();
            $nextQId = array_key_exists('anQId', $structureColumns) && !isset($nonInsertableColumns['anQId'])
                ? ((int) (DB::table($structureTable)->max('anQId') ?? 0)) + 1
                : null;
            $rowsToInsert = [];

            foreach ($normalizedComponents as $index => $component) {
                $preferredValues = [
                    'acIdent' => $productCode,
                    'acIdentChild' => (string) ($component['acIdentChild'] ?? ''),
                    'acDescr' => (string) ($component['acDescr'] ?? ''),
                    'acUM' => (string) ($component['acUM'] ?? ''),
                    'acOperationType' => (string) ($component['acOperationType'] ?? ''),
                    'anNo' => (int) ($component['anNo'] ?? ($index + 1)),
                    'anQId' => $nextQId !== null ? ($nextQId + $index) : null,
                    'anGrossQty' => (float) ($component['anGrossQty'] ?? 0),
                    'anQty' => (float) ($component['anGrossQty'] ?? 0),
                    'anNormQty' => (float) ($component['anGrossQty'] ?? 0),
                    'anPlanQty' => (float) ($component['anGrossQty'] ?? 0),
                    'anUserIns' => $userId > 0 ? $userId : 0,
                    'anUserChg' => $userId > 0 ? $userId : 0,
                    'adTimeIns' => $now,
                    'adTimeChg' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $insertPayload = [];

                foreach ($structureColumns as $columnName => $columnMeta) {
                    if (isset($nonInsertableColumns[$columnName])) {
                        continue;
                    }

                    if (array_key_exists($columnName, $preferredValues)) {
                        $insertValue = $preferredValues[$columnName];
                    } elseif (($columnMeta['nullable'] ?? true) || ($columnMeta['default'] ?? null) !== null) {
                        continue;
                    } else {
                        $insertValue = self::defaultValueForColumnMeta($columnMeta, $now, $userId);
                    }

                    if (is_string($insertValue)) {
                        $maxLength = (int) ($columnMeta['max_length'] ?? 0);

                        if ($maxLength > 0) {
                            $insertValue = mb_substr($insertValue, 0, $maxLength);
                        }
                    }

                    $insertPayload[$columnName] = $insertValue;
                }

                $rowsToInsert[] = $insertPayload;
            }

            if (!empty($rowsToInsert)) {
                DB::table($structureTable)->insert($rowsToInsert);
            }

            return [
                'created' => !empty($rowsToInsert),
                'count' => count($rowsToInsert),
            ];
        });
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

    private static function productStructureColumns(): array
    {
        if (self::$productStructureColumnsCache !== null) {
            return self::$productStructureColumnsCache;
        }

        self::$productStructureColumnsCache = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->select('COLUMN_NAME', 'DATA_TYPE', 'CHARACTER_MAXIMUM_LENGTH', 'IS_NULLABLE', 'COLUMN_DEFAULT')
            ->where('TABLE_SCHEMA', self::sourceSchema())
            ->where('TABLE_NAME', self::productStructureTable())
            ->orderBy('ORDINAL_POSITION')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    (string) $row->COLUMN_NAME => [
                        'type' => strtolower((string) ($row->DATA_TYPE ?? '')),
                        'max_length' => is_numeric((string) ($row->CHARACTER_MAXIMUM_LENGTH ?? null))
                            ? (int) $row->CHARACTER_MAXIMUM_LENGTH
                            : null,
                        'nullable' => strtoupper((string) ($row->IS_NULLABLE ?? 'YES')) === 'YES',
                        'default' => $row->COLUMN_DEFAULT,
                    ],
                ];
            })
            ->all();

        return self::$productStructureColumnsCache;
    }

    private static function productStructureNonInsertableColumns(): array
    {
        if (self::$productStructureNonInsertableColumnsCache !== null) {
            return self::$productStructureNonInsertableColumnsCache;
        }

        self::$productStructureNonInsertableColumnsCache = DB::table('sys.columns as c')
            ->join('sys.tables as t', 'c.object_id', '=', 't.object_id')
            ->join('sys.schemas as s', 't.schema_id', '=', 's.schema_id')
            ->where('s.name', self::sourceSchema())
            ->where('t.name', self::productStructureTable())
            ->where(function ($query) {
                $query
                    ->where('c.is_identity', 1)
                    ->orWhere('c.is_computed', 1)
                    ->orWhere('c.generated_always_type', '<>', 0);
            })
            ->pluck('c.name')
            ->map(function ($value) {
                return (string) $value;
            })
            ->flip()
            ->all();

        return self::$productStructureNonInsertableColumnsCache;
    }

    private static function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = trim(str_replace(',', '.', $value));

            if ($normalized === '' || !is_numeric($normalized)) {
                return null;
            }

            return (float) $normalized;
        }

        if (is_numeric((string) $value)) {
            return (float) $value;
        }

        return null;
    }

    private static function toIntOrNull(mixed $value): ?int
    {
        $floatValue = self::toFloatOrNull($value);

        return $floatValue === null ? null : (int) $floatValue;
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

    private static function catalogItemColumns(): array
    {
        if (self::$catalogItemColumnsCache !== null) {
            return self::$catalogItemColumnsCache;
        }

        self::$catalogItemColumnsCache = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->select('COLUMN_NAME', 'DATA_TYPE', 'CHARACTER_MAXIMUM_LENGTH', 'IS_NULLABLE', 'COLUMN_DEFAULT')
            ->where('TABLE_SCHEMA', self::sourceSchema())
            ->where('TABLE_NAME', self::itemsTable())
            ->orderBy('ORDINAL_POSITION')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    (string) $row->COLUMN_NAME => [
                        'type' => strtolower((string) ($row->DATA_TYPE ?? '')),
                        'max_length' => is_numeric((string) ($row->CHARACTER_MAXIMUM_LENGTH ?? null))
                            ? (int) $row->CHARACTER_MAXIMUM_LENGTH
                            : null,
                        'nullable' => strtoupper((string) ($row->IS_NULLABLE ?? 'YES')) === 'YES',
                        'default' => $row->COLUMN_DEFAULT,
                    ],
                ];
            })
            ->all();

        return self::$catalogItemColumnsCache;
    }

    private static function catalogItemNonInsertableColumns(): array
    {
        if (self::$catalogItemNonInsertableColumnsCache !== null) {
            return self::$catalogItemNonInsertableColumnsCache;
        }

        self::$catalogItemNonInsertableColumnsCache = DB::table('sys.columns as c')
            ->join('sys.tables as t', 'c.object_id', '=', 't.object_id')
            ->join('sys.schemas as s', 't.schema_id', '=', 's.schema_id')
            ->where('s.name', self::sourceSchema())
            ->where('t.name', self::itemsTable())
            ->where(function ($query) {
                $query
                    ->where('c.is_identity', 1)
                    ->orWhere('c.is_computed', 1)
                    ->orWhere('c.generated_always_type', '<>', 0);
            })
            ->pluck('c.name')
            ->map(function ($value) {
                return (string) $value;
            })
            ->flip()
            ->all();

        return self::$catalogItemNonInsertableColumnsCache;
    }

    private static function defaultValueForColumnMeta(array $columnMeta, $timestamp, int $userId = 0): mixed
    {
        $dataType = strtolower((string) ($columnMeta['type'] ?? ''));

        if (in_array($dataType, ['char', 'nchar', 'varchar', 'nvarchar', 'text', 'ntext'], true)) {
            return '';
        }

        if (in_array($dataType, ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'real', 'money', 'smallmoney'], true)) {
            return 0;
        }

        if ($dataType === 'bit') {
            return 0;
        }

        if (in_array($dataType, ['date', 'datetime', 'datetime2', 'smalldatetime', 'time'], true)) {
            return $timestamp;
        }

        if ($dataType === 'uniqueidentifier') {
            return (string) Str::uuid();
        }

        if (in_array($dataType, ['binary', 'varbinary', 'image', 'timestamp', 'rowversion'], true)) {
            return null;
        }

        return $userId > 0 ? $userId : null;
    }
}
