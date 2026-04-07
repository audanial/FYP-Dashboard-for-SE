<?php

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\LivewireManager;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route(Auth::user()->dashboardRouteName());
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')
        ->middleware('role:coordinator,supervisor,student')
        ->name('dashboard');

    Route::get('student/logbook', function () {
        $container = Container::getInstance();

        return $container->call([
            $container->make(LivewireManager::class)->new('my-logbook'),
            '__invoke',
        ]);
    })
        ->middleware('role:student')
        ->name('student.logbook');

    Route::get('supervisor/students', function () {
        $container = Container::getInstance();

        return $container->call([
            $container->make(LivewireManager::class)->new('my-students'),
            '__invoke',
        ]);
    })
        ->middleware('role:supervisor')
        ->name('supervisor.students');

    Volt::route('supervisor/students/{studentId}/logbook', 'pages::supervisor-student-logbook')
        ->middleware('role:supervisor')
        ->name('supervisor.students.logbook');

    Route::get('admin/users', function (Request $request) {
        if (! $request->user()->hasRole('coordinator')) {
            return redirect()->route($request->user()->dashboardRouteName());
        }

        $container = Container::getInstance();

        return $container->call([
            $container->make(LivewireManager::class)->new('user-management'),
            '__invoke',
        ]);
    })
        ->name('admin.users');
});

require __DIR__.'/auth.php';
