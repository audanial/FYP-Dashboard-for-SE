<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('auth')->group(function () {
    Volt::route('settings/profile', 'pages::settings.profile')
        ->name('profile.edit');

    Volt::route('settings/security', 'pages::settings.security')
        ->middleware('password.confirm')
        ->name('security.edit');

    Volt::route('settings/appearance', 'pages::settings.appearance')
        ->name('appearance.edit');
});
