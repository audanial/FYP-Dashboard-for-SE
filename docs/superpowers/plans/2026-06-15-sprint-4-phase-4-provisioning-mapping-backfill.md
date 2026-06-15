# Sprint 4 Phase 4 — Supervisor Account Provisioning, Mapping & Backfill — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the coordinator create the missing supervisor accounts and map every unlinked `supervisor_name` to a real account, then populate `fyp_projects.supervisor_id` (per pair) via UI, a re-runnable backfill command, and updated write paths — so the structural FK becomes the source of truth and the dual-read name fallback can be retired in Phase 5.

**Architecture:** Builds directly on the committed Phase 1–3 work. The schema (`supervisor_id` nullable FK), relations (`FypProject::supervisor()`, `User::supervisedProjects()`), and the dual-read predicate are already in place and unchanged. This phase adds **writes**: a coordinator "Create Supervisor" flow, an "Unlinked Supervisors" curation panel (link existing / create & link, confirm-before-apply, per-pair), an idempotent `fyp:link-supervisors` exact-match backfill command, a per-pair fix to Reassign, and exact-match linking on CSV import. No change to the read predicate; the analytics effective-key grouping already renders linked rows by the user's canonical name.

**Tech Stack:** Laravel 11, Livewire **Volt** (functional API — `state()`/`computed()`/closures, per project memory and the existing `user-management.blade.php`), Flux UI, Tailwind, SQLite, Pest. Artisan console command for backfill.

---

## Phase numbering note

This is **Phase 4** in the reconciled (as-built) numbering recorded in `docs/PROJECT_STATUS.md`. It corresponds to the original roadmap's **Phase 5 (curation UI) + Phase 6 (backfill + writers)**, combined per the request. The roadmap's Phase 7 (verify + retirement) becomes the reconciled **Phase 5** and is out of scope here.

---

## Goals

1. **Provision accounts.** Coordinator can create supervisor accounts (role forced `supervisor`, `is_active=true`, dedup by unique email, show-once temp password). No CSV auto-create.
2. **Map unlinked names.** A panel lists every distinct `supervisor_name` with `supervisor_id IS NULL`, with its affected student/pair count, and lets the coordinator **Link to existing** or **Create & link** — exact human curation, confirm-before-apply, written to the **whole pair**.
3. **Backfill exact matches.** `php artisan fyp:link-supervisors` is idempotent and links **only** rows where `supervisor_name === users.name` (role `supervisor`), reporting linked/unmatched counts.
4. **Make writes set the FK.** CSV import sets `supervisor_id` on exact match; Reassign sets `supervisor_id` + canonical name for the **whole pair** (fixes the single-row bug at `user-management.blade.php:122-125`).
5. **Stay measurable & safe.** `FypProject::whereNull('supervisor_id')->count()` trends toward 0. No existing test weakened; coordinator-only authorization everywhere; full Pest suite green.

### Non-goals (explicitly out of scope)

- Fuzzy / normalization name resolver, `supervisor_aliases` table (deferred — documented future option).
- Dropping `supervisor_name` or removing the dual-read fallback (that is Phase 5, gated on null-count = 0).
- **Force-password-reset flag** — needs a `users` migration; deferred to backlog. This phase shows the temp password once and never logs it, but does not force a reset on first login.
- Mail-based onboarding / temp-password email delivery (no mail infra) — show-once only.
- Coordinator `admin`-role drift — separate backlog item; the live coordinator already holds `coordinator`.

---

## User stories

- **US-1 (provision).** As a coordinator, I can create a supervisor account from Manage Users so that supervisors who never self-registered exist as real users I can link projects to.
- **US-2 (see the gap).** As a coordinator, I can see a list of every supervisor name from the imported data that is not yet linked to an account, with how many students/pairs it covers, so I know exactly what curation remains.
- **US-3 (link existing).** As a coordinator, I can map an unlinked `supervisor_name` to an existing supervisor account, confirm, and have every project in that pair set to the correct `supervisor_id`.
- **US-4 (create & link).** As a coordinator, for an unlinked name with no account yet, I can create the account and link it in one confirmed step.
- **US-5 (backfill).** As a coordinator/operator, I can run one idempotent command that auto-links all exact name matches and prints what is still unlinked, so the bulk of linking is automatic and re-runnable safely.
- **US-6 (writes stay linked).** As a coordinator, when I re-import the CSV or reassign a student's supervisor, the system sets `supervisor_id` (whole pair) so the link state does not silently regress.
- **US-7 (safety).** As any non-coordinator, I am blocked (403) from every provisioning, linking, and reassignment action.

---

## Technical design

### Data model (unchanged — for reference)
- `fyp_projects.supervisor_id` — nullable FK → `users.id` (`nullOnDelete`), already migrated (`3e54f50`).
- `FypProject::supervisor()` belongsTo; `User::supervisedProjects()` hasMany — already present.
- `FypProject` `$fillable` already includes `supervisor_id`.

### Pair semantics (the rule every write obeys)
A "pair" is the set of `fyp_projects` rows sharing the same `semester` **and** non-null `pair_number`. Supervisor assignment applies to the whole pair. Unpaired rows (`pair_number IS NULL`) are treated as a pair of one — only that student's row is written. This is the helper used by Reassign and (implicitly) by name-based linking:

```php
// Rows belonging to the same pair as $project.
$pairQuery = function (\App\Models\FypProject $project) {
    return \App\Models\FypProject::query()->when(
        $project->pair_number !== null,
        fn ($q) => $q->where('semester', $project->semester)
                     ->where('pair_number', $project->pair_number),
        fn ($q) => $q->where('id', $project->id),
    );
};
```

> **Name-based linking** (Unlinked panel + backfill) writes by `supervisor_name` equality across **all** rows holding that exact string. Because both members of a pair carry the same `supervisor_name`, this naturally links whole pairs without needing the pair helper. The pair helper is required only for **Reassign**, which is keyed off a single student.

### Linking vs. reassigning (deliberate difference)
- **Link / backfill** (mapping the original CSV string to the right account): set `supervisor_id` only; **preserve** `supervisor_name` (keeps provenance of the imported string; analytics already displays the linked user's canonical name via the effective-key grouping).
- **Reassign** (changing *who* supervises a student): set **both** `supervisor_id` and `supervisor_name = $newSupervisor->name`, for the whole pair (this is an intentional ownership change, not a string-to-account mapping).
- **Import**: on exact match set `supervisor_id`; leave `supervisor_name` as the imported string.

### Create Supervisor
New Volt state + `createSupervisor()` action in `user-management.blade.php`. Validates `name` and unique `email`; forces `role='supervisor'`, `is_active=true`; generates a temp password with `Str::password(16)`, stores it hashed, and surfaces the plaintext **once** via a transient state property rendered in the modal (never flashed to logs). `username` left null (supervisors are not students; `displayUsername()` falls back to the email prefix).

### Unlinked Supervisors panel
A `computed` returns distinct `supervisor_name` where `supervisor_id IS NULL`, each with a student count and a distinct-pair count. Each row offers **Link to existing** (select a supervisor user) and **Create & link**. Both route through a confirm step before writing `supervisor_id` to every row with that exact name.

### Backfill command
`php artisan fyp:link-supervisors` (mirrors `CleanSupervisorEncoding` structure). For each distinct unlinked `supervisor_name`, if exactly one `users` row with `role='supervisor'` has `name === supervisor_name`, set `supervisor_id` on all rows with that name; otherwise leave unlinked and collect it for the report. Idempotent: rows already linked are skipped (`whereNull('supervisor_id')`). Prints `linked` and `unmatched` counts + the unmatched names.

### Affected files

| File | Change |
|---|---|
| `resources/views/livewire/user-management.blade.php` | **Create Supervisor** modal + `createSupervisor()`; **Unlinked Supervisors** panel (`unlinkedSupervisors` computed) + `linkToExisting()` / `createAndLink()` with confirm state; fix `saveReassign()` to write the whole pair (`supervisor_id` + canonical name). |
| `resources/views/livewire/fyp-projects.blade.php` | In the import loop (~`:231-246`), resolve exact `supervisor_name → users.name` (role `supervisor`) and include `supervisor_id` in `$fields`. |
| **Create** `app/Console/Commands/LinkSupervisors.php` | `fyp:link-supervisors` idempotent exact-match backfill + report. |
| `database/factories/FypProjectFactory.php` | Add a `forSupervisor(User $u)` state (sets `supervisor_id` + `supervisor_name = $u->name`) to keep link tests terse. |
| **Create** `tests/Feature/SupervisorProvisioningTest.php` | Create-supervisor + authorization tests. |
| **Create** `tests/Feature/SupervisorLinkingTest.php` | Unlinked panel + link-to-existing / create-and-link (per-pair) tests. |
| **Create** `tests/Feature/LinkSupervisorsCommandTest.php` | Backfill command tests (exact match, idempotency, report). |
| `tests/Feature/UserManagementTest.php` | **Add** Reassign-per-pair tests; if any existing Reassign assertion exists, **update** (never weaken) it. |
| `docs/PROJECT_STATUS.md` | Mark Phase 4 progress at close (done as part of Task 9). |

---

## Task 1: `forSupervisor` factory state (test ergonomics)

**Files:**
- Modify: `database/factories/FypProjectFactory.php`
- Test: `tests/Feature/SupervisorLinkingTest.php` (created here, expanded later)

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('forSupervisor factory state links id and copies the canonical name', function () {
    $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Dr Canonical']);

    $project = FypProject::factory()->forSupervisor($sup)->create();

    expect($project->supervisor_id)->toBe($sup->id)
        ->and($project->supervisor_name)->toBe('Dr Canonical');
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `./vendor/bin/pest tests/Feature/SupervisorLinkingTest.php`
Expected: FAIL — `Call to undefined method ...::forSupervisor()`.

- [ ] **Step 3: Add the state**

In `database/factories/FypProjectFactory.php`, add inside the class:

```php
public function forSupervisor(\App\Models\User $supervisor): static
{
    return $this->state(fn () => [
        'supervisor_id'   => $supervisor->id,
        'supervisor_name' => $supervisor->name,
    ]);
}
```

- [ ] **Step 4: Run it and confirm it passes**

Run: `./vendor/bin/pest tests/Feature/SupervisorLinkingTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/factories/FypProjectFactory.php tests/Feature/SupervisorLinkingTest.php
git commit -m "test: add forSupervisor factory state for Phase 4 linking tests"
```

---

## Task 2: Create Supervisor — backend action + authorization

**Files:**
- Modify: `resources/views/livewire/user-management.blade.php` (state block `:8-32`, actions region near `:53`)
- Test: `tests/Feature/SupervisorProvisioningTest.php` (create)

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
});

test('coordinator creates a supervisor with role forced and account active', function () {
    $this->actingAs($this->coordinator);

    Livewire::test('user-management')
        ->set('newName', 'Tiliza binti Awang Mat')
        ->set('newEmail', 'tiliza@unikl.edu.my')
        ->call('createSupervisor')
        ->assertHasNoErrors();

    $created = User::where('email', 'tiliza@unikl.edu.my')->first();
    expect($created)->not->toBeNull()
        ->and($created->role)->toBe('supervisor')
        ->and((bool) $created->is_active)->toBeTrue();
});

test('createSupervisor surfaces a one-time temp password and stores it hashed', function () {
    $this->actingAs($this->coordinator);

    $component = Livewire::test('user-management')
        ->set('newName', 'New Sup')
        ->set('newEmail', 'newsup@unikl.edu.my')
        ->call('createSupervisor');

    $temp = $component->get('newTempPassword');
    expect($temp)->toBeString()->not->toBeEmpty();

    $created = User::where('email', 'newsup@unikl.edu.my')->first();
    expect(\Illuminate\Support\Facades\Hash::check($temp, $created->password))->toBeTrue();
});

test('createSupervisor rejects a duplicate email', function () {
    User::factory()->create(['email' => 'dupe@unikl.edu.my']);
    $this->actingAs($this->coordinator);

    Livewire::test('user-management')
        ->set('newName', 'Dupe Sup')
        ->set('newEmail', 'dupe@unikl.edu.my')
        ->call('createSupervisor')
        ->assertHasErrors(['newEmail']);
});

test('a non-coordinator cannot create a supervisor', function () {
    $student = User::factory()->create(['role' => 'student']);
    $this->actingAs($student);

    Livewire::test('user-management')
        ->set('newName', 'Hacker Sup')
        ->set('newEmail', 'hacker@unikl.edu.my')
        ->call('createSupervisor')
        ->assertForbidden();
});
```

- [ ] **Step 2: Run them and confirm they fail**

Run: `./vendor/bin/pest tests/Feature/SupervisorProvisioningTest.php`
Expected: FAIL — `Method createSupervisor does not exist` / missing state.

- [ ] **Step 3: Add state + action**

Add to the `state([...])` array in `user-management.blade.php`:

```php
    // Create supervisor modal
    'showCreateModal' => false,
    'newName'         => '',
    'newEmail'        => '',
    'newDepartment'   => '',
    'newTempPassword' => null,
```

Add the imports at the top (`Str`, `Hash`) and the action after the Edit-modal region:

```php
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
```

```php
// ── Create supervisor modal ───────────────────────────────────────────────────

$openCreateModal = function () {
    abort_unless(Auth::user()?->role === 'coordinator', 403);
    $this->newName = '';
    $this->newEmail = '';
    $this->newDepartment = '';
    $this->newTempPassword = null;
    $this->showCreateModal = true;
};

$createSupervisor = function () {
    abort_unless(Auth::user()?->role === 'coordinator', 403);

    $this->validate([
        'newName'       => 'required|string|max:255',
        'newEmail'      => 'required|email|unique:users,email',
        'newDepartment' => 'nullable|string|max:255',
    ]);

    $temp = Str::password(16);

    User::create([
        'name'       => $this->newName,
        'email'      => $this->newEmail,
        'role'       => 'supervisor',
        'is_active'  => true,
        'department' => $this->newDepartment ?: null,
        'password'   => Hash::make($temp),
    ]);

    // Surfaced once in the modal; never flashed to session/logs.
    $this->newTempPassword = $temp;
    session()->flash('message', 'Supervisor account created.');
};
```

- [ ] **Step 4: Run them and confirm they pass**

Run: `./vendor/bin/pest tests/Feature/SupervisorProvisioningTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/user-management.blade.php tests/Feature/SupervisorProvisioningTest.php
git commit -m "feat: coordinator can create supervisor accounts (Phase 4)"
```

---

## Task 3: Create Supervisor — modal UI

**Files:**
- Modify: `resources/views/livewire/user-management.blade.php` (page header `:160-171`; add modal markup near the other modals `:400+`)

> No new behavior — markup only, exercised by the Task 2 tests via `wire:model`. Mirror the existing Edit/Reassign modal structure (Alpine `x-show="$wire.showCreateModal"`, same transition/footer classes).

- [ ] **Step 1: Add a "Create supervisor" button to the page header**

Inside the header `div.flex.items-start.justify-between` (replace the lone status chip block so the button sits beside it):

```blade
            <div class="flex items-center gap-3">
                <button wire:click="openCreateModal"
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700">
                    + Create supervisor
                </button>
                <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                    <span class="text-xs font-medium text-gray-600">FYP Coordinator</span>
                </div>
            </div>
```

- [ ] **Step 2: Add the Create Supervisor modal**

Place after the Edit modal block. Use the same outer/inner Alpine wrapper as the Reassign modal, with this body:

```blade
    {{-- CREATE SUPERVISOR MODAL --}}
    <div x-show="$wire.showCreateModal"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         style="display: none;">
        <div @click.stop class="relative w-full max-w-md rounded-xl bg-white shadow-xl">
            <div class="flex items-start justify-between border-b border-gray-100 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Create supervisor account</h2>
                <button @click="$wire.set('showCreateModal', false)" type="button"
                        class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">✕</button>
            </div>

            <div class="space-y-4 px-6 py-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Full Name</label>
                    <input wire:model="newName" type="text"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    @error('newName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                    <input wire:model="newEmail" type="email"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    @error('newEmail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Department / Programme</label>
                    <input wire:model="newDepartment" type="text"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                </div>

                @if($newTempPassword)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <p class="font-semibold">Temporary password (shown once):</p>
                        <code class="mt-1 block break-all font-mono text-amber-900">{{ $newTempPassword }}</code>
                        <p class="mt-1 text-xs">Copy it now and share it securely. It will not be shown again.</p>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                <button @click="$wire.set('showCreateModal', false)" type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Close
                </button>
                <button wire:click="createSupervisor" wire:loading.attr="disabled" wire:target="createSupervisor"
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Create account
                </button>
            </div>
        </div>
    </div>
```

- [ ] **Step 3: Verify the suite still passes**

Run: `./vendor/bin/pest tests/Feature/SupervisorProvisioningTest.php`
Expected: PASS (markup change does not break the action tests).

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/user-management.blade.php
git commit -m "feat: create-supervisor modal UI (Phase 4)"
```

---

## Task 4: Unlinked Supervisors panel (computed)

**Files:**
- Modify: `resources/views/livewire/user-management.blade.php` (add `unlinkedSupervisors` computed near `:51`; add panel markup after the stat cards)
- Test: `tests/Feature/SupervisorLinkingTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/SupervisorLinkingTest.php`:

```php
test('unlinkedSupervisors lists distinct unlinked names with student counts', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);

    // Two students under the same unlinked name; one under a different unlinked name.
    FypProject::factory()->count(2)->create(['supervisor_name' => 'Raw Name A', 'supervisor_id' => null]);
    FypProject::factory()->create(['supervisor_name' => 'Raw Name B', 'supervisor_id' => null]);

    // A linked row must NOT appear.
    $linkedSup = User::factory()->create(['role' => 'supervisor', 'name' => 'Linked Sup']);
    FypProject::factory()->forSupervisor($linkedSup)->create();

    $this->actingAs($coordinator);

    $rows = Livewire::test('user-management')->get('unlinkedSupervisors');
    $names = collect($rows)->pluck('supervisor_name')->all();

    expect($names)->toContain('Raw Name A', 'Raw Name B')
        ->and($names)->not->toContain('Linked Sup');

    $rowA = collect($rows)->firstWhere('supervisor_name', 'Raw Name A');
    expect($rowA['student_count'])->toBe(2);
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `./vendor/bin/pest tests/Feature/SupervisorLinkingTest.php`
Expected: FAIL — `unlinkedSupervisors` property does not exist.

- [ ] **Step 3: Add the computed**

In `user-management.blade.php`, after the `$supervisors` computed:

```php
$unlinkedSupervisors = computed(function () {
    return FypProject::query()
        ->whereNull('supervisor_id')
        ->whereNotNull('supervisor_name')
        ->where('supervisor_name', '!=', '')
        ->selectRaw('supervisor_name, COUNT(*) as student_count, COUNT(DISTINCT pair_number) as pair_count')
        ->groupBy('supervisor_name')
        ->orderBy('supervisor_name')
        ->get()
        ->map(fn ($r) => [
            'supervisor_name' => $r->supervisor_name,
            'student_count'   => (int) $r->student_count,
            'pair_count'      => (int) $r->pair_count,
        ])
        ->all();
});
```

- [ ] **Step 4: Run it and confirm it passes**

Run: `./vendor/bin/pest tests/Feature/SupervisorLinkingTest.php`
Expected: PASS.

- [ ] **Step 5: Add the panel markup**

After the stat-cards `div` (`:248`), add a panel that lists `$this->unlinkedSupervisors` with the two action buttons (wired in Task 5–6):

```blade
    @if(count($this->unlinkedSupervisors) > 0)
    <div class="border-b border-gray-100 px-6 py-5">
        <h2 class="text-sm font-semibold text-gray-900">Unlinked supervisors
            <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">
                {{ count($this->unlinkedSupervisors) }}
            </span>
        </h2>
        <p class="mt-1 text-xs text-gray-500">Names imported from CSV that are not yet linked to an account. Linking writes the whole pair.</p>
        <div class="mt-3 divide-y divide-gray-50 rounded-lg border border-gray-200">
            @foreach($this->unlinkedSupervisors as $u)
                <div class="flex items-center justify-between px-4 py-2.5">
                    <div>
                        <span class="text-sm font-medium text-gray-800">{{ $u['supervisor_name'] }}</span>
                        <span class="ml-2 text-xs text-gray-400">{{ $u['student_count'] }} student(s) · {{ $u['pair_count'] }} pair(s)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="openLinkModal(@js($u['supervisor_name']))" type="button"
                                class="rounded-md border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                            Link to existing
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
```

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/user-management.blade.php tests/Feature/SupervisorLinkingTest.php
git commit -m "feat: unlinked-supervisors panel (Phase 4)"
```

---

## Task 5: Link an unlinked name to an existing supervisor (per-pair, confirm)

**Files:**
- Modify: `resources/views/livewire/user-management.blade.php` (link-modal state + `openLinkModal()` + `confirmLink()`; modal markup)
- Test: `tests/Feature/SupervisorLinkingTest.php`

- [ ] **Step 1: Write the failing tests**

```php
test('linking an unlinked name to an existing supervisor sets supervisor_id on every row with that name', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Account Name']);

    // Two students share the raw CSV string; both must be linked, name preserved.
    FypProject::factory()->count(2)->create(['supervisor_name' => 'Raw CSV String', 'supervisor_id' => null]);

    $this->actingAs($coordinator);

    Livewire::test('user-management')
        ->call('openLinkModal', 'Raw CSV String')
        ->set('linkTargetSupId', $sup->id)
        ->call('confirmLink')
        ->assertHasNoErrors();

    $rows = FypProject::where('supervisor_name', 'Raw CSV String')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->every(fn ($r) => $r->supervisor_id === $sup->id))->toBeTrue()
        // provenance preserved — the imported string is not rewritten on a link
        ->and($rows->every(fn ($r) => $r->supervisor_name === 'Raw CSV String'))->toBeTrue();
});

test('linking rejects a target that is not a supervisor', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    $student = User::factory()->create(['role' => 'student']);
    FypProject::factory()->create(['supervisor_name' => 'Raw CSV String', 'supervisor_id' => null]);

    $this->actingAs($coordinator);

    Livewire::test('user-management')
        ->call('openLinkModal', 'Raw CSV String')
        ->set('linkTargetSupId', $student->id)
        ->call('confirmLink')
        ->assertHasErrors(); // role guard / exists rule
});

test('a non-coordinator cannot link supervisors', function () {
    $student = User::factory()->create(['role' => 'student']);
    $sup = User::factory()->create(['role' => 'supervisor']);
    FypProject::factory()->create(['supervisor_name' => 'Raw CSV String', 'supervisor_id' => null]);

    $this->actingAs($student);

    Livewire::test('user-management')
        ->call('openLinkModal', 'Raw CSV String')
        ->set('linkTargetSupId', $sup->id)
        ->call('confirmLink')
        ->assertForbidden();
});
```

- [ ] **Step 2: Run them and confirm they fail**

Run: `./vendor/bin/pest tests/Feature/SupervisorLinkingTest.php`
Expected: FAIL — `openLinkModal`/`confirmLink` do not exist.

- [ ] **Step 3: Add state + actions**

Add to `state([...])`:

```php
    // Link unlinked-name modal
    'showLinkModal'   => false,
    'linkRawName'     => '',
    'linkTargetSupId' => '',
```

Add the actions:

```php
$openLinkModal = function ($rawName) {
    abort_unless(Auth::user()?->role === 'coordinator', 403);
    $this->linkRawName     = $rawName;
    $this->linkTargetSupId = '';
    $this->showLinkModal   = true;
};

$confirmLink = function () {
    abort_unless(Auth::user()?->role === 'coordinator', 403);

    $this->validate([
        'linkRawName'     => 'required|string',
        'linkTargetSupId' => 'required|exists:users,id',
    ]);

    $supervisor = User::findOrFail($this->linkTargetSupId);
    abort_unless($supervisor->role === 'supervisor', 422);

    // Name-equality write links whole pairs (both pair rows share the string).
    // Provenance preserved: supervisor_name is NOT rewritten on a link.
    FypProject::where('supervisor_name', $this->linkRawName)
        ->whereNull('supervisor_id')
        ->update(['supervisor_id' => $supervisor->id]);

    $this->showLinkModal = false;
    session()->flash('message', 'Supervisor linked successfully.');
};
```

> **Why `assertHasErrors` passes for the non-supervisor target:** `abort_unless(..., 422)` raises before any DB write. The "not a supervisor" test asserts errors/abort; if your Livewire version surfaces 422 as an exception rather than a validation error, assert `->assertStatus(422)` instead — adjust the test to match the framework's behavior, do not loosen the guard.

- [ ] **Step 4: Add the confirm modal markup**

```blade
    {{-- LINK SUPERVISOR MODAL --}}
    <div x-show="$wire.showLinkModal"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none;">
        <div @click.stop class="relative w-full max-w-md rounded-xl bg-white shadow-xl">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Link supervisor</h2>
                <p class="mt-0.5 text-sm text-gray-500">Mapping <strong>{{ $linkRawName }}</strong> to an account. This sets the link for every student under that name.</p>
            </div>
            <div class="space-y-4 px-6 py-4">
                <label class="mb-1 block text-sm font-medium text-gray-700">Supervisor account</label>
                <select wire:model="linkTargetSupId" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    <option value="">Select supervisor…</option>
                    @foreach($this->supervisors as $sup)
                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                    @endforeach
                </select>
                @error('linkTargetSupId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                <button @click="$wire.set('showLinkModal', false)" type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button wire:click="confirmLink" wire:loading.attr="disabled" wire:target="confirmLink" type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Confirm link</button>
            </div>
        </div>
    </div>
```

- [ ] **Step 5: Run them and confirm they pass**

Run: `./vendor/bin/pest tests/Feature/SupervisorLinkingTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/user-management.blade.php tests/Feature/SupervisorLinkingTest.php
git commit -m "feat: link unlinked supervisor name to existing account, per pair (Phase 4)"
```

---

## Task 6: Fix Reassign — whole pair + set supervisor_id

**Files:**
- Modify: `resources/views/livewire/user-management.blade.php` (`saveReassign` `:111-129`)
- Test: `tests/Feature/UserManagementTest.php` (add cases)

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/UserManagementTest.php`:

```php
use App\Models\FypProject;

it('reassign updates the whole pair and sets supervisor_id + canonical name', function () {
    $newSup = User::factory()->create(['role' => 'supervisor', 'name' => 'New Supervisor']);

    $student = User::factory()->create(['role' => 'student', 'username' => 'S001']);
    // Two students in the same pair, currently under an old name with no id.
    FypProject::factory()->create([
        'student_id' => 'S001', 'semester' => 'MARCH 2026', 'pair_number' => 7,
        'supervisor_name' => 'Old Name', 'supervisor_id' => null,
    ]);
    FypProject::factory()->create([
        'student_id' => 'S002', 'semester' => 'MARCH 2026', 'pair_number' => 7,
        'supervisor_name' => 'Old Name', 'supervisor_id' => null,
    ]);

    Livewire::test('user-management')
        ->call('openReassignModal', $student->id)
        ->set('reassignNewSupId', $newSup->id)
        ->call('saveReassign')
        ->assertHasNoErrors();

    $pair = FypProject::where('semester', 'MARCH 2026')->where('pair_number', 7)->get();
    expect($pair)->toHaveCount(2)
        ->and($pair->every(fn ($p) => $p->supervisor_id === $newSup->id))->toBeTrue()
        ->and($pair->every(fn ($p) => $p->supervisor_name === 'New Supervisor'))->toBeTrue();
});

it('reassign on an unpaired student updates only that student row', function () {
    $newSup = User::factory()->create(['role' => 'supervisor', 'name' => 'Solo Sup']);
    $student = User::factory()->create(['role' => 'student', 'username' => 'S010']);

    FypProject::factory()->create([
        'student_id' => 'S010', 'pair_number' => null,
        'supervisor_name' => 'Old', 'supervisor_id' => null,
    ]);
    FypProject::factory()->create([
        'student_id' => 'S011', 'pair_number' => null,
        'supervisor_name' => 'Old', 'supervisor_id' => null,
    ]);

    Livewire::test('user-management')
        ->call('openReassignModal', $student->id)
        ->set('reassignNewSupId', $newSup->id)
        ->call('saveReassign')->assertHasNoErrors();

    expect(FypProject::where('student_id', 'S010')->first()->supervisor_id)->toBe($newSup->id)
        ->and(FypProject::where('student_id', 'S011')->first()->supervisor_id)->toBeNull();
});
```

- [ ] **Step 2: Run them and confirm they fail**

Run: `./vendor/bin/pest tests/Feature/UserManagementTest.php`
Expected: FAIL — current `saveReassign` updates by `student_id` only and never sets `supervisor_id`, so the partner row and the id assertions fail.

- [ ] **Step 3: Rewrite `saveReassign` to be per-pair**

Replace the `saveReassign` closure body's write block:

```php
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
        // Reassignment changes ownership → set BOTH id and canonical name,
        // for the whole pair (unpaired rows are a pair of one).
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
```

- [ ] **Step 4: Run the whole UserManagement suite and confirm green**

Run: `./vendor/bin/pest tests/Feature/UserManagementTest.php`
Expected: PASS — new cases green; the existing 11 cases unchanged.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/user-management.blade.php tests/Feature/UserManagementTest.php
git commit -m "fix: reassign writes whole pair and sets supervisor_id (Phase 4)"
```

---

## Task 7: CSV import sets `supervisor_id` on exact match

**Files:**
- Modify: `resources/views/livewire/fyp-projects.blade.php` (import loop `:231-246`)
- Test: `tests/Feature/CsvImportSupervisorLinkTest.php` (create)

> Inspect the existing import test setup first (`tests/Feature/CsvImportRealFormatTest.php`) to reuse its fixture-upload helper; mirror that harness rather than inventing a new upload path.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an imported row whose supervisor_name exactly equals a supervisor account name is linked', function () {
    $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Exact Match Sup']);

    // Simulate the importer's per-row resolution by exercising the same helper the
    // component uses. If the importer is only reachable via upload, drive it through
    // the CsvImportRealFormatTest harness with a fixture containing 'Exact Match Sup'.
    $resolved = User::where('role', 'supervisor')->where('name', 'Exact Match Sup')->value('id');
    expect($resolved)->toBe($sup->id);

    FypProject::create([
        'student_id' => 'IMP001', 'student_name' => 'Imported Student',
        'title' => 'X', 'supervisor_name' => 'Exact Match Sup',
        'supervisor_id' => $resolved, 'semester' => 'MARCH 2026',
    ]);

    expect(FypProject::where('student_id', 'IMP001')->first()->supervisor_id)->toBe($sup->id);
});

test('an imported row whose supervisor_name has no exact account match stays unlinked', function () {
    User::factory()->create(['role' => 'supervisor', 'name' => 'Some Other Name']);

    $resolved = User::where('role', 'supervisor')->where('name', 'No Such Match')->value('id');
    expect($resolved)->toBeNull();
});
```

> **Note for the implementer:** the cleaner version of test 1 drives a real upload through the existing import harness with a fixture row naming `Exact Match Sup`, then asserts `supervisor_id` is set. Prefer that if the harness is reusable; the inline version above locks the resolution contract regardless.

- [ ] **Step 2: Run it and confirm the contract**

Run: `./vendor/bin/pest tests/Feature/CsvImportSupervisorLinkTest.php`
Expected: the resolution-contract assertions pass; the end-to-end link assertion fails until Step 3 when driven through the importer.

- [ ] **Step 3: Resolve and set `supervisor_id` in the import loop**

In `fyp-projects.blade.php`, build a name→id lookup once before the `foreach` loop (avoids a query per row):

```php
$supervisorIdByName = \App\Models\User::query()
    ->where('role', 'supervisor')
    ->pluck('id', 'name'); // ['Exact Name' => 3, ...]
```

Then inside the loop, add `supervisor_id` to `$fields`:

```php
                        $fields = [
                            'student_name'     => $studentName,
                            'student_id'       => $studentId,
                            'title'            => $title,
                            'supervisor_name'  => $supervisorName,
                            'supervisor_id'    => $supervisorIdByName[$supervisorName] ?? null,
                            'assessor_name'    => $assessorName,
                            'domain'           => $this->normalizeDomain($domain),
                            'application_type' => $this->normalizeApplicationType($applicationType),
                            'is_ifyp'          => $this->normalizeIfyp($type),
                            'fyp_phase'        => $this->phase,
                            'semester'         => $this->semester,
                            'pair_number'      => $pairNumber,
                        ];
```

> **Re-import caution:** `updateOrCreate` will overwrite `supervisor_id` with `null` for names that don't exactly match an account — which would *unlink* a row previously linked by hand. Guard against regression: only set the key when a match exists, and when re-importing, prefer `?? null` → change to omit the key on no-match by building `$fields` without `supervisor_id` and merging it only when resolved:
>
> ```php
> if (isset($supervisorIdByName[$supervisorName])) {
>     $fields['supervisor_id'] = $supervisorIdByName[$supervisorName];
> }
> ```
> This preserves any hand-curated link on rows whose CSV name never matches. Add a test asserting a hand-linked row survives a re-import of the same CSV.

- [ ] **Step 4: Run it and confirm it passes**

Run: `./vendor/bin/pest tests/Feature/CsvImportSupervisorLinkTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/fyp-projects.blade.php tests/Feature/CsvImportSupervisorLinkTest.php
git commit -m "feat: CSV import links supervisor_id on exact name match (Phase 4)"
```

---

## Task 8: `fyp:link-supervisors` backfill command

**Files:**
- Create: `app/Console/Commands/LinkSupervisors.php`
- Test: `tests/Feature/LinkSupervisorsCommandTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('command links only exact name matches and leaves the rest unlinked', function () {
    $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Exact Person']);

    FypProject::factory()->count(2)->create(['supervisor_name' => 'Exact Person', 'supervisor_id' => null]);
    FypProject::factory()->create(['supervisor_name' => 'Fuzzy Person - Ts.', 'supervisor_id' => null]);

    $this->artisan('fyp:link-supervisors')->assertExitCode(0);

    expect(FypProject::where('supervisor_name', 'Exact Person')->whereNull('supervisor_id')->count())->toBe(0)
        ->and(FypProject::where('supervisor_name', 'Exact Person')->where('supervisor_id', $sup->id)->count())->toBe(2)
        ->and(FypProject::where('supervisor_name', 'Fuzzy Person - Ts.')->whereNull('supervisor_id')->count())->toBe(1);
});

test('command does not match a non-supervisor account', function () {
    User::factory()->create(['role' => 'student', 'name' => 'Looks Like Sup']);
    FypProject::factory()->create(['supervisor_name' => 'Looks Like Sup', 'supervisor_id' => null]);

    $this->artisan('fyp:link-supervisors')->assertExitCode(0);

    expect(FypProject::where('supervisor_name', 'Looks Like Sup')->whereNull('supervisor_id')->count())->toBe(1);
});

test('command is idempotent on re-run', function () {
    $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Idem Sup']);
    FypProject::factory()->create(['supervisor_name' => 'Idem Sup', 'supervisor_id' => null]);

    $this->artisan('fyp:link-supervisors')->assertExitCode(0);
    $this->artisan('fyp:link-supervisors')->assertExitCode(0);

    expect(FypProject::where('supervisor_name', 'Idem Sup')->where('supervisor_id', $sup->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run them and confirm they fail**

Run: `./vendor/bin/pest tests/Feature/LinkSupervisorsCommandTest.php`
Expected: FAIL — command `fyp:link-supervisors` not registered.

- [ ] **Step 3: Create the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Console\Command;

class LinkSupervisors extends Command
{
    protected $signature = 'fyp:link-supervisors';

    protected $description = 'Backfill fyp_projects.supervisor_id by exact match of supervisor_name to a supervisor account name. Idempotent.';

    public function handle(): int
    {
        // Exact name → id, only for supervisor accounts.
        $idByName = User::query()
            ->where('role', 'supervisor')
            ->pluck('id', 'name');

        $linked = 0;
        $unmatched = [];

        $names = FypProject::query()
            ->whereNull('supervisor_id')
            ->whereNotNull('supervisor_name')
            ->where('supervisor_name', '!=', '')
            ->distinct()
            ->pluck('supervisor_name');

        foreach ($names as $name) {
            if (isset($idByName[$name])) {
                $linked += FypProject::where('supervisor_name', $name)
                    ->whereNull('supervisor_id')
                    ->update(['supervisor_id' => $idByName[$name]]);
            } else {
                $unmatched[] = $name;
            }
        }

        $remaining = FypProject::whereNull('supervisor_id')->count();

        $this->info("Linked {$linked} project row(s) by exact match.");
        $this->info('Unmatched names: '.count($unmatched));
        foreach ($unmatched as $name) {
            $this->line("  • {$name}");
        }
        $this->info("Remaining unlinked rows (fallback surface): {$remaining}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run them and confirm they pass**

Run: `./vendor/bin/pest tests/Feature/LinkSupervisorsCommandTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/LinkSupervisors.php tests/Feature/LinkSupervisorsCommandTest.php
git commit -m "feat: fyp:link-supervisors idempotent exact-match backfill (Phase 4)"
```

---

## Task 9: Full-suite verification + status update

**Files:**
- Modify: `docs/PROJECT_STATUS.md` (mark Phase 4 done; update test count)

- [ ] **Step 1: Run the whole suite**

Run: `./vendor/bin/pest`
Expected: PASS — all prior tests green, no existing test weakened, plus the new Phase 4 tests.

- [ ] **Step 2: Sanity-check the metric command**

Run: `php artisan fyp:link-supervisors`
Expected: prints linked/unmatched counts and the remaining fallback surface without error.

- [ ] **Step 3: Update PROJECT_STATUS.md**

Move Phase 4 from "next/planned" to a Completed Work entry; set the reconciled table's Phase 4 status to ✅ with the commit range; refresh the passing test count and the "Last Updated" date. Note the new null-`supervisor_id` count so Phase 5's retirement decision has a baseline.

- [ ] **Step 4: Commit**

```bash
git add docs/PROJECT_STATUS.md
git commit -m "docs: mark Sprint 4 Phase 4 complete; update test count and metric baseline"
```

---

## Test strategy

- **TDD throughout** (RED → GREEN per task), per `CLAUDE.md` ("always run `./vendor/bin/pest` after changes"; "never remove existing tests"). New suites: `SupervisorProvisioningTest`, `SupervisorLinkingTest`, `LinkSupervisorsCommandTest`, `CsvImportSupervisorLinkTest`; additions to `UserManagementTest`.
- **Authorization is tested as behavior, not UI:** every write action (`createSupervisor`, `confirmLink`, `saveReassign`) has a non-coordinator → 403/forbidden test, satisfying the `CLAUDE.md` rule that Livewire actions enforce server-side authorization.
- **Per-pair correctness** is asserted both for paired rows (both members written) and unpaired rows (only the one row written).
- **Idempotency & no-regression:** backfill re-run test; a re-import test asserting a hand-linked row is **not** unlinked when its CSV name doesn't match (Task 7 guard).
- **Read-side untouched:** the Phase 3 tests (`SupervisorIdentityTest`, `SupervisorStudentLogbookTest`, `AnalyticsChartsTest`, `SupervisorStudentsTest`) must stay green — they are the regression guard proving the dual-read still behaves while we populate the FK.
- **DB isolation:** suite runs against in-memory SQLite (`phpunit.xml`), so no live data is touched. The backfill command is only run against live data after explicit approval.

## Risks

- 🔴 **Mis-assignment via the curation UI is a wrong-access vector.** Linking the wrong account exposes the wrong students' logbooks. *Mitigation:* confirm-before-apply, exact human selection, coordinator-only guard, per-pair writes, and the Phase 3 id-precedence tests that already prove a wrong link is the only way to leak.
- 🔴 **Re-import silently unlinks hand-curated rows.** `updateOrCreate` overwriting `supervisor_id` with `null` on a non-matching name would erase curation. *Mitigation:* Task 7 sets the key only when a match exists + a regression test.
- 🟠 **Pair-grouping correctness** (`semester` + `pair_number`). A wrong grouping writes the wrong rows. *Mitigation:* explicit paired and unpaired tests; unpaired treated as pair-of-one by `id`.
- 🟠 **Temp-password handling.** No mail infra. *Mitigation:* show-once in the modal, hashed at rest, never flashed to session/logs. Force-reset flag deferred (needs migration) — documented.
- 🟠 **`UserManagementTest` expectations change** for Reassign (now per-pair + id). *Mitigation:* update/extend the tests, never weaken; the existing 11 cases must stay green.
- 🟠 **Dual-read masks the true link state.** UI looks identical whether a student surfaced via id or name. *Mitigation:* rely on `whereNull('supervisor_id')->count()` (printed by the backfill command) — do not infer link state from the UI.
- ⚪ **SQLite FK enforcement / `nullOnDelete`** already covered by Phase 2 tests; no schema change here.
- ⚪ **Duplicate-account creation** vs. self-registration. *Mitigation:* `unique:users,email` on create.

## Acceptance criteria

1. A coordinator can create a supervisor account (role `supervisor`, `is_active=true`, unique email enforced); the temp password is shown once and stored hashed.
2. The Unlinked Supervisors panel lists every distinct unlinked `supervisor_name` with student/pair counts and excludes already-linked names.
3. Linking an unlinked name to an existing account sets `supervisor_id` on **every** row carrying that exact name, preserves `supervisor_name`, and is coordinator-only + confirmed.
4. Reassign writes the **whole pair** (paired → all members; unpaired → only that row) and sets both `supervisor_id` and the canonical `supervisor_name`.
5. CSV import sets `supervisor_id` on exact name matches and never unlinks a hand-curated row on re-import.
6. `php artisan fyp:link-supervisors` links only exact matches, ignores non-supervisor accounts, is idempotent, and reports linked/unmatched/remaining counts.
7. Every new write action denies non-coordinators (403/forbidden) — proven by tests.
8. Full Pest suite green; no existing test removed or weakened; the Phase 3 read tests still pass.
9. `FypProject::whereNull('supervisor_id')->count()` is measurably lower after backfill, with the baseline recorded in `PROJECT_STATUS.md` for the Phase 5 retirement decision.

## Self-review (against the request)

- **Goals / user stories / technical design / affected files / test strategy / risks / acceptance criteria** — all present.
- **Spec coverage:** provisioning (Tasks 2–3), mapping (Tasks 4–5), backfill (Task 8), writers (Tasks 6–7), verification (Task 9). The roadmap's Phase 5+6 scope items each map to a task.
- **Type/name consistency:** state keys (`newTempPassword`, `linkTargetSupId`, `linkRawName`, `reassignNewSupId`), actions (`createSupervisor`, `openLinkModal`, `confirmLink`, `saveReassign`), and the command signature (`fyp:link-supervisors`) are used identically across tasks and tests.
- **Deferred items** (force-reset flag, fallback removal, fuzzy resolver) are stated as non-goals to prevent scope creep.

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-15-sprint-4-phase-4-provisioning-mapping-backfill.md`. When you are ready to build (this phase requires explicit approval before any live-DB backfill), two execution options:

1. **Subagent-Driven (recommended)** — a fresh subagent per task with review between tasks.
2. **Inline Execution** — execute tasks in-session with checkpoints.
