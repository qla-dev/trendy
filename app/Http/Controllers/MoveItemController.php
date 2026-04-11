<?php

namespace App\Http\Controllers;

use App\Models\MoveItem;

class MoveItemController extends Controller
{
    protected function fetchMoveItemsByQids(array $moveItemQids): array
    {
        $moveItemQids = array_values(array_unique(array_filter(array_map(function ($qid) {
            return trim((string) $qid);
        }, $moveItemQids), function ($qid) {
            return $qid !== '';
        })));

        if (empty($moveItemQids)) {
            return [];
        }

        $columns = MoveItem::sourceColumns();

        if (!in_array('anQId', $columns, true)) {
            return [];
        }

        $selectColumns = array_values(array_filter([
            in_array('anQId', $columns, true) ? 'anQId' : null,
            in_array('acKey', $columns, true) ? 'acKey' : null,
            in_array('acKeyView', $columns, true) ? 'acKeyView' : null,
            in_array('adTimeIns', $columns, true) ? 'adTimeIns' : null,
            in_array('adTimeChg', $columns, true) ? 'adTimeChg' : null,
            in_array('adDate', $columns, true) ? 'adDate' : null,
            in_array('acStatus', $columns, true) ? 'acStatus' : null,
            in_array('acStatusMF', $columns, true) ? 'acStatusMF' : null,
        ]));

        return MoveItem::newSourceQuery()
            ->whereIn('anQId', $moveItemQids)
            ->get($selectColumns)
            ->mapWithKeys(function ($row) {
                $mapped = (array) $row;
                $qid = trim((string) ($mapped['anQId'] ?? ''));

                return [$qid => $mapped];
            })
            ->all();
    }
}

