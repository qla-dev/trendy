<?php

namespace App\Console\Commands;

use App\Services\WorkOrder\WorkOrderClosingService;
use Illuminate\Console\Command;

class RecoverExcessStock6400Command extends Command
{
    protected $signature = 'work-orders:recover-excess-stock-6400
        {work-order : Ključ ili prikazani broj radnog naloga}
        {--user=0 : Pantheon korisnički ID za evidenciju}
        {--connection= : Mora biti konfigurirana Testna konekcija za radne naloge}';

    protected $description = 'Kreiraj samo nedostajući posebni 6400 za već zatvoren Testna radni nalog.';

    public function handle(WorkOrderClosingService $closing): int
    {
        $connection = trim((string) ($this->option('connection') ?: config('services.work_order.target_connection')));
        $database = \Illuminate\Support\Facades\DB::connection($connection)->getDatabaseName();
        if (!str_contains(strtoupper((string) $database), 'TESTNA')) {
            $this->error('Oporavak je strogo blokiran izvan TESTNA baze.');
            return self::FAILURE;
        }

        $result = $closing->recoverMissingExcessStock6400((string) $this->argument('work-order'), (int) $this->option('user'));
        if ($result === null) {
            $this->warn('Nije pronađen nedostajući posebni 6400 za oporavak.');
            return self::SUCCESS;
        }

        $this->info('Kreiran je posebni 6400: ' . $result['document_number']);
        return self::SUCCESS;
    }
}
