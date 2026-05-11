<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, computed};

state([
    'search'     => '',
    'roleFilter' => 'all',
]);

$filteredUsers = computed(function () {
    return User::query()
        ->when($this->roleFilter !== 'all', fn($q) => $q->where('role', $this->roleFilter))
        ->when($this->search, fn($q) => $q->where(function ($q) {
            $q->where('name', 'like', '%' . $this->search . '%')
              ->orWhere('email', 'like', '%' . $this->search . '%');
        }))
        ->orderBy('name')
        ->get();
});

$stats = computed(fn() => [
    'students'     => User::where('role', 'student')->count(),
    'supervisors'  => User::where('role', 'supervisor')->count(),
    'coordinators' => User::where('role', 'coordinator')->count(),
]);

$updateRole = function ($userId, $newRole) {
    abort_unless(Auth::user()?->role === 'coordinator', 403);
    abort_unless(in_array($newRole, ['student', 'supervisor', 'coordinator']), 422);

    if (Auth::id() === (int) $userId) {
        session()->flash('error', 'You cannot change your own role.');
        return;
    }

    $user = User::findOrFail($userId);
    $user->update(['role' => $newRole]);

    session()->flash('message', 'User role updated successfully!');
};

?>

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

    {{-- ① PAGE HEADER --}}
    <div class="border-b border-gray-100 px-6 py-5">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Manage Users</h1>
                <p class="mt-1 text-sm text-gray-500">View and manage all registered users in the FYP system.</p>
            </div>
            <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                <span class="text-xs font-medium text-gray-600">FYP Coordinator</span>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    <div class="px-6">
        @if (session()->has('message'))
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif
    </div>

    {{-- ② STAT CARDS --}}
    <div class="grid grid-cols-1 gap-4 border-b border-gray-100 bg-gray-50 px-6 py-5 sm:grid-cols-3">

        {{-- Students --}}
        <div class="rounded-xl border border-sky-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-600">Total Students</p>
                    <p class="mt-2 text-3xl font-bold text-sky-900">{{ $this->stats['students'] }}</p>
                </div>
                <div class="rounded-lg bg-sky-100 p-2 text-sky-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.627 48.627 0 0 1 12 20.904a48.627 48.627 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.57 50.57 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.902 59.902 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Supervisors --}}
        <div class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Total Supervisors</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $this->stats['supervisors'] }}</p>
                </div>
                <div class="rounded-lg bg-emerald-100 p-2 text-emerald-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Coordinators --}}
        <div class="rounded-xl border border-violet-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-violet-600">FYP Coordinators</p>
                    <p class="mt-2 text-3xl font-bold text-violet-900">{{ $this->stats['coordinators'] }}</p>
                </div>
                <div class="rounded-lg bg-violet-100 p-2 text-violet-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                    </svg>
                </div>
            </div>
        </div>

    </div>

    {{-- ③ FILTER TABS + SEARCH --}}
    <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-4 md:flex-row md:items-center md:justify-between">

        {{-- Role filter pill tabs --}}
        <div class="flex items-center gap-1 rounded-xl bg-gray-100 p-1">
            <button wire:click="$set('roleFilter', 'all')"
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition-colors {{ $roleFilter === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                All
            </button>
            <button wire:click="$set('roleFilter', 'student')"
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition-colors {{ $roleFilter === 'student' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Students
            </button>
            <button wire:click="$set('roleFilter', 'supervisor')"
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition-colors {{ $roleFilter === 'supervisor' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Supervisors
            </button>
            <button wire:click="$set('roleFilter', 'coordinator')"
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition-colors {{ $roleFilter === 'coordinator' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                FYP Coordinators
            </button>
        </div>

        {{-- Search input with clear button --}}
        <div class="relative" x-data="{ hasText: false }">
            <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 text-gray-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </div>
            <input wire:model.live="search"
                   type="text"
                   @input="hasText = $el.value.length > 0"
                   placeholder="Search by name or email…"
                   class="block w-full rounded-lg border border-gray-200 py-2 pl-9 pr-9 text-sm text-gray-700 placeholder-gray-400 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 md:w-72">
            <button x-show="hasText"
                    x-transition
                    @click="hasText = false; $wire.set('search', '')"
                    type="button"
                    class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

    </div>

    {{-- ④ USER TABLE --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Email</th>
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Role</th>
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Department</th>
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($this->filteredUsers as $user)
                    <tr class="transition-colors hover:bg-gray-50/50">
                        <td class="px-6 py-4">
                            <div class="font-semibold text-gray-900">{{ $user->name }}</div>
                            <div class="text-xs text-gray-400">{{ $user->displayUsername() }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $user->email }}</td>
                        <td class="px-6 py-4">
                            @php
                                $roleClasses = match($user->role) {
                                    'student'     => 'bg-blue-100 text-blue-700',
                                    'supervisor'  => 'bg-emerald-100 text-emerald-700',
                                    'coordinator' => 'bg-violet-100 text-violet-700',
                                    default       => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize {{ $roleClasses }}">
                                {{ $user->role }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $user->department ?? 'Not assigned' }}
                        </td>
                        <td class="px-6 py-4">
                            @if($user->is_active)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">Active</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <button type="button"
                                    disabled
                                    title="Edit functionality coming in a future step"
                                    class="cursor-not-allowed rounded-md border border-gray-200 px-3 py-1 text-xs font-medium text-gray-400 opacity-50">
                                Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-400">
                            No users match the current search or filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
