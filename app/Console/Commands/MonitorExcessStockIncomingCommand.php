<?php

namespace App\Console\Commands;

use App\Services\WorkOrder\ExcessStockService;
use Illuminate\Console\Command;

class MonitorExcessStockIncomingCommand extends Command
{
    protected $signature = 'work-orders:monitor-excess-stock-incoming {--connection= : Izvorna konekcija}';
    protected $description = 'Pošalji obavijest o novom ulazu u skladište dodatnih sirovina.';

    public function handle(ExcessStockService $service): int
    {
        if (!(bool) config('excess-stock.enabled', false)) {
            return self::SUCCESS;
        }
        $connection = trim((string) ($this->option('connection') ?: config('services.work_order.target_connection')));
        $sent = $service->notifyRecentIncomingMovements($service->connection($connection));
        $this->info('Poslano obavijesti o ulazu dodatnih sirovina: ' . $sent);
        return self::SUCCESS;
    }
}
