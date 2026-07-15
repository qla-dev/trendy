<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class PantheonDocumentNumberGenerator
{
    public function next(ConnectionInterface $connection, string $documentType, Carbon $date): array
    {
        $prefix = $date->format('y') . $documentType;
        $length = function_exists('app') && app()->bound('config')
            ? (int) config('work_order_closing.sequence_length', 7)
            : 7;
        $row = $connection->selectOne(
            "SELECT TOP 1 LTRIM(RTRIM(acKey)) AS acKey
             FROM dbo.tHE_Move WITH (UPDLOCK, HOLDLOCK)
             WHERE acDocType = ? AND acKey LIKE ?
             ORDER BY acKey DESC",
            [$documentType, $prefix . '%']
        );
        $lastKey = trim((string) ($row->acKey ?? ''));
        $sequence = 0;

        if ($lastKey !== '' && str_starts_with($lastKey, $prefix)) {
            $suffix = substr($lastKey, strlen($prefix));
            if (ctype_digit($suffix)) {
                $sequence = (int) $suffix;
            }
        }

        $sequence++;
        $key = $prefix . str_pad((string) $sequence, $length, '0', STR_PAD_LEFT);

        if (strlen($key) !== strlen($prefix) + $length) {
            throw new RuntimeException('Nije moguće generisati Pantheon broj dokumenta ' . $documentType . '.');
        }

        return [
            'key' => $key,
            'number' => substr($key, 0, 2) . '-' . substr($key, 2, 4) . '-' . substr($key, -6),
            'type' => $documentType,
            'sequence' => $sequence,
        ];
    }
}
