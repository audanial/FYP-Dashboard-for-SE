# Fix "Pairs vs Students" Metrics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the "Total Pairs", "Avg Pairs / Supervisor", and "Supervisor Workload" analytics count distinct groups (`pair_number`) instead of individual student records, with TDD coverage that locks the behavior.

**Architecture:** All three defects live in one file — the `analytics-charts` Volt component. The fix splits `summaryStats` into a student count and a distinct-`pair_number` count, points the "Total Pairs" card and "Avg / Supervisor" at the pair count, and changes `supervisorWorkload` to count distinct pairs per supervisor. The Industrial card's "% of cohort" deliberately stays student-based, so a separate `students` key is preserved. No schema change is needed (`pair_number` already exists); the supervisor-FK work is a separate roadmap step (Step 3) and is out of scope here.

**Tech Stack:** Laravel 11, Livewire Volt (functional API), Pest, Livewire test helpers (`Livewire::test`, `assertDispatched`).

---

## Background: why this is correct

- `pair_number` is a nullable integer (migration `2026_05_13_000000_add_pair_number_to_fyp_projects_table.php`). One distinct `pair_number` within a semester = one group/pair.
- A "pair" count = number of **distinct non-null `pair_number`** values inside the current semester (+ phase) filter. `->pluck('pair_number')->filter()->unique()->count()` drops nulls, so a project with no recorded pair number is not counted as a pair. Read-only DB checks (2026-06-08) confirmed **0 null `pair_number` rows** in both the seeded (410) and the real-import contexts, so `filter()` is safe and lossless today; it is also the documented decision for any future import that does contain nulls.
- The chart.js dataset for the supervisor chart is **already labelled `'Pairs'`** (line 288). Only the underlying values were wrong (student counts under a "Pairs" label). No JS label edit is required — only the computed values change.

## Files

- **Modify:** `resources/views/livewire/analytics-charts.blade.php`
  - `summaryStats` computed (lines 37–53): add `students` + `pairs`; keep `industrial_pct` on students; base `avg_per_supervisor` on pairs.
  - "Total Pairs" card value binding (line 118): `summaryStats['total']` → `summaryStats['pairs']`.
  - `supervisorWorkload` computed (lines 55–62): count distinct `pair_number` per supervisor instead of student rows.
- **Create:** `tests/Feature/AnalyticsChartsTest.php` — new Pest feature test (RefreshDatabase is applied to all `Feature` tests via `tests/Pest.php`).

No other files change. The dashboard's top "Total SE Students" card (`dashboard.blade.php`) is already a correct student count and is left untouched.

---

### Task 1: Pairs metrics — "Total Pairs" + "Avg Pairs / Supervisor" + industrial-% lock

**Files:**
- Create: `tests/Feature/AnalyticsChartsTest.php`
- Modify: `resources/views/livewire/analytics-charts.blade.php:37-53` (summaryStats), `:118` (card binding)

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AnalyticsChartsTest.php`:

```php
<?php

use App\Models\FypProject;
use App\Models\User;
use Livewire\Livewire;

/**
 * Helper: create one MARCH 2026 / FYP 1 project with a fixed pair + supervisor.
 * student_id is left to the factory so it stays unique across tests.
 */
function makeProject(int $pair, string $supervisor, array $overrides = []): FypProject
{
    return FypProject::factory()->create(array_merge([
        'semester'        => 'MARCH 2026',
        'fyp_phase'       => 'FYP 1',
        'supervisor_name' => $supervisor,
        'pair_number'     => $pair,
        'domain'          => 'AI',
        'is_ifyp'         => false,
    ], $overrides));
}

test('total pairs card counts distinct pair_number, not student rows', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    // 6 students across 3 pairs, all under one supervisor.
    foreach ([1, 1, 2, 2, 3, 3] as $pair) {
        makeProject($pair, 'Dr. Solo');
    }

    Livewire::test('analytics-charts')
        // "Total Pairs" value is the indigo card → 3 pairs (not 6 students)
        ->assertSee('text-indigo-900">3</p>', false)
        // "Avg Pairs / Supervisor" is the violet card → 3 pairs / 1 supervisor = 3
        ->assertSee('text-violet-900">3</p>', false);
});

test('industrial percentage stays based on student count, not pairs', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    // 4 students across 2 pairs; exactly 1 student is industrial.
    makeProject(1, 'Dr. Solo', ['is_ifyp' => true]);
    makeProject(1, 'Dr. Solo');
    makeProject(2, 'Dr. Solo');
    makeProject(2, 'Dr. Solo');

    Livewire::test('analytics-charts')
        ->assertSee('text-amber-900">1</p>', false) // industrial count = 1 student
        ->assertSee('25% of cohort')                // 1/4 students = 25% (NOT 1/2 pairs = 50%)
        ->assertDontSee('50% of cohort');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest --filter=AnalyticsChartsTest`
Expected: the first test FAILS — current code renders `text-indigo-900">6</p>` and `text-violet-900">6</p>` (student counts). The second test PASSES already (it locks existing correct behavior).

- [ ] **Step 3: Fix `summaryStats` to compute students and pairs separately**

In `resources/views/livewire/analytics-charts.blade.php`, replace the whole `summaryStats` computed (lines 37–53) with:

```php
$summaryStats = computed(function () {
    $projects = FypProject::where('semester', $this->semester)
        ->get()
        ->filter(fn($p) => $this->phase === 'all' || $p->fyp_phase === $this->phase);

    $students        = $projects->count();
    $pairs           = $projects->pluck('pair_number')->filter()->unique()->count();
    $industrial      = $projects->filter(fn($p) => (bool) $p->is_ifyp)->count();
    $supervisorCount = $projects->pluck('supervisor_name')->filter()->unique()->count();

    return [
        'students'           => $students,
        'pairs'              => $pairs,
        'industrial'         => $industrial,
        'industrial_pct'     => $students > 0 ? round($industrial / $students * 100) : 0,
        'domains_covered'    => $projects->pluck('domain')->filter()->unique()->count(),
        'avg_per_supervisor' => $supervisorCount > 0 ? round($pairs / $supervisorCount, 1) : 0,
    ];
});
```

- [ ] **Step 4: Point the "Total Pairs" card at the pair count**

In the same file, change the Total Pairs card value (line 118) from:

```php
<p class="mt-2 text-3xl font-bold text-indigo-900">{{ $this->summaryStats['total'] }}</p>
```

to:

```php
<p class="mt-2 text-3xl font-bold text-indigo-900">{{ $this->summaryStats['pairs'] }}</p>
```

Leave the Industrial card (`summaryStats['industrial']`, `['industrial_pct']`), Domains card (`['domains_covered']`), and Avg card (`['avg_per_supervisor']`) bindings unchanged — they already reference keys that still exist.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `./vendor/bin/pest --filter=AnalyticsChartsTest`
Expected: both tests PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/AnalyticsChartsTest.php resources/views/livewire/analytics-charts.blade.php
git commit -m "Count distinct pairs for Total Pairs and Avg/Supervisor metrics"
```

---

### Task 2: Supervisor Workload chart — pairs per supervisor

**Files:**
- Modify: `tests/Feature/AnalyticsChartsTest.php` (append one test)
- Modify: `resources/views/livewire/analytics-charts.blade.php:55-62` (supervisorWorkload)

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/AnalyticsChartsTest.php`:

```php
test('supervisor workload plots distinct pairs per supervisor, not student rows', function () {
    $this->actingAs(User::factory()->create(['role' => 'coordinator']));

    // Dr. Alpha supervises 2 pairs (4 students); Dr. Beta supervises 1 pair (2 students).
    // NOTE: the shared helper was renamed to makeAnalyticsProject in Task 1 (review feedback).
    makeAnalyticsProject(1, 'Dr. Alpha');
    makeAnalyticsProject(1, 'Dr. Alpha');
    makeAnalyticsProject(2, 'Dr. Alpha');
    makeAnalyticsProject(2, 'Dr. Alpha');
    makeAnalyticsProject(3, 'Dr. Beta');
    makeAnalyticsProject(3, 'Dr. Beta');

    Livewire::test('analytics-charts')
        ->call('updatedSemester')
        ->assertDispatched('charts-updated', fn ($name, $params) =>
            $params['supervisorLabels'] === ['Dr. Alpha', 'Dr. Beta']
            && $params['supervisorValues'] === [2, 1] // pairs, sorted desc — NOT [4, 2] students
        );
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest --filter="supervisor workload plots distinct pairs"`
Expected: FAIL — current code dispatches `supervisorValues = [4, 2]` (student counts).

- [ ] **Step 3: Fix `supervisorWorkload` to count distinct pairs**

In `resources/views/livewire/analytics-charts.blade.php`, replace the `supervisorWorkload` computed (lines 55–62) with:

```php
$supervisorWorkload = computed(function () {
    return FypProject::where('semester', $this->semester)
        ->get()
        ->filter(fn($p) => $this->phase === 'all' || $p->fyp_phase === $this->phase)
        ->groupBy(fn($p) => $p->supervisor_name ?: 'Unknown')
        ->map(fn($group) => $group->pluck('pair_number')->filter()->unique()->count())
        ->sortDesc();
});
```

(The only change is the `->map(...)` line: `$group->count()` → `$group->pluck('pair_number')->filter()->unique()->count()`.)

- [ ] **Step 4: Run the test to verify it passes**

Run: `./vendor/bin/pest --filter="supervisor workload plots distinct pairs"`
Expected: PASS — dispatched `supervisorValues = [2, 1]`.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/AnalyticsChartsTest.php resources/views/livewire/analytics-charts.blade.php
git commit -m "Count distinct pairs per supervisor in workload chart"
```

---

### Task 3: Full-suite verification

- [ ] **Step 1: Run the entire test suite**

Run: `./vendor/bin/pest`
Expected: PASS — all pre-existing tests plus the 3 new analytics tests are green. No existing test was modified or removed.

- [ ] **Step 2: Manual smoke check (optional but recommended)**

Visit `/dashboard` as a coordinator. Confirm the "Total Pairs" card now reads the group count (e.g. **205** on the current seeded DB; **77** on the real March 2026 import) rather than the student count, and that the Supervisor Workload bars are scaled to pairs.

---

## Risks & side effects

| # | Risk | Mitigation |
|---|------|-----------|
| 1 | `industrial_pct` silently switches denominator from students→pairs during the refactor | Task 1 Step 1 includes an explicit lock test (`25% of cohort`, not `50%`). |
| 2 | Renaming the `total` key breaks an unseen consumer | Grep confirms `summaryStats['total']` is referenced only at line 118 of this one file; the new `students`/`pairs` keys cover both meanings. |
| 3 | Null `pair_number` rows could undercount pairs on a future import | `->filter()` drops nulls by design (documented above); current data has 0 nulls. If solo/null-pair projects must count as pairs, revisit this rule. |
| 4 | Brittle `assertSee` on Tailwind-classed value (`text-3xl` contains a stray `3`) | Assertions match the value **inside its colour-class wrapper** (`text-indigo-900">3</p>`), which is unique per card, so the stray `3` in `text-3xl` cannot satisfy them. |
| 5 | Equal supervisor pair-counts make chart order non-deterministic in the test | Task 2 fixture uses distinct counts (2 vs 1) so `sortDesc` is deterministic. |

## Out of scope (separate roadmap steps — do not touch here)

- Supervisor identity via `supervisor_id` FK (Roadmap Step 3) — the string-match fragility in `supervisorWorkload`'s `groupBy(supervisor_name)` is **not** fixed by this plan.
- Semester single-source-of-truth (Step 4), dashboard role scoping (Step 5), SQL-side aggregation / Chart.js self-hosting (Step 6).

## Open decision (resolved per audit exit criteria)

The audit (§4, open decision #2) asked whether "Total Pairs" should be **corrected to count pairs (77)** or **relabelled "Total Students" (142)**. This plan follows the audit's stated exit criteria — *"Total Pairs reads 77"* — i.e. **correct the count, keep the label**. Total students remains visible via the dashboard's top "Total SE Students" card, so no information is lost. If the data owner instead wants the analytics card relabelled to students, the only change is the card title plus binding it to `summaryStats['students']`.
