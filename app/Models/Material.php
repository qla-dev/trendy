<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Material extends Model
{
    use HasFactory;

    private static ?array $catalogItemColumnsCache = null;
    private static ?array $catalogItemNonInsertableColumnsCache = null;

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
        $itemsTable = self::sourceSchema() . '.' . self::itemsTable() . ' as i';
        $stockTable = self::sourceSchema() . '.' . self::stockTable() . ' as s';
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

        self::applyLikeAny($query, ['i.acIdent', 'i.acName'], $search);

        $codeExpr = "LTRIM(RTRIM(ISNULL(i.acIdent, '')))";
        $rows = $query
            ->selectRaw("$codeExpr as material_code")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acName, ''))) as material_name")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acUM, ''))) as material_um")
            ->selectRaw("MIN(LTRIM(RTRIM(ISNULL(s.acWarehouse, '')))) as material_warehouse")
            ->selectRaw("COALESCE(SUM(CAST(ISNULL(s.anStock, 0) as float)), 0) as material_qty")
            ->groupBy('i.acIdent', 'i.acName', 'i.acUM')
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
                'acWarehouse' => trim((string) ($row['material_warehouse'] ?? '')),
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
        string $sortDir = 'asc',
        string $warehouseFilter = ''
    ): array {
        $resolvedLimit = self::resolveScannerLimit($limit);
        $resolvedOffset = max(0, (int) $offset);
        $resolvedSortDir = strtolower(trim($sortDir)) === 'desc' ? 'desc' : 'asc';
        $query = DB::query()->fromSub(
            self::barcodeGeneratorBaseQuery($search, $materialsSets, $warehouseFilter),
            'm'
        );

        switch (trim($sortBy)) {
            case 'material_name':
                $query
                    ->orderByRaw("CASE WHEN LTRIM(RTRIM(ISNULL(m.material_name, ''))) = '' THEN 1 ELSE 0 END ASC")
                    ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(m.material_name, '')))) {$resolvedSortDir}")
                    ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(m.material_code, '')))) ASC");
                break;
            case 'material_warehouse':
                $query
                    ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(m.material_warehouse, '')))) {$resolvedSortDir}")
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
                    'material_set' => trim((string) ($row->material_set ?? '')),
                    'material_warehouse' => trim((string) ($row->material_warehouse ?? '')),
                    'material_qty' => $materialQty,
                    'barcode_value' => trim((string) ($row->material_code ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    public static function barcodeGeneratorTotalCount(
        array $materialsSets = [],
        string $warehouseFilter = ''
    ): int
    {
        return (int) DB::query()
            ->fromSub(self::barcodeGeneratorBaseQuery('', $materialsSets, $warehouseFilter), 'm')
            ->count();
    }

    public static function barcodeGeneratorFilteredCount(
        string $search = '',
        array $materialsSets = [],
        string $warehouseFilter = ''
    ): int {
        return (int) DB::query()
            ->fromSub(self::barcodeGeneratorBaseQuery($search, $materialsSets, $warehouseFilter), 'm')
            ->count();
    }

    public static function stockWarehouseOptions(array $materialsSets = []): array
    {
        $normalizedSets = self::normalizeMaterialsSets($materialsSets);
        $stockTable = self::sourceSchema() . '.' . self::stockTable() . ' as s';
        $itemsTable = self::sourceSchema() . '.' . self::itemsTable() . ' as i';
        $query = DB::table($stockTable)
            ->join($itemsTable, function ($join) {
                $join->whereRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) = LTRIM(RTRIM(ISNULL(s.acIdent, '')))");
            })
            ->whereRaw("LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) <> ''")
            ->whereRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) <> ''");

        if (!empty($normalizedSets)) {
            $query->whereIn(
                DB::raw("LTRIM(RTRIM(ISNULL(i.acSetOfItem, '')))"),
                $normalizedSets
            );
        }

        return $query
            ->selectRaw("LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) as warehouse_name")
            ->groupBy('s.acWarehouse')
            ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(s.acWarehouse, '')))) ASC")
            ->pluck('warehouse_name')
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->filter(function ($value) {
                return $value !== '';
            })
            ->values()
            ->all();
    }

    public static function materialUnitOptions(array $materialsSets = []): array
    {
        $normalizedSets = self::normalizeMaterialsSets($materialsSets);
        $itemsTable = self::sourceSchema() . '.' . self::itemsTable() . ' as i';
        $query = DB::table($itemsTable)
            ->whereRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) <> ''")
            ->whereRaw("LTRIM(RTRIM(ISNULL(i.acUM, ''))) <> ''");

        if (!empty($normalizedSets)) {
            $query->whereIn(
                DB::raw("LTRIM(RTRIM(ISNULL(i.acSetOfItem, '')))"),
                $normalizedSets
            );
        }

        return $query
            ->selectRaw("UPPER(LTRIM(RTRIM(ISNULL(i.acUM, '')))) as material_um")
            ->groupBy('i.acUM')
            ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(i.acUM, '')))) ASC")
            ->pluck('material_um')
            ->map(function ($value) {
                return strtoupper(substr(trim((string) $value), 0, 3));
            })
            ->filter(function ($value) {
                return $value !== '';
            })
            ->values()
            ->all();
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
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acSupplier, ''))) as material_supplier")
            ->selectRaw("CAST(ISNULL(i.anQId, 0) as bigint) as material_qid")
            ->selectRaw("CAST(ISNULL(i.anBuyPrice, 0) as float) as material_buy_price")
            ->selectRaw("CAST(ISNULL(i.anPrice, 0) as float) as material_price")
            ->selectRaw("CAST(ISNULL(i.anVAT, 0) as float) as material_vat_rate")
            ->selectRaw("CAST(ISNULL(i.anDeliveryDeadline, 0) as int) as material_delivery_deadline")
            ->selectRaw("CONVERT(varchar(19), i.adTimeChg, 120) as material_changed_at")
            ->selectRaw("COALESCE(SUM(CAST(ISNULL(s.anStock, 0) as float)), 0) as material_qty")
            ->groupBy(
                'i.acIdent',
                'i.acName',
                'i.acUM',
                'i.acCode',
                'i.acSetOfItem',
                'i.acSupplier',
                'i.anQId',
                'i.anBuyPrice',
                'i.anPrice',
                'i.anVAT',
                'i.anDeliveryDeadline',
                'i.adTimeChg'
            )
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
            'material_supplier' => trim((string) ($row->material_supplier ?? '')),
            'material_qid' => is_numeric((string) ($row->material_qid ?? null))
                ? (int) $row->material_qid
                : null,
            'material_buy_price' => is_numeric((string) ($row->material_buy_price ?? null))
                ? (float) $row->material_buy_price
                : null,
            'material_price' => is_numeric((string) ($row->material_price ?? null))
                ? (float) $row->material_price
                : null,
            'material_vat_rate' => is_numeric((string) ($row->material_vat_rate ?? null))
                ? (float) $row->material_vat_rate
                : null,
            'material_delivery_deadline' => is_numeric((string) ($row->material_delivery_deadline ?? null))
                ? (int) $row->material_delivery_deadline
                : null,
            'material_changed_at' => trim((string) ($row->material_changed_at ?? '')),
            'material_qty' => $materialQty,
        ];
    }

    public static function scannerTotalCount(array $materialsSets = []): int
    {
        $normalizedSets = self::normalizeMaterialsSets($materialsSets);

        $itemsTable = self::sourceSchema() . '.' . self::itemsTable() . ' as i';
        $stockTable = self::sourceSchema() . '.' . self::stockTable() . ' as s';
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

        $grouped = $query
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) as material_code")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acName, ''))) as material_name")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acUM, ''))) as material_um")
            ->groupBy('i.acIdent', 'i.acName', 'i.acUM');

        return (int) DB::query()->fromSub($grouped, 'm')->count();
    }

    public static function bulkAdjustStock(array $items, int $userId = 0, string $preferredWarehouse = ''): array
    {
        $normalizedItems = self::normalizeStockAdjustments($items);

        if (empty($normalizedItems)) {
            return [];
        }

        $stockTable = self::sourceSchema() . '.' . self::stockTable();
        $normalizedPreferredWarehouse = trim($preferredWarehouse);

        return DB::transaction(function () use ($normalizedItems, $userId, $stockTable, $normalizedPreferredWarehouse) {
            $codes = array_values(array_unique(array_map(function (array $item) {
                return (string) ($item['material_code'] ?? '');
            }, $normalizedItems)));

            $stockRows = DB::table($stockTable)
                ->selectRaw("CAST(ISNULL(anQId, 0) as int) as stock_qid")
                ->selectRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) as material_code")
                ->selectRaw("LTRIM(RTRIM(ISNULL(acWarehouse, ''))) as warehouse")
                ->selectRaw("CAST(ISNULL(anStock, 0) as float) as stock_qty")
                ->whereIn(DB::raw("LTRIM(RTRIM(ISNULL(acIdent, '')))"), $codes)
                ->orderByRaw("UPPER(LTRIM(RTRIM(ISNULL(acIdent, '')))) ASC")
                ->orderByRaw("CASE WHEN CAST(ISNULL(anStock, 0) as float) > 0 THEN 0 ELSE 1 END ASC")
                ->orderByRaw("CAST(ISNULL(anStock, 0) as float) DESC")
                ->orderBy('anQId')
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->values()
                ->all();

            $rowsByCode = [];
            foreach ($stockRows as $row) {
                $codeKey = strtolower(trim((string) ($row['material_code'] ?? '')));
                if ($codeKey === '') {
                    continue;
                }

                if (!array_key_exists($codeKey, $rowsByCode)) {
                    $rowsByCode[$codeKey] = [];
                }

                $rowsByCode[$codeKey][] = $row;
            }

            $defaultWarehouse = trim((string) DB::table($stockTable)->value('acWarehouse'));
            $updatesByQId = [];
            $insertRows = [];
            $results = [];
            $now = now();

            foreach ($normalizedItems as $item) {
                $materialCode = trim((string) ($item['material_code'] ?? ''));
                $codeKey = strtolower($materialCode);
                $materialRows = $rowsByCode[$codeKey] ?? [];
                $currentTotal = 0.0;

                foreach ($materialRows as $materialRow) {
                    $currentTotal += is_numeric((string) ($materialRow['stock_qty'] ?? null))
                        ? (float) $materialRow['stock_qty']
                        : 0.0;
                }

                $deltaValue = is_numeric((string) ($item['value'] ?? null))
                    ? (float) $item['value']
                    : null;
                $hasNewStockValue = is_numeric((string) ($item['new_stock_value'] ?? null));
                $targetTotal = $hasNewStockValue
                    ? (float) $item['new_stock_value']
                    : ($currentTotal - (float) ($deltaValue ?? 0));
                $delta = $targetTotal - $currentTotal;
                $warehouse = trim((string) ($item['warehouse'] ?? ''));

                if (empty($materialRows)) {
                    $resolvedWarehouse = $warehouse !== ''
                        ? $warehouse
                        : ($normalizedPreferredWarehouse !== '' ? $normalizedPreferredWarehouse : $defaultWarehouse);

                    if ($resolvedWarehouse === '') {
                        $results[] = [
                            'action' => 'skipped',
                            'material_code' => $materialCode,
                            'value' => $deltaValue,
                            'current_stock_value' => $currentTotal,
                            'new_stock_value' => $targetTotal,
                            'reason' => 'warehouse_missing',
                        ];
                        continue;
                    }

                    if (abs($targetTotal) <= 0.000001) {
                        $results[] = [
                            'action' => 'unchanged',
                            'material_code' => $materialCode,
                            'value' => $deltaValue,
                            'current_stock_value' => $currentTotal,
                            'new_stock_value' => $targetTotal,
                            'warehouse' => $resolvedWarehouse,
                        ];
                        continue;
                    }

                    $insertRows[] = [
                        'acWarehouse' => $resolvedWarehouse,
                        'acIdent' => $materialCode,
                        'anStock' => $targetTotal,
                        'anValue' => 0,
                        'anLastPrice' => 0,
                        'anReserved' => 0,
                        'adTimeChg' => $now,
                        'adTimeIns' => $now,
                        'anUserIns' => $userId > 0 ? $userId : null,
                        'anUserChg' => $userId > 0 ? $userId : null,
                        'anMinStock' => -1,
                        'anOptStock' => -1,
                        'anMaxStock' => -1,
                    ];

                    $results[] = [
                        'action' => 'inserted',
                        'material_code' => $materialCode,
                        'value' => $deltaValue,
                        'current_stock_value' => $currentTotal,
                        'new_stock_value' => $targetTotal,
                        'stock_qid' => null,
                        'warehouse' => $resolvedWarehouse,
                    ];
                    continue;
                }

                $primaryRow = self::selectPrimaryStockRow(
                    $materialRows,
                    $warehouse !== '' ? $warehouse : $normalizedPreferredWarehouse
                );

                if ($primaryRow === null) {
                    $results[] = [
                        'action' => 'skipped',
                        'material_code' => $materialCode,
                        'value' => $deltaValue,
                        'current_stock_value' => $currentTotal,
                        'new_stock_value' => $targetTotal,
                        'reason' => 'stock_row_missing',
                    ];
                    continue;
                }

                $primaryStock = is_numeric((string) ($primaryRow['stock_qty'] ?? null))
                    ? (float) $primaryRow['stock_qty']
                    : 0.0;
                $updatedPrimaryStock = $primaryStock + $delta;
                $stockQId = (int) ($primaryRow['stock_qid'] ?? 0);

                if ($stockQId > 0 && abs($updatedPrimaryStock - $primaryStock) > 0.000001) {
                    $updatesByQId[$stockQId] = $updatedPrimaryStock;
                }

                $results[] = [
                    'action' => abs($updatedPrimaryStock - $primaryStock) > 0.000001 ? 'updated' : 'unchanged',
                    'material_code' => $materialCode,
                    'value' => $deltaValue,
                    'current_stock_value' => $currentTotal,
                    'new_stock_value' => $targetTotal,
                    'stock_qid' => $stockQId > 0 ? $stockQId : null,
                    'warehouse' => trim((string) ($primaryRow['warehouse'] ?? '')),
                    'previous_row_stock_value' => $primaryStock,
                    'row_stock_value' => $updatedPrimaryStock,
                ];
            }

            if (!empty($updatesByQId)) {
                self::bulkUpdateStockRows($updatesByQId, $userId, $now);
            }

            if (!empty($insertRows)) {
                DB::table($stockTable)->insert($insertRows);
            }

            return $results;
        });
    }

    public static function createCatalogMaterial(array $payload, int $userId = 0): array
    {
        $materialCode = trim((string) ($payload['material_code'] ?? ''));
        $materialName = trim((string) ($payload['material_name'] ?? ''));
        $materialUnit = strtoupper(substr(trim((string) ($payload['material_um'] ?? '')), 0, 3));
        $materialWarehouse = trim((string) ($payload['material_warehouse'] ?? $payload['warehouse'] ?? ''));
        $materialSet = trim((string) ($payload['material_set'] ?? '011'));
        $materialQty = self::toNullableFloat($payload['material_qty'] ?? $payload['stock_qty'] ?? null) ?? 0.0;

        if ($materialCode === '' || $materialName === '' || $materialUnit === '') {
            throw new \InvalidArgumentException('Materijal mora imati šifru, naziv i MJ.');
        }

        $itemsTable = self::sourceSchema() . '.' . self::itemsTable();
        $catalogColumns = self::catalogItemColumns();
        $nonInsertableColumns = self::catalogItemNonInsertableColumns();
        $now = now();

        return DB::transaction(function () use (
            $itemsTable,
            $materialCode,
            $materialName,
            $materialUnit,
            $materialWarehouse,
            $materialSet,
            $materialQty,
            $userId,
            $catalogColumns,
            $nonInsertableColumns,
            $now
        ) {
            $exists = DB::table($itemsTable)
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$materialCode])
                ->exists();

            if ($exists) {
                throw new \RuntimeException('Materijal sa ovom šifrom već postoji.');
            }

            $nextQId = array_key_exists('anQId', $catalogColumns)
                ? ((int) (DB::table($itemsTable)->max('anQId') ?? 0)) + 1
                : null;

            $preferredValues = [
                'acIdent' => $materialCode,
                'acCode' => $materialCode,
                'acName' => $materialName,
                'acUM' => $materialUnit,
                'acSetOfItem' => $materialSet,
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

            $stockAdjustments = self::bulkAdjustStock([
                [
                    'material_code' => $materialCode,
                    'warehouse' => $materialWarehouse,
                    'new_stock_value' => $materialQty,
                ],
            ], $userId, $materialWarehouse);

            return [
                'material_code' => $materialCode,
                'material_name' => $materialName,
                'material_um' => $materialUnit,
                'material_set' => $materialSet,
                'material_warehouse' => $materialWarehouse,
                'material_qty' => $materialQty,
                'barcode_value' => $materialCode,
                'stock_adjustments' => $stockAdjustments,
            ];
        });
    }

    public static function deleteCatalogMaterial(string $materialCode): array
    {
        $materialCode = trim($materialCode);

        if ($materialCode === '') {
            throw new \InvalidArgumentException('Materijal mora imati šifru za brisanje.');
        }

        $itemsTable = self::sourceSchema() . '.' . self::itemsTable();
        $stockTable = self::sourceSchema() . '.' . self::stockTable();

        return DB::transaction(function () use ($itemsTable, $stockTable, $materialCode) {
            $materialRow = DB::table($itemsTable)
                ->selectRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) as material_code")
                ->selectRaw("LTRIM(RTRIM(ISNULL(acName, ''))) as material_name")
                ->selectRaw("LTRIM(RTRIM(ISNULL(acUM, ''))) as material_um")
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$materialCode])
                ->first();

            if ($materialRow === null) {
                throw new \RuntimeException('Materijal sa ovom šifrom nije pronađen.');
            }

            $deletedStockRows = DB::table($stockTable)
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$materialCode])
                ->delete();

            $deletedItemRows = DB::table($itemsTable)
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) = ?", [$materialCode])
                ->delete();

            if ($deletedItemRows < 1) {
                throw new \RuntimeException('Materijal nije moguće obrisati.');
            }

            return [
                'material_code' => trim((string) ($materialRow->material_code ?? '')),
                'material_name' => trim((string) ($materialRow->material_name ?? '')),
                'material_um' => trim((string) ($materialRow->material_um ?? '')),
                'deleted_stock_rows' => (int) $deletedStockRows,
                'deleted_item_rows' => (int) $deletedItemRows,
            ];
        });
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
        array $materialsSets = [],
        string $warehouseFilter = ''
    ): Builder {
        $normalizedSets = self::normalizeMaterialsSets($materialsSets);
        $normalizedWarehouseFilter = trim($warehouseFilter);
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

        if ($normalizedWarehouseFilter !== '') {
            $query->whereRaw(
                "LTRIM(RTRIM(ISNULL(s.acWarehouse, ''))) = ?",
                [$normalizedWarehouseFilter]
            );
        }

        self::applyLikeAny($query, ['i.acIdent', 'i.acName', 'i.acCode'], $search);

        return $query
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acIdent, ''))) as material_code")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acName, ''))) as material_name")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acUM, ''))) as material_um")
            ->selectRaw("LTRIM(RTRIM(ISNULL(i.acSetOfItem, ''))) as material_set")
            ->selectRaw("MIN(LTRIM(RTRIM(ISNULL(s.acWarehouse, '')))) as material_warehouse")
            ->selectRaw("COALESCE(SUM(CAST(ISNULL(s.anStock, 0) as float)), 0) as material_qty")
            ->groupBy('i.acIdent', 'i.acName', 'i.acUM', 'i.acSetOfItem');
    }

    private static function normalizeMaterialsSets(array $materialsSets = []): array
    {
        return array_values(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $materialsSets), function ($value) {
            return $value !== '';
        }));
    }

    private static function normalizeStockAdjustments(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $materialCode = trim((string) ($item['material_code'] ?? $item['code'] ?? $item['acIdent'] ?? ''));
            if ($materialCode === '') {
                continue;
            }

            $value = self::toNullableFloat($item['value'] ?? null);
            $newStockValue = self::toNullableFloat($item['new_stock_value'] ?? $item['newStockValue'] ?? null);
            if ($value === null && $newStockValue === null) {
                continue;
            }

            $key = strtolower($materialCode);
            if (!array_key_exists($key, $normalized)) {
                $normalized[$key] = [
                    'material_code' => $materialCode,
                    'value' => $value,
                    'new_stock_value' => $newStockValue,
                    'warehouse' => trim((string) ($item['warehouse'] ?? '')),
                ];
                continue;
            }

            if ($value !== null) {
                $normalized[$key]['value'] = (float) (($normalized[$key]['value'] ?? 0) + $value);
            }

            if ($newStockValue !== null) {
                $normalized[$key]['new_stock_value'] = $newStockValue;
            }

            $warehouse = trim((string) ($item['warehouse'] ?? ''));
            if ($warehouse !== '') {
                $normalized[$key]['warehouse'] = $warehouse;
            }
        }

        return array_values($normalized);
    }

    private static function selectPrimaryStockRow(array $rows, string $preferredWarehouse = ''): ?array
    {
        if (empty($rows)) {
            return null;
        }

        $normalizedPreferredWarehouse = strtolower(trim($preferredWarehouse));
        if ($normalizedPreferredWarehouse !== '') {
            foreach ($rows as $row) {
                $rowWarehouse = strtolower(trim((string) ($row['warehouse'] ?? '')));
                if ($rowWarehouse === $normalizedPreferredWarehouse) {
                    return $row;
                }
            }
        }

        return $rows[0] ?? null;
    }

    private static function bulkUpdateStockRows(array $updatesByQId, int $userId, $timestamp): void
    {
        if (empty($updatesByQId)) {
            return;
        }

        $stockTable = self::sourceSchema() . '.' . self::stockTable();
        $stockIds = array_map('intval', array_keys($updatesByQId));
        sort($stockIds);

        $caseParts = [];
        $bindings = [];

        foreach ($stockIds as $stockId) {
            $caseParts[] = 'WHEN ' . $stockId . ' THEN ?';
            $bindings[] = (float) $updatesByQId[$stockId];
        }

        $setParts = [
            '[anStock] = CASE [anQId] ' . implode(' ', $caseParts) . ' ELSE [anStock] END',
            '[adTimeChg] = ?',
        ];
        $bindings[] = $timestamp;

        if ($userId > 0) {
            $setParts[] = '[anUserChg] = ?';
            $bindings[] = $userId;
        }

        DB::update(
            'UPDATE ' . $stockTable . ' SET ' . implode(', ', $setParts) .
            ' WHERE [anQId] IN (' . implode(', ', $stockIds) . ')',
            $bindings
        );
    }

    private static function toNullableFloat($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private static function normalizeScannerBarcode(string $barcode): string
    {
        $value = strtoupper(trim($barcode));

        if ($value === '') {
            return '';
        }

        return preg_replace('/[^0-9A-Z]/', '', $value) ?? '';
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
