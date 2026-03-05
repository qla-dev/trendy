<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationsController extends Controller
{
    public function scannerIndex(Request $request, string $id): JsonResponse
    {
        try {
            $search = trim((string) $request->query('q', ''));
            $limit = $this->resolveLimit((int) $request->integer('limit', 100));
            $operations = Operation::scannerList($search, $limit);

            return response()->json([
                'data' => $operations,
                'meta' => [
                    'count' => count($operations),
                    'limit' => $limit,
                    'search' => $search,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Operations scanner list failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'table' => Operation::scannerSourceTable(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greska pri ucitavanju operacija.',
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
