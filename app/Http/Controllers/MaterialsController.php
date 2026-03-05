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
            $materials = Material::scannerList(
                $search,
                $limit,
                self::MATERIALS_SETS
            );

            return response()->json([
                'data' => $materials,
                'meta' => [
                    'count' => count($materials),
                    'limit' => $limit,
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

    private function resolveLimit(?int $requestedLimit = null): int
    {
        $limit = (int) ($requestedLimit ?? 100);

        if ($limit < 1) {
            return 100;
        }

        return min($limit, 100);
    }
}
