<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('register', 'auth.register')
        ->name('register');

    Volt::route('login', 'auth.login')
        ->name('login');

    Volt::route('forgot-password', 'auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'auth.reset-password')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'auth.verify-email')
        ->name('verification.notice');

    Volt::route('confirm-password', 'auth.confirm-password')
        ->name('password.confirm');

    Volt::route('settings/profile', 'pages::settings.profile')
        ->name('profile.edit');

    Volt::route('settings/security', 'pages::settings.security')
        ->middleware('password.confirm')
        ->name('security.edit');

    Volt::route('settings/appearance', 'pages::settings.appearance')
        ->name('appearance.edit');
});
