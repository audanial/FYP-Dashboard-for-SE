# Sprint 4 — Phase 3 (Identity Reads) Handoff

| | |
|---|---|
| **Sprint / Phase** | Sprint 4 · Phase 3 — switch supervisor identity *reads* to `supervisor_id` |
| **Prepared** | 2026-06-09 (end of Phase 2) |
| **For** | next session (2026-06-10) |
| **State coming in** | Phases 1–2 complete; full suite **100 passing**. `supervisor_id` FK + relations exist; no read uses it yet. |
| **Status** | Planning/handoff only — no Phase 3 code written. |

## Goal
Make ownership reads prefer the `supervisor_id` FK, with a **temporary, measurable name fallback**, so messy-named supervisors see their students while the legacy data/tests keep working. Reads only — no writes (import/reassign stay on name until Phase 6).

## Dual-read predicate (the rule every read uses)
```
supervisor_id = Auth::id()
   OR ( supervisor_id IS NULL AND supervisor_name = Auth::user()->name )
```
**Id precedence is mandatory:** a row linked to another supervisor must never reach the name branch — the fallback applies only to `supervisor_id IS NULL` rows.

**Measurability:** `FypProject::whereNull('supervisor_id')->count()` = remaining fallback surface. When it hits 0, the fallback is provably dead and removable (retirement criterion).

## Approved analytics decision — effective-key grouping (during transition)
Group each project by its **effective supervisor key**, in this order:
1. `supervisor_id` exists → **use the supervisor user's name** (canonical; collapses string duplicates like `Noor Widasuria` ×2).
2. `supervisor_id` null **but** `supervisor_name` exists → **use `supervisor_name`** (keeps the chart meaningful before Phase 6 backfill).
3. both missing → **"Unlinked"** bucket.

Rationale: avoids dumping all 142 not-yet-linked rows into one giant "Unlinked" bar during the transition. Requires eager-loading `with('supervisor')` to avoid N+1. Keep `supervisorCount` / `avg_per_supervisor` consistent with this grouping.

## Security risks (read carefully before coding)
- **🔴 Malformed `OR` broadens access.** A missing nested closure could turn the logbook gate into "any project with this `student_id`," leaking another student's logbook. Always wrap the dual-read as a single nested `where(function ($q) { … })`.
- **🔴 Wrong-access via name coincidence.** A row with `supervisor_id` = *another* user but a `supervisor_name` matching mine must **not** be visible/openable to me. Id precedence + the `whereNull('supervisor_id')` guard prevents this — and it must be tested explicitly.
- **🟠 Fail-closed gate.** `supervisor-student-logbook.blade.php` must keep `firstOrFail()` (403 on no match). Don't loosen it.
- **🟠 Dual-read masks failures.** Seeing students via the name branch looks identical to via id. Lean on the null-count metric (and optional fallback log) to know the real link state.

## First RED test to write tomorrow
**File:** `tests/Feature/SupervisorIdentityTest.php` (new).

**Test — the defect anchor:**
- Create supervisor `User` named `Tiliza binti Awang Mat`.
- Create a `FypProject` with `supervisor_name = "Tiliza Awang Mat - Ts."` **and** `supervisor_id = $tiliza->id`.
- Assert "My Students" shows that student.

**Expected:** **RED today** — the current read matches on name only, the names differ, so the list is empty. Goes **GREEN** once `my-students` uses the dual-read (id matches). This locks the exact production defect.

Then follow with: id-precedence/wrong-access 403 test, name-fallback still-works test (protects the 4 legacy `SupervisorStudentsTest`), and analytics effective-key grouping test.

## Files expected to change
| File | Change |
|---|---|
| **NEW** `tests/Feature/SupervisorIdentityTest.php` | RED anchor + dual-read + security tests |
| `resources/views/livewire/my-students.blade.php` (~`:19-29`) | ownership query → dual-read closure |
| `resources/views/pages/supervisor-student-logbook.blade.php` (~`:21-24`) | auth gate → dual-read closure (keep `firstOrFail`) |
| `resources/views/livewire/analytics-charts.blade.php` (`:38-64`) | effective-key grouping + `with('supervisor')` + Unlinked bucket |
| *(optional)* small metric helper / `Log` | fallback observability |

**Out of scope for Phase 3:** migrations, writes (import/reassign), UI/markup, coordinator supervisor-name search (later cleanup), factory/seeder changes.

## Definition of done (Phase 3)
RED anchor goes GREEN · wrong-access 403 proven · 4 legacy `SupervisorStudentsTest` still green · analytics groups by effective key (linked duplicates collapse, unlinked still visible, empty → Unlinked) · full suite green · null-`supervisor_id` count metric in place.
