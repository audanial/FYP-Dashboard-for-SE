<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
    $this->withoutMiddleware(PreventRequestForgery::class);
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(User::query()->where('email', 'test@example.com')->first()?->role)->toBe('student');
});

test('staff access code creates an admin account case insensitively', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'staff_access_code' => 'se-pc-2026',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->where('email', 'admin@example.com')->first()?->role)->toBe('admin');
});

test('staff access code creates a supervisor account case insensitively', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Supervisor User',
        'email' => 'supervisor@example.com',
        'staff_access_code' => 'Se-Sv-2026',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->where('email', 'supervisor@example.com')->first()?->role)->toBe('supervisor');
});
