<?php

use App\Models\FypProject;
use App\Models\User;
use Livewire\Livewire;

/**
 * Helper: create one MARCH 2026 / FYP 1 project with a fixed pair + supervisor.
 * student_id is left to the factory so it stays unique across tests.
 */
function makeAnalyticsProject(int $pair, string $supervisor, array $overrides = []): FypProject
{
    return FypProject::factory()->create(array_merge([
        'semester'        => 'MARCH 2026',
        'fyp_phase'       => 'FYP 1',
        'supervisor_name' => $supervisor,
        'pair_number'     => $pair,
        'domain'          => 'AI',
        'is_ifyp'         => false,
    ], $overrides));
}

test('total pairs card counts distinct pair_number, not student rows', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    // 6 students across 3 pairs, all under one supervisor.
    foreach ([1, 1, 2, 2, 3, 3] as $pair) {
        makeAnalyticsProject($pair, 'Dr. Solo');
    }

    Livewire::test('analytics-charts')
        // "Total Pairs" value is the indigo card → 3 pairs (not 6 students)
        ->assertSeeInOrder(['Total Pairs', 'text-indigo-900">3</p>'], false)
        // "Avg Pairs / Supervisor" is the violet card → 3 pairs / 1 supervisor = 3
        ->assertSeeInOrder(['Avg Pairs / Supervisor', 'text-violet-900">3</p>'], false);
});

test('industrial percentage stays based on student count, not pairs', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    // 4 students across 2 pairs; exactly 1 student is industrial.
    makeAnalyticsProject(1, 'Dr. Solo', ['is_ifyp' => true]);
    makeAnalyticsProject(1, 'Dr. Solo');
    makeAnalyticsProject(2, 'Dr. Solo');
    makeAnalyticsProject(2, 'Dr. Solo');

    Livewire::test('analytics-charts')
        ->assertSeeInOrder(['Industrial (IFYP)', 'text-amber-900">1</p>'], false) // industrial count = 1 student
        ->assertSee('25% of cohort')                // 1/4 students = 25% (NOT 1/2 pairs = 50%)
        ->assertDontSee('50% of cohort');
});

test('supervisor workload plots distinct pairs per supervisor, not student rows', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    // Dr. Alpha supervises 2 pairs (4 students); Dr. Beta supervises 1 pair (2 students).
    // NOTE: the shared helper was renamed to makeAnalyticsProject in Task 1 (review feedback).
    makeAnalyticsProject(1, 'Dr. Alpha');
    makeAnalyticsProject(1, 'Dr. Alpha');
    makeAnalyticsProject(2, 'Dr. Alpha');
    makeAnalyticsProject(2, 'Dr. Alpha');
    makeAnalyticsProject(3, 'Dr. Beta');
    makeAnalyticsProject(3, 'Dr. Beta');

    // All 6 seeded rows are FYP 1 — so phase='FYP 1' still shows both supervisors.
    // We set phase to 'FYP 1' (from default 'all') to trigger updatedPhase dispatch.
    Livewire::test('analytics-charts')
        ->set('phase', 'FYP 1')
        ->assertDispatched('charts-updated', fn ($name, $params) =>
            $params['supervisorLabels'] === ['Dr. Alpha', 'Dr. Beta']
            && $params['supervisorValues'] === [2, 1] // pairs, sorted desc — NOT [4, 2] students
        );
});
