<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\CompositionSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\OperationSeeder;
use Database\Seeders\WorkOrderSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();

        $this->call([
            AdminUserSeeder::class,
            WorkOrderSeeder::class,
            CompositionSeeder::class,
            MaterialSeeder::class,
            OperationSeeder::class,
        ]);

    }
}
