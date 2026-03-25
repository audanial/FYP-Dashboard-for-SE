<?php

namespace Database\Seeders;

use App\Models\FypProject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FypProjectSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('fyp_projects')->truncate();

        // A much larger list to reduce obvious repeats
        $names = [
            'Amir Umar Danial Bin Mohd Azmi', 'Siti Nurhaliza Binti Ahmad', 'Muhammad Hafiz Bin Ismail',
            'Tan Wei Shen', 'Letchumi A/P Rajoo', 'Nurul Izzah Binti Anwar', 'Chong Jia Hao',
            'Ahmad Faizal Bin Bakri', 'Puteri Balqis Binti Roslan', 'Arul Kumar A/L Subramaniam',
            'Farhan Bin Najib', 'Emily Wong Siew Mei', 'Karthik A/L Ganesan', 'Nurul Ain Binti Zulkifli',
            'Mohd Ridzuan Bin Hashim', 'Lim Kai Ze', 'Saraswathy A/P Mohan', 'Aishah Binti Abu Bakar'
        ];

        $supervisors = [
            'Ts. Tiliza Binti Awang Mat', 'Dr. Syarifah Bahiyah Rahayu', 'Ts. Dr. Mohd Nizam Bin Husen',
            'Pn. Norhaidi Binti Ibrahim', 'En. Khirulnizam Bin Abd Wahab', 'Ts. Azman Bin Abu Bakar'
        ];

        // Generate MARCH 2026
        for ($i = 0; $i < 210; $i++) {
            $phase = ($i < 95) ? 'FYP 1' : 'FYP 2';
            FypProject::create([
                'student_name' => $names[array_rand($names)], // Removed the (56) numbers!
                'student_id' => '5221' . rand(20000000, 23999999),
                'title' => 'Development of ' . collect(['AI System', 'Mobile App', 'IoT Framework', 'Cloud Portal'])->random(),
                'supervisor_name' => $supervisors[array_rand($supervisors)],
                'application_type' => ($i % 2 == 0) ? 'Web App' : 'Mobile App',
                'fyp_phase' => $phase,
                'semester' => 'MARCH 2026',
            ]);
        }

        // Generate OCTOBER 2025
        for ($i = 0; $i < 200; $i++) {
            $phase = ($i < 115) ? 'FYP 1' : 'FYP 2';
            FypProject::create([
                'student_name' => $names[array_rand($names)], // Removed the (56) numbers!
                'student_id' => '5221' . rand(10000000, 19999999),
                'title' => 'Legacy System Project ' . rand(100, 999),
                'supervisor_name' => $supervisors[array_rand($supervisors)],
                'application_type' => 'Web App',
                'fyp_phase' => $phase,
                'semester' => 'OCTOBER 2025',
            ]);
        }
    }
}
