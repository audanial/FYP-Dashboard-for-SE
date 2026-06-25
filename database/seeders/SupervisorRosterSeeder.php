<?php

namespace Database\Seeders;

use App\Models\Supervisor;
use App\Models\User;
use App\Support\SupervisorName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SupervisorRosterSeeder extends Seeder
{
    /**
     * Import the SE lecturer roster (storage/app/csv/SE_Lecturers.csv).
     *
     * For each row: auto-create the lecturer's login account (role supervisor)
     * if absent, then upsert the supervisors roster row linked to it. Keyed on
     * email so the seeder is safe to re-run.
     *
     * CSV columns: #, Canonical Name, Email (@unikl.edu.my), Confirmed?
     */
    public function run(): void
    {
        $path = storage_path('app/csv/SE_Lecturers.csv');

        if (! is_file($path)) {
            $this->command?->warn("Roster file not found: {$path}");

            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        array_shift($lines); // discard header row

        foreach ($lines as $line) {
            [, $name, $email, $confirmed] = array_pad(str_getcsv($line), 4, '');

            $name  = trim($name);
            $email = trim($email);

            if ($name === '' || $email === '') {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'      => $name,
                    'role'      => 'supervisor',
                    'is_active' => true,
                    'username'  => Str::before($email, '@'),
                    'password'  => Hash::make(Str::password(16)),
                ],
            );

            Supervisor::updateOrCreate(
                ['email' => $email],
                [
                    'name'      => $name,
                    'name_slug' => SupervisorName::slug($name),
                    'user_id'   => $user->id,
                    'confirmed' => strtoupper(trim($confirmed)) === 'YES',
                ],
            );
        }
    }
}
