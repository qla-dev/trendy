<?php

namespace App\Services\Procurement;

use RuntimeException;

/** Private, durable ledger. A separate lock survives atomic ledger replacement. */
class ProcurementStore
{
    public function __construct(private ?string $directory = null) {}

    public function directory(): string
    {
        $connection = config('services.work_order.target_connection', 'sqlsrv');
        $identity = [$connection, config("database.connections.$connection.host"), config("database.connections.$connection.database")];
        return $this->directory ?? storage_path('app/procurement/' . hash('sha256', json_encode($identity)));
    }

    public function all(): array
    {
        return $this->locked(fn (array &$rows) => $rows, false);
    }

    public function update(callable $callback): mixed
    {
        return $this->locked($callback, true);
    }

    private function locked(callable $callback, bool $write): mixed
    {
        $directory = $this->directory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Evidencija nabavki nije dostupna.');
        }
        $lock = fopen($directory . '/ledger.lock', 'c+');
        if (!$lock) throw new RuntimeException('Zaključavanje nabavki nije dostupno.');
        $temporary = null;
        try {
            if (!flock($lock, $write ? LOCK_EX : LOCK_SH)) throw new RuntimeException('Evidencija je zauzeta.');
            $path = $directory . '/ledger.json';
            $rows = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];
            if (!is_array($rows) || ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1))) throw new RuntimeException('Evidencija nabavki je oštećena.');
            $result = $callback($rows);
            if ($write) {
                $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $temporary = tempnam($directory, 'ledger-');
                if ($temporary === false || file_put_contents($temporary, $json) !== strlen($json)) throw new RuntimeException('Nabavka nije sačuvana.');
                if (!rename($temporary, $path)) throw new RuntimeException('Nabavka nije sačuvana.');
            }
            return $result;
        } finally {
            if ($temporary && is_file($temporary)) unlink($temporary);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
