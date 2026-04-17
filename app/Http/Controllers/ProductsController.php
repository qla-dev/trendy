<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductsController extends Controller
{
    public function scannerIndex(Request $request, string $id): JsonResponse
    {
        try {
            $search = trim((string) $request->query('q', ''));
            $selected = trim((string) $request->query('selected', ''));
            $limit = $this->resolveLimit((int) $request->integer('limit', 100));
            $products = Product::scannerList($search, $limit, $selected);

            return response()->json([
                'data' => $products,
                'meta' => [
                    'count' => count($products),
                    'limit' => $limit,
                    'search' => $search,
                    'selected' => $selected,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Products scanner list failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'table' => Product::scannerSourceTable(),
                'product_structure_table' => Product::structureSourceTable(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greška pri učitavanju proizvoda.',
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
