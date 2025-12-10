<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $admin = User::firstOrNew(['username' => 'admin']);

        $admin->name = 'Administrator';
        $admin->email = 'admin@trendy.local';
        $admin->role = 'admin';
        $admin->username = 'admin';

        if (! $admin->exists) {
            $admin->password = Hash::make('password1234');
        }

        $admin->save();
    }
}
