<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PantheonWorkerSearchService
{
    private ConnectionInterface $connection;

    public function __construct(?ConnectionInterface $connection = null)
    {
        $name = trim((string) config('services.work_order.target_connection', 'work_order_target'));
        $this->connection = $connection ?: DB::connection($name !== '' ? $name : 'work_order_target');
    }

    public function search(string $term, int $limit = 20): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min(30, $limit));
        $needle = mb_strtolower($term);

        return array_values(array_slice(array_filter($this->activeWorkers(), function (array $worker) use ($needle) {
            return str_contains(mb_strtolower($worker['text'] . ' ' . $worker['worker']), $needle);
        }), 0, $limit));
    }

    public function findActive(int $qid): ?array
    {
        $row = $this->connection->table('dbo.tHR_Prsn')
            ->where('anQId', $qid)
            ->where('acActive', 'T')
            ->first(['anQId', 'acWorker', 'acName', 'acSurname']);

        if ($row === null) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            trim((string) ($row->acName ?? '')),
            trim((string) ($row->acSurname ?? '')),
        ])));
        $worker = trim((string) ($row->acWorker ?? ''));

        return [
            'id' => (int) $row->anQId,
            'worker' => $worker !== '' ? $worker : $name,
            'text' => $name !== '' ? $name : $worker,
        ];
    }

    private function activeWorkers(): array
    {
        // Keep the browser search responsive without sending the full list to
        // every client. The close operation still validates the chosen worker
        // directly against Pantheon immediately before writing documents.
        $cacheKey = 'pantheon-worker-search.active.v1.' . md5((string) config('services.work_order.target_connection', 'work_order_target'));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () {
            return $this->connection->table('dbo.tHR_Prsn')
                ->where('acActive', 'T')
                ->orderBy('acName')
                ->orderBy('acSurname')
                ->get(['anQId', 'acWorker', 'acName', 'acSurname'])
                ->map(function ($row) {
                    $worker = trim((string) ($row->acWorker ?? ''));
                    $name = trim(implode(' ', array_filter([
                        trim((string) ($row->acName ?? '')),
                        trim((string) ($row->acSurname ?? '')),
                    ])));

                    return [
                        'id' => (int) ($row->anQId ?? 0),
                        'worker' => $worker !== '' ? $worker : $name,
                        'text' => $name !== '' ? $name : $worker,
                    ];
                })
                ->filter(fn (array $worker) => $worker['id'] > 0 && $worker['worker'] !== '')
                ->values()
                ->all();
        });
    }
}
