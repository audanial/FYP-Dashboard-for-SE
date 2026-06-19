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
| **Current phase** | **Phase 4 — Supervisor Account Provisioning, Mapping & Backfill** in progress. Tasks 1–7 done; Tasks 8–9 not started. |
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
| **Phase 4** — Supervisor Account Provisioning, Mapping & Backfill | ⏳ next — **planned** (`docs/superpowers/plans/2026-06-15-sprint-4-phase-4-provisioning-mapping-backfill.md`) | Phase 5 + Phase 6 |
| **Phase 5** — Verification & dual-read retirement criteria | ⬜ planned | Phase 7 |

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

---

## Test Status

- **Full Pest suite: 133 passing (316 assertions).** Latest run 2026-06-19; green including Phase 4
  Task 7 (`CsvImportSupervisorLinkTest` +3).
- Test database is in-memory SQLite (`phpunit.xml` → `DB_DATABASE=:memory:`), so the suite never
  touches the live database.
- Sprint 4 added 21 tests across `CsvImportEncodingTest`, `SupervisorRelationTest`,
  `SupervisorIdentityTest`, `SupervisorStudentLogbookTest`, `AnalyticsChartsTest`,
  `UserManagementTest`, and `CsvImportSupervisorLinkTest`.

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
- **Reads are migrated; writes are not.** CSV import and supervisor reassignment still write
  `supervisor_name` only — `supervisor_id` is not yet populated by any write path.
- **Measurability / retirement metric:** `FypProject::whereNull('supervisor_id')->count()` is the
  remaining fallback surface. When it reaches 0, the name fallback is provably dead and removable.

---

## Known Technical Debt

- **`supervisor_id` is unpopulated on existing data.** Practically all rows have
  `supervisor_id = null`, so production reads still flow through the name fallback until Phase 4
  backfill. The new ID path is proven by tests but not yet exercised by live data.
- **Supervisor accounts are missing.** Investigation found ~24 distinct supervisor people in the data
  but only **1** supervisor user account. Backfill is only meaningful once coordinator-created
  accounts exist.
- **~~CSV import write path~~** — **fixed (Task 7, 2026-06-19).** Import now sets `supervisor_id` on exact name match; omits the key (never sets null) on no-match so hand-curated links survive re-import.
- **Reassignment write path** still name-based until Task 6 (reassign fixed) and Task 8 (backfill).
- **~~Reassign partner-row bug~~** — **fixed (Task 6, 2026-06-19).** `saveReassign` now writes the
  whole pair (`supervisor_id` + canonical `supervisor_name`); unpaired rows treated as pair-of-one.
- **Coordinator role drift (deferred to backlog):** some creation paths store coordinators as
  `role = 'admin'` while middleware checks `hasRole('coordinator')`. Currently dormant (the live
  coordinator is `'coordinator'`), but latent.
- **Dual-read masks failures:** the ID branch and the name branch look identical to a user; rely on
  the null-`supervisor_id` count metric to know the true link state.
- **"Unlinked" is a new visible analytics label** (replaces "Unknown").

---

## Next Planned Work — Sprint 4 Phase 4: Supervisor Account Provisioning, Mapping & Backfill

> Planning only — **not implemented**. Full plan:
> `docs/superpowers/plans/2026-06-15-sprint-4-phase-4-provisioning-mapping-backfill.md`.

**Objective.** Close the provisioning gap and populate `supervisor_id` on existing `fyp_projects`
rows so the structural FK becomes the real source of truth and the dual-read name fallback can be
retired in Phase 5.

**Scope (planned, not built yet):**
- Coordinator **creates** the missing supervisor user accounts (today only 1 exists) — role forced
  `supervisor`, show-once temp password, dedup by email. No CSV auto-create.
- **Unlinked Supervisors** panel maps each distinct unlinked `supervisor_name` to an account via
  coordinator-curated **"Link to existing"** / **"Create & link"** — exact-match only, no fuzzy
  resolver, confirm-before-apply.
- One re-runnable `php artisan fyp:link-supervisors` backfill **auto-links only exact matches**
  (`supervisor_name === users.name`) and reports the rest.
- Switch write paths — CSV import and Reassign both set `supervisor_id`; Reassign is fixed to write
  the **whole pair**, not a single student row.
- Track progress with `FypProject::whereNull('supervisor_id')->count()` trending toward 0.

**Deferred from this phase (documented in the plan):** force-password-reset flag (needs a migration),
and the actual removal of the dual-read fallback (Phase 5, gated on the null-count metric reaching 0).
No live-database operation runs until Phase 4 is explicitly approved.

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

19 June 2026 — Phase 4 Tasks 6–7 complete: reassign writes whole pair + sets `supervisor_id`; CSV import sets `supervisor_id` on exact match with re-import guard (133 tests / 316 assertions).
