# User Management Step 2 — Display & Filtering Redesign

**Date:** 2026-05-08
**Scope:** Read-only UI overhaul of the Manage Users page for UniKL MIIT FYP Dashboard
**Status:** Approved for implementation

---

## Context

Step 1 added `department` and `is_active` fields to the `users` table. Step 2 makes these visible and adds a professional admin UI on top of the existing `user-management` Volt component. No write operations are introduced in this step.

---

## Goals

- Add a modern page header that communicates context clearly
- Show stat cards (student / supervisor / coordinator counts) at a glance
- Add reactive role-filter tabs and live name/email search
- Redesign the user table to include: Name, Email, Role, Department, Status, Actions
- Display all user data in a read-only, professional layout suitable for a university FYP demo

## Non-Goals (deferred to future steps)

- Add user, edit user, delete user — no write UI in Step 2
- Role-change dropdown removed from view (existing `updateRole` action remains in code but has no UI trigger)
- CSV import of users
- Pagination (not needed at current scale)

---

## File Changed

**Only one file is modified:**
`resources/views/livewire/user-management.blade.php`

No new files, no new routes, no new migrations, no new Livewire components.

---

## Architecture

### State

```php
state([
    'search'     => '',
    'roleFilter' => 'all',  // 'all' | 'student' | 'supervisor' | 'coordinator'
]);
```

### Computed — filtered users

Replaces the eager `User::all()`. Reactive to `search` and `roleFilter` changes via `wire:model.live`.

```php
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
```

### Computed — stat counts

Runs three lightweight `COUNT` queries on page load. Not reactive to search/filter — stats always reflect total registered users, not the current filtered view.

```php
$stats = computed(fn() => [
    'students'     => User::where('role', 'student')->count(),
    'supervisors'  => User::where('role', 'supervisor')->count(),
    'coordinators' => User::where('role', 'coordinator')->count(),
]);
```

### Existing `updateRole` action

Kept completely untouched. No UI element calls it in Step 2. It will be wired up in a future step when an Edit modal is introduced.

---

## UI Sections

### 1. Page Header

- **Title:** "Manage Users"
- **Subtitle:** "View and manage all registered users in the FYP system."
- **Role indicator badge** (top-right): small pill showing "FYP Coordinator" with a green dot — confirms the current user's permission level
- Style: clean white background, bottom border separator

### 2. Stat Cards (3-column grid)

Three cards in a horizontal row, stacking to a single column on small screens (`grid-cols-1 sm:grid-cols-3`).

| Card | Accent | Icon | Value source |
|------|--------|------|--------------|
| Total Students | Blue (`sky`) | Heroicons v2 outline `academic-cap` (inline SVG) | `$this->stats['students']` |
| Total Supervisors | Green (`emerald`) | Heroicons v2 outline `briefcase` (inline SVG) | `$this->stats['supervisors']` |
| FYP Coordinators | Purple (`violet`) | Heroicons v2 outline `building-library` (inline SVG) | `$this->stats['coordinators']` |

- Subtle border and shadow (`shadow-sm`), no gradients
- Icon rendered in a small rounded square with a tinted background
- Stats do not change when the user filters or searches — they always show total counts

### 3. Role Filter Tabs

Segmented pill control. Each tab is a `<button>` with `wire:click="$set('roleFilter', 'value')"`. The active tab is highlighted by comparing `$roleFilter` to the tab value in the template.

Options (with display labels):
- `all` → **All**
- `student` → **Students**
- `supervisor` → **Supervisors**
- `coordinator` → **FYP Coordinators**

Tabs show labels only — no per-tab counts. Showing counts per tab would require extra queries or client-side aggregation that adds complexity without meaningful benefit at this stage.

Active tab: solid indigo background, white text. Inactive tabs: neutral background, muted text. The tab strip sits inside a light gray container with rounded corners.

### 4. Search Input

- Single `<input>` with `wire:model.live="search"`
- Placeholder: `"Search by name or email…"`
- Magnifying glass icon left-aligned inside the input
- Clear button (×) appears when input has text, resets `search` to empty
- Positioned to the right of the filter tabs on wide screens; stacks below on narrow screens

### 5. User Table

Columns in order:

| Column | Content | Notes |
|--------|---------|-------|
| **Name** | `$user->name` (bold, dark) + `$user->displayUsername()` as muted subtext below | Strongest visual column |
| **Email** | `$user->email` | Muted slate color |
| **Role** | Colored badge | Blue=student, Green=supervisor, Purple=coordinator. Read-only. Compact, consistent size. |
| **Department** | `$user->department` or "Not assigned" if null | Plain text, muted |
| **Status** | Badge: green "Active" / red "Inactive" | Driven by `$user->is_active`. Soft tones, not neon. |
| **Actions** | Disabled "Edit" button | Gray border, muted text, `cursor-not-allowed`, `opacity-50`. Communicates future functionality without appearing broken. No `wire:click`. |

**Role badge colors (soft tones):**
- `student` → `bg-blue-100 text-blue-700`
- `supervisor` → `bg-emerald-100 text-emerald-700`
- `coordinator` → `bg-violet-100 text-violet-700`

**Status badge colors:**
- Active → `bg-green-100 text-green-700`
- Inactive → `bg-red-100 text-red-700`

### 6. Empty State

Shown when `$this->filteredUsers` is empty:

> "No users match the current search or filter."

Centered in the table body area, muted text, no icon required.

---

## Reactivity

Both `search` and `roleFilter` use `wire:model.live` so the table updates instantly on each keystroke / tab click without a submit button. This mirrors the existing pattern in `fyp-dashboard.blade.php`.

---

## Session Flash Messages

The existing success/error flash blocks (`session('message')` and `session('error')`) are retained in the template. They display above the table when set.

---

## Responsiveness

- Stat cards: `grid-cols-1 sm:grid-cols-3`
- Filter tabs + search: flex row on md+, stacked column on smaller screens
- Table: `overflow-x-auto` wrapper so it scrolls horizontally on narrow displays

---

## Testing

No new tests are written for this step. The existing `DashboardTest.php` and auth tests continue to pass. The `updateRole` logic is not changed, so existing test coverage for it is unaffected.

A future step should add:
- A test that search by name returns only matching users
- A test that role filter returns only matching roles
- A test that stat counts reflect DB state

---

## What Is Not Changing

- Authentication and routing — untouched
- The `admin/users` route and its coordinator-only guard — untouched
- The `updateRole` action — code stays, UI hidden
- Flash message rendering — kept as-is
- The `User` model — no changes needed

---

## Implementation Notes

- Use `$this->filteredUsers` (not `$filteredUsers`) in the Blade template when referencing the computed property
- Keep the PHP block at the top of the file (Volt convention)
- Do not use `protected` or `public` properties — only `state()` and `computed()` (project Volt rule)
- Inline SVG for Heroicons (no extra package dependency)
