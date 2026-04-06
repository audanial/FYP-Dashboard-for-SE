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

test('home redirects authenticated admins to the admin dashboard', function () {
    $user = User::factory()->create([
        'role' => 'admin',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('home'));

    $response->assertRedirect(route('admin.dashboard'));
});

test('home redirects authenticated supervisors to the supervisor dashboard', function () {
    $user = User::factory()->create([
        'role' => 'supervisor',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('home'));

    $response->assertRedirect(route('supervisor.dashboard'));
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

test('dashboard shows the assessor column and values', function () {
    $user = User::factory()->create();
    $project = FypProject::factory()->create([
        'fyp_phase' => 'FYP 1',
        'semester' => 'MARCH 2026',
        'assessor_name' => 'Dr. Example Assessor',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Assessor');
    $response->assertSee($project->assessor_name);
});

test('students cannot access the admin dashboard', function () {
    $user = User::factory()->create([
        'role' => 'student',
    ]);
    $this->actingAs($user);

    $this->get(route('admin.dashboard'))->assertForbidden();
});

test('supervisors cannot access the student dashboard', function () {
    $user = User::factory()->create([
        'role' => 'supervisor',
    ]);
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertForbidden();
});
