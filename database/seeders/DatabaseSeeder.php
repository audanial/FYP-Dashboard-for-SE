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
        // Keeps your login user if you need it
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // CHOP CHOP: This is the missing piece!
        // This tells Laravel to run your FypProjectSeeder.php file.
        $this->call([
            FypProjectSeeder::class,
        ]);
    }
}
