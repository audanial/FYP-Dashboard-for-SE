# Sprint 4 — Supervisor Identity: Final Implementation Roadmap

| | |
|---|---|
| **Project** | FYP Dashboard System — UniKL MIIT |
| **Branch** | `claude/laravel-fyp-dashboard-ot8rR` |
| **Prepared** | 2026-06-09 (Sprint 4, Day 1) |
| **Supersedes** | Original Option A proposal (post architecture review) |
| **Status** | **Approved** — Phase 0 deferred to backlog; Sprint 4 begins at Phase 1 (no code written yet) |

## Accepted decisions (locked)
- Keep `supervisor_id` as the identity anchor.
- **Coordinator-curated mapping**, not a fuzzy resolver. Auto-link only on **exact equality**; everything else is curated by hand.
- **Fix encoding/import before supervisor linking.**
- Supervisor assignment applies to the **whole pair**, never a single row.
- **Coordinator creates** supervisor accounts. **No automatic creation from CSV.**
- Dual-read, if used, is **temporary and measurable** (instrumented + documented retirement).

## Explicitly out of scope
Fuzzy/normalization resolver · `supervisor_aliases` table · `pairs`/`groups` refactor · dropping `supervisor_name` · mail-based onboarding. (Each is a documented future option.)

## Investigation baseline (real data)
142 rows · 77 pairs · 25 distinct supervisor strings = **24 people** (1 dup) · **1** supervisor account (messy CSV-form name) · 0 co-supervision · mojibake present (`Rohaya Abu Hassan � Ts.`).

## Dependency order
`1 encoding → 2 schema → 3+4 reads (RED→GREEN) → 5 curate/create → 6 backfill+writers → 7 verify`  *(Phase 0 deferred to backlog — see below)*

---

## Phase 1 — Stabilize `supervisor_name` encoding

> **Test baseline (verified 2026-06-09):** the CSV import suite is **green** — `CsvImportRealFormatTest` (9) + `CsvHeaderResolverRealFormatTest` (4) = **13 passing**; full suite **94 passing**. The earlier "paused / RED" status was stale: the importer and header mappings are already complete. Phase 1 therefore **builds on the green suite, leaves the 13 existing CSV tests unchanged** (`CLAUDE.md`: never weaken/remove tests), and only **adds** the encoding coverage that is currently missing. No reconciliation with half-finished RED work is needed.

1. **Goal:** imports store clean UTF-8 (no `�`), and the existing 142 rows are repaired, so identity keys are stable and display is clean. Identity must not be built on mojibake.
2. **Files affected:** `resources/views/livewire/fyp-projects.blade.php` (BOM strip, `fgetcsv` escape `''`, mb-safe trim); `app/Services/CsvHeaderResolver.php` (strip the UTF-8 BOM before lookup — `trim()` does **not** remove a BOM, so a BOM'd `Group` header silently fails to map today); new one-time `php artisan fyp:clean-supervisor-encoding` (the import fix alone will not repair already-stored rows). **No edits to existing CSV tests or to `real-sample.csv`.**
3. **Tests required (all NEW — purely additive, existing CSV tests untouched):**
   - **NEW fixture** `tests/Files/real-sample-utf8-bom.csv` (UTF-8 BOM + a multibyte name). Do **not** mutate `real-sample.csv` — 9 tests depend on it byte-for-byte.
   - **NEW** import-encoding test: BOM'd headers still resolve and names store without `�` (expected **RED** first — it reproduces the production mojibake).
   - **NEW** mojibake-repair test: `fyp:clean-supervisor-encoding` repairs a seeded `Rohaya Abu Hassan � Ts.` row to clean UTF-8.
   - **Regression:** all **13** existing CSV tests stay green; full suite stays green (94 → ~96+ as the new tests land).
4. **Risks:** parser change regresses the 13 green CSV tests (mitigate: re-run them after every change — they are the regression guard); stored mojibake needs explicit cleanup (import fix won't touch existing rows); re-import idempotency; **scope creep** into the full CSV-hardening sprint — keep to the encoding slice only.
5. **Commit checkpoint:** `Stabilize supervisor_name encoding on import + repair existing rows`.
6. **Estimated effort:** **M — ~0.5–1 day.** Lower risk than first scoped: the import path is already green and well-covered for *structure*; only *encoding* coverage is missing.

## Phase 2 — Additive `supervisor_id` FK + relations

1. **Goal:** structural foundation with **zero behavior change** (nothing reads it yet); suite stays green.
2. **Files affected:** new migration `..._add_supervisor_id_to_fyp_projects` (nullable `unsignedBigInteger`, FK → `users.id` `nullOnDelete`, indexed); `app/Models/FypProject.php` (`$fillable += supervisor_id`, `supervisor()` belongsTo); `app/Models/User.php` (`supervisedProjects()` hasMany); `database/factories/FypProjectFactory.php` (nullable `supervisor_id` + `forSupervisor(User)` state); `database/seeders/*` parity.
3. **Tests required:** `FypProject::supervisor()` resolves a linked user; migration up/down clean; full suite green.
4. **Risks:** SQLite `PRAGMA foreign_keys` must be on; factory/seeder churn; `down()` must drop FK cleanly.
5. **Commit checkpoint:** `Add nullable supervisor_id FK and relations`.
6. **Estimated effort:** **S — ~0.5 day.**

## Phase 3 + 4 — Switch reads to `supervisor_id` with measured dual-read *(RED → GREEN)*

1. **Goal:** lock the defect with a RED test, then make reads prefer `supervisor_id` and fall back to name **only when id is null**, with a fallback **counter** so the transition is measurable.
2. **Files affected:** new `tests/Feature/SupervisorIdentityTest.php`; `resources/views/livewire/my-students.blade.php:21`; `resources/views/pages/supervisor-student-logbook.blade.php:23`; `resources/views/livewire/analytics-charts.blade.php:45,61` (group by id + **Unlinked bucket**); a lightweight fallback metric (`Log`/counter on the name branch).
3. **Tests required:** RED→GREEN — canonical `Tiliza binti Awang Mat` + `supervisor_name="Tiliza Awang Mat - Ts."` linked by `supervisor_id` sees the student; non-assigned still 403/hidden; analytics groups by id + shows Unlinked; existing name-only `SupervisorStudentsTest` (4) stay green; fallback counter increments when id is null.
4. **Risks:** `OR (supervisor_id IS NULL AND name = …)` precedence/binding footgun; dual-read **masking** failures (mitigated by the counter); existing name-match tests must not be weakened — the fallback is what keeps them green.
5. **Commit checkpoint:** `Read supervisors by supervisor_id with temporary measured name fallback`.
6. **Estimated effort:** **M — ~1 day.**

## Phase 5 — Coordinator curation: create supervisors + link unmatched

1. **Goal:** fill the provisioning gap deterministically — coordinator creates supervisor accounts and maps each unmatched `supervisor_name` to a user. No fuzzy matching, no CSV auto-create.
2. **Files affected:** `resources/views/livewire/user-management.blade.php` — **Create Supervisor** modal (role forced `supervisor`, temp password shown once + force-reset flag, `is_active=true`, dedup by unique email); **Unlinked Supervisors** panel (distinct `supervisor_name` with null `supervisor_id`) with "Create & link" / "Link to existing", each writing `supervisor_id` to the **whole pair** behind a confirm step; routes/nav if needed.
3. **Tests required:** create supervisor → role/temp-password/`is_active`/dedup correct; linking a raw name writes `supervisor_id` on **both** pair rows; coordinator-only authorization (403 for others); confirm-before-apply.
4. **Risks:** duplicate accounts vs self-registration (dedup on email); temp-password delivery with no mail infra (show-once, never log); **mis-assignment via UI is a wrong-access vector** → confirm + per-pair; coordinator gate relies on the live account already holding `coordinator` (confirmed) — the `admin` drift is in the backlog and does not affect this sprint.
5. **Commit checkpoint:** `Add coordinator supervisor creation and unmatched-name linking`.
6. **Estimated effort:** **L — ~1.5–2 days** (largest phase).

## Phase 6 — Apply mapping: exact-match backfill + per-pair writers

1. **Goal:** one re-runnable backfill auto-links **only exact matches** and reports the rest; CSV import and Reassign both set `supervisor_id` for the **whole pair**; fix the Reassign partner-row bug.
2. **Files affected:** new `php artisan fyp:link-supervisors` (idempotent; exact `supervisor_name === users.name` → set id per pair; report unmatched + counts); `resources/views/livewire/fyp-projects.blade.php` import (set `supervisor_id` on exact match, both pair rows); `resources/views/livewire/user-management.blade.php:121-124` `saveReassign` (set `supervisor_id` + canonical name for the **whole pair**).
3. **Tests required:** backfill links exact match per pair + reports unmatched + idempotent re-run; import sets `supervisor_id` on both pair rows; Reassign updates the whole pair's id **and** name; non-exact names stay Unlinked.
4. **Risks:** pair grouping correctness (`semester`+`pair_number`); idempotency on re-run; `updateOrCreate` ordering on import; **existing `UserManagementTest` expectations change** (Reassign now per-pair + id) — update, don't weaken.
5. **Commit checkpoint:** `Backfill exact matches and set supervisor_id per pair on import/reassign`.
6. **Estimated effort:** **M — ~1 day.**

## Phase 7 — Verify and define fallback retirement

1. **Goal:** prove the sprint; confirm the dual-read fallback is trending to zero; document when it can be removed (not removed this sprint).
2. **Files affected:** `docs/reports/` closeout; optionally gate the fallback behind a config flag for a clean future cutover. No core logic changes.
3. **Tests required:** full Pest suite green; manual Tiliza login sees students; analytics shows **distinct people** + Unlinked bucket; fallback counter ≈ 0 after backfill.
4. **Risks:** dual-read becoming permanent → mitigate with documented retirement criteria ("fallback-hit count = 0 across a full semester import → remove").
5. **Commit checkpoint:** `Verify Sprint 4 supervisor identity; document dual-read retirement`.
6. **Estimated effort:** **S — ~0.5 day.**

---

## Effort summary
| Phase | Size | Estimate |
|---|---|---|
| 1 Encoding | M | 0.5–1 day |
| 2 Schema + relations | S | 0.5 day |
| 3+4 Reads + dual-read | M | 1 day |
| 5 Curation UI | L | 1.5–2 days |
| 6 Backfill + writers | M | 1 day |
| 7 Verify | S | 0.5 day |
| **Total** | | **≈ 5–6 days** |

## Definition of done
Supervisor with a messy CSV name sees their students (RED→GREEN) · analytics shows distinct people + Unlinked bucket · CSV import & Reassign set `supervisor_id` per pair · coordinator can create/link supervisors · backfill linked exact matches and reported the rest · full Pest suite green, no existing test weakened · fallback counter ≈ 0 with documented retirement criteria.

## Backlog (deferred from Sprint 4)
- **Standardize coordinator role (drop `admin` alias).** Decided **out of Sprint 4** on 2026-06-09: the live coordinator account already uses the `coordinator` role, no `admin` users exist, and the issue — though real — is **dormant** and does not block Phases 1–7. Tracked as a standalone backlog item.
  - *Fix when picked up:* `app/Actions/Fortify/CreateNewUser.php:41` and `database/seeders/DatabaseSeeder.php:22` (`'admin'` → `'coordinator'`); add a test that `SE-PC-2026` registration and the seeder both yield role `coordinator`. Effort: XS (~0.5–1h).

## Pre-flight status — ready to start
1. ~~Phase 0 in or out~~ — **Resolved 2026-06-09: OUT.** Moved to Backlog (above). Sprint 4 begins at **Phase 1**.
2. ~~State of the paused CSV-hardening tests~~ — **Resolved 2026-06-09:** verified **green** (13 CSV tests passing; full suite 94). Phase 1 builds on them and adds encoding coverage; no reconciliation needed.

**Sprint 4 roadmap approved. Entry point: Phase 1 — Stabilize `supervisor_name` encoding.**
