# User Management Step 2 — Display & Filtering Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the Manage Users page with a page header, stat cards, reactive role filter tabs, live search, and a modern read-only user table — all inside the existing `user-management` Volt component.

**Architecture:** All changes are confined to `resources/views/livewire/user-management.blade.php`. The PHP block gains two `computed()` properties (`filteredUsers`, `stats`) and two `state()` values (`search`, `roleFilter`). The Blade template is rebuilt section-by-section incrementally, never deleting more than it replaces in a single task. The existing `updateRole` action is preserved untouched; it has no UI trigger in this step.

**Tech Stack:** Laravel 11, Livewire Volt v3 (functional API), Tailwind CSS, Alpine.js (for the search clear button), Pest for tests. Heroicons v2 outline inline SVG for stat card icons.

---

## File Map

| File | Change |
|------|--------|
| `resources/views/livewire/user-management.blade.php` | Modify — PHP block rewrite + incremental Blade template redesign |
| `tests/Feature/UserManagementTest.php` | Create — Pest feature tests for search, role filter, display |

**Risk areas:**
- **PHP block** (Task 2): replacing `state(['users' => ...])` with `computed()` — highest risk of hydration issues; verify page loads before proceeding
- **Table loop variable** (Task 2): changing `$users` → `$this->filteredUsers` must happen in the same commit as the PHP block change, or the page will throw `Undefined variable $users`
- **All other tasks** are pure HTML additions; they carry low risk

---

## Task 1: Write failing Pest tests

**Files:**
- Create: `tests/Feature/UserManagementTest.php`

These tests verify the behaviours introduced in Tasks 2–6. Run them now — they are expected to fail. They pass progressively as each task completes.

- [ ] **Step 1.1: Create the test file**

```php
<?php

use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinator = User::factory()->create([
        'name'      => 'Test Coordinator',
        'role'      => 'coordinator',
        'is_active' => true,
    ]);
    $this->actingAs($this->coordinator);
});

// ── Route access ─────────────────────────────────────────────────────────────

it('allows a coordinator to access the manage users page', function () {
    $this->get(route('admin.users'))->assertOk();
});

it('redirects a student away from the manage users page', function () {
    $student = User::factory()->create(['role' => 'student']);
    $this->actingAs($student)
         ->get(route('admin.users'))
         ->assertRedirect();
});

// ── Filtering — these fail until Task 2 ──────────────────────────────────────

it('filters the table by name search', function () {
    User::factory()->create(['name' => 'Ahmad Razif', 'role' => 'student']);
    User::factory()->create(['name' => 'Nurul Ain',   'role' => 'student']);

    Livewire::test('user-management')
        ->set('search', 'Ahmad')
        ->assertSee('Ahmad Razif')
        ->assertDontSee('Nurul Ain');
});

it('filters the table by email search', function () {
    User::factory()->create(['name' => 'Alpha User', 'email' => 'alpha@test.edu', 'role' => 'student']);
    User::factory()->create(['name' => 'Beta User',  'email' => 'beta@test.edu',  'role' => 'student']);

    Livewire::test('user-management')
        ->set('search', 'alpha@')
        ->assertSee('Alpha User')
        ->assertDontSee('Beta User');
});

it('filters the table to students only when role tab is student', function () {
    User::factory()->create(['name' => 'Student One',    'role' => 'student']);
    User::factory()->create(['name' => 'Supervisor One', 'role' => 'supervisor']);

    Livewire::test('user-management')
        ->set('roleFilter', 'student')
        ->assertSee('Student One')
        ->assertDontSee('Supervisor One');
});

it('filters the table to supervisors only when role tab is supervisor', function () {
    User::factory()->create(['name' => 'Student One',    'role' => 'student']);
    User::factory()->create(['name' => 'Supervisor One', 'role' => 'supervisor']);

    Livewire::test('user-management')
        ->set('roleFilter', 'supervisor')
        ->assertSee('Supervisor One')
        ->assertDontSee('Student One');
});

it('shows all users when role filter is all', function () {
    User::factory()->create(['name' => 'Student One',    'role' => 'student']);
    User::factory()->create(['name' => 'Supervisor One', 'role' => 'supervisor']);

    Livewire::test('user-management')
        ->assertSee('Student One')
        ->assertSee('Supervisor One');
});

// ── Display — these fail until Task 6 ────────────────────────────────────────

it('shows empty state message when search matches no users', function () {
    User::factory()->create(['name' => 'Ahmad Razif', 'role' => 'student']);

    Livewire::test('user-management')
        ->set('search', 'zzznomatch')
        ->assertSee('No users match the current search or filter.');
});

it('shows Not assigned for users without a department', function () {
    User::factory()->create(['name' => 'No Dept User', 'role' => 'student', 'department' => null]);

    Livewire::test('user-management')
        ->assertSee('Not assigned');
});

it('shows Inactive badge for deactivated users', function () {
    User::factory()->create(['name' => 'Inactive User', 'role' => 'student', 'is_active' => false]);

    Livewire::test('user-management')
        ->assertSee('Inactive');
});
```

- [ ] **Step 1.2: Run the tests to confirm they fail as expected**

```
./vendor/bin/pest tests/Feature/UserManagementTest.php --colors
```

Expected: several failures — filtering tests fail because the component still uses `User::all()` with no filtering; display tests fail because the old table has no Department or Status columns. The route access tests should already pass.

---

## Task 2: Update the PHP block and fix the Blade loop variable

**Files:**
- Modify: `resources/views/livewire/user-management.blade.php` (PHP block + one line in the table loop)

This is the highest-risk task. Read carefully before editing.

**What changes:**
1. The PHP `<?php ... ?>` block is completely replaced.
2. The single line `@foreach($users as $user)` in the Blade template becomes `@foreach($this->filteredUsers as $user)`.

These two changes must be committed together. If you change the PHP block without updating the loop variable, the page will crash with `Undefined variable $users`.

- [ ] **Step 2.1: Replace the entire PHP block**

Replace everything from `<?php` through the closing `?>` (lines 1–26 in the current file) with:

```php
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
```

- [ ] **Step 2.2: Update the loop variable in the Blade template**

In the `<tbody>` section, find:
```html
@foreach($users as $user)
```
Replace with:
```html
@foreach($this->filteredUsers as $user)
```

- [ ] **Step 2.3: Manual smoke test — load the page**

Visit `http://fyp-dashboard.test/admin/users` as a coordinator. The page must load without any PHP errors or blank screen. The existing table (old columns) should show all users, sorted alphabetically. If there is an error, do not proceed.

- [ ] **Step 2.4: Run the filtering tests**

```
./vendor/bin/pest tests/Feature/UserManagementTest.php --colors
```

Expected result after this task:
- **Pass:** all route access tests, all filtering tests (`search`, `roleFilter`)
- **Still failing:** `shows Not assigned...` and `shows Inactive badge...` (those test Task 6 display, not yet implemented)

- [ ] **Step 2.5: Run the full test suite**

```
./vendor/bin/pest --colors
```

Expected: no regressions. All tests that passed before Task 2 must still pass.

- [ ] **Step 2.6: Commit**

```
git add resources/views/livewire/user-management.blade.php tests/Feature/UserManagementTest.php
git commit -m "feat: add search + role filter state and computed properties to user-management"
```

---

## Task 3: Replace outer wrapper and add page header

**Files:**
- Modify: `resources/views/livewire/user-management.blade.php` (outer div + header only)

This task changes the outer card wrapper class and replaces the old two-line header (`<h2>User Role Management</h2>` + `System Foundation` span) with the new page header block. The flash messages and table are untouched.

- [ ] **Step 3.1: Replace the outer opening div and old header**

Find and replace this block (the outer div opening + old header div):
```html
<div class="mt-6 p-6 bg-white border border-neutral-200 rounded-xl shadow-sm">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-gray-800">User Role Management</h2>
        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">System Foundation</span>
    </div>
```

Replace with:
```html
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

```

- [ ] **Step 3.2: Add horizontal padding to the flash message divs**

The flash messages currently rely on the outer `p-6` padding which is now removed. Wrap both flash `@if` blocks in a padding div immediately after the header:

```html
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
```

Replace the existing two standalone `@if (session()->has(...))` blocks with the above.

- [ ] **Step 3.3: Manual smoke test**

Load the page. Confirm:
- New "Manage Users" title and subtitle visible
- Green dot + "FYP Coordinator" badge top-right
- Old table still present and working below the header

- [ ] **Step 3.4: Run the full test suite**

```
./vendor/bin/pest --colors
```

Expected: same pass/fail as after Task 2. No new failures.

- [ ] **Step 3.5: Commit**

```
git add resources/views/livewire/user-management.blade.php
git commit -m "feat: add page header to user management"
```

---

## Task 4: Add stat cards section

**Files:**
- Modify: `resources/views/livewire/user-management.blade.php` (insert stat cards between header and table)

- [ ] **Step 4.1: Insert the stat cards block**

After the closing `</div>` of the flash messages wrapper (from Task 3) and before the `<div class="overflow-x-auto">` table wrapper, insert:

```html
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
```

- [ ] **Step 4.2: Manual smoke test**

Load the page. Confirm:
- Three stat cards visible below the header
- Each shows a live count from the database
- Old table still present below the cards

- [ ] **Step 4.3: Run the full test suite**

```
./vendor/bin/pest --colors
```

Expected: same pass/fail as after Task 3.

- [ ] **Step 4.4: Commit**

```
git add resources/views/livewire/user-management.blade.php
git commit -m "feat: add stat cards to user management page"
```

---

## Task 5: Add filter tabs and search input

**Files:**
- Modify: `resources/views/livewire/user-management.blade.php` (insert filter row between stat cards and table)

- [ ] **Step 5.1: Insert the filter tabs + search block**

After the closing `</div>` of the stat cards section and before the `<div class="overflow-x-auto">` table wrapper, insert:

```html
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
```

- [ ] **Step 5.2: Manual smoke test**

Load the page. Confirm:
- Filter tabs visible; clicking each tab updates the URL/state and the old table responds (only correct role shown)
- Search input responsive; typing a name filters the old table rows
- Clear button (×) appears after typing and clears the input

- [ ] **Step 5.3: Run the full test suite**

```
./vendor/bin/pest --colors
```

Expected: same pass/fail as after Task 4.

- [ ] **Step 5.4: Commit**

```
git add resources/views/livewire/user-management.blade.php
git commit -m "feat: add role filter tabs and live search to user management"
```

---

## Task 6: Replace old table with redesigned table

**Files:**
- Modify: `resources/views/livewire/user-management.blade.php` (replace entire `<div class="overflow-x-auto">` block)

This is the final UI task. The old 4-column table (with the hidden role-change dropdown) is fully replaced with the new 6-column read-only table.

- [ ] **Step 6.1: Replace the entire old table wrapper**

Find this block (from the `<div class="overflow-x-auto">` through its closing `</div>`):
```html
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
```

Replace it with:

```html
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
```

- [ ] **Step 6.2: Manual smoke test — full page review**

Load the page as a coordinator. Verify:
1. Header, stat cards, filter tabs, search all visible and working
2. Table shows: Name (bold + username subtext), Email (muted), Role (colored badge), Department ("Not assigned" if null), Status (Active/Inactive badge), Actions (disabled Edit button)
3. Clicking a filter tab updates the table instantly
4. Typing in search filters the table live
5. Empty state message shows when no results
6. No role-change dropdown visible anywhere

- [ ] **Step 6.3: Run the full test suite — all tests must pass**

```
./vendor/bin/pest --colors
```

Expected: **all tests pass**, including the display tests (`Not assigned`, `Inactive`) that were failing in earlier tasks. Zero regressions.

- [ ] **Step 6.4: Commit**

```
git add resources/views/livewire/user-management.blade.php
git commit -m "feat: replace user table with redesigned read-only layout (Step 2 complete)"
```

---

## Final state of `user-management.blade.php`

After all six tasks, the file structure is:

```
PHP block
  └─ state: search, roleFilter
  └─ computed: filteredUsers (reactive query)
  └─ computed: stats (three DB counts)
  └─ updateRole action (untouched, no UI trigger)

Blade template
  └─ Outer wrapper: overflow-hidden rounded-xl border
      ├─ ① Page header
      ├─ Flash messages (padded wrapper)
      ├─ ② Stat cards (3-col grid, sky/emerald/violet)
      ├─ ③ Filter tabs + search (responsive flex row)
      └─ ④ User table (6 cols: Name, Email, Role, Department, Status, Actions)
           └─ @forelse with empty state
```

---

## Testing checkpoint summary

| After task | Tests that should pass |
|------------|----------------------|
| Task 1 | Route access tests only |
| Task 2 | + Filtering tests (search by name, email, role filter, show all) |
| Task 3 | No change in test results |
| Task 4 | No change in test results |
| Task 5 | No change in test results |
| Task 6 | + Display tests (empty state message, Not assigned, Inactive badge) — full suite green |
