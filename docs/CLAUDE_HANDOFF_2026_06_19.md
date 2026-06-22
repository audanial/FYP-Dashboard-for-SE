# Claude Handoff — FYP Dashboard System
### Session date: 19 June 2026 · Branch: `claude/laravel-fyp-dashboard-ot8rR`

> This document exists so a new Claude session — with zero prior context — can immediately
> understand the project, its history, and what to do next. Read this before touching any code.

---

## 1. Project Overview

**Name:** FYP Dashboard System — UniKL MIIT

**Purpose:** A web application that tracks Final Year Projects (FYP) for Software Engineering
students at Universiti Kuala Lumpur (UniKL) Malaysian Institute of Information Technology (MIIT).
It is used daily by three roles:

| Role | What they do in the system |
|---|---|
| **coordinator** | Imports the master CSV of all student-project assignments; manages user accounts; views all projects and analytics. |
| **supervisor** | Views their assigned students' project details; reads those students' logbook entries. |
| **student** | Maintains their own logbook (entries, progress notes). |

**Tech stack:**
- Laravel 11 + Livewire **Volt** (functional API — `state()`/`computed()`/closures) + Flux UI + Tailwind CSS
- SQLite database (file-based; also `:memory:` for tests)
- Pest for feature and unit tests
- PHP 8.4 (pinned via `.php-version` for Railway deployment)
- One Playwright E2E spec at `tests/import.spec.js` (not the focus of recent sprints)

**Deployment:** Railway. The `.php-version` file pins PHP 8.4.

**Important Volt rule:** All Livewire components under `resources/views/livewire/` use the
**functional API only** — `state([...])`, `computed(function() {...})`, plain closures. Never use
class-based Volt syntax (`new class extends Component`), protected/public properties, or `mount()`
methods in Volt files. This is a hard project constraint.

---

## 2. Business Problem Being Solved

### The core defect (Sprint 4 reason for existence)

Before Sprint 4, the system matched supervisors to their projects using an **exact string
comparison** between `users.name` and `fyp_projects.supervisor_name`. This was fundamentally
broken for real data because the CSV file and the user account used completely different name
formats for the same person:

```
users.name          → "Tiliza binti Awang Mat"
fyp_projects.supervisor_name (from CSV) → "Tiliza Awang Mat - Ts."
```

The differences include: prefix moved to suffix, missing `binti`, and inconsistent casing. As a
result, **supervisors logged in and saw zero students** — their "My Students" page was empty. This
was not a cosmetic bug; the entire supervisor workflow failed on real data.

Additional consequences of the string-match approach:
- A supervisor with a name coincidentally similar to another's could open the wrong student's logbook (privilege escalation).
- Analytics grouped by the raw string, so one person counted as multiple supervisors.
- The "Reassign Supervisor" UI only updated a single student row, leaving the pair partner with the old supervisor.

### The fix strategy (Sprint 4)

Replace string-based ownership with a real **`supervisor_id` foreign key** on `fyp_projects`
pointing to `users.id`. Migrate all reads, writes, and the backfill of existing data onto the FK.
Keep the legacy `supervisor_name` string during the transition as a measured fallback while data
is being linked.

---

## 3. Architecture Overview

### Database tables (relevant)

```
users
  id, name, email, password, role (coordinator|supervisor|student),
  username, department, is_active, email_verified_at, profile_photo_path

fyp_projects
  id, student_id (FK → users.username), student_name, title,
  supervisor_name (string — legacy, retained for display/fallback),
  supervisor_id (FK → users.id, nullable, nullOnDelete),   ← added Sprint 4
  assessor_name, domain, application_type, is_ifyp,
  fyp_phase, semester, pair_number

logbooks
  id, user_id (FK → users.id), ...
```

### Key models

**`app/Models/FypProject.php`**
- `supervisor()` → `belongsTo(User::class, 'supervisor_id')` — the structural FK relation.
- `supervisor_id` is in `$fillable`.

**`app/Models/User.php`**
- `supervisedProjects()` → `hasMany(FypProject::class, 'supervisor_id')` — inverse relation.
- `hasRole(string ...$roles): bool` — role check used by all authorization guards.

### Pair semantics

A "pair" is two students sharing the same `semester` AND `pair_number`. Supervisor assignment
always writes to the **whole pair**. Unpaired rows (`pair_number IS NULL`) are treated as a pair
of one. This rule applies everywhere: the Reassign UI, the Unlinked Supervisors panel, and the
backfill command.

### Dual-read predicate (temporary, in effect now)

Both `my-students.blade.php` and `supervisor-student-logbook.blade.php` use this query pattern:

```php
->where(function ($q) {
    $q->where('supervisor_id', Auth::id())
      ->orWhere(function ($q) {
          $q->whereNull('supervisor_id')
            ->where('supervisor_name', Auth::user()->name);
      });
})
```

**ID precedence is mandatory.** A row linked to another supervisor via `supervisor_id` must
never surface to the current user through a name coincidence. The name fallback applies **only**
to rows where `supervisor_id IS NULL`.

This predicate is **temporary**. It will be removed in Phase 5 once all rows are linked.

### Analytics effective-key grouping

`analytics-charts.blade.php` groups the supervisor workload chart and the "Avg Pairs /
Supervisor" card by an **effective key**:
1. `supervisor_id` is set and the relation resolves → use the linked user's canonical name.
2. `supervisor_id` is null but `supervisor_name` exists → use `supervisor_name` (keeps the chart meaningful during transition).
3. Both missing → bucket as **"Unlinked"**.

Eager-loads `with('supervisor')` to avoid N+1.

### Key Livewire components

| File | Serves |
|---|---|
| `resources/views/livewire/fyp-projects.blade.php` | Coordinator: CSV import, project list with filters |
| `resources/views/livewire/user-management.blade.php` | Coordinator: user table, Create Supervisor modal, Unlinked Supervisors panel, Reassign modal |
| `resources/views/livewire/my-students.blade.php` | Supervisor: "My Students" list |
| `resources/views/livewire/analytics-charts.blade.php` | Coordinator: analytics charts and stat cards |
| `resources/views/livewire/my-logbook.blade.php` | Student: logbook CRUD |
| `resources/views/pages/supervisor-student-logbook.blade.php` | Supervisor: reads a student's logbook |

### Artisan commands

| Command | Purpose |
|---|---|
| `php artisan fyp:clean-supervisor-encoding` | Repair Windows-1252/mojibake characters already stored in `supervisor_name`. Idempotent. |
| `php artisan fyp:link-supervisors` | Backfill `supervisor_id` by exact name match (`supervisor_name === users.name` where `role=supervisor`). Idempotent. Prints linked / unmatched / remaining counts. |

---

## 4. Sprint History Summary

| Sprint | Goal | Status |
|---|---|---|
| Sprint 1–2 | Core scaffolding: CSV import, user management, logbooks, auth. | ✅ Complete |
| Sprint 3 | Analytics correctness: fix pairs-vs-students metrics; supervisor workload chart. | ✅ Complete (94 tests at close) |
| **Sprint 4** | Replace string-based supervisor ownership with a real `supervisor_id` FK. | ✅ Phases 1–4 done; Phase 5 pending |

---

## 5. Current Status

| | |
|---|---|
| **Branch** | `claude/laravel-fyp-dashboard-ot8rR` |
| **Working tree** | Clean (`.claude/settings.local.json` is a harness file, not application code) |
| **Test suite** | **137 passing / 328 assertions** — verified 2026-06-19 |
| **Last commit** | `a187cc1` — docs: Phase 4 sign-off |
| **Blocking coordinator action** | Provision 24 missing supervisor accounts before Phase 5 can begin |

---

## 6. Completed Sprint 4 Phases

### Phase 1 — Encoding Stabilization · `8bea149`
**Problem:** The real CSV contains Windows-1252 encoded names (e.g., `Rohaya Abu Hassan ï¿½ Ts.`).
These poisoned `supervisor_name` values before any identity comparison could work.

**Solution:**
- `app/Support/Encoding.php` — strips UTF-8 BOM; coerces Windows-1252 bytes → UTF-8.
- Import loop in `fyp-projects.blade.php` normalizes each name at ingest time.
- `fyp:clean-supervisor-encoding` command repairs already-stored corrupted rows.

**Tests:** `CsvImportEncodingTest.php` (+3)

---

### Phase 2 — `supervisor_id` Foundation · `3e54f50`
**Goal:** Add the FK with zero behavior change — nothing reads it yet.

**Changes:**
- Migration `2026_06_09_120000_add_supervisor_id_to_fyp_projects_table.php` — nullable `unsignedBigInteger`, FK → `users.id` with `nullOnDelete`, indexed.
- `FypProject::supervisor()` belongsTo relation.
- `User::supervisedProjects()` hasMany relation.

**Tests:** `SupervisorRelationTest.php` (+3)

---

### Phase 3 — Identity Reads / Dual-Read · `38a9c11`, `448cefc`, `7eee670`

**3.1 My Students** — `my-students.blade.php` switches from `where('supervisor_name', name)` to
the dual-read predicate. A supervisor with a messy CSV name now sees their students if linked by
`supervisor_id`.

**3.2 Logbook Authorization** — `supervisor-student-logbook.blade.php` applies the same
dual-read in `mount()`. Closed a real **privilege-escalation hole**: a supervisor whose name
coincidentally matched another's project could previously open the wrong logbook.

**3.3 Analytics Effective-Key Grouping** — `analytics-charts.blade.php` groups by effective key
(linked name → raw name → "Unlinked") instead of raw `supervisor_name` string. Prevents one
person counting as multiple supervisors in the workload chart.

**Tests:** `SupervisorIdentityTest.php` (+3), `SupervisorStudentLogbookTest.php` (+3),
`AnalyticsChartsTest.php` (+4)

---

### Phase 4 — Provisioning, Mapping & Backfill · `c20cdef`→`adb28ab`

Full plan: `docs/superpowers/plans/2026-06-15-sprint-4-phase-4-provisioning-mapping-backfill.md`

#### Task 1 — Factory state (`c20cdef`)
`FypProjectFactory::forSupervisor(User $supervisor)` — sets `supervisor_id` and copies
`supervisor_name` from the user. Used throughout Phase 4 tests for terse setup.

#### Tasks 2–3 — Create Supervisor modal (`d1094fc`, `29762cd`)
New `createSupervisor()` action and modal in `user-management.blade.php`:
- Role forced to `supervisor`, `is_active = true`, `email_verified_at` set immediately.
- Unique email enforced. Temp password generated with `Str::password(16)`, stored hashed,
  surfaced **once** in the modal (never flashed to logs).

#### Tasks 4–5 — Unlinked Supervisors panel (`6d9fc59`, `58753a1`)
New `unlinkedSupervisors` computed property and panel in `user-management.blade.php`:
- Lists every distinct `supervisor_name` where `supervisor_id IS NULL`, with student count and
  pair count.
- "Link to existing" button opens a modal to select a supervisor account → `confirmLink()`
  writes `supervisor_id` to every row sharing that exact raw name (whole-pair semantics via
  name-equality). Preserves `supervisor_name` (provenance of the imported string).
- Coordinator-only (`abort_unless` guard). Confirm-before-apply.

#### Task 5.5 — Email verification (`be91da0`)
Coordinator-provisioned accounts get `email_verified_at = now()` on creation so they can log in
immediately without an email verification flow.

#### Task 6 — Fix Reassign per-pair (`d6a9c72`)
`saveReassign()` previously updated only the triggering student's row and never set `supervisor_id`.
Rewritten to:
- Find the student's project.
- Scope the update to the whole pair (`semester` + `pair_number`) or just that row if unpaired.
- Set **both** `supervisor_id` (FK) and `supervisor_name` (canonical name) — because a reassign
  is an intentional ownership change, not just a string-to-account mapping.

#### Task 7 — CSV import sets `supervisor_id` (`f78e086`)
Import loop in `fyp-projects.blade.php` now:
1. Builds a `supervisor_name → id` lookup (`pluck('id','name')`) once before the `foreach`.
2. Conditionally merges `supervisor_id` into `$fields` **only when a match exists**.
3. Never sets `supervisor_id = null` explicitly — so `updateOrCreate` in a future update mode
   will not unlink a hand-curated row whose CSV name doesn't match an account.

#### Task 8 — `fyp:link-supervisors` backfill command (`adb28ab`)
`app/Console/Commands/LinkSupervisors.php`:
- Collects all distinct `supervisor_name` values where `supervisor_id IS NULL`.
- For each: if exactly one `role=supervisor` account has `name === supervisor_name`, sets
  `supervisor_id` on all matching rows. Otherwise adds to the unmatched list.
- Idempotent — the `whereNull('supervisor_id')` guard on the update means re-running is safe.
- Prints: linked row count, list of unmatched names, remaining unlinked row count.

---

## 7. Phase 4 Accomplishments Summary

| Capability | Before Phase 4 | After Phase 4 |
|---|---|---|
| Coordinator creates supervisor accounts | Not possible | ✅ "Create Supervisor" modal |
| Unlinked names visible to coordinator | Not visible | ✅ Panel with student/pair counts |
| Linking a raw CSV name to an account | Not possible | ✅ "Link to existing" (whole-pair write) |
| Reassign writes to whole pair | ❌ Single row only | ✅ Pair-scoped update |
| Reassign sets `supervisor_id` | ❌ Never | ✅ Always |
| CSV import sets `supervisor_id` | ❌ Never | ✅ On exact name match |
| Bulk backfill for existing rows | ❌ No tooling | ✅ `fyp:link-supervisors` command |

---

## 8. Current Metrics

| Metric | Value |
|---|---|
| **Pest tests passing** | **137** |
| **Assertions** | **328** |
| **Null `supervisor_id` rows (live DB)** | **137** (baseline 2026-06-19) |
| **Exact-match rows linked on first backfill run** | 5 |
| **Unmatched supervisor names** | 24 (all messy CSV-format strings) |
| **Supervisor accounts in live DB** | 1 (only 1 existed at Sprint 4 start) |

### The 24 unmatched names (from live DB run 2026-06-19)

These are the `supervisor_name` values in `fyp_projects` that have no exact-match supervisor
account. All require coordinator action (provision account, then link):

```
Hana Munira Muhd Mukhtar         Azrai Bin Abdul Aziz
Zailatul Syeema Mahadi            Norshaharizan Puteh
Nor Azlina Ali - Ts.              Mohamad Adib Baihaqi Bin Ahmad
Nik Azlina Nik Ahmad              Munaisyah Abdullah - Assoc. Prof. Ts. Dr.
Suguneswari Raja Gopal - Ts.      Nurdatillah Hasim - Ts.
Siti Fatimah Omar - Ts.           Suzana Kassim
Noor Widasuria Abu Bakar - Ts. Dr  Azaliza Zainal - Dr
Zanariah Abu Bakar -Ts.           Noor Widasuria Abu Bakar - Ts. Dr.
Ahmad Zhafri Hariz Roslan         Nor Haqkiem Hamdam
Juliana Jaafar - Ts. Dr.          Azizah Rahmat - Dr.
Rohaya Abu Hassan ï¿½ Ts.         Nurul Sharaz Azmanuddin
(mojibake — needs encoding fix too) Norhaidah Abu Haris - Dr
                                   Chen Xinyuan - Dr
```

Note `Rohaya Abu Hassan ï¿½ Ts.` — this name has residual mojibake. Run
`php artisan fyp:clean-supervisor-encoding` first to repair it, then re-run the backfill.

---

## 9. Important Architectural Decisions

### A. FK over string matching
**Decision:** Add `supervisor_id` (FK → `users.id`) as the identity anchor; do not patch strings.

**Why:** String patches don't scale. Every new CSV format or honorific variant reintroduces the
bug. A real FK gives referential integrity and lets the database enforce ownership.

### B. Exact-match only — no fuzzy resolver
**Decision:** Auto-link only when `supervisor_name === users.name` exactly. Everything else
requires coordinator curation.

**Why:** Fuzzy matching risks wrong assignment. In a system where the consequence of a wrong link
is a supervisor seeing the wrong students' logbooks, false positives are worse than false
negatives. Human curation is required for ambiguous cases.

### C. Dual-read as a temporary, measurable transition
**Decision:** Keep the name fallback while `supervisor_id` is still being populated, but make
it measurable (`whereNull('supervisor_id')->count()`) and document the retirement criterion.

**Why:** Dropping the fallback immediately would break every existing supervisor (all 137
unlinked rows would return zero results). The metric gives a clear, objective signal for when
the fallback is provably dead.

### D. Pair semantics for all writes
**Decision:** Supervisor assignment always writes to the whole pair (both students sharing the
same `semester` + `pair_number`). Unpaired rows are treated as a pair of one.

**Why:** A paired project has two students who share the same supervisor. Writing to only one
row leaves the partner orphaned under the old supervisor. This was the original bug in
`saveReassign`.

### E. Link vs. Reassign are deliberately different operations
- **Link** (curation panel / backfill): map a raw CSV string to the correct account. Sets
  `supervisor_id` only; **preserves** `supervisor_name` (keeps the provenance of the original
  imported string).
- **Reassign** (coordinator UI): change who supervises a student. Sets **both** `supervisor_id`
  and `supervisor_name` (updates the canonical name too, since this is an intentional ownership
  change).

### F. Re-import guard
When `supervisor_id` is set in the import loop, the key is **omitted from `$fields`** (not set
to `null`) when no exact match is found. This means `updateOrCreate` in update mode will never
silently unlink a hand-curated `supervisor_id` on a re-import of the same CSV.

---

## 10. Remaining Phases

### Phase 5 — Dual-Read Retirement (planned, not started)

**Trigger:** `FypProject::whereNull('supervisor_id')->count()` = 0

**Prerequisite coordinator steps (outside the codebase):**
1. Use **Create Supervisor** modal → provision accounts for the 24 unmatched names.
2. Run `php artisan fyp:link-supervisors` → auto-links exact matches.
3. Use **Unlinked Supervisors** panel → manually map any residual non-exact names.
4. Repeat steps 2–3 until the null-count reaches 0.

**Code changes in Phase 5:**
- Remove the `OR (supervisor_id IS NULL AND supervisor_name = …)` branch from
  `my-students.blade.php` and `supervisor-student-logbook.blade.php`.
- Simplify analytics to group purely by `supervisor_id` (remove the effective-key helper).
- Regression test pass to confirm no supervisor loses visibility.
- Mark Sprint 4 complete.

---

## 11. Phase 5 Goals

1. **Zero name-fallback reads.** Every supervisor access flows through `supervisor_id`.
2. **Simpler query logic.** No dual-read `OR` clause; no effective-key helper.
3. **Cleaner analytics.** Workload groups by `supervisor_id` only; no "Unlinked" bucket (or the bucket is empty and can be removed).
4. **Documented retirement.** A final commit notes that `supervisor_name` is now a display-only field and can be dropped in a future migration when no display code references it.

---

## 12. Known Risks

| Risk | Severity | Status | Mitigation |
|---|---|---|---|
| Mojibake in `Rohaya Abu Hassan ï¿½ Ts.` persists in live DB | 🟠 | Open — run `fyp:clean-supervisor-encoding` first | Encoding repair command exists |
| 24 supervisor accounts still unprovisioned | 🔴 | Open — coordinator action required | Unlinked Supervisors panel ready |
| Dual-read masking failures | 🟠 | Ongoing during transition | `whereNull('supervisor_id')->count()` metric |
| Wrong assignment via curation UI | 🔴 | Mitigated | Confirm-before-apply; exact human selection; per-pair write; id-precedence tests prove wrong link = only path to leak |
| Coordinator role drift (`admin` alias) | 🟡 | Dormant | Live coordinator is `coordinator`; backlog item |
| Force-password-reset for provisioned accounts | 🟡 | Deferred | Show-once temp password; needs `users` migration for flag |

---

## 13. Key Commands

```bash
# Run the full test suite
php vendor/bin/pest

# Run a single test file
php vendor/bin/pest tests/Feature/SupervisorIdentityTest.php

# Run only tests matching a pattern
php vendor/bin/pest --filter "reassign"

# Repair mojibake in existing supervisor_name values (idempotent)
php artisan fyp:clean-supervisor-encoding

# Backfill supervisor_id by exact name match (idempotent, read-before-write, safe to re-run)
php artisan fyp:link-supervisors

# Check the remaining fallback surface (the Phase 5 retirement metric)
php artisan tinker --execute="echo \App\Models\FypProject::whereNull('supervisor_id')->count();"
```

---

## 14. Important Files

### Application code

| File | Purpose |
|---|---|
| `app/Models/FypProject.php` | Main project model; `supervisor()` relation; `supervisor_id` in `$fillable` |
| `app/Models/User.php` | User model; `supervisedProjects()` relation; `hasRole()` |
| `app/Models/Logbook.php` | Student logbook entries |
| `app/Support/Encoding.php` | UTF-8 BOM strip + Windows-1252 → UTF-8 coercion |
| `app/Console/Commands/CleanSupervisorEncoding.php` | `fyp:clean-supervisor-encoding` |
| `app/Console/Commands/LinkSupervisors.php` | `fyp:link-supervisors` |
| `app/Http/Middleware/EnsureUserHasRole.php` | Route-level role middleware |
| `app/Services/CsvHeaderResolver.php` | Maps real CSV header variants to internal field names |
| `resources/views/livewire/fyp-projects.blade.php` | CSV import loop; project list |
| `resources/views/livewire/user-management.blade.php` | Create Supervisor; Unlinked Supervisors panel; Reassign |
| `resources/views/livewire/my-students.blade.php` | Supervisor "My Students" with dual-read |
| `resources/views/pages/supervisor-student-logbook.blade.php` | Supervisor logbook access gate |
| `resources/views/livewire/analytics-charts.blade.php` | Charts + effective-key grouping |

### Migrations

| File | Purpose |
|---|---|
| `database/migrations/2026_03_24_014932_create_fyp_projects_table.php` | Base `fyp_projects` table |
| `database/migrations/2026_06_09_120000_add_supervisor_id_to_fyp_projects_table.php` | Adds `supervisor_id` FK (Sprint 4 Phase 2) |

### Test files

| File | Covers |
|---|---|
| `tests/Feature/SupervisorIdentityTest.php` | Dual-read: id-linked, wrong-access guard, name fallback |
| `tests/Feature/SupervisorStudentLogbookTest.php` | Logbook access gate with dual-read |
| `tests/Feature/SupervisorStudentsTest.php` | My Students page — legacy name-match tests |
| `tests/Feature/AnalyticsChartsTest.php` | Effective-key grouping, pair counts, workload chart |
| `tests/Feature/SupervisorProvisioningTest.php` | Create Supervisor modal: role, password, dedup |
| `tests/Feature/SupervisorLinkingTest.php` | Unlinked panel, confirmLink per-pair, guards |
| `tests/Feature/UserManagementTest.php` | User table filters + Reassign per-pair + supervisor_id |
| `tests/Feature/CsvImportSupervisorLinkTest.php` | Import sets supervisor_id on exact match |
| `tests/Feature/LinkSupervisorsCommandTest.php` | Backfill command: exact match, role guard, idempotency |
| `tests/Feature/CsvImportRealFormatTest.php` | Full import behaviour with real CSV format |
| `tests/Feature/CsvImportEncodingTest.php` | BOM handling, Windows-1252 repair |
| `tests/Feature/SupervisorRelationTest.php` | Model relations: supervisor(), supervisedProjects() |

### Planning documents

| File | Purpose |
|---|---|
| `docs/PROJECT_STATUS.md` | Single source of truth for current state (always check this) |
| `docs/superpowers/plans/2026-06-09-sprint-4-supervisor-identity-roadmap.md` | Original Sprint 4 roadmap (phases 1–7) |
| `docs/superpowers/plans/2026-06-15-sprint-4-phase-4-provisioning-mapping-backfill.md` | Phase 4 detailed task plan |
| `docs/reports/sprint-4-supervisor-identity-handoff.md` | Sprint 4 investigation findings |
| `docs/reports/sprint-4-phase-3-reads-handoff.md` | Phase 3 dual-read design decisions |

---

## 15. Recommended Next Steps

### Immediate — coordinator action (no code required)

These steps must happen in the live application before Phase 5 code work can begin:

1. **Run the encoding repair** first (otherwise `Rohaya Abu Hassan ï¿½ Ts.` will never match):
   ```bash
   php artisan fyp:clean-supervisor-encoding
   ```

2. **Check the remaining unlinked count:**
   ```bash
   php artisan fyp:link-supervisors
   ```
   This is safe to re-run — it will now show 0 exact matches (the 5 that matched already ran),
   confirm all 24 names are still unmatched, and give the updated remaining count.

3. **Log in as coordinator → Manage Users → "Create Supervisor"** — provision accounts for
   each of the 24 unmatched names. Use the exact name format you want as the canonical name
   (this is what supervisors will see in their account).

4. **Re-run the backfill** after creating accounts:
   ```bash
   php artisan fyp:link-supervisors
   ```
   Names where you used the exact same string as the CSV will be auto-linked.

5. **Use the Unlinked Supervisors panel** in Manage Users for any residual names that still
   don't match (e.g., because the account was created with a different canonical spelling).

6. **Verify the count is 0:**
   ```bash
   php artisan tinker --execute="echo \App\Models\FypProject::whereNull('supervisor_id')->count();"
   ```

### When null-count = 0 — Phase 5 code work

Once all rows are linked, open a new Claude session and direct it to:

1. Remove the name-fallback `OR` branch from `my-students.blade.php`.
2. Remove the name-fallback `OR` branch from `supervisor-student-logbook.blade.php`.
3. Simplify `analytics-charts.blade.php` to group purely by `supervisor_id` (remove the
   effective-key helper; the "Unlinked" bucket should be empty or removable).
4. Run `php vendor/bin/pest` — all tests should still pass (the fallback tests in
   `SupervisorIdentityTest`, `SupervisorStudentLogbookTest`, and `SupervisorStudentsTest` will
   need updating to reflect that the name-fallback path no longer exists).
5. Commit and mark Sprint 4 complete.

### Things to NOT do in Phase 5

- Do not drop the `supervisor_name` column yet — it is still used for display throughout the UI
  and as provenance of the original imported string. Schedule column removal as a separate
  migration once all display code has been updated.
- Do not touch `supervisor_aliases` or a fuzzy resolver — those were explicitly deferred to
  the backlog and are not needed if the curation workflow succeeds.

---

*Generated 2026-06-19 as Sprint 4 Phase 4 handoff. For the authoritative current state, always
check `docs/PROJECT_STATUS.md` first — this document captures a point-in-time snapshot.*
