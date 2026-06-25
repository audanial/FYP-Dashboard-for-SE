<?php

use App\Models\FypProject;
use App\Models\Supervisor;
use App\Models\User;
use App\Support\SupervisorName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 * Item 5: the workload chart was missing lecturers because it was derived purely
 * from project rows — a supervisor with no pairs this term simply never appeared.
 * Now that the roster exists, every roster lecturer must show (zero-pair ones
 * included), while supervisors actually carrying pairs keep their real counts and
 * are not duplicated.
 */

function makeRosterSupervisor(string $name, string $email): User
{
    $user = User::factory()->create(['role' => 'supervisor', 'name' => $name]);

    Supervisor::create([
        'name'      => $name,
        'name_slug' => SupervisorName::slug($name),
        'email'     => $email,
        'user_id'   => $user->id,
        'confirmed' => true,
    ]);

    return $user;
}

test('the workload chart includes every roster lecturer, even with zero pairs this term', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    $busy = makeRosterSupervisor('Busy Lecturer', 'busy@unikl.edu.my');
    makeRosterSupervisor('Idle Lecturer', 'idle@unikl.edu.my');

    // Busy supervises 2 pairs; Idle supervises none.
    foreach ([1, 1, 2, 2] as $pair) {
        FypProject::factory()->create([
            'semester'        => 'MARCH 2026',
            'fyp_phase'       => 'FYP 1',
            'supervisor_name' => 'Busy Lecturer',
            'supervisor_id'   => $busy->id,
            'pair_number'     => $pair,
            'domain'          => 'AI',
            'is_ifyp'         => false,
        ]);
    }

    Livewire::test('analytics-charts')
        ->set('phase', 'FYP 1')
        ->assertDispatched('charts-updated', function ($name, $params) {
            $labels = $params['supervisorLabels'];
            $values = $params['supervisorValues'];

            $busyIdx = array_search('Busy Lecturer', $labels, true);
            $idleIdx = array_search('Idle Lecturer', $labels, true);

            return $busyIdx !== false
                && $idleIdx !== false
                && $values[$busyIdx] === 2                       // real pair count
                && $values[$idleIdx] === 0                       // zero-pair lecturer still shown
                && count(array_keys($labels, 'Busy Lecturer', true)) === 1; // not duplicated
        });
});
