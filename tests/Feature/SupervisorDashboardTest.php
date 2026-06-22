<?php

use App\Models\FypProject;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Sprint 5: Supervisor Dashboard
 *
 * Covers route resolution, access control, the three dashboard widgets on the
 * dedicated supervisor.dashboard page, and the supervisor panel embedded in the
 * shared /dashboard for all roles.
 */

// ── Route resolution ──────────────────────────────────────────────────────────

test('dashboardRouteName returns dashboard for supervisors', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);

    expect($supervisor->dashboardRouteName())->toBe('dashboard');
});

test('dashboardRouteName still returns dashboard for coordinators', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);

    expect($coordinator->dashboardRouteName())->toBe('dashboard');
});

test('dashboardRouteName still returns dashboard for students', function () {
    $student = User::factory()->create(['role' => 'student']);

    expect($student->dashboardRouteName())->toBe('dashboard');
});

// ── Home redirect ─────────────────────────────────────────────────────────────

test('home redirects authenticated supervisors to the dashboard', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);
    $this->actingAs($supervisor);

    $this->get(route('home'))
        ->assertRedirect(route('dashboard'));
});

// ── /dashboard accessible to supervisors ─────────────────────────────────────

test('supervisors can visit the dashboard route', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);
    $this->actingAs($supervisor);

    $this->get(route('dashboard'))->assertOk();
});

// ── Access control for the dedicated supervisor page ──────────────────────────

test('supervisors can access the supervisor dashboard', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);
    $this->actingAs($supervisor);

    $this->get(route('supervisor.dashboard'))->assertOk();
});

test('coordinators cannot access the supervisor dashboard', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    $this->actingAs($coordinator);

    $this->get(route('supervisor.dashboard'))->assertForbidden();
});

test('students cannot access the supervisor dashboard', function () {
    $student = User::factory()->create(['role' => 'student']);
    $this->actingAs($student);

    $this->get(route('supervisor.dashboard'))->assertForbidden();
});

test('guests are redirected to login from the supervisor dashboard', function () {
    $this->get(route('supervisor.dashboard'))->assertRedirect(route('login'));
});

// ── Supervisor panel on the shared /dashboard ─────────────────────────────────

test('supervisor visiting the shared dashboard sees the assigned students panel', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);

    FypProject::factory()->forSupervisor($supervisor)->create([
        'student_name' => 'Test Student Alpha',
    ]);

    $this->actingAs($supervisor);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My Assigned Students')
        ->assertSee('Test Student Alpha');
});

test('coordinator visiting the shared dashboard does not see the supervisor panel', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    $this->actingAs($coordinator);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('My Assigned Students');
});

test('student visiting the shared dashboard does not see the supervisor panel', function () {
    $student = User::factory()->create(['role' => 'student']);
    $this->actingAs($student);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('My Assigned Students');
});

test('supervisor visiting the shared dashboard still sees shared analytics', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);
    $this->actingAs($supervisor);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Total SE Students');
});

// ── Dedicated supervisor.dashboard content ────────────────────────────────────

test('supervisor dashboard shows only their assigned student count', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);
    $other      = User::factory()->create(['role' => 'supervisor']);

    FypProject::factory()->count(3)->forSupervisor($supervisor)->create();
    FypProject::factory()->count(2)->forSupervisor($other)->create();

    $this->actingAs($supervisor);

    // Must see 3 (their count) — cohort total is 5 which would be wrong
    $this->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertSee('3 Assigned');
});

test('supervisor dashboard shows student names and project titles', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);

    FypProject::factory()->forSupervisor($supervisor)->create([
        'student_name' => 'Siti Hajar binti Razak',
        'title'        => 'Smart Campus Navigation System',
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertSee('Siti Hajar binti Razak')
        ->assertSee('Smart Campus Navigation System');
});

test('supervisor dashboard shows last logbook date for a linked student', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);

    FypProject::factory()->forSupervisor($supervisor)->create([
        'student_id' => 'SV2026001',
    ]);

    $student = User::factory()->create([
        'username' => 'SV2026001',
        'role'     => 'student',
    ]);

    Logbook::factory()->create([
        'user_id' => $student->id,
        'date'    => '2026-06-10',
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertSee('10 Jun 2026');
});

test('supervisor dashboard flags a student with no logbook entry as needing attention', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);

    FypProject::factory()->forSupervisor($supervisor)->create([
        'student_id' => 'SV2026002',
    ]);

    // Student has a linked account but zero logbook entries
    User::factory()->create([
        'username' => 'SV2026002',
        'role'     => 'student',
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertSee('Needs Attention');
});

test('supervisor dashboard flags a student with a stale logbook entry as needing attention', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);

    FypProject::factory()->forSupervisor($supervisor)->create([
        'student_id' => 'SV2026003',
    ]);

    $student = User::factory()->create([
        'username' => 'SV2026003',
        'role'     => 'student',
    ]);

    Logbook::factory()->create([
        'user_id' => $student->id,
        'date'    => now()->subDays(15)->format('Y-m-d'),
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertSee('Needs Attention');
});

test('supervisor dashboard does not flag a student with a recent logbook entry', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);

    FypProject::factory()->forSupervisor($supervisor)->create([
        'student_id' => 'SV2026004',
    ]);

    $student = User::factory()->create([
        'username' => 'SV2026004',
        'role'     => 'student',
    ]);

    // Entry within the last 14 days
    Logbook::factory()->create([
        'user_id' => $student->id,
        'date'    => now()->subDays(7)->format('Y-m-d'),
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertDontSee('Needs Attention');
});
