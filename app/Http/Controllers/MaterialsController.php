<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class MaterialsController extends Controller
{
    private const MATERIALS_SETS = [
        '011',
        '020',
        '021',
        '022',
        '023',
        '024',
        '025',
        '026',
        '101',
        '102',
        '103',
        '104',
        '10A',
        '111',
        '120',
        '134',
        '13S',
        '13X',
        'H1',
    ];

    private const DEFAULT_MATERIAL_SET = '011';

    public function scannerIndex(Request $request, string $id): JsonResponse
    {
        try {
            $search = trim((string) $request->query('q', ''));
            $limit = $this->resolveLimit((int) $request->integer('limit', 100));
            $offset = max(0, (int) $request->integer('offset', 0));
            $materials = Material::scannerList(
                $search,
                $limit,
                self::MATERIALS_SETS,
                $offset
            );
            $totalAll = Material::scannerTotalCount(self::MATERIALS_SETS);

            return response()->json([
                'data' => $materials,
                'meta' => [
                    'count' => count($materials),
                    'total_all' => $totalAll,
                    'limit' => $limit,
                    'offset' => $offset,
                    'search' => $search,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Materials scanner list failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'table' => Material::scannerSourceTable(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greška pri učitavanju materijala.',
            ], 500);
        }
    }

    public function stockIndex(Request $request)
    {
        $pageConfigs = ['pageHeader' => false];
        $canCreateMaterial = $this->canCreateMaterial($request->user());
        $canDeleteMaterial = $this->canDeleteMaterial($request->user());
        $shouldAutoOpenCreate = strtolower(trim((string) $request->query('open', ''))) === 'create-material';
        $warehouseOptions = Material::stockWarehouseOptions(self::MATERIALS_SETS);
        $unitOptions = Material::materialUnitOptions(self::MATERIALS_SETS);

        return view('content.apps.materials.app-material', [
            'pageConfigs' => $pageConfigs,
            'stockTableUrl' => route('app-stock-data'),
            'stockCreateUrl' => route('app-materials-store'),
            'stockDeleteUrl' => route('app-materials-destroy'),
            'canCreateMaterial' => $canCreateMaterial,
            'canDeleteMaterial' => $canDeleteMaterial,
            'shouldAutoOpenCreateMaterial' => $canCreateMaterial && $shouldAutoOpenCreate,
            'materialSetOptions' => self::MATERIALS_SETS,
            'stockWarehouseOptions' => $warehouseOptions,
            'materialUnitOptions' => $unitOptions,
            'defaultMaterialSet' => self::DEFAULT_MATERIAL_SET,
        ]);
    }

    public function stockData(Request $request): JsonResponse
    {
        try {
            $draw = (int) $request->input('draw', 0);
            $start = max(0, (int) $request->integer('start', 0));
            $length = $request->filled('length')
                ? (int) $request->input('length')
                : (int) $request->integer('limit', 25);
            $limit = $this->resolveTableLimit($length);
            $search = trim((string) data_get($request->all(), 'search.value', $request->input('search', '')));
            $sortBy = trim((string) $request->input('sort_by', 'material_code'));
            $sortDir = trim((string) $request->input('sort_dir', 'asc'));
            $warehouseFilter = trim((string) $request->input('warehouse', ''));

            $materials = Material::barcodeGeneratorList(
                $search,
                $limit,
                self::MATERIALS_SETS,
                $start,
                $sortBy,
                $sortDir,
                $warehouseFilter
            );

            return response()->json([
                'draw' => $draw,
                'data' => $materials,
                'recordsTotal' => Material::barcodeGeneratorTotalCount(self::MATERIALS_SETS, $warehouseFilter),
                'recordsFiltered' => Material::barcodeGeneratorFilteredCount($search, self::MATERIALS_SETS, $warehouseFilter),
            ]);
        } catch (Throwable $exception) {
            Log::error('Materials barcode generator list failed.', [
                'connection' => config('database.default'),
                'table' => Material::scannerSourceTable(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greška pri učitavanju materijala za barcode generator.',
            ], 500);
        }
    }

    public function barcodeGenerator()
    {
        return redirect()->route('app-stock');
    }

    public function barcodeGeneratorData(Request $request): JsonResponse
    {
        return $this->stockData($request);
    }

    public function storeMaterial(Request $request): JsonResponse
    {
        if (!$this->canCreateMaterial($request->user())) {
            return response()->json([
                'message' => 'Nemate dozvolu za dodavanje novog materijala.',
            ], 403);
        }

        $warehouseOptions = Material::stockWarehouseOptions(self::MATERIALS_SETS);
        $unitOptions = Material::materialUnitOptions(self::MATERIALS_SETS);
        $unitRules = ['required', 'string', 'max:3'];
        $warehouseRules = ['required', 'string', 'max:64'];

        if (!empty($unitOptions)) {
            $unitRules[] = Rule::in($unitOptions);
        }

        if (!empty($warehouseOptions)) {
            $warehouseRules[] = Rule::in($warehouseOptions);
        }

        $validator = Validator::make($request->all(), [
            'material_code' => ['required', 'string', 'max:64'],
            'material_name' => ['required', 'string', 'max:255'],
            'material_um' => $unitRules,
            'material_warehouse' => $warehouseRules,
            'material_qty' => ['nullable', 'numeric'],
            'material_set' => ['nullable', 'string', Rule::in(self::MATERIALS_SETS)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravni podaci za novi materijal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $material = Material::createCatalogMaterial([
                'material_code' => trim((string) ($validated['material_code'] ?? '')),
                'material_name' => trim((string) ($validated['material_name'] ?? '')),
                'material_um' => strtoupper(substr(trim((string) ($validated['material_um'] ?? '')), 0, 3)),
                'material_warehouse' => trim((string) ($validated['material_warehouse'] ?? '')),
                'material_qty' => $validated['material_qty'] ?? 0,
                'material_set' => trim((string) ($validated['material_set'] ?? self::DEFAULT_MATERIAL_SET)),
            ], (int) ($request->user()->id ?? 0));

            return response()->json([
                'message' => 'Materijal je uspješno dodan.',
                'data' => $material,
            ]);
        } catch (Throwable $exception) {
            Log::error('Material create failed.', [
                'connection' => config('database.default'),
                'table' => Material::scannerSourceTable(),
                'material_code' => trim((string) ($validated['material_code'] ?? '')),
                'message' => $exception->getMessage(),
            ]);

            $isUserFacingException = $exception instanceof \RuntimeException || $exception instanceof \InvalidArgumentException;

            return response()->json([
                'message' => $isUserFacingException
                    ? $exception->getMessage()
                    : 'Greška pri dodavanju novog materijala.',
            ], $isUserFacingException ? 422 : 500);
        }
    }

    public function destroyMaterial(Request $request): JsonResponse
    {
        if (!$this->canDeleteMaterial($request->user())) {
            return response()->json([
                'message' => 'Nemate dozvolu za brisanje materijala.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'material_code' => ['required', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravni podaci za brisanje materijala.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $materialCode = trim((string) ($validated['material_code'] ?? ''));

        try {
            $result = Material::deleteCatalogMaterial($materialCode);

            return response()->json([
                'message' => 'Materijal je uspješno obrisan.',
                'data' => $result,
            ]);
        } catch (Throwable $exception) {
            Log::error('Material delete failed.', [
                'connection' => config('database.default'),
                'table' => Material::scannerSourceTable(),
                'material_code' => $materialCode,
                'message' => $exception->getMessage(),
            ]);

            $isUserFacingException = $exception instanceof \RuntimeException || $exception instanceof \InvalidArgumentException;

            return response()->json([
                'message' => $isUserFacingException
                    ? $exception->getMessage()
                    : 'Greška pri brisanju materijala.',
            ], $isUserFacingException ? 422 : 500);
        }
    }

    public function bulkAdjustStock(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if (!is_array($payload) || empty($payload)) {
            $payload = $request->all();
        }

        $itemsPayload = $this->resolveStockAdjustItemsPayload($payload);
        $normalizedItems = array_values(array_filter(array_map(function ($item) {
            if (!is_array($item)) {
                return null;
            }

            return [
                'material_code' => trim((string) ($item['material_code'] ?? $item['code'] ?? $item['acIdent'] ?? '')),
                'value' => $item['value'] ?? null,
                'new_stock_value' => $item['new_stock_value'] ?? $item['newStockValue'] ?? null,
                'warehouse' => trim((string) ($item['warehouse'] ?? '')),
            ];
        }, $itemsPayload), function ($item) {
            return is_array($item);
        }));

        $validatedPayload = [
            'adjust_mode' => strtolower(trim((string) ($payload['adjust_mode'] ?? 'api'))),
            'warehouse' => trim((string) ($payload['warehouse'] ?? '')),
            'items' => $normalizedItems,
        ];

        $validator = Validator::make($validatedPayload, [
            'adjust_mode' => ['nullable', 'string', 'max:32'],
            'warehouse' => ['nullable', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.material_code' => ['required', 'string', 'max:64'],
            'items.*.value' => ['nullable', 'numeric'],
            'items.*.new_stock_value' => ['nullable', 'numeric'],
            'items.*.warehouse' => ['nullable', 'string', 'max:64'],
        ]);

        $validator->after(function ($validator) use ($validatedPayload) {
            foreach ((array) ($validatedPayload['items'] ?? []) as $index => $item) {
                $hasDelta = is_numeric((string) ($item['value'] ?? ''));
                $hasTarget = is_numeric((string) ($item['new_stock_value'] ?? ''));

                if ($hasDelta || $hasTarget) {
                    continue;
                }

                $validator->errors()->add(
                    'items.' . $index,
                    'Svaka stavka mora imati value ili new_stock_value.'
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravni podaci za azuriranje zalihe.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $adjustMode = (string) ($validatedPayload['adjust_mode'] ?? 'api');

        try {
            $results = Material::bulkAdjustStock(
                (array) ($validatedPayload['items'] ?? []),
                (int) ($request->user()->id ?? 0),
                (string) ($validatedPayload['warehouse'] ?? '')
            );

            return response()->json([
                'message' => 'Zaliha je uspjesno azurirana.',
                'data' => [
                    'count' => count($results),
                    'items' => $results,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Materials bulk stock adjust failed.', [
                'connection' => config('database.default'),
                'table' => Material::scannerSourceTable(),
                'adjust_mode' => $adjustMode,
                'items_count' => count((array) ($validatedPayload['items'] ?? [])),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greska pri azuriranju zalihe.',
            ], 500);
        }
    }

    private function resolveLimit(?int $requestedLimit = null): int
    {
        $limit = (int) ($requestedLimit ?? 100);

        if ($limit < 1) {
            return 100;
        }

        return min($limit, 100);
    }

    private function resolveTableLimit(?int $requestedLimit = null): int
    {
        $limit = (int) ($requestedLimit ?? 25);

        if ($limit < 1) {
            return 25;
        }

        return min($limit, 100);
    }

    private function resolveStockAdjustItemsPayload($payload): array
    {
        if (!is_array($payload) || empty($payload)) {
            return [];
        }

        if (array_key_exists('items', $payload) && is_array($payload['items'])) {
            return array_values($payload['items']);
        }

        if ($this->isSequentialArray($payload)) {
            return array_values($payload);
        }

        return [$payload];
    }

    private function isSequentialArray(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function canCreateMaterial(mixed $user = null): bool
    {
        if (!is_object($user)) {
            return false;
        }

        if (method_exists($user, 'isAdmin')) {
            return (bool) $user->isAdmin();
        }

        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }

    private function canDeleteMaterial(mixed $user = null): bool
    {
        return $this->canCreateMaterial($user);
    }
}
