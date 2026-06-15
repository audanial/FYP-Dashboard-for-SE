<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('forSupervisor factory state links id and copies the canonical name', function () {
    $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Dr Canonical']);

    $project = FypProject::factory()->forSupervisor($sup)->create();

    expect($project->supervisor_id)->toBe($sup->id)
        ->and($project->supervisor_name)->toBe('Dr Canonical');
});

// ── Task 4: unlinkedSupervisors computed + panel ──────────────────────────────

test('unlinkedSupervisors lists distinct unlinked names with student and pair counts', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);

    // Two students under the same unlinked name in the same pair.
    FypProject::factory()->count(2)->create([
        'supervisor_name' => 'Raw Name A',
        'supervisor_id'   => null,
        'semester'        => 'MARCH 2026',
        'pair_number'     => 1,
    ]);
    // One student under a different unlinked name (no pair).
    FypProject::factory()->create([
        'supervisor_name' => 'Raw Name B',
        'supervisor_id'   => null,
        'pair_number'     => null,
    ]);

    $this->actingAs($coordinator);

    $rows = Livewire::test('user-management')->get('unlinkedSupervisors');
    $names = collect($rows)->pluck('supervisor_name')->all();

    expect($names)->toContain('Raw Name A', 'Raw Name B');

    $rowA = collect($rows)->firstWhere('supervisor_name', 'Raw Name A');
    expect($rowA['student_count'])->toBe(2);
});

test('unlinkedSupervisors excludes rows already linked via supervisor_id', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    $linkedSup   = User::factory()->create(['role' => 'supervisor', 'name' => 'Linked Sup']);

    FypProject::factory()->forSupervisor($linkedSup)->create();
    FypProject::factory()->create(['supervisor_name' => 'Unlinked Name', 'supervisor_id' => null]);

    $this->actingAs($coordinator);

    $rows  = Livewire::test('user-management')->get('unlinkedSupervisors');
    $names = collect($rows)->pluck('supervisor_name')->all();

    expect($names)->toContain('Unlinked Name')
        ->and($names)->not->toContain('Linked Sup');
});

test('the unlinked supervisors panel is rendered when unlinked names exist', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    FypProject::factory()->create(['supervisor_name' => 'Panel Sup', 'supervisor_id' => null]);

    $this->actingAs($coordinator);

    Livewire::test('user-management')
        ->assertSee('Unlinked supervisors')
        ->assertSee('Panel Sup');
});

test('the unlinked supervisors panel is hidden when all names are linked', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    $sup         = User::factory()->create(['role' => 'supervisor', 'name' => 'All Linked']);

    FypProject::factory()->forSupervisor($sup)->create();

    $this->actingAs($coordinator);

    Livewire::test('user-management')
        ->assertDontSee('Unlinked supervisors');
});
