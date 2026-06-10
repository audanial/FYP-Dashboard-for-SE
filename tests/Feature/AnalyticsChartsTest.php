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

    // Trigger the chart payload via ->set('phase', ...) rather than ->call('updatedPhase'):
    // Livewire forbids calling updated* lifecycle hooks directly, so we fire the hook as a
    // side-effect of the property change. All 6 rows are FYP 1, so phase='FYP 1' (from the
    // default 'all') still surfaces both supervisors in the dispatched payload.
    Livewire::test('analytics-charts')
        ->set('phase', 'FYP 1')
        ->assertDispatched('charts-updated', fn ($name, $params) =>
            $params['supervisorLabels'] === ['Dr. Alpha', 'Dr. Beta']
            && $params['supervisorValues'] === [2, 1] // pairs, sorted desc — NOT [4, 2] students
        );
});

/*
 * Sprint 4 — Phase 3.3 (analytics effective-key grouping).
 *
 * Supervisor aggregations group by an "effective supervisor key":
 *   A) supervisor_id set + relation resolves -> linked supervisor user's name
 *   B) else supervisor_name present          -> supervisor_name
 *   C) else                                  -> "Unlinked"
 * Applied consistently to the workload chart and the supervisor count.
 */

test('supervisor workload groups by the linked supervisor name when supervisor_id is set, collapsing name-string duplicates', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Canonical Supervisor',
    ]);

    // One real supervisor recorded under two different name strings, two pairs.
    makeAnalyticsProject(1, 'Canonical Supervisor',       ['supervisor_id' => $supervisor->id]);
    makeAnalyticsProject(1, 'Canonical Supervisor',       ['supervisor_id' => $supervisor->id]);
    makeAnalyticsProject(2, 'Canonical Supervisor - Ts.', ['supervisor_id' => $supervisor->id]);
    makeAnalyticsProject(2, 'Canonical Supervisor - Ts.', ['supervisor_id' => $supervisor->id]);

    Livewire::test('analytics-charts')
        ->set('phase', 'FYP 1')
        ->assertDispatched('charts-updated', fn ($name, $params) =>
            $params['supervisorLabels'] === ['Canonical Supervisor'] // collapsed to one bucket
            && $params['supervisorValues'] === [2]                   // 2 distinct pairs
        );
});

test('supervisor workload falls back to supervisor_name when supervisor_id is null', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    makeAnalyticsProject(1, 'Named Only Supervisor'); // supervisor_id null by default
    makeAnalyticsProject(1, 'Named Only Supervisor');

    Livewire::test('analytics-charts')
        ->set('phase', 'FYP 1')
        ->assertDispatched('charts-updated', fn ($name, $params) =>
            $params['supervisorLabels'] === ['Named Only Supervisor']
            && $params['supervisorValues'] === [1]
        );
});

test('supervisor workload buckets projects with no supervisor_id and no supervisor_name under Unlinked', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    // Blank supervisor_name (column is non-nullable) and no supervisor_id -> Unlinked.
    makeAnalyticsProject(1, '', ['supervisor_id' => null]);
    makeAnalyticsProject(1, '', ['supervisor_id' => null]);

    Livewire::test('analytics-charts')
        ->set('phase', 'FYP 1')
        ->assertDispatched('charts-updated', fn ($name, $params) =>
            in_array('Unlinked', $params['supervisorLabels'], true)
            && ! in_array('Unknown', $params['supervisorLabels'], true)
        );
});

test('avg pairs per supervisor counts distinct effective supervisors, collapsing linked duplicates', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Solo Linked',
    ]);

    // One real supervisor, two name strings, two distinct pairs.
    makeAnalyticsProject(1, 'Solo Linked',       ['supervisor_id' => $supervisor->id]);
    makeAnalyticsProject(1, 'Solo Linked',       ['supervisor_id' => $supervisor->id]);
    makeAnalyticsProject(2, 'Solo Linked - Ts.', ['supervisor_id' => $supervisor->id]);
    makeAnalyticsProject(2, 'Solo Linked - Ts.', ['supervisor_id' => $supervisor->id]);

    // 2 pairs / 1 effective supervisor = 2 (NOT 2 pairs / 2 name strings = 1).
    Livewire::test('analytics-charts')
        ->assertSeeInOrder(['Avg Pairs / Supervisor', 'text-violet-900">2</p>'], false);
});
