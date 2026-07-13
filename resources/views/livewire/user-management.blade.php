<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, computed};

state([
    'search'     => '',
    'roleFilter' => 'all',

    // Edit modal
    'showEditModal'  => false,
    'editUserId'     => null,
    'editName'       => '',
    'editEmail'      => '',
    'editRole'       => '',
    'editIsActive'   => 1,
    'editDepartment' => '',

    // Reassign supervisor modal
    'showReassignModal'   => false,
    'reassignUserId'      => null,
    'reassignStudentName' => '',
    'reassignCurrentSup'  => '—',
    'reassignNewSupId'    => '',

    // Delete modal
    'showDeleteModal' => false,
    'deleteUserId'    => null,
    'deleteUserName'  => '',
]);

$filteredUsers = computed(function () {
    return User::query()
        ->when($this->roleFilter !== 'all', fn($q) => $q->where('role', $this->roleFilter))
        ->when($this->search, fn($q) => $q->where(function ($q) {
            $q->whereLike('name', '%' . $this->search . '%')
              ->orWhereLike('email', '%' . $this->search . '%');
        }))
        ->orderBy('name')
        ->get();
});

$stats = computed(fn() => [
    'students'     => User::where('role', 'student')->count(),
    'supervisors'  => User::where('role', 'supervisor')->count(),
    'coordinators' => User::where('role', 'coordinator')->count(),
]);

$supervisors = computed(fn() => User::where('role', 'supervisor')->orderBy('name')->get());

// ── Edit modal ───────────────────────────────────────────────────────────────

$openEditModal = function ($userId) {
    abort_unless(Auth::user()?->role === 'coordinator', 403);
    $user = User::findOrFail($userId);
    $this->editUserId     = $user->id;
    $this->editName       = $user->name;
    $this->editEmail      = $user->email;
    $this->editRole       = $user->role;
    $this->editIsActive   = (int) $user->is_active;
    $this->editDepartment = $user->department ?? '';
    $this->showEditModal  = true;
};

$saveUser = function () {
    abort_unless(Auth::user()?->role === 'coordinator', 403);

    $this->validate([
        'editName'       => 'required|string|max:255',
        'editEmail'      => 'required|email|unique:users,email,' . $this->editUserId,
        'editRole'       => 'required|in:student,supervisor,coordinator',
        'editIsActive'   => 'required|in:0,1',
        'editDepartment' => 'nullable|string|max:255',
    ]);

    $user = User::findOrFail($this->editUserId);

    $data = [
        'name'       => $this->editName,
        'email'      => $this->editEmail,
        'is_active'  => (bool) $this->editIsActive,
        'department' => $this->editDepartment ?: null,
    ];

    if (Auth::id() !== $user->id) {
        $data['role'] = $this->editRole;
    }

    $user->update($data);
    $this->showEditModal = false;
    session()->flash('message', 'User updated successfully!');
};

// ── Reassign supervisor modal ─────────────────────────────────────────────────

$openReassignModal = function ($userId) {
    abort_unless(Auth::user()?->role === 'coordinator', 403);
    $user = User::findOrFail($userId);
    $this->reassignUserId      = $user->id;
    $this->reassignStudentName = $user->name;
    $project = $user->username
        ? FypProject::where('student_id', $user->username)->first()
        : null;
    $this->reassignCurrentSup = $project?->supervisor_name ?? '—';
    $this->reassignNewSupId   = '';
    $this->showReassignModal  = true;
};

$saveReassign = function () {
    abort_unless(Auth::user()?->role === 'coordinator', 403);

    $this->validate([
        'reassignNewSupId' => 'required|exists:users,id',
    ]);

    $supervisor = User::findOrFail($this->reassignNewSupId);
    abort_unless($supervisor->role === 'supervisor', 422);

    $student = User::findOrFail($this->reassignUserId);
    $project = $student->username
        ? FypProject::where('student_id', $student->username)->first()
        : null;

    if ($project) {
        // Reassignment changes ownership — set both FK and canonical name for
        // the whole pair (paired rows share semester + pair_number; unpaired
        // rows are treated as a pair of one, matched by id only).
        FypProject::query()
            ->when(
                $project->pair_number !== null,
                fn ($q) => $q->where('semester', $project->semester)
                             ->where('pair_number', $project->pair_number),
                fn ($q) => $q->where('id', $project->id),
            )
            ->update([
                'supervisor_id'   => $supervisor->id,
                'supervisor_name' => $supervisor->name,
            ]);
    }

    $this->showReassignModal = false;
    session()->flash('message', 'Supervisor reassigned successfully!');
};

// ── Delete modal ─────────────────────────────────────────────────────────────

$openDeleteModal = function ($userId) {
    abort_unless(Auth::user()?->role === 'coordinator', 403);
    $user = User::findOrFail($userId);
    $this->deleteUserId   = $user->id;
    $this->deleteUserName = $user->name;
    $this->showDeleteModal = true;
};

$deleteUser = function () {
    abort_unless(Auth::user()?->role === 'coordinator', 403);

    if (Auth::id() === (int) $this->deleteUserId) {
        session()->flash('error', 'You cannot delete your own account.');
        $this->showDeleteModal = false;
        return;
    }

    User::findOrFail($this->deleteUserId)->delete();
    $this->showDeleteModal = false;
    session()->flash('message', 'User removed successfully!');
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
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                    <span class="text-xs font-medium text-gray-600">FYP Coordinator</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    <div class="px-6">
        @if (session()->has('message'))
            <div x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => { show = false }, 4000)"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => { show = false }, 4000)"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
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

        {{-- Role filter pill tabs with live counts --}}
        <div class="flex items-center gap-1 rounded-xl bg-gray-100 p-1">
            <button wire:click="$set('roleFilter', 'all')"
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition-colors {{ $roleFilter === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                All
            </button>
            <button wire:click="$set('roleFilter', 'student')"
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition-colors {{ $roleFilter === 'student' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Students ({{ $this->stats['students'] }})
            </button>
            <button wire:click="$set('roleFilter', 'supervisor')"
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition-colors {{ $roleFilter === 'supervisor' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Supervisors ({{ $this->stats['supervisors'] }})
            </button>
            <button wire:click="$set('roleFilter', 'coordinator')"
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition-colors {{ $roleFilter === 'coordinator' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                FYP Coordinators ({{ $this->stats['coordinators'] }})
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
                            <div class="flex items-center gap-1.5">

                                {{-- Reassign link — students only --}}
                                @if($user->role === 'student')
                                    <button wire:click="openReassignModal({{ $user->id }})"
                                            type="button"
                                            title="Reassign supervisor"
                                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-500 transition-colors hover:bg-gray-100 hover:text-indigo-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3.5 w-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 1 1.242 7.244" />
                                        </svg>
                                        Reassign
                                    </button>
                                @endif

                                {{-- Edit button --}}
                                <button wire:click="openEditModal({{ $user->id }})"
                                        type="button"
                                        title="Edit user"
                                        class="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                </button>

                                {{-- Delete button --}}
                                <button wire:click="openDeleteModal({{ $user->id }})"
                                        type="button"
                                        title="Remove user"
                                        class="rounded-md p-1.5 text-red-400 transition-colors hover:bg-red-50 hover:text-red-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>

                            </div>
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

    {{-- ══════════════════════════════════════════════════════════════════════
         MODALS — fixed-position, rendered inside component root
         ══════════════════════════════════════════════════════════════════ --}}

    {{-- ① EDIT MODAL --}}
    <div x-show="$wire.showEditModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         style="display: none;">
        <div x-show="$wire.showEditModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.stop
             class="relative w-full max-w-lg rounded-xl bg-white shadow-xl">

            {{-- Header --}}
            <div class="flex items-start justify-between border-b border-gray-100 px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Edit user details</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Update profile, role and status.</p>
                </div>
                <button @click="$wire.set('showEditModal', false)"
                        type="button"
                        class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="space-y-4 px-6 py-4">

                {{-- Two-column grid --}}
                <div class="grid grid-cols-2 gap-4">

                    {{-- Full Name --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Full Name</label>
                        <input wire:model="editName"
                               type="text"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        @error('editName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                        <input wire:model="editEmail"
                               type="email"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        @error('editEmail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Role --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Role</label>
                        <select wire:model="editRole"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                            <option value="student">student</option>
                            <option value="supervisor">supervisor</option>
                            <option value="coordinator">FYP Coordinator</option>
                        </select>
                        @error('editRole') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                        <select wire:model="editIsActive"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        @error('editIsActive') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                </div>

                {{-- Department / Programme --}}
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Department / Programme</label>
                    <input wire:model="editDepartment"
                           type="text"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                    @error('editDepartment') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Yellow warning banner --}}
                <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                    <strong class="font-semibold">Reminder:</strong> when a student is removed (e.g. medical leave, withdrawal), upload an updated CSV so the active cohort stays in sync.
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                <button @click="$wire.set('showEditModal', false)"
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                    Cancel
                </button>
                <button wire:click="saveUser"
                        wire:loading.attr="disabled"
                        wire:target="saveUser"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700">
                    Save changes
                </button>
            </div>

        </div>
    </div>

    {{-- ② REASSIGN SUPERVISOR MODAL --}}
    <div x-show="$wire.showReassignModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         style="display: none;">
        <div x-show="$wire.showReassignModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.stop
             class="relative w-full max-w-md rounded-xl bg-white shadow-xl">

            {{-- Header --}}
            <div class="flex items-start justify-between border-b border-gray-100 px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Reassign supervisor</h2>
                    <p class="mt-0.5 text-sm text-gray-500">For {{ $reassignStudentName }}</p>
                </div>
                <button @click="$wire.set('showReassignModal', false)"
                        type="button"
                        class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="space-y-4 px-6 py-4">

                {{-- Current supervisor (read-only) --}}
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Current supervisor</label>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                        {{ $reassignCurrentSup ?: '—' }}
                    </div>
                </div>

                {{-- New supervisor dropdown --}}
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">New supervisor</label>
                    <select wire:model="reassignNewSupId"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <option value="">Select supervisor...</option>
                        @if($showReassignModal)
                            @foreach($this->supervisors as $sup)
                                <option value="{{ $sup->id }}">
                                    {{ $sup->name }}{{ ! $sup->is_active ? ' (inactive)' : '' }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                    @error('reassignNewSupId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                <button @click="$wire.set('showReassignModal', false)"
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                    Cancel
                </button>
                <button wire:click="saveReassign"
                        wire:loading.attr="disabled"
                        wire:target="saveReassign"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700">
                    Save
                </button>
            </div>

        </div>
    </div>

    {{-- ③ DELETE MODAL --}}
    <div x-show="$wire.showDeleteModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         style="display: none;">
        <div x-show="$wire.showDeleteModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.stop
             class="relative w-full max-w-md rounded-xl bg-white shadow-xl">

            {{-- Body --}}
            <div class="px-6 py-6">
                <div class="flex items-start gap-4">

                    {{-- Warning icon --}}
                    <div class="flex-shrink-0 rounded-full bg-rose-100 p-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-rose-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Remove this user?</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            <strong class="font-medium text-gray-700">{{ $deleteUserName }}</strong> will be removed from the system. If this is a student withdrawal (accident, medical leave, deferment), upload the updated cohort CSV next to keep records in sync.
                        </p>
                    </div>

                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                <button @click="$wire.set('showDeleteModal', false)"
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                    Cancel
                </button>
                <button wire:click="deleteUser"
                        wire:loading.attr="disabled"
                        wire:target="deleteUser"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                        type="button"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-rose-700">
                    Remove
                </button>
            </div>

        </div>
    </div>

</div>
