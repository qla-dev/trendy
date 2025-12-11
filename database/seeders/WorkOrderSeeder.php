<?php

namespace Database\Seeders;

use App\Models\Composition;
use App\Models\Material;
use App\Models\Operation;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class WorkOrderSeeder extends Seeder
{
    public function run()
    {
        $workOrder = WorkOrder::create([
            'work_order_number' => '#240100000005',
            'title' => 'INTERSPORT Ltd Work Order',
            'description' => 'Production batch for Metro order 2024',
            'status' => 'U toku',
            'priority' => 'Z',
            'client_name' => 'INTERSPORT Ltd.',
            'client_address' => 'Korinsik 32, Sarajevo',
            'client_phone' => '+387 33 123 456',
            'client_email' => 'info@intersport.com',
            'recipient_name' => 'Bingo d.o.o.',
            'recipient_address' => 'Grbavička 12, 71000 Sarajevo, B&H',
            'recipient_phone' => '+387 33 789 012',
            'recipient_email' => 'info@bingo.ba',
            'total' => 0,
            'currency' => 'EUR',
            'planned_start' => Carbon::create(2024, 1, 25),
            'planned_end' => Carbon::create(2024, 1, 27),
            'actual_start' => Carbon::create(2024, 1, 25),
            'actual_end' => null,
            'linked_document' => 'DOC-2401',
            'created_by' => 'Administrator',
            'notes' => 'Sample data for local work order list'
        ]);

        // Compositions, materials, and operations seeded via dedicated seeders
    }
}
