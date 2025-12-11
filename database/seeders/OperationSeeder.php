<?php

namespace Database\Seeders;

use App\Models\Operation;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class OperationSeeder extends Seeder
{
    public function run()
    {
        $workOrder = WorkOrder::where('work_order_number', '#240100000005')->first();
        if (! $workOrder) {
            return;
        }

        $workOrder->operations()->delete();

        $workOrder->operations()->createMany([
            [
                'alternative' => false,
                'position' => 10,
                'operation_code' => 'OP001',
                'name' => 'Rezanje',
                'note' => 'Rezanje po dimenzijama',
                'unit' => 'KOM',
                'unit_value' => 0.5,
                'normative' => 'Normativ 1',
                'va' => 'VA1',
                'primary_class' => 'PK1',
                'secondary_class' => 'SK1',
            ],
            [
                'alternative' => false,
                'position' => 20,
                'operation_code' => 'OP002',
                'name' => 'Sva ranje',
                'note' => 'Sva ranje šavova',
                'unit' => 'KOM',
                'unit_value' => 1.2,
                'normative' => 'Normativ 2',
                'va' => 'VA2',
                'primary_class' => 'PK2',
                'secondary_class' => 'SK2',
            ],
            [
                'alternative' => false,
                'position' => 30,
                'operation_code' => 'OP003',
                'name' => 'Poliranje',
                'note' => 'Finalno poliranje',
                'unit' => 'KOM',
                'unit_value' => 0.8,
                'normative' => 'Normativ 3',
                'va' => 'VA3',
                'primary_class' => 'PK3',
                'secondary_class' => 'SK3',
            ],
            [
                'alternative' => true,
                'position' => 40,
                'operation_code' => 'OP004',
                'name' => 'Montaža',
                'note' => 'Montaža komponenti',
                'unit' => 'KOM',
                'unit_value' => 2,
                'normative' => 'Normativ 4',
                'va' => 'VA4',
                'primary_class' => 'PK4',
                'secondary_class' => 'SK4',
            ],
        ]);
    }
}
