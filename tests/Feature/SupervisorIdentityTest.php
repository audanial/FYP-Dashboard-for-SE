<?php

use App\Models\FypProject;
use App\Models\User;

/*
 * Sprint 4 — Phase 3 (identity reads, My Students only).
 *
 * Ownership reads must prefer the structural supervisor_id FK, keeping the legacy
 * supervisor_name match only as a fallback for rows that are not yet linked
 * (supervisor_id IS NULL). ID match takes precedence: a row linked to another
 * supervisor must never surface through a coincidental name match.
 */

test('a supervisor sees a student linked by supervisor_id even when the supervisor_name string does not match', function () {
    // The production defect: the account name and the imported CSV string differ.
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Tiliza binti Awang Mat',
    ]);

    FypProject::factory()->create([
        'student_name'    => 'Identity Anchor Student',
        'student_id'      => '522120009001',
        'supervisor_name' => 'Tiliza Awang Mat - Ts.', // deliberately mismatched
        'supervisor_id'   => $supervisor->id,           // but structurally linked
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.students'))
        ->assertOk()
        ->assertSee('Identity Anchor Student')
        ->assertSee('522120009001');
});

test('a supervisor does not see a project owned by another supervisor even when supervisor_name matches their own name', function () {
    $current = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Shared Supervisor Name',
    ]);

    $other = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Other Supervisor',
    ]);

    // supervisor_name coincides with the current user's name, but the row is
    // structurally owned by someone else. ID precedence must win.
    FypProject::factory()->create([
        'student_name'    => 'Wrong Access Student',
        'student_id'      => '522120009002',
        'supervisor_name' => 'Shared Supervisor Name',
        'supervisor_id'   => $other->id,
    ]);

    $this->actingAs($current);

    $this->get(route('supervisor.students'))
        ->assertOk()
        ->assertDontSee('Wrong Access Student')
        ->assertDontSee('522120009002');
});

test('a supervisor still sees an unlinked project (supervisor_id is null) that matches their name', function () {
    // Backward-compatible fallback: legacy rows have no supervisor_id yet and must
    // stay visible through the name match until they are backfilled in a later phase.
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Legacy Named Supervisor',
    ]);

    FypProject::factory()->create([
        'student_name'    => 'Legacy Fallback Student',
        'student_id'      => '522120009003',
        'supervisor_name' => 'Legacy Named Supervisor',
        'supervisor_id'   => null,
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.students'))
        ->assertOk()
        ->assertSee('Legacy Fallback Student')
        ->assertSee('522120009003');
});
