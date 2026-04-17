<?php

namespace App\Http\Controllers;

use App\Models\OrderItemMoveLink;

class OrderItemMoveLinkController extends Controller
{
    protected function fetchProducedMoveOrderItemQids(array $orderItemQids): array
    {
        $orderItemQids = array_values(array_unique(array_filter(array_map(function ($qid) {
            return trim((string) $qid);
        }, $orderItemQids), function ($qid) {
            return $qid !== '';
        })));

        if (empty($orderItemQids)) {
            return [];
        }

        $columns = OrderItemMoveLink::sourceColumns();

        if (!in_array('anOrderItemQId', $columns, true)) {
            return [];
        }

        $query = OrderItemMoveLink::newSourceQuery()
            ->whereIn('anOrderItemQId', $orderItemQids);

        if (in_array('anQty', $columns, true)) {
            $query->where('anQty', '>', 0);
        }

        return $query
            ->pluck('anOrderItemQId')
            ->mapWithKeys(function ($qid) {
                return [trim((string) $qid) => true];
            })
            ->all();
    }
}
