<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
                'message' => 'Greska pri ucitavanju materijala.',
            ], 500);
        }
    }

    public function barcodeGenerator()
    {
        $pageConfigs = ['pageHeader' => false];

        return view('content.apps.materials.app-material-barcode-generator', [
            'pageConfigs' => $pageConfigs,
            'barcodeTableUrl' => route('app-barcode-generator-data'),
        ]);
    }

    public function barcodeGeneratorData(Request $request): JsonResponse
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

            $materials = Material::barcodeGeneratorList(
                $search,
                $limit,
                self::MATERIALS_SETS,
                $start,
                $sortBy,
                $sortDir
            );

            return response()->json([
                'draw' => $draw,
                'data' => $materials,
                'recordsTotal' => Material::barcodeGeneratorTotalCount(self::MATERIALS_SETS),
                'recordsFiltered' => Material::barcodeGeneratorFilteredCount($search, self::MATERIALS_SETS),
            ]);
        } catch (Throwable $exception) {
            Log::error('Materials barcode generator list failed.', [
                'connection' => config('database.default'),
                'table' => Material::scannerSourceTable(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greska pri ucitavanju materijala za barcode generator.',
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
}
