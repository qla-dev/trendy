<?php

namespace App\Services\WorkOrder;

use Illuminate\Support\Facades\DB;
use Throwable;

class DeliveryPriorityOptions
{
    private const FALLBACK_PRIORITIES = [
        1 => 'Visoki prioritet',
        5 => 'Uobičajeni prioritet',
        7 => 'Materijal razdužen',
        10 => 'Niski prioritet',
        15 => 'Uzorci',
    ];

    /**
     * Returns every active Pantheon delivery priority, ordered by its code.
     * Each option has a numeric code, its name, and the code-prefixed label.
     */
    public function all(): array
    {
        try {
            $rows = DB::table(config('workorders.schema', 'dbo') . '.tHE_SetDeliveryPriority')
                ->where('abActive', 1)
                ->orderBy('anPriority')
                ->get(['anPriority', 'acName']);

            if ($rows->isNotEmpty()) {
                return $rows->map(function ($row) {
                    $code = (int) $row->anPriority;
                    $name = trim((string) $row->acName) ?: (string) $code;

                    return [
                        'code' => $code,
                        'name' => $name,
                        'label' => $code . ' - ' . $name,
                    ];
                })->all();
            }
        } catch (Throwable) {
            // Keep the existing standard choices available when Pantheon is unavailable.
        }

        return collect(self::FALLBACK_PRIORITIES)
            ->map(fn (string $name, int $code) => [
                'code' => $code,
                'name' => $name,
                'label' => $code . ' - ' . $name,
            ])
            ->values()
            ->all();
    }

    public function labels(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $priority) => [$priority['code'] => $priority['label']])
            ->all();
    }
}
