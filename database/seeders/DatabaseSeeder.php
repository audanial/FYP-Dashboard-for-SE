<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Program Coordinator
        User::query()->updateOrCreate(
            ['email' => 'pc@unikl.edu.my'],
            [
                'name' => 'Amir Coordinator',
                'username' => 'pc_unikl',
                'password' => bcrypt('password'),
                'role' => 'coordinator',
            ],
        );

        // Supervisor
        User::query()->updateOrCreate(
            ['email' => 'umar@unikl.edu.my'],
            [
                'name' => 'Dr. Umar',
                'username' => 'umar_unikl',
                'password' => bcrypt('password'),
                'role' => 'supervisor',
            ],
        );
    }
}
