<?php

use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
    $this->actingAs($this->coordinator);
});

test('coordinator creates a supervisor with role forced to supervisor and account active', function () {
    Livewire::test('user-management')
        ->set('newName', 'Tiliza binti Awang Mat')
        ->set('newEmail', 'tiliza@unikl.edu.my')
        ->call('createSupervisor')
        ->assertHasNoErrors();

    $created = User::where('email', 'tiliza@unikl.edu.my')->first();

    expect($created)->not->toBeNull()
        ->and($created->role)->toBe('supervisor')
        ->and((bool) $created->is_active)->toBeTrue();
});

test('coordinator-provisioned supervisor is email-verified immediately', function () {
    Livewire::test('user-management')
        ->set('newName', 'Verified Sup')
        ->set('newEmail', 'verified@unikl.edu.my')
        ->call('createSupervisor')
        ->assertHasNoErrors();

    $created = User::where('email', 'verified@unikl.edu.my')->first();

    expect($created->email_verified_at)->not->toBeNull();
});

test('createSupervisor surfaces a one-time temp password and stores it hashed', function () {
    $component = Livewire::test('user-management')
        ->set('newName', 'New Sup')
        ->set('newEmail', 'newsup@unikl.edu.my')
        ->call('createSupervisor');

    $temp = $component->get('newTempPassword');

    expect($temp)->toBeString()->not->toBeEmpty();

    $created = User::where('email', 'newsup@unikl.edu.my')->first();
    expect(Hash::check($temp, $created->password))->toBeTrue();
});

test('createSupervisor rejects a duplicate email', function () {
    User::factory()->create(['email' => 'dupe@unikl.edu.my']);

    Livewire::test('user-management')
        ->set('newName', 'Dupe Sup')
        ->set('newEmail', 'dupe@unikl.edu.my')
        ->call('createSupervisor')
        ->assertHasErrors(['newEmail']);
});

test('a non-coordinator cannot create a supervisor', function () {
    $student = User::factory()->create(['role' => 'student']);
    $this->actingAs($student);

    Livewire::test('user-management')
        ->set('newName', 'Hacker Sup')
        ->set('newEmail', 'hacker@unikl.edu.my')
        ->call('createSupervisor')
        ->assertForbidden();
});

// ── Task 3: modal UI ──────────────────────────────────────────────────────────

test('the page header renders a create supervisor button', function () {
    Livewire::test('user-management')
        ->assertSee('Create supervisor');
});

test('the create supervisor modal renders name and email input bindings', function () {
    Livewire::test('user-management')
        ->assertSeeHtml('wire:model="newName"')
        ->assertSeeHtml('wire:model="newEmail"');
});

test('the temp password block is rendered after a supervisor is created', function () {
    Livewire::test('user-management')
        ->set('newName', 'UI Sup')
        ->set('newEmail', 'uisup@unikl.edu.my')
        ->call('createSupervisor')
        ->assertSee('Temporary password');
});
