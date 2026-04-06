<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route(Auth::user()->dashboardRouteName());
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')
        ->middleware('role:student')
        ->name('dashboard');

    Route::view('admin/dashboard', 'dashboard')
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::view('supervisor/dashboard', 'dashboard')
        ->middleware('role:supervisor')
        ->name('supervisor.dashboard');
});

require __DIR__.'/auth.php';
