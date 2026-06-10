<?php

use App\Models\FypProject;
use App\Models\Logbook;
use App\Models\User;

test('supervisors can view logbooks for their own assigned students', function () {
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Ts. Tiliza Binti Awang Mat',
    ]);
    $student = User::factory()->create([
        'role' => 'student',
        'username' => '522120000003',
        'name' => 'Student Three',
    ]);
    $otherStudent = User::factory()->create([
        'role' => 'student',
        'username' => '522120000004',
    ]);

    FypProject::factory()->create([
        'student_name' => 'Student Three',
        'student_id' => '522120000003',
        'title' => 'AI Attendance System',
        'domain' => 'AI',
        'application_type' => 'Web App',
        'fyp_phase' => 'FYP 1',
        'supervisor_name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    Logbook::factory()->create([
        'user_id' => $student->id,
        'title' => 'Week 1',
        'content' => 'Completed proposal draft.',
        'date' => '2026-04-01',
    ]);

    Logbook::factory()->create([
        'user_id' => $otherStudent->id,
        'title' => 'Week 2',
        'content' => 'Should not be visible.',
        'date' => '2026-04-02',
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.students.logbook', ['studentId' => '522120000003']))
        ->assertOk()
        ->assertSee('Student Logbook')
        ->assertSee('Student Three')
        ->assertSee('AI Attendance System')
        ->assertSee('Week 1')
        ->assertSee('Completed proposal draft.')
        ->assertDontSee('Should not be visible.');
});

test('supervisors cannot view logbooks for students not assigned to them', function () {
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    FypProject::factory()->create([
        'student_name' => 'Student Four',
        'student_id' => '522120000004',
        'supervisor_name' => 'Dr. Someone Else',
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.students.logbook', ['studentId' => '522120000004']))
        ->assertNotFound();
});

test('students cannot access the supervisor student logbook page', function () {
    $student = User::factory()->create([
        'role' => 'student',
    ]);

    $this->actingAs($student);

    $this->get(route('supervisor.students.logbook', ['studentId' => '522120000004']))
        ->assertForbidden();
});

/*
 * Sprint 4 — Phase 3.2 (logbook authorization, dual-read identity).
 *
 * The logbook gate must prefer the structural supervisor_id FK and fall back to
 * the legacy supervisor_name only for rows not yet linked (supervisor_id IS NULL).
 * ID precedence is mandatory and the gate stays fail-closed (firstOrFail -> 404).
 */

test('a supervisor can view the logbook of a student linked by supervisor_id even when supervisor_name does not match', function () {
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Tiliza binti Awang Mat',
    ]);
    $student = User::factory()->create([
        'role' => 'student',
        'username' => '522120009101',
        'name' => 'Linked Logbook Student',
    ]);

    FypProject::factory()->create([
        'student_name'    => 'Linked Logbook Student',
        'student_id'      => '522120009101',
        'title'           => 'Identity Linked Project',
        'supervisor_name' => 'Tiliza Awang Mat - Ts.', // deliberately mismatched
        'supervisor_id'   => $supervisor->id,           // but structurally linked
    ]);

    Logbook::factory()->create([
        'user_id' => $student->id,
        'title'   => 'Linked Week 1',
        'content' => 'Visible to the linked supervisor.',
        'date'    => '2026-04-01',
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.students.logbook', ['studentId' => '522120009101']))
        ->assertOk()
        ->assertSee('Linked Logbook Student')
        ->assertSee('Identity Linked Project')
        ->assertSee('Linked Week 1');
});

test('a supervisor cannot view a logbook owned by another supervisor even when supervisor_name matches their own name', function () {
    $current = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Shared Supervisor Name',
    ]);
    $other = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Other Supervisor',
    ]);

    // supervisor_name coincides with the current user's name, but the row is
    // structurally owned by someone else. ID precedence must keep it out of reach.
    FypProject::factory()->create([
        'student_name'    => 'Foreign Student',
        'student_id'      => '522120009102',
        'supervisor_name' => 'Shared Supervisor Name',
        'supervisor_id'   => $other->id,
    ]);

    $this->actingAs($current);

    $this->get(route('supervisor.students.logbook', ['studentId' => '522120009102']))
        ->assertNotFound();
});

test('a supervisor can view the logbook of an unlinked student (supervisor_id null) that matches their name', function () {
    // Backward-compatible fallback for legacy rows that are not yet backfilled.
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Legacy Logbook Supervisor',
    ]);
    $student = User::factory()->create([
        'role' => 'student',
        'username' => '522120009103',
        'name' => 'Legacy Logbook Student',
    ]);

    FypProject::factory()->create([
        'student_name'    => 'Legacy Logbook Student',
        'student_id'      => '522120009103',
        'title'           => 'Legacy Fallback Project',
        'supervisor_name' => 'Legacy Logbook Supervisor',
        'supervisor_id'   => null,
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.students.logbook', ['studentId' => '522120009103']))
        ->assertOk()
        ->assertSee('Legacy Logbook Student')
        ->assertSee('Legacy Fallback Project');
});
