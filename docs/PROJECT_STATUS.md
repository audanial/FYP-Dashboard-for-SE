# FYP Dashboard — Project Status

> Single source of truth for the current state of the project. Planning detail lives in
> `docs/superpowers/plans/` and `docs/reports/`; this file is the at-a-glance summary.

---

## Project Overview

**Purpose.** A Final Year Project (FYP) tracking dashboard for Software Engineering students at
UniKL MIIT. It lets coordinators manage the cohort and import the master project CSV, lets
supervisors see their assigned students and read those students' logbooks, and lets students keep
their own logbook.

**User roles.**
- **coordinator** — manages users, imports CSV, views all projects and analytics.
- **supervisor** — views their assigned students and reads those students' logbooks.
- **student** — manages their own logbook entries.

**Technology stack.**
- Laravel 11, Livewire **Volt** (functional API for components; a few pages use class-based Volt),
  Flux UI, Tailwind CSS.
- SQLite database.
- Pest (feature/unit tests); one Playwright E2E spec (`tests/import.spec.js`).
- PHP 8.4 (pinned via `.php-version` for Railway deployment).

---

## Current Status

| | |
|---|---|
| **Current sprint** | Sprint 4 — Supervisor Identity |
| **Current phase** | **Phase 4 — Supervisor Account Provisioning, Mapping & Backfill** ✅ complete (`c20cdef`→`adb28ab`). Phase 5 (dual-read retirement) is next. |
| **Current branch** | `claude/laravel-fyp-dashboard-ot8rR` |
| **Working tree** | Clean except an incidental `.claude/settings.local.json` (harness permissions; not application code). |

### Phase numbering (reconciled 2026-06-15)

The original roadmap (`docs/superpowers/plans/2026-06-09-sprint-4-supervisor-identity-roadmap.md`)
split the reads work across "Phase 3+4" and numbered the remaining work 5/6/7. As built, the
reads work shipped as a single **Phase 3** (sub-phases 3.1–3.3, per the commit labels), so every
later phase shifts down by one. This document and the new plan use the **reconciled (as-built)**
numbering below; the roadmap's original numbers are shown for cross-reference.

| Reconciled phase | Status | Roadmap original |
|---|---|---|
| **Phase 1** — Encoding stabilization | ✅ done (`8bea149`) | Phase 1 |
| **Phase 2** — `supervisor_id` foundation (FK + relations) | ✅ done (`3e54f50`) | Phase 2 |
| **Phase 3** — Identity reads / dual-read (3.1 My Students, 3.2 Logbook, 3.3 Analytics) | ✅ done (`38a9c11`, `448cefc`, `7eee670`) | Phase 3 + Phase 4 |
| **Phase 4** — Supervisor Account Provisioning, Mapping & Backfill | ✅ done (`c20cdef`→`adb28ab`) | Phase 5 + Phase 6 |
| **Phase 5** — Dual-read retirement (remove name fallback once null-count = 0) | ⬜ planned | Phase 7 |

**Why this sprint exists.** Supervisor→project ownership was historically resolved by exact
string match between `users.name` and `fyp_projects.supervisor_name`. Real names diverge (e.g.
account `Tiliza binti Awang Mat` vs CSV `Tiliza Awang Mat - Ts.`), which left supervisors with empty
"My Students" lists. Sprint 4 introduces a real `supervisor_id` foreign key and migrates reads onto
it while keeping the legacy name path working during the transition.

---

## Completed Work (Sprint 4)

All phases below are implemented, test-covered (TDD: RED → GREEN), and committed.

### Phase 1 — Encoding Stabilization  · commit `8bea149`
- **Objective:** stop encoding corruption from poisoning supervisor names on import, and repair
  names already stored with corruption — so identity keys are stable and display is clean.
- **Changes:** `app/Support/Encoding.php` (strip UTF-8 BOM; coerce Windows-1252 → UTF-8);
  normalization pass in `resources/views/livewire/fyp-projects.blade.php`;
  `app/Console/Commands/CleanSupervisorEncoding.php` (`fyp:clean-supervisor-encoding` repair command).
- **Tests:** `tests/Feature/CsvImportEncodingTest.php` (+3). Existing CSV tests unchanged.

### Phase 2 — `supervisor_id` Foundation  · commit `3e54f50`
- **Objective:** add the structural FK with **zero behavior change**.
- **Changes:** migration `2026_06_09_120000_add_supervisor_id_to_fyp_projects_table.php` (nullable
  `supervisor_id` FK → `users.id`, `nullOnDelete`); `FypProject::supervisor()` (belongsTo) and
  `User::supervisedProjects()` (hasMany).
- **Tests:** `tests/Feature/SupervisorRelationTest.php` (+3).

### Phase 3.1 — My Students Dual-Read  · commit `38a9c11`
- **Objective:** make the "My Students" list prefer `supervisor_id`, with a measurable name fallback.
- **Changes:** `resources/views/livewire/my-students.blade.php` — ownership query switched to the
  dual-read predicate (ID precedence; name fallback only when `supervisor_id IS NULL`).
- **Tests:** `tests/Feature/SupervisorIdentityTest.php` (+3): id-linked-with-mismatched-name,
  wrong-access protection, null-id name fallback.

### Phase 3.2 — Logbook Authorization  · commit `448cefc`
- **Objective:** apply the same dual-read to the supervisor logbook access gate, without weakening it.
- **Changes:** `resources/views/pages/supervisor-student-logbook.blade.php` — `mount()` authorization
  query switched to the dual-read predicate; `firstOrFail()` (fail-closed → 404) preserved.
- **Security:** closed a real privilege-escalation hole — a supervisor whose name coincided with a
  project's `supervisor_name` could previously open another supervisor's student logbook; ID
  precedence now blocks this.
- **Tests:** `tests/Feature/SupervisorStudentLogbookTest.php` (+3): id-based access,
  wrong-access 404, legacy null-id fallback.

### Phase 3.3 — Analytics Effective-Key Grouping  · commit `7eee670`
- **Objective:** group supervisor analytics by an effective key during the transition.
- **Changes:** `resources/views/livewire/analytics-charts.blade.php` — added an
  `$effectiveSupervisorKey` helper and applied it to both the workload chart grouping and the
  supervisor count (drives "Avg Pairs / Supervisor"); eager-loads `with('supervisor')` to avoid N+1;
  fallback label changed from **"Unknown"** to **"Unlinked"**.
- **Effective key:** (A) `supervisor_id` set + relation resolves → linked user's name; (B) else
  `supervisor_name`; (C) else **"Unlinked"**.
- **Tests:** `tests/Feature/AnalyticsChartsTest.php` (+4): linked-collapse, name fallback, Unlinked
  bucket, count accuracy.

### Phase 4 — Supervisor Account Provisioning, Mapping & Backfill  · commits `c20cdef`→`adb28ab`
- **Objective:** give coordinators the tools to create supervisor accounts and map every unlinked
  `supervisor_name` to a real account; fix all write paths to set `supervisor_id`; provide a
  re-runnable backfill command for existing rows.
- **Tasks completed:**
  - **Task 1** (`c20cdef`) — `FypProjectFactory::forSupervisor()` state for terse linking tests.
  - **Tasks 2–3** (`d1094fc`, `29762cd`) — coordinator "Create Supervisor" modal: role forced `supervisor`, `is_active=true`, unique email, show-once temp password stored hashed.
  - **Tasks 4–5** (`6d9fc59`, `58753a1`) — Unlinked Supervisors panel: lists every distinct unlinked `supervisor_name` with student/pair counts; "Link to existing" maps a raw name to an account (whole-pair write, confirm-before-apply, coordinator-only).
  - **Task 5.5** (`be91da0`) — coordinator-provisioned accounts marked `email_verified_at` immediately.
  - **Task 6** (`d6a9c72`) — `saveReassign` rewritten to update the whole pair (`semester` + `pair_number`) and set both `supervisor_id` and canonical `supervisor_name`; unpaired rows treated as pair-of-one.
  - **Task 7** (`f78e086`) — CSV import sets `supervisor_id` on exact name match; key omitted (not null) on no-match so hand-curated links survive re-import.
  - **Task 8** (`adb28ab`) — `php artisan fyp:link-supervisors`: single-query exact-match backfill, role-filtered, idempotent (`whereNull` guard), prints linked / unmatched names / remaining count.
- **Tests added:** `SupervisorProvisioningTest` (+8), `SupervisorLinkingTest` (+10), `UserManagementTest` (+2), `CsvImportSupervisorLinkTest` (+3), `LinkSupervisorsCommandTest` (+4) = **+27 tests**.
- **Backfill baseline (live DB, 2026-06-19):** `fyp:link-supervisors` linked **5 rows** on first run; **137 rows remain unlinked** across **24 unmatched supervisor names** (all in messy CSV format). Coordinator must provision accounts for those 24 names, then re-run the command.

---

## Test Status

- **Full Pest suite: 137 passing (328 assertions).** Verified 2026-06-19 (Phase 4 Task 9 sign-off); all green, no existing test removed or weakened.
- Test database is in-memory SQLite (`phpunit.xml` → `DB_DATABASE=:memory:`), so the suite never touches the live database.
- Sprint 4 total: **+37 tests** spanning `CsvImportEncodingTest`, `SupervisorRelationTest`, `SupervisorIdentityTest`, `SupervisorStudentLogbookTest`, `AnalyticsChartsTest`, `UserManagementTest`, `CsvImportSupervisorLinkTest`, `LinkSupervisorsCommandTest`, and `SupervisorLinkingTest` / `SupervisorProvisioningTest`.

---

## Architecture Status — Supervisor Identity Model

- **Schema:** `fyp_projects.supervisor_id` — nullable FK → `users.id` (`nullOnDelete`). The legacy
  `supervisor_name` string is retained for display and as the transition fallback.
- **Relations:** `FypProject::supervisor()` (belongsTo), `User::supervisedProjects()` (hasMany).
- **Dual-read predicate** (used by My Students and the logbook gate):
  ```
  supervisor_id = Auth::id()
     OR ( supervisor_id IS NULL AND supervisor_name = Auth::user()->name )
  ```
  ID precedence is mandatory — a row owned by another supervisor must never surface through a
  coincidental name match; the name branch applies only to unlinked (`supervisor_id IS NULL`) rows.
- **Analytics** group by the effective key (linked user name → `supervisor_name` → "Unlinked").
- **Reads, writes, and backfill are all migrated.** CSV import, reassignment, and the curation panel all set `supervisor_id`. The backfill command links existing rows on exact match.
- **Measurability / retirement metric:** `FypProject::whereNull('supervisor_id')->count()` is the remaining fallback surface. **Baseline (2026-06-19): 137 rows.** Phase 5 retirement criterion: when this count reaches **0** after coordinator provisioning + backfill, the dual-read name fallback is provably dead and can be removed.

---

## Known Technical Debt

- **137 rows remain unlinked (live DB baseline, 2026-06-19).** The backfill command ran and linked 5 rows on first run; 24 unmatched names (all messy CSV-format strings) remain. Coordinator must provision those accounts via the "Create Supervisor" modal, then re-run `php artisan fyp:link-supervisors`, then use the Unlinked Supervisors panel for any residual non-exact names.
- **~~CSV import write path~~** — **fixed (Task 7, 2026-06-19).** Import now sets `supervisor_id` on exact name match; omits the key (never sets null) on no-match so hand-curated links survive re-import.
- **~~Backfill gap~~** — **addressed (Task 8, 2026-06-19).** `php artisan fyp:link-supervisors` links all existing rows by exact name match, ignores non-supervisor accounts, is idempotent, and reports linked / unmatched / remaining counts. Run this against the live DB after coordinator provisions the missing accounts.
- **~~Reassign partner-row bug~~** — **fixed (Task 6, 2026-06-19).** `saveReassign` now writes the
  whole pair (`supervisor_id` + canonical `supervisor_name`); unpaired rows treated as pair-of-one.
- **Coordinator role drift (deferred to backlog):** some creation paths store coordinators as
  `role = 'admin'` while middleware checks `hasRole('coordinator')`. Currently dormant (the live
  coordinator is `'coordinator'`), but latent.
- **Dual-read masks failures:** the ID branch and the name branch look identical to a user; rely on
  the null-`supervisor_id` count metric to know the true link state.
- **"Unlinked" is a new visible analytics label** (replaces "Unknown").

---

## Next Planned Work — Sprint 4 Phase 5: Dual-Read Retirement

**Trigger condition:** `FypProject::whereNull('supervisor_id')->count()` = 0 (currently 137).

**Prerequisite steps (coordinator, outside the codebase):**
1. Use the **Create Supervisor** modal to provision accounts for the 24 unmatched names.
2. Run `php artisan fyp:link-supervisors` — links all exact-name matches automatically.
3. Use the **Unlinked Supervisors** panel to curate any residual non-exact names.
4. Repeat step 2 until the remaining count is 0.

**Phase 5 scope (once count = 0):**
- Remove the `OR (supervisor_id IS NULL AND supervisor_name = …)` name-fallback branch from `my-students.blade.php` and `supervisor-student-logbook.blade.php`.
- Simplify analytics to group by `supervisor_id` only (drop the effective-key helper).
- Add a migration to drop the fallback from any index or constraint if applicable.
- Final test pass; mark Sprint 4 complete.

**Deferred to backlog:** force-password-reset flag (needs a `users` migration); coordinator role drift (`admin` alias).

---

## Future Roadmap (post-Sprint 4)

Planned, not started — summarized for direction:

- **Sprint 5 — Role-Based Experience.** Tailor each role's landing experience:
  - **Coordinator Dashboard** — cohort overview, import/curation entry points, analytics.
  - **Supervisor Dashboard** — assigned students, logbook activity at a glance.
  - **Student Dashboard** — own project and logbook progress.
- **Notifications** — surface relevant events to each role.
- **UI/UX Refinement** — consistency and usability pass across views.
- **Rule-Based Classification** — deterministic categorization of projects (domain / type) from
  defined rules.
- **AI-Assisted Classification** — model-assisted suggestions for project classification.
- **Coordinator Review Workflow** — coordinator review/approval step over classifications and
  curated mappings.

---

## Last Updated

19 June 2026 — Sprint 4 Phase 4 complete (Task 9 sign-off). 137 tests / 328 assertions, all green. Live-DB baseline: 137 rows unlinked, 24 unmatched names. Phase 5 retirement gated on null-count reaching 0.
