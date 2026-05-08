<?php

use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinator = User::factory()->create([
        'name'      => 'Test Coordinator',
        'role'      => 'coordinator',
        'is_active' => true,
    ]);
    $this->actingAs($this->coordinator);
});

// ── Route access ─────────────────────────────────────────────────────────────

it('allows a coordinator to access the manage users page', function () {
    $this->get(route('admin.users'))->assertOk();
});

it('redirects a student away from the manage users page', function () {
    $student = User::factory()->create(['role' => 'student']);
    $this->actingAs($student)
         ->get(route('admin.users'))
         ->assertRedirect();
});

// ── Filtering — these fail until Task 2 ──────────────────────────────────────

it('filters the table by name search', function () {
    User::factory()->create(['name' => 'Ahmad Razif', 'role' => 'student']);
    User::factory()->create(['name' => 'Nurul Ain',   'role' => 'student']);

    Livewire::test('user-management')
        ->set('search', 'Ahmad')
        ->assertSee('Ahmad Razif')
        ->assertDontSee('Nurul Ain');
});

it('filters the table by email search', function () {
    User::factory()->create(['name' => 'Alpha User', 'email' => 'alpha@test.edu', 'role' => 'student']);
    User::factory()->create(['name' => 'Beta User',  'email' => 'beta@test.edu',  'role' => 'student']);

    Livewire::test('user-management')
        ->set('search', 'alpha@')
        ->assertSee('Alpha User')
        ->assertDontSee('Beta User');
});

it('filters the table to students only when role tab is student', function () {
    User::factory()->create(['name' => 'Student One',    'role' => 'student']);
    User::factory()->create(['name' => 'Supervisor One', 'role' => 'supervisor']);

    Livewire::test('user-management')
        ->set('roleFilter', 'student')
        ->assertSee('Student One')
        ->assertDontSee('Supervisor One');
});

it('filters the table to supervisors only when role tab is supervisor', function () {
    User::factory()->create(['name' => 'Student One',    'role' => 'student']);
    User::factory()->create(['name' => 'Supervisor One', 'role' => 'supervisor']);

    Livewire::test('user-management')
        ->set('roleFilter', 'supervisor')
        ->assertSee('Supervisor One')
        ->assertDontSee('Student One');
});

it('shows all users when role filter is all', function () {
    User::factory()->create(['name' => 'Student One',    'role' => 'student']);
    User::factory()->create(['name' => 'Supervisor One', 'role' => 'supervisor']);

    Livewire::test('user-management')
        ->assertSee('Student One')
        ->assertSee('Supervisor One');
});

// ── Display — these fail until Task 6 ────────────────────────────────────────

it('shows empty state message when search matches no users', function () {
    User::factory()->create(['name' => 'Ahmad Razif', 'role' => 'student']);

    Livewire::test('user-management')
        ->set('search', 'zzznomatch')
        ->assertSee('No users match the current search or filter.');
});

it('shows Not assigned for users without a department', function () {
    User::factory()->create(['name' => 'No Dept User', 'role' => 'student', 'department' => null]);

    Livewire::test('user-management')
        ->assertSee('Not assigned');
});

it('shows Inactive badge for deactivated users', function () {
    User::factory()->create(['name' => 'Inactive User', 'role' => 'student', 'is_active' => false]);

    Livewire::test('user-management')
        ->assertSee('Inactive');
});
