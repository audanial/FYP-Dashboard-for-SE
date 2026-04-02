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

        $names = [
            'Amir Umar Danial Bin Mohd Azmi', 'Siti Nurhaliza Binti Ahmad', 'Muhammad Hafiz Bin Ismail',
            'Tan Wei Shen', 'Letchumi A/P Rajoo', 'Nurul Izzah Binti Anwar', 'Chong Jia Hao',
            'Ahmad Faizal Bin Bakri', 'Puteri Balqis Binti Roslan', 'Arul Kumar A/L Subramaniam',
            'Farhan Bin Najib', 'Emily Wong Siew Mei', 'Karthik A/L Ganesan', 'Nurul Ain Binti Zulkifli'
        ];

        $supervisors = [
            'Ts. Tiliza Binti Awang Mat', 'Dr. Syarifah Bahiyah Rahayu', 'Ts. Dr. Mohd Nizam Bin Husen',
            'Pn. Norhaidi Binti Ibrahim', 'En. Khirulnizam Bin Abd Wahab', 'Ts. Azman Bin Abu Bakar'
        ];

        // 1. Generate MARCH 2026 (Consolidated into one loop of 410)
        for ($i = 0; $i < 410; $i++) {
            $phase = ($i < 205) ? 'FYP 1' : 'FYP 2';
            FypProject::create([
                'student_name' => $names[array_rand($names)],
                'student_id' => '5221' . rand(20000000, 23999999),
                'title' => 'Development of ' . collect(['AI System', 'Mobile App', 'IoT Framework', 'Cloud Portal'])->random(),
                'supervisor_name' => $supervisors[array_rand($supervisors)],
                'application_type' => collect(['Web App', 'Mobile App', 'PWA', 'Cross Platform'])->random(),
                'domain' => collect(['Medical', 'AI', 'IoT', 'Education', 'Business', 'Others'])->random(),
                'is_ifyp' => (rand(1, 10) > 8),
                'fyp_phase' => $phase,
                'semester' => 'MARCH 2026',
            ]);
        }

        // 2. Generate OCTOBER 2025 (Fixed the year typo!)
        for ($i = 0; $i < 200; $i++) {
            $phase = ($i < 100) ? 'FYP 1' : 'FYP 2';
            FypProject::create([
                'student_name' => $names[array_rand($names)],
                'student_id' => '5221' . rand(10000000, 19999999),
                'title' => 'Legacy System: ' . collect(['Inventory Management', 'Booking Portal', 'Data Analytics'])->random(),
                'supervisor_name' => $supervisors[array_rand($supervisors)],
                'application_type' => collect(['Web App', 'Mobile App', 'PWA', 'Cross Platform'])->random(),
                'domain' => collect(['Medical', 'AI', 'IoT', 'Education', 'Business', 'Others'])->random(),
                'is_ifyp' => (rand(1, 10) > 9),
                'fyp_phase' => $phase,
                'semester' => 'OCTOBER 2025', // Fixed from 2026 to 2025
            ]);
        }
    }
}
