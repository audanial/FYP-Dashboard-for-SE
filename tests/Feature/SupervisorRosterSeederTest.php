<?php

use App\Models\Supervisor;
use App\Models\User;
use Database\Seeders\SupervisorRosterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Item 6: seed the SE lecturer roster from storage/app/csv/SE_Lecturers.csv.
 * Each roster row becomes a supervisors record AND an auto-created users
 * account (role supervisor) linked via supervisors.user_id.
 */

test('it seeds every roster row as a supervisor with a linked supervisor user', function () {
    $this->seed(SupervisorRosterSeeder::class);

    // The roster CSV currently holds 27 lecturers.
    expect(Supervisor::count())->toBe(27)
        ->and(User::where('role', 'supervisor')->count())->toBeGreaterThanOrEqual(27);

    // Every roster row is linked to an auto-created login account.
    expect(Supervisor::whereNull('user_id')->count())->toBe(0);
});

test('it stores the normalized name_slug and confirmed flag from the roster', function () {
    $this->seed(SupervisorRosterSeeder::class);

    $tiliza = Supervisor::where('email', 'tiliza@unikl.edu.my')->first();
    expect($tiliza)->not->toBeNull()
        ->and($tiliza->name_slug)->toBe('tiliza_awang_mat')
        ->and($tiliza->confirmed)->toBeTrue();

    // A row with a blank "Confirmed?" cell stores false.
    $azaliza = Supervisor::where('email', 'azaliza@unikl.edu.my')->first();
    expect($azaliza)->not->toBeNull()
        ->and($azaliza->confirmed)->toBeFalse();
});

test('the auto-created account is an active supervisor matching the roster', function () {
    $this->seed(SupervisorRosterSeeder::class);

    $tiliza = Supervisor::where('email', 'tiliza@unikl.edu.my')->first();
    $user   = $tiliza->user;

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('supervisor')
        ->and($user->is_active)->toBeTrue()
        ->and($user->email)->toBe('tiliza@unikl.edu.my');
});

test('seeding twice is idempotent and creates no duplicates', function () {
    $this->seed(SupervisorRosterSeeder::class);
    $this->seed(SupervisorRosterSeeder::class);

    expect(Supervisor::count())->toBe(27)
        ->and(User::where('email', 'tiliza@unikl.edu.my')->count())->toBe(1);
});
