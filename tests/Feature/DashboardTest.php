<?php

use App\Models\FypProject;
use App\Models\User;

test('home redirects guests to the login page', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('home redirects authenticated users to the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});

test('home redirects authenticated coordinators to the dashboard', function () {
    $user = User::factory()->create([
        'role' => 'coordinator',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});

test('home redirects authenticated supervisors to the dashboard', function () {
    $user = User::factory()->create([
        'role' => 'supervisor',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee("window.Flux.applyAppearance('light');", false);
    $response->assertDontSee('<html lang="en" class="dark">', false);
});

test('coordinators can visit the dashboard route', function () {
    $user = User::factory()->create([
        'role' => 'coordinator',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk();
});

test('supervisors can visit the dashboard route', function () {
    $user = User::factory()->create([
        'role' => 'supervisor',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk();
});

test('student dashboard shows minimal sidebar navigation', function () {
    $user = User::factory()->create([
        'role' => 'student',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Navigation')
        ->assertSee(route('dashboard'), false)
        ->assertSee(route('student.logbook'), false)
        ->assertSee('My Logbook');
});

test('coordinator dashboard shows role-aware sidebar navigation', function () {
    $user = User::factory()->create([
        'role' => 'coordinator',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('dashboard'), false)
        ->assertSee(route('admin.users'), false)
        ->assertSee('Manage Users');
});

test('only coordinators see csv import controls on the fyp projects page', function () {
    $coordinator = User::factory()->create([
        'role' => 'coordinator',
    ]);

    $this->actingAs($coordinator);

    $this->get(route('fyp.projects'))
        ->assertOk()
        ->assertSee('Import CSV')
        ->assertSee('type="file"', false);
});

test('supervisor dashboard shows role-aware sidebar navigation', function () {
    $user = User::factory()->create([
        'role' => 'supervisor',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('dashboard'), false)
        ->assertSee(route('supervisor.students'), false)
        ->assertSee('My Students');
});

test('supervisors do not see dashboard csv import controls', function () {
    $user = User::factory()->create([
        'role' => 'supervisor',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Import CSV')
        ->assertDontSee('type="file"', false);
});

test('students do not see dashboard csv import controls', function () {
    $user = User::factory()->create([
        'role' => 'student',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Import CSV')
        ->assertDontSee('type="file"', false);
});

test('coordinator can access the user management page', function () {
    $user = User::factory()->create([
        'role' => 'coordinator',
    ]);

    $this->actingAs($user);

    $this->get(route('admin.users'))
        ->assertOk()
        ->assertSee('User Role Management');
});

test('students are redirected away from the user management page', function () {
    $user = User::factory()->create([
        'role' => 'student',
    ]);

    $this->actingAs($user);

    $this->get(route('admin.users'))
        ->assertRedirect(route('dashboard'));
});

test('supervisors are redirected away from the user management page', function () {
    $user = User::factory()->create([
        'role' => 'supervisor',
    ]);

    $this->actingAs($user);

    $this->get(route('admin.users'))
        ->assertRedirect(route('dashboard'));
});

test('fyp projects page shows the assessor column and values', function () {
    $user = User::factory()->create();
    $project = FypProject::factory()->create([
        'fyp_phase' => 'FYP 1',
        'semester' => 'MARCH 2026',
        'assessor_name' => 'Dr. Example Assessor',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('fyp.projects'));

    $response->assertOk();
    $response->assertSee('Assessor');
    $response->assertSee($project->assessor_name);
});

test('fyp projects page platform filter shows distinct app types from projects', function () {
    $user = User::factory()->create([
        'role' => 'coordinator',
    ]);

    FypProject::factory()->create([
        'application_type' => 'Cross Platform',
    ]);

    FypProject::factory()->create([
        'application_type' => 'Desktop App',
    ]);

    FypProject::factory()->create([
        'application_type' => 'Cross Platform',
    ]);

    $this->actingAs($user);

    $this->get(route('fyp.projects'))
        ->assertOk()
        ->assertSee('All Platforms')
        ->assertSee('Cross Platform')
        ->assertSee('Desktop App');
});
