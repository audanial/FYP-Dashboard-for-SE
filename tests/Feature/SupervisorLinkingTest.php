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

// ── Task 5: link unlinked name to existing supervisor ─────────────────────────

test('confirmLink sets supervisor_id on every row sharing the raw name and preserves supervisor_name', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    $sup         = User::factory()->create(['role' => 'supervisor', 'name' => 'Account Name']);

    // Two students share the raw CSV string — both must be linked.
    FypProject::factory()->count(2)->create([
        'supervisor_name' => 'Raw CSV String',
        'supervisor_id'   => null,
    ]);

    $this->actingAs($coordinator);

    Livewire::test('user-management')
        ->call('openLinkModal', 'Raw CSV String')
        ->set('linkTargetSupId', $sup->id)
        ->call('confirmLink')
        ->assertHasNoErrors();

    $rows = FypProject::where('supervisor_name', 'Raw CSV String')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->every(fn ($r) => $r->supervisor_id === $sup->id))->toBeTrue()
        ->and($rows->every(fn ($r) => $r->supervisor_name === 'Raw CSV String'))->toBeTrue();
});

test('confirmLink only affects unlinked rows — already-linked rows with the same name are untouched', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    $supA        = User::factory()->create(['role' => 'supervisor', 'name' => 'Sup A']);
    $supB        = User::factory()->create(['role' => 'supervisor', 'name' => 'Sup B']);

    // One row already linked to supA but sharing the same supervisor_name string.
    FypProject::factory()->create([
        'supervisor_name' => 'Shared Name',
        'supervisor_id'   => $supA->id,
    ]);
    // One unlinked row with the same string — should be linked to supB.
    FypProject::factory()->create([
        'supervisor_name' => 'Shared Name',
        'supervisor_id'   => null,
    ]);

    $this->actingAs($coordinator);

    Livewire::test('user-management')
        ->call('openLinkModal', 'Shared Name')
        ->set('linkTargetSupId', $supB->id)
        ->call('confirmLink')
        ->assertHasNoErrors();

    // Already-linked row must not be overwritten.
    expect(FypProject::where('supervisor_id', $supA->id)->count())->toBe(1);
    // Unlinked row is now linked to supB.
    expect(FypProject::where('supervisor_name', 'Shared Name')->where('supervisor_id', $supB->id)->count())->toBe(1);
});

test('confirmLink rejects a user who is not a supervisor', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    $student     = User::factory()->create(['role' => 'student']);

    FypProject::factory()->create(['supervisor_name' => 'Raw Name', 'supervisor_id' => null]);

    $this->actingAs($coordinator);

    Livewire::test('user-management')
        ->call('openLinkModal', 'Raw Name')
        ->set('linkTargetSupId', $student->id)
        ->call('confirmLink')
        ->assertHasErrors(['linkTargetSupId']);
});

test('a non-coordinator cannot call confirmLink', function () {
    $student = User::factory()->create(['role' => 'student']);
    $sup     = User::factory()->create(['role' => 'supervisor']);

    FypProject::factory()->create(['supervisor_name' => 'Raw Name', 'supervisor_id' => null]);

    $this->actingAs($student);

    Livewire::test('user-management')
        ->set('linkRawName', 'Raw Name')
        ->set('linkTargetSupId', $sup->id)
        ->call('confirmLink')
        ->assertForbidden();
});

test('the link modal renders the supervisor select binding', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    $this->actingAs($coordinator);

    Livewire::test('user-management')
        ->assertSeeHtml('wire:model="linkTargetSupId"');
});
