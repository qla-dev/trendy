<?php

namespace Database\Seeders;

use App\Models\Composition;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class CompositionSeeder extends Seeder
{
    public function run()
    {
        $workOrder = WorkOrder::where('work_order_number', '#240100000005')->first();
        if (! $workOrder) {
            return;
        }

        $workOrder->compositions()->delete();

        $workOrder->compositions()->createMany([
            [
                'alternative' => false,
                'position' => 10,
                'article_code' => 'ALPL2017',
                'description' => 'Alu pločevina 2017',
                'image_url' => '/images/placeholders/aluminum.png',
                'note' => 'Osnovni materijal',
                'quantity' => 2.05,
                'unit' => 'KG',
                'series' => '1,00',
                'normative' => '1 jedinica',
                'active' => true,
                'final' => false,
                'va' => 'VA1',
                'primary_class' => 'PK1',
                'secondary_class' => 'SK1',
            ],
            [
                'alternative' => false,
                'position' => 20,
                'article_code' => 'STL2024',
                'description' => 'Čelična ploča 2024',
                'image_url' => '/images/placeholders/steel.png',
                'note' => 'Sekundarni materijal',
                'quantity' => 1.5,
                'unit' => 'KG',
                'series' => '2,00',
                'normative' => '1 jedinica',
                'active' => true,
                'final' => false,
                'va' => 'VA2',
                'primary_class' => 'PK2',
                'secondary_class' => 'SK2',
            ],
            [
                'alternative' => true,
                'position' => 30,
                'article_code' => 'PLST2023',
                'description' => 'Plastična komponenta 2023',
                'image_url' => '/images/placeholders/plastic.png',
                'note' => 'Alternativni materijal',
                'quantity' => 3.25,
                'unit' => 'KG',
                'series' => '1,50',
                'normative' => '1 jedinica',
                'active' => false,
                'final' => true,
                'va' => 'VA3',
                'primary_class' => 'PK3',
                'secondary_class' => 'SK3',
            ],
            [
                'alternative' => false,
                'position' => 40,
                'article_code' => 'ELK2025',
                'description' => 'Električna komponenta 2025',
                'image_url' => '/images/placeholders/electric.png',
                'note' => 'Za montažu',
                'quantity' => 0.5,
                'unit' => 'KG',
                'series' => '2,00',
                'normative' => '1 jedinica',
                'active' => true,
                'final' => false,
                'va' => 'VA4',
                'primary_class' => 'PK4',
                'secondary_class' => 'SK4',
            ],
        ]);
    }
}
