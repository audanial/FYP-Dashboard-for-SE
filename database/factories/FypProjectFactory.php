<?php

namespace Database\Factories;

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FypProjectFactory extends Factory
{
    protected $model = FypProject::class;

    // This stays outside the function to keep counting 1, 2, 3...
    protected static $counter = 1;

    public function definition(): array
    {
        $count = static::$counter++;
        $academicPrefixes = ['Ts.', 'Dr.', 'Prof.', 'Pn.', 'En.'];

        $malayNames = [
            'Amir Umar Danial Bin Mohd Azmi', 'Muhammad Mukmin Muhaimin bin Nor Azmi',
            'Mohd Azrie bin Mohammad Yusof', 'Siti Nurhaliza binti Ahmad',
            'Nurul Izzah binti Anwar', 'Ahmad Faizal bin Baktiar',
            'Wan Muhammad Amirul bin Wan Mansor', 'Farah Nabilah binti Zulkifli',
        ];

        $supervisors = [
            'Ts. Tiliza binti Awang Mat', 'Dr. Ahmad Zaki bin Hamzah',
            'Prof. Madya Dr. Rohana binti Hassan', 'Ts. Mohd Syukri bin Ali',
        ];

        return [
            // ID: 5221312001, 5221312002...
            'student_id' => '5221312'.str_pad($count, 3, '0', STR_PAD_LEFT),
            'student_name' => $this->faker->randomElement($malayNames),
            'title' => ucwords($this->faker->words(4, true)).' System',
            'supervisor_name' => $this->faker->randomElement($supervisors),
            'assessor_name' => $this->faker->randomElement($academicPrefixes).' '.$this->faker->name(),

            // Domain: Must match Migration exactly
            'domain' => $this->faker->randomElement(['Medical', 'AI', 'IoT', 'Education', 'Business', 'Others']),

            // Platform: Must match Migration exactly (Changed 'Mobile' to 'Mobile App' etc)
            'application_type' => $this->faker->randomElement(['Web App', 'Mobile App', 'PWA', 'Cross Platform']),

            'is_ifyp' => $this->faker->boolean(20),
            'fyp_phase' => $this->faker->randomElement(['FYP 1', 'FYP 2']),
            'semester' => 'MARCH 2026',
        ];
    }

    /**
     * Create a project structurally linked to a supervisor: sets supervisor_id
     * and copies the user's canonical name into supervisor_name (the linked,
     * name-consistent case). Explicitly defined to override Laravel's magic
     * `for<Relationship>` method resolution.
     */
    public function forSupervisor(User $supervisor): static
    {
        return $this->state(fn () => [
            'supervisor_id'   => $supervisor->id,
            'supervisor_name' => $supervisor->name,
        ]);
    }
}
