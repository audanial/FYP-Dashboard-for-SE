<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Unit-level tests for FypProject::scopeForSupervisor().
 *
 * These three cases mirror the dual-read contract used by both
 * my-students.blade.php and supervisor-student-logbook.blade.php.
 * Having the contract in one place means the HTTP tests for each
 * page can stay as integration guards while the logic lives here.
 */

test('forSupervisor scope returns a project linked by supervisor_id even when supervisor_name differs', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'Tiliza binti Awang Mat']);

    FypProject::factory()->create([
        'student_id'      => 'SCOPE001',
        'supervisor_name' => 'Tiliza Awang Mat - Ts.', // deliberately mismatched
        'supervisor_id'   => $supervisor->id,
    ]);

    $results = FypProject::forSupervisor($supervisor->id, $supervisor->name)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->student_id)->toBe('SCOPE001');
});

test('forSupervisor scope excludes a project owned by another supervisor even when supervisor_name matches', function () {
    $current = User::factory()->create(['role' => 'supervisor', 'name' => 'Shared Name']);
    $other   = User::factory()->create(['role' => 'supervisor', 'name' => 'Other Supervisor']);

    // supervisor_name coincides with $current but the row is owned by $other —
    // ID precedence must win; $current must see nothing.
    FypProject::factory()->create([
        'student_id'      => 'SCOPE002',
        'supervisor_name' => 'Shared Name',
        'supervisor_id'   => $other->id,
    ]);

    $results = FypProject::forSupervisor($current->id, $current->name)->get();

    expect($results)->toHaveCount(0);
});

test('forSupervisor scope returns an unlinked project (supervisor_id null) that matches the name', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'Legacy Supervisor']);

    FypProject::factory()->create([
        'student_id'      => 'SCOPE003',
        'supervisor_name' => 'Legacy Supervisor',
        'supervisor_id'   => null,
    ]);

    $results = FypProject::forSupervisor($supervisor->id, $supervisor->name)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->student_id)->toBe('SCOPE003');
});
