<?php

namespace App\Console\Commands;

use App\Services\WorkOrder\ExcessStockService;
use Illuminate\Console\Command;

class BackfillExcessStockCommand extends Command
{
    protected $signature = 'work-orders:backfill-excess-stock
        {--apply : Sačuvaj rezervacije nakon pregleda probnog pokretanja}
        {--limit= : Ograniči otvorene RN-ove za mali Testna test}
        {--work-order= : Primijeni/prikaži samo jedan ključ ili broj RN-a}
        {--allow-limited-apply : Potvrdi da ograničeni Testna test smije upisati rezervacije}
        {--user=0 : Pantheon korisnički ID za evidenciju upisa}
        {--connection= : Eksplicitna konekcija; inače EXCESS_STOCK_BACKFILL_CONNECTION}';

    protected $description = 'Prikaži ili dodaj rezervacije Dodatnih sirovina na otvorene radne naloge.';

    public function handle(ExcessStockService $service): int
    {
        $connection = trim((string) ($this->option('connection') ?: config('excess-stock.backfill_connection')));
        $limit = $this->option('limit');
        $limit = is_numeric((string) $limit) ? max(0, (int) $limit) : null;
        $db = $service->connection($connection);
        $plan = $service->assignPreview($db, $limit);
        $workOrder = trim((string) $this->option('work-order'));
        if ($workOrder !== '') {
            $plan['assignments'] = array_values(array_filter($plan['assignments'], function (array $assignment) use ($workOrder): bool {
                $order = $assignment['work_order'];
                return strcasecmp(trim((string) ($order['acKey'] ?? '')), $workOrder) === 0
                    || strcasecmp(trim((string) ($order['acKeyView'] ?? '')), $workOrder) === 0;
            }));
            if ($plan['assignments'] === []) {
                $this->error("Otvoreni radni nalog '{$workOrder}' nije pronađen; rezervacije nisu kreirane.");
                return self::FAILURE;
            }
        }
        $this->info('Konekcija: ' . $db->getDatabaseName());
        $this->line('Skladište: ' . $service->warehouse());
        $this->line('NajviĹˇe stavki po RN-u: ' . config('excess-stock.max_materials_per_work_order', 5) . '; limit: ' . bcmul((string) $plan['sales_price_percent'], '100', 2) . '% prodajne cijene po komadu.');
        $this->table(
            ['WO', 'Prodajna cijena/kom', 'Limit/kom', 'Dodijeljeno/kom', 'Ukupno', 'Materials'],
            array_map(fn ($a) => [
                trim((string)($a['work_order']['acKeyView'] ?? $a['work_order']['acKey'])),
                $a['work_order']['sales_price'],
                $a['work_order']['budget_per_piece'],
                $a['work_order']['assigned_per_piece'],
                $a['work_order']['assigned_total'],
                implode(', ', array_map(fn ($m) => $m['code'].'='.$m['assignment_qty'].' '.$m['unit'].' @ '.$m['price'].' = '.$m['assignment_total'].' KM', $a['materials'])),
            ], $plan['assignments'])
        );
        if (!$this->option('apply')) {
            $this->warn('Ovo je samo probno pokretanje. Rezervacije i stanje zalihe nisu mijenjani. Pokrenite s --apply tek nakon Testna provjere.');
            return self::SUCCESS;
        }
        if (!(bool) config('excess-stock.enabled', false)) {
            $this->error('Funkcionalnost nije uključena. Postavite EXCESS_STOCK_ENABLED=true samo u Testna prije upisa.');
            return self::FAILURE;
        }
        if (!(bool) config('excess-stock.allow_apply', false)) {
            $this->error('Upis nije uključen. Postavite EXCESS_STOCK_ALLOW_APPLY=true samo u Testna.');
            return self::FAILURE;
        }
        if (!str_contains(strtoupper((string) $db->getDatabaseName()), 'TESTNA')) {
            $this->error('Upis je strogo blokiran izvan TESTNA baze. Rezervacije nisu kreirane.');
            return self::FAILURE;
        }
        if ($limit !== null && !$this->option('allow-limited-apply')) {
            $this->error('Ograničeno pokretanje je po pravilu samo pregled. Dodajte --allow-limited-apply za potvrdu Testna testa.');
            return self::FAILURE;
        }
        $added = 0;
        foreach ($plan['assignments'] as $assignment) $added += $service->assign($db, $assignment, (int) $this->option('user'));
        $this->info('Kreirano rezervacija dodatnih sirovina: ' . $added . '. Fizičko stanje zalihe nije mijenjano.');
        return self::SUCCESS;
    }
}
