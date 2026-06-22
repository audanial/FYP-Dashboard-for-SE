<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registering with the coordinator access code assigns the coordinator role', function () {
    $action = new CreateNewUser;

    $user = $action->create([
        'name'              => 'Program Coordinator',
        'email'             => 'pc@unikl.edu.my',
        'password'          => 'Password1!',
        'password_confirmation' => 'Password1!',
        'staff_access_code' => 'SE-PC-2026',
    ]);

    expect($user->role)->toBe('coordinator');
});

test('the database seeder creates the coordinator account with the coordinator role', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    $coordinator = User::where('email', 'pc@unikl.edu.my')->first();

    expect($coordinator)->not->toBeNull()
        ->and($coordinator->role)->toBe('coordinator');
});
