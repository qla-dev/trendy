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
    private static ?array $pantheonUsersCache = null;

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

    public static function ensureCatalogProduct(array $attributes, mixed $auditUser = null): array
    {
        $productCode = trim((string) ($attributes['product_code'] ?? $attributes['acIdent'] ?? ''));

        if ($productCode === '') {
            throw new \InvalidArgumentException('Product code is required.');
        }

        $productName = trim((string) ($attributes['product_name'] ?? $attributes['acName'] ?? ''));
        $productUnit = strtoupper(substr(trim((string) ($attributes['product_um'] ?? $attributes['acUM'] ?? '')), 0, 3));
        $productSet = strtoupper(trim((string) ($attributes['product_set'] ?? $attributes['acSetOfItem'] ?? '')));
        $productClassif = trim((string) ($attributes['product_classification'] ?? $attributes['product_classif'] ?? $attributes['acClassif'] ?? ''));
        $auditUserId = self::resolveCatalogAuditUserId($auditUser);
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
        $preferredValues = self::buildCatalogPreferredValues(
            $attributes,
            $productCode,
            $productName,
            $productUnit,
            $productSet,
            $productClassif,
            $nextQId,
            $now,
            $auditUserId
        );
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
                $insertValue = self::defaultValueForColumnMeta($columnMeta, $now, $auditUserId);
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
                'napomena' => trim((string) ($component['napomena'] ?? '')),
                'acUM' => strtoupper(substr(trim((string) ($component['acUM'] ?? '')), 0, 3)),
                'acOperationType' => strtoupper(substr(trim((string) ($component['acOperationType'] ?? '')), 0, 1)),
                'acDelayType' => strtoupper(substr(trim((string) ($component['acDelayType'] ?? '')), 0, 1)),
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
            $noteColumn = self::firstExistingColumn(array_keys($structureColumns), ['acNote', 'acFieldSE']);
            $existingRows = DB::table($structureTable)
                ->select(array_values(array_intersect(['anNo', 'acIdentChild'], array_keys($structureColumns))))
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$productCode])
                ->get()
                ->map(static function ($row) {
                    return (array) $row;
                })
                ->all();
            $existingExactKeys = [];
            $existingComponentKeys = [];
            $usedLineNos = [];

            foreach ($existingRows as $existingRow) {
                $existingLineNo = self::toIntOrNull($existingRow['anNo'] ?? null);
                $existingComponent = strtolower(trim((string) ($existingRow['acIdentChild'] ?? '')));

                if ($existingLineNo !== null && $existingLineNo > 0) {
                    $usedLineNos[$existingLineNo] = true;
                }

                if ($existingComponent === '') {
                    continue;
                }

                if ($existingLineNo !== null && $existingLineNo > 0) {
                    $existingExactKeys[self::productStructureSelectionKey($existingLineNo, $existingComponent)] = true;
                }

                $existingComponentKeys[$existingComponent] = true;
            }

            $now = now();
            $nextQId = array_key_exists('anQId', $structureColumns) && !isset($nonInsertableColumns['anQId'])
                ? ((int) (DB::table($structureTable)->max('anQId') ?? 0)) + 1
                : null;
            $rowsToInsert = [];
            $nextLineNo = self::nextAvailableProductStructureLineNo($usedLineNos);

            foreach ($normalizedComponents as $index => $component) {
                $componentIdent = strtolower(trim((string) ($component['acIdentChild'] ?? '')));
                $preferredLineNo = (int) ($component['anNo'] ?? ($index + 1));

                if ($componentIdent === '') {
                    continue;
                }

                if (
                    $preferredLineNo > 0
                    && isset($existingExactKeys[self::productStructureSelectionKey($preferredLineNo, $componentIdent)])
                ) {
                    continue;
                }

                if (isset($existingComponentKeys[$componentIdent])) {
                    continue;
                }

                $insertLineNo = $preferredLineNo > 0 && !isset($usedLineNos[$preferredLineNo])
                    ? $preferredLineNo
                    : $nextLineNo;

                while (isset($usedLineNos[$insertLineNo])) {
                    $insertLineNo++;
                }

                $usedLineNos[$insertLineNo] = true;
                $existingExactKeys[self::productStructureSelectionKey($insertLineNo, $componentIdent)] = true;
                $existingComponentKeys[$componentIdent] = true;
                $nextLineNo = self::nextAvailableProductStructureLineNo($usedLineNos, $insertLineNo + 1);

                $preferredValues = [
                    'acIdent' => $productCode,
                    'acIdentChild' => (string) ($component['acIdentChild'] ?? ''),
                    'acDescr' => (string) ($component['acDescr'] ?? ''),
                    'acUM' => (string) ($component['acUM'] ?? ''),
                    'acOperationType' => (string) ($component['acOperationType'] ?? ''),
                    'acDelayType' => (string) ($component['acDelayType'] ?? ''),
                    'anNo' => $insertLineNo,
                    'anQId' => $nextQId,
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

                if ($noteColumn !== null) {
                    $preferredValues[$noteColumn] = trim((string) ($component['napomena'] ?? ''));
                }

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

                if ($nextQId !== null) {
                    $nextQId++;
                }
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

    private static function productStructureSelectionKey(int $lineNo, string $componentCode): string
    {
        return $lineNo . '|' . strtolower(trim($componentCode));
    }

    private static function nextAvailableProductStructureLineNo(array $usedLineNos, int $startAt = 1): int
    {
        $lineNo = max(1, $startAt);

        while (isset($usedLineNos[$lineNo])) {
            $lineNo++;
        }

        return $lineNo;
    }

    public static function deleteCatalogProductStructure(string $productCode): array
    {
        $normalizedProductCode = trim($productCode);

        if ($normalizedProductCode === '') {
            throw new \InvalidArgumentException('Product code is required for structure deletion.');
        }

        $structureTable = self::sourceSchema() . '.' . self::productStructureTable();

        return DB::transaction(function () use ($structureTable, $normalizedProductCode) {
            $query = DB::table($structureTable)
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$normalizedProductCode]);

            $count = (int) (clone $query)->count();

            if ($count < 1) {
                return [
                    'deleted' => false,
                    'count' => 0,
                ];
            }

            $deletedCount = (int) $query->delete();

            return [
                'deleted' => $deletedCount > 0,
                'count' => $deletedCount,
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

    private static function buildCatalogPreferredValues(
        array $attributes,
        string $productCode,
        string $productName,
        string $productUnit,
        string $productSet,
        string $productClassif,
        ?int $nextQId,
        mixed $timestamp,
        int $auditUserId
    ): array {
        $defaults = self::catalogProductDefaults();
        $vatCode = self::resolveCatalogStringValue($attributes, ['vat_code', 'acVATCode', 'acVATCodeLow', 'acVATCodeReceive'], (string) ($defaults['vat_code'] ?? 'P1'));
        $currency = self::resolveCatalogStringValue($attributes, ['currency', 'acCurrency', 'currency_code'], (string) ($defaults['currency'] ?? 'KM'));
        $purchaseCurrency = self::resolveCatalogStringValue($attributes, ['purchase_currency', 'acPurchCurr'], (string) ($defaults['purchase_currency'] ?? 'KM'));
        $productionDocType = self::resolveCatalogStringValue($attributes, ['production_doc_type', 'acDocTypeProd'], (string) ($defaults['production_doc_type'] ?? '6000'));
        $unitDimension2 = self::resolveCatalogStringValue($attributes, ['unit_dimension_2', 'acUMDim2'], (string) ($defaults['unit_dimension_2'] ?? 'KO'));
        $vatRate = self::resolveCatalogNumericValue($attributes, ['vat_rate', 'anVAT', 'anVATReceive'], (float) ($defaults['vat_rate'] ?? 17));
        $deliveryDeadline = self::resolveCatalogNumericValue($attributes, ['delivery_deadline', 'anDeliveryDeadline'], (float) ($defaults['delivery_deadline'] ?? 7));
        $allowedInvShort = self::resolveCatalogNumericValue($attributes, ['allowed_inv_short', 'anAllowedInvShort'], (float) ($defaults['allowed_inv_short'] ?? -1));
        $prstOptimalQty = self::resolveCatalogNumericValue($attributes, ['prst_optimal_qty', 'anPrStOptimalQty'], (float) ($defaults['prst_optimal_qty'] ?? 0));
        $prstDailyQty = self::resolveCatalogNumericValue($attributes, ['prst_daily_qty', 'anPrStDailyQty'], (float) ($defaults['prst_daily_qty'] ?? 0));

        return [
            'acIdent' => $productCode,
            'acCode' => $productCode,
            'acName' => $productName !== '' ? $productName : $productCode,
            'acUM' => $productUnit,
            'acSetOfItem' => $productSet,
            'acClassif' => $productClassif,
            'acCurrency' => $currency,
            'acPurchCurr' => $purchaseCurrency,
            'acVATCode' => $vatCode,
            'acVATCodeLow' => $vatCode,
            'acVATCodeReceive' => $vatCode,
            'acDocTypeProd' => $productionDocType,
            'acUMDim2' => $unitDimension2,
            'anQId' => $nextQId,
            'anPLUCode' => 0,
            'anPLUCode2' => 0,
            'anBuyPrice' => 0,
            'anPrice' => 0,
            'anVAT' => $vatRate,
            'anVATReceive' => $vatRate,
            'anDeliveryDeadline' => $deliveryDeadline,
            'anAllowedInvShort' => $allowedInvShort,
            'anPrStOptimalQty' => $prstOptimalQty,
            'anPrStDailyQty' => $prstDailyQty,
            'anUserIns' => $auditUserId > 0 ? $auditUserId : 0,
            'anUserChg' => $auditUserId > 0 ? $auditUserId : 0,
            'adTimeIns' => $timestamp,
            'adTimeChg' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
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

    private static function catalogProductDefaults(): array
    {
        $defaults = config('workorders.catalog_product_defaults', []);

        return is_array($defaults) ? $defaults : [];
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

    private static function resolveCatalogStringValue(array $attributes, array $keys, string $fallback = ''): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $attributes)) {
                continue;
            }

            $value = trim((string) ($attributes[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return trim($fallback);
    }

    private static function resolveCatalogNumericValue(array $attributes, array $keys, float $fallback): float
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $attributes)) {
                continue;
            }

            $value = $attributes[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric((string) $value)) {
                return (float) $value;
            }
        }

        return $fallback;
    }

    private static function resolveCatalogAuditUserId(mixed $auditUser): int
    {
        $explicitPantheonId = self::extractAuditUserIntegerValue($auditUser, [
            'pantheon_user_id',
            'pantheonUserId',
            'anUserId',
        ]);

        if ($explicitPantheonId > 0 && self::pantheonUserIdExists($explicitPantheonId)) {
            return $explicitPantheonId;
        }

        $numericUserId = self::extractAuditUserIntegerValue($auditUser, ['id', 'user_id', 'anUserIns']);

        if ($numericUserId > 0 && self::pantheonUserIdExists($numericUserId)) {
            return $numericUserId;
        }

        $mappedUserId = self::resolveMappedPantheonUserId($auditUser);

        if ($mappedUserId > 0) {
            return $mappedUserId;
        }

        return $numericUserId > 0 ? $numericUserId : 0;
    }

    private static function resolveMappedPantheonUserId(mixed $auditUser): int
    {
        $configuredMap = config('workorders.pantheon_user_map', []);
        $normalizedConfiguredMap = [];

        if (is_array($configuredMap)) {
            foreach ($configuredMap as $key => $value) {
                $normalizedKey = self::normalizePantheonUserLookupValue((string) $key);
                $resolvedValue = is_numeric((string) $value) ? (int) $value : 0;

                if ($normalizedKey !== '' && $resolvedValue > 0) {
                    $normalizedConfiguredMap[$normalizedKey] = $resolvedValue;
                }
            }
        }

        foreach (self::buildAuditUserLookupCandidates($auditUser) as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if (array_key_exists($candidate, $normalizedConfiguredMap)) {
                return (int) $normalizedConfiguredMap[$candidate];
            }

            foreach (self::pantheonUsers() as $pantheonUser) {
                if ($candidate === (string) ($pantheonUser['normalized_user_code'] ?? '')) {
                    return (int) ($pantheonUser['id'] ?? 0);
                }

                if ($candidate === (string) ($pantheonUser['normalized_title'] ?? '')) {
                    return (int) ($pantheonUser['id'] ?? 0);
                }
            }
        }

        return 0;
    }

    private static function buildAuditUserLookupCandidates(mixed $auditUser): array
    {
        $rawCandidates = [];

        foreach ([
            'pantheon_username',
            'pantheon_user_code',
            'username',
            'name',
            'email',
            'acUserId',
            'acTitle',
        ] as $key) {
            $value = self::extractAuditUserValue($auditUser, $key);

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $stringValue = trim((string) ($value ?? ''));

            if ($stringValue === '') {
                continue;
            }

            $rawCandidates[] = $stringValue;

            if (str_contains($stringValue, '@')) {
                $localPart = trim((string) strstr($stringValue, '@', true));

                if ($localPart !== '') {
                    $rawCandidates[] = $localPart;
                }
            }
        }

        $normalizedCandidates = [];

        foreach ($rawCandidates as $candidate) {
            $normalizedCandidate = self::normalizePantheonUserLookupValue($candidate);

            if ($normalizedCandidate !== '') {
                $normalizedCandidates[$normalizedCandidate] = true;
            }
        }

        return array_keys($normalizedCandidates);
    }

    private static function normalizePantheonUserLookupValue(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private static function pantheonUserIdExists(int $pantheonUserId): bool
    {
        if ($pantheonUserId < 1) {
            return false;
        }

        foreach (self::pantheonUsers() as $pantheonUser) {
            if ((int) ($pantheonUser['id'] ?? 0) === $pantheonUserId) {
                return true;
            }
        }

        return false;
    }

    private static function pantheonUsers(): array
    {
        if (self::$pantheonUsersCache !== null) {
            return self::$pantheonUsersCache;
        }

        self::$pantheonUsersCache = DB::connection('sqlsrv')
            ->table(self::sourceSchema() . '.tPA_User')
            ->select('anUserId', 'acUserId', 'acTitle', 'acActive')
            ->get()
            ->map(function ($row) {
                $userCode = trim((string) ($row->acUserId ?? ''));
                $title = trim((string) ($row->acTitle ?? ''));

                return [
                    'id' => (int) ($row->anUserId ?? 0),
                    'user_code' => $userCode,
                    'title' => $title,
                    'active' => strtoupper(trim((string) ($row->acActive ?? 'T'))) !== 'F',
                    'normalized_user_code' => self::normalizePantheonUserLookupValue($userCode),
                    'normalized_title' => self::normalizePantheonUserLookupValue($title),
                ];
            })
            ->filter(function (array $row): bool {
                return (int) ($row['id'] ?? 0) > 0 && (bool) ($row['active'] ?? true);
            })
            ->values()
            ->all();

        return self::$pantheonUsersCache;
    }

    private static function extractAuditUserIntegerValue(mixed $auditUser, array $keys): int
    {
        foreach ($keys as $key) {
            $value = self::extractAuditUserValue($auditUser, $key);

            if (!is_numeric((string) $value)) {
                continue;
            }

            $resolvedValue = (int) $value;

            if ($resolvedValue > 0) {
                return $resolvedValue;
            }
        }

        if (
            is_int($auditUser)
            || is_float($auditUser)
            || (is_string($auditUser) && is_numeric(trim($auditUser)))
        ) {
            $resolvedValue = (int) $auditUser;

            return $resolvedValue > 0 ? $resolvedValue : 0;
        }

        return 0;
    }

    private static function extractAuditUserValue(mixed $auditUser, string $key): mixed
    {
        if (is_array($auditUser)) {
            return $auditUser[$key] ?? null;
        }

        if (is_object($auditUser)) {
            return $auditUser->{$key} ?? null;
        }

        return null;
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

    private static function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
