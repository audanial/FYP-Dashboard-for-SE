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

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
            <tr class="border-b border-neutral-100 bg-neutral-50/50">
                <th class="p-3 text-xs font-semibold text-gray-600 uppercase">User Name</th>
                <th class="p-3 text-xs font-semibold text-gray-600 uppercase">Email Address</th>
                <th class="p-3 text-xs font-semibold text-gray-600 uppercase">Current Role</th>
                <th class="p-3 text-xs font-semibold text-gray-600 uppercase">Assign New Role</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
            @foreach($this->filteredUsers as $user)
                <tr class="hover:bg-neutral-50/50 transition-colors">
                    <td class="p-3 text-sm text-gray-700 font-medium">{{ $user->name }}</td>
                    <td class="p-3 text-sm text-gray-500">{{ $user->email }}</td>
                    <td class="p-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200 uppercase">
                            {{ $user->role }}
                        </span>
                    </td>
                    <td class="p-3">
                        <select wire:change="updateRole({{ $user->id }}, $event.target.value)"
                                class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="student" {{ $user->role == 'student' ? 'selected' : '' }}>Student</option>
                            <option value="supervisor" {{ $user->role == 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                            <option value="coordinator" {{ $user->role == 'coordinator' ? 'selected' : '' }}>Coordinator</option>
                        </select>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
