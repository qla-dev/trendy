<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    public function run()
    {
        $workOrder = WorkOrder::where('work_order_number', '#240100000005')->first();
        if (! $workOrder) {
            return;
        }

        $workOrder->materials()->delete();

        $workOrder->materials()->createMany([
            [
                'position' => 10,
                'material_code' => 'ALPL2017',
                'name' => 'Alu pločevina 2017',
                'quantity' => 2.05,
                'unit' => 'KG',
                'note' => 'Za montažu'
            ],
            [
                'position' => 20,
                'material_code' => 'STL2024',
                'name' => 'Čelična ploča 2024',
                'quantity' => 1.5,
                'unit' => 'KG',
                'note' => 'Visokokvalitetni čelik'
            ],
            [
                'position' => 30,
                'material_code' => 'ELK2025',
                'name' => 'Električna komponenta 2025',
                'quantity' => 0.5,
                'unit' => 'KG',
                'note' => 'Za montažu'
            ],
            [
                'position' => 40,
                'material_code' => 'GLASS2024',
                'name' => 'Staklena komponenta 2024',
                'quantity' => 1.0,
                'unit' => 'KG',
                'note' => 'Ostalo'
            ],
        ]);
    }
}
