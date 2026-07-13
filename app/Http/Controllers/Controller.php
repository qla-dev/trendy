<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function workOrderTargetConnectionName(): string
    {
        $connection = trim((string) config('services.work_order.target_connection', 'sqlsrv'));

        return $connection !== '' ? $connection : 'sqlsrv';
    }

    protected function runWithWorkOrderTargetConnection(callable $callback): mixed
    {
        return $this->runWithDatabaseConnection($this->workOrderTargetConnectionName(), $callback);
    }

    protected function runWithDatabaseConnection(string $connectionName, callable $callback): mixed
    {
        $connectionName = trim($connectionName);

        if ($connectionName === '') {
            $connectionName = 'sqlsrv';
        }

        $previousConnection = DB::getDefaultConnection();

        if ($previousConnection === $connectionName) {
            return $callback();
        }

        DB::setDefaultConnection($connectionName);

        try {
            return $callback();
        } finally {
            DB::setDefaultConnection($previousConnection);
        }
    }
}
