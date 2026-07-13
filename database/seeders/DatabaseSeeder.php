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

        // Program Coordinator (secondary account)
        User::query()->updateOrCreate(
            ['email' => 'amir@unikl.edu.my'],
            [
                'name' => 'Amir Umar Danial',
                'username' => 'amir_unikl',
                'password' => bcrypt('abcd1234'),
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

        // Seed the full SE lecturer roster (creates supervisor accounts + roster rows).
        $this->call(SupervisorRosterSeeder::class);
    }
}
