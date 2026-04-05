<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //Program Coordinator
        \App\Models\User::factory()->create([
           'name' => 'Amir Coordinator',
            'email' => 'pc@unikl.edu.my',
            'password' => bcrypt('password'),
            'role' => 'coordinator',
        ]);

        //Supervisor
        \App\Models\User::factory()->create([
           'name' => 'Dr. Umar',
            'email' => 'umar@unikl.edu.my',
            'password' => bcrypt('password'),
            'role' => 'supervisor',
        ]);

    }
}
