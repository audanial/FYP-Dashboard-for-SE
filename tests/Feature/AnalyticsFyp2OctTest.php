<?php

use App\Models\FypProject;
use App\Models\User;
use Livewire\Livewire;

/*
 * Item 4: the FYP2 OCTOBER 2025 analytics (total pairs, avg pairs/supervisor,
 * workload distribution) were blank ONLY because pair_number imported as null
 * (the "Seat" header issue, fixed in Item 3). The analytics code itself has no
 * October-2025-specific bug — every computed reads $this->semester / $this->phase
 * dynamically. These tests prove the analytics compute correctly for a
 * FYP2 / OCTOBER 2025 dataset once pair numbers are present, and lock that in so
 * it cannot silently regress.
 */

function makeOctAnalyticsProject(string $semester, string $phase, int $pair, string $supervisor): FypProject
{
    return FypProject::factory()->create([
        'semester'        => $semester,
        'fyp_phase'       => $phase,
        'supervisor_name' => $supervisor,
        'pair_number'     => $pair,
        'domain'          => 'AI',
        'is_ifyp'         => false,
    ]);
}

test('FYP2 October 2025 total pairs and avg per supervisor compute once pair numbers are present', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    // OCT 2025 / FYP 2: Dr. Xavier supervises 2 pairs, Dr. Young supervises 1 pair.
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 1, 'Dr. Xavier');
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 1, 'Dr. Xavier');
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 2, 'Dr. Xavier');
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 2, 'Dr. Xavier');
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 3, 'Dr. Young');
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 3, 'Dr. Young');

    // Noise that must be excluded by the semester + phase filters.
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 1', 5, 'Dr. Noise'); // wrong phase
    makeOctAnalyticsProject('MARCH 2026',   'FYP 2', 9, 'Dr. Other'); // wrong semester

    // Apply the filter state at mount so the initial render is plain HTML
    // (chaining ->set() would make assertSeeInOrder search the JSON update payload).
    Livewire::test('analytics-charts', ['semester' => 'OCTOBER 2025', 'phase' => 'FYP 2'])
        // 3 distinct pairs (not 6 students, not the excluded noise rows)
        ->assertSeeInOrder(['Total Pairs', 'text-indigo-900">3</p>'], false)
        // 3 pairs / 2 supervisors = 1.5
        ->assertSeeInOrder(['Avg Pairs / Supervisor', 'text-violet-900">1.5</p>'], false);
});

test('FYP2 October 2025 supervisor workload distribution lists each supervisor with distinct pair counts', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 1, 'Dr. Xavier');
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 1, 'Dr. Xavier');
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 2, 'Dr. Xavier');
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 2, 'Dr. Xavier');
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 3, 'Dr. Young');
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 2', 3, 'Dr. Young');

    // Excluded by phase — proves the workload chart respects the FYP 2 filter.
    makeOctAnalyticsProject('OCTOBER 2025', 'FYP 1', 7, 'Dr. Noise');

    Livewire::test('analytics-charts')
        ->set('semester', 'OCTOBER 2025')
        ->set('phase', 'FYP 2')
        ->assertDispatched('charts-updated', fn ($name, $params) =>
            $params['supervisorLabels'] === ['Dr. Xavier', 'Dr. Young'] // sorted desc by pairs
            && $params['supervisorValues'] === [2, 1]                    // distinct pairs, not students
        );
});
