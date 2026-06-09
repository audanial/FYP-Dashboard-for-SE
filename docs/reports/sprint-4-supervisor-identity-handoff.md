# Sprint 4 Handoff — Supervisor Identity & Data Ownership

| | |
|---|---|
| **Project** | FYP Dashboard System — UniKL MIIT (BSE Final Year Projects) |
| **Branch** | `claude/laravel-fyp-dashboard-ot8rR` |
| **Prepared** | 2026-06-08 (end of Sprint 3) |
| **For** | Sprint 4 — Day 1 (2026-06-09) |
| **Sprint goal** | Replace fragile supervisor **name-string matching** with a real **`supervisor_id`** relationship so supervisors reliably see their assigned students. |
| **Type** | Investigation + implementation sprint |
| **Status of this doc** | Handoff / planning only — no code changed. |

> **TL;DR** — A supervisor logs in and their **"My Students" page is empty**. Cause: the app joins supervisors to projects by matching `users.name` **byte-for-byte** against `fyp_projects.supervisor_name`, but the CSV stores names in a different format (`"Tiliza Awang Mat - Ts."` vs account `"Tiliza binti Awang Mat"`). This was flagged **High Priority (R2/R3)** in the Analytics Audit. Sprint 4 introduces a `supervisor_id` foreign key, backfills it via a name-normalisation resolver, and switches all reads/writes to the FK. **Start tomorrow by writing the failing test that reproduces the empty "My Students" page using the two real names.**

---

## 1. Executive Summary

- Supervisor-to-project ownership currently depends on an **exact string match** between `users.name` and `fyp_projects.supervisor_name`.
- Real data breaks this match (academic prefixes, prefix-as-suffix, `binti`/`bin`, casing, mojibake), so **real supervisors see zero students** and their **student logbooks are inaccessible**.
- This is a **data-integrity / referential-integrity** defect, not a display bug. Patching strings is not acceptable long-term (Finding #6).
- **Fix:** add a nullable `supervisor_id` FK on `fyp_projects` → `users.id`, backfill it once via a normalisation resolver, and point every read (My Students, Supervisor Logbook, Analytics) and every write (CSV import, Reassign Supervisor) at the FK.
- Sprint 3 (analytics correctness) is **complete and green (94 tests)**; this sprint removes the last 🔴 High risk from the audit.

---

## 2. Current System Behaviour

- [ ] **Login as supervisor →** "My Students" runs `FypProject::where('supervisor_name', Auth::user()->name)` → **0 rows** when names don't match exactly.
- [ ] **Open a student logbook →** `firstOrFail()` on the same name match → **403 / not found** for legitimately-assigned students.
- [ ] **Analytics "Supervisor Workload" / "Avg Pairs per Supervisor" →** groups by the raw `supervisor_name` **string**, so encoding variants of one person count as **separate supervisors**.
- [ ] **Coordinator "Reassign Supervisor" →** writes `fyp_projects.supervisor_name = $supervisor->name`. Reassigned rows *do* match; **CSV-imported rows do not** → inconsistent state across the same table.
- [ ] **CSV import →** stores the **raw CSV supervisor string** verbatim (with lead-row fill-down), never reconciled against the `users` table.

**Net effect:** ownership is correct *only* for projects last touched by the reassignment UI; everything imported from CSV is silently orphaned from its supervisor.

---

## 3. Evidence Collected

| Source | Value |
|---|---|
| Supervisor **account** (`users.name`) | `Tiliza binti Awang Mat` |
| Supervisor in **CSV** (`fyp_projects.supervisor_name`) | `Tiliza Awang Mat - Ts.` |
| Match used by code | `users.name === fyp_projects.supervisor_name` (exact) |
| Result | `false` → "My Students" empty for this real supervisor |

Differences in this single pair: **prefix moved to suffix** (`- Ts.`), **missing `binti`**, **word order/spacing**. Seed/factory data adds further variants (`Ts. Tiliza Binti Awang Mat` — note `Binti` vs `binti` casing). Cross-referenced in the **Analytics Audit (§2.2, risks R2/R3)** and the **CSV Data Quality Report**.

---

## 4. Root Cause Analysis

- [ ] **No referential link.** `fyp_projects` has no `supervisor_id`; the only supervisor reference is a free-text `supervisor_name` string.
- [ ] **Two uncoordinated writers** populate that string with different conventions:
  - CSV import → raw external format (`fyp-projects.blade.php:190–221`).
  - Reassign Supervisor → internal `users.name` (`user-management.blade.php:124`).
- [ ] **Exact-string reads** assume both writers agree — they don't.
- [ ] **No normalisation** of prefixes (`Ts. Dr. Prof. Pn. En.`), honorific position, `binti/bin`, casing, or mojibake at any layer.
- [ ] **Precedent ignored:** students are already linked structurally via `users.username == fyp_projects.student_id` (see `supervisor-student-logbook.blade.php:27`), but supervisors have **no equivalent structural link**.

**Root cause:** identity is modelled as a display string, not as a relationship.

---

## 5. Why This Is A Problem

- 🔴 **Whole role broken:** a supervisor cannot see or supervise their students — the core supervisor workflow fails on real data.
- 🔴 **Untrustworthy analytics:** supervisor counts/workload are inflated/incorrect (distinct strings ≠ distinct people).
- 🟠 **No referential integrity:** renames, re-imports, and typos silently orphan projects; nothing enforces a valid supervisor.
- 🟠 **Inconsistent state:** the same table mixes matched (reassigned) and unmatched (imported) rows.
- ⛔ **String patches don't scale:** every new CSV format or honorific reintroduces the bug (Finding #6).

---

## 6. Affected Features

- [ ] Supervisor **My Students** page (empty list bug)
- [ ] Supervisor **Student Logbook** access (`firstOrFail` denies legitimate access)
- [ ] Analytics **Supervisor Workload** chart + **Avg Pairs / Supervisor** card (miscount by string)
- [ ] Coordinator **Reassign Supervisor** (writes name string)
- [ ] **CSV Import** (writes raw name string)
- [ ] Coordinator **search by supervisor name** (`fyp-projects.blade.php:57`)

---

## 7. Affected Files (known and suspected)

**Known — read/match by name (must switch to `supervisor_id`):**
- [ ] `resources/views/livewire/my-students.blade.php:21`
- [ ] `resources/views/pages/supervisor-student-logbook.blade.php:23`
- [ ] `resources/views/livewire/analytics-charts.blade.php:45, 61` (supervisorCount, supervisorWorkload)

**Known — write/ingest `supervisor_name` (must also set `supervisor_id`):**
- [ ] `resources/views/livewire/fyp-projects.blade.php:190–221` (CSV import)
- [ ] `resources/views/livewire/user-management.blade.php:111–124` (Reassign Supervisor)

**Known — schema / config / model:**
- [ ] `database/migrations/2026_03_24_014932_create_fyp_projects_table.php` (base table)
- [ ] **NEW** `database/migrations/2026_06_09_xxxxxx_add_supervisor_id_to_fyp_projects.php` (to be created)
- [ ] `app/Models/FypProject.php:23–36` (fillable + add `supervisor()` relation)
- [ ] `app/Models/User.php` (add `supervisedProjects()` relation)
- [ ] `config/csv_mappings.php:36–37` (supervisor header mapping — unchanged, but feeds the resolver)

**Suspected / to confirm:**
- [ ] `resources/views/livewire/fyp-projects.blade.php:427, 519, 561` (supervisor display — may switch to relation)
- [ ] `database/seeders/FypProjectSeeder.php` + `database/factories/FypProjectFactory.php` (add `supervisor_id` for realistic test data)
- [ ] **NEW** `app/Services/SupervisorNameResolver.php` (normalisation/matching service — to be created)
- [ ] Tests: `tests/Feature/SupervisorStudentsTest.php`, `tests/Feature/SupervisorStudentLogbookTest.php`, `tests/Feature/AnalyticsChartsTest.php`, `tests/Feature/UserManagementTest.php`

---

## 8. Investigation Tasks (do first, time-boxed)

- [ ] List **all distinct `fyp_projects.supervisor_name`** values for MARCH 2026 (read-only `tinker`).
- [ ] List **all `users` where `role = 'supervisor'`** (name + username).
- [ ] Build a **name→user match table**: how many auto-match after normalisation, how many are **ambiguous**, how many are **unmatched**.
- [ ] Confirm whether any **two supervisors normalise to the same name** (collision risk).
- [ ] Confirm the **`username` convention** for supervisors (is it set? could it be the join key instead of name?).
- [ ] Check for **co-supervised / multi-supervisor** projects in the source data (does the single-FK model hold?).
- [ ] Grep for any **other consumers** of `supervisor_name` not listed in §7.

---

## 9. Questions To Answer Tomorrow

- [ ] **Hard FK vs username match?** Recommend `supervisor_id → users.id` (FK). Confirm.
- [ ] **Source of truth for names?** Presumed the `users` table. Confirm.
- [ ] **Unmatched backfill rows** → leave `supervisor_id` null + flag for manual reassignment? (Recommended.)
- [ ] **Ambiguous matches** → never auto-link; require coordinator confirmation? (Recommended.)
- [ ] **Keep `supervisor_name`?** Keep as denormalised display + fallback this sprint; schedule removal later.
- [ ] **CSV import for unknown supervisors** → auto-create a supervisor `User`, or link-existing-only and report the rest?
- [ ] **Can a project have >1 supervisor (co-supervisor)?** Confirms whether single FK is sufficient.
- [ ] **Normalisation aggressiveness** → is case-insensitive + prefix/`binti` + mojibake folding acceptable without manual sign-off?

---

## 10. Proposed `supervisor_id` Strategy

- [ ] **Schema:** add nullable `supervisor_id` (unsignedBigInteger) to `fyp_projects`, FK → `users.id`, `nullOnDelete`, indexed.
- [ ] **Relations:** `FypProject::supervisor()` → `belongsTo(User)`; `User::supervisedProjects()` → `hasMany(FypProject)`.
- [ ] **Resolver:** `SupervisorNameResolver` normalises a raw name (strip/relocate `Ts./Dr./Prof./Pn./En.`, fold `binti/bin`, lowercase, trim, repair mojibake) and returns the matching supervisor `User` or `null`.
- [ ] **Backfill (one-off):** for each project, resolve `supervisor_name` → set `supervisor_id`; **log every unmatched/ambiguous row** for triage. Never guess on collisions.
- [ ] **Switch reads:** `my-students`, `supervisor-student-logbook`, and analytics use `supervisor_id` (analytics groups by supervisor **id**, displays the user's canonical name).
- [ ] **Switch writes:** CSV import resolves and sets `supervisor_id` at ingest (keeps `supervisor_name` as fallback display); Reassign Supervisor sets `supervisor_id` (it already holds the `User`).
- [ ] **Keep `supervisor_name`** populated for display/fallback this sprint; mark for later deprecation once the FK is trusted.

---

## 11. Migration Considerations

- [ ] **Additive first** — adding `supervisor_id` is non-destructive; do **not** drop `supervisor_name` this sprint.
- [ ] **Reversible** `down()` drops the FK + column cleanly.
- [ ] **Order:** add column → backfill → switch reads → switch writes → verify → (future) deprecate name.
- [ ] **SQLite caveats:** ensure `PRAGMA foreign_keys` is on; FK + index creation on a `Schema::table` add is fine. (Future column *drop* on SQLite triggers a table rebuild — defer.)
- [ ] **Backfill as code, not data-loss:** prefer a standalone idempotent artisan command (re-runnable after fixes) over a one-shot in-migration loop, so unmatched rows can be re-resolved after manual cleanup.
- [ ] **Re-import safety:** importing the same CSV again must re-resolve `supervisor_id`, not duplicate or orphan.
- [ ] **Factory/seeder parity:** seed both `supervisor_name` and a valid `supervisor_id` so tests reflect production shape.

---

## 12. Testing Strategy (TDD — RED → GREEN; never weaken/remove existing tests)

- [ ] **Resolver unit tests** (`SupervisorNameResolver`): `"Tiliza Awang Mat - Ts."` ↔ `"Tiliza binti Awang Mat"` match; prefix-strip; `binti/bin`; casing; mojibake; **ambiguous → null**; **no match → null**.
- [ ] **Feature (the bug):** supervisor `Tiliza binti Awang Mat` + project `supervisor_name = "Tiliza Awang Mat - Ts."` → **RED** today (empty), **GREEN** after FK → sees the student.
- [ ] **Feature:** supervisor logbook opens for an assigned student via `supervisor_id`; still 403s for non-assigned.
- [ ] **Feature:** analytics workload groups by `supervisor_id` — encoding variants of one person collapse to **one** bar/count.
- [ ] **Feature:** Reassign Supervisor sets `supervisor_id`; CSV import sets `supervisor_id`; unmatched → null + reported.
- [ ] **Regression:** full Pest suite stays green (currently **94**); analytics tests from Sprint 3 unaffected.

---

## 13. Risks

| # | Risk | Severity | Mitigation |
|---|------|----------|-----------|
| R1 | Backfill can't auto-match some names → projects stay unlinked | 🟠 | Report unmatched count; manual reassignment UI; re-runnable backfill command |
| R2 | Two people normalise to the same name → wrong link | 🔴 | Detect ambiguity; **never auto-link**; leave null for coordinator review |
| R3 | Co-supervised projects don't fit a single FK | 🟠 | Confirm in §8; if real, scope a join table separately (not this sprint) |
| R4 | Dropping/relying-away from `supervisor_name` breaks display/search | 🟠 | Keep `supervisor_name` this sprint; defer removal |
| R5 | SQLite FK/migration quirks | 🟡 | Additive migration only; verify `foreign_keys` pragma in tests |
| R6 | Import + reassign must keep `supervisor_id` **and** name consistent | 🟠 | Single resolver used by both writers; test both paths |

---

## 14. Definition of Done

- [ ] `supervisor_id` FK migration merged, indexed, and reversible.
- [ ] `SupervisorNameResolver` implemented and **unit-tested**.
- [ ] Backfill executed; **unmatched/ambiguous rows reported and triaged** (target: `Tiliza binti Awang Mat` linked).
- [ ] `my-students`, `supervisor-student-logbook`, and analytics read via `supervisor_id`.
- [ ] CSV import **and** Reassign Supervisor write `supervisor_id`.
- [ ] Feature test proves a supervisor with a **messy CSV name** sees their students (RED→GREEN).
- [ ] Full Pest suite **green**; no existing test removed or weakened (per `CLAUDE.md`).
- [ ] Manual check: log in as **Tiliza** → "My Students" shows real students; logbook opens.
- [ ] Analytics workload shows **distinct people**, not distinct strings.
- [ ] Audit risks **R2 & R3 marked resolved**; `supervisor_name` flagged for future deprecation.

---

## 15. Recommended First Task Tomorrow Morning

> **Reproduce the bug with a failing test before touching schema.**

1. [ ] Add a feature test: create a supervisor `User` named **`Tiliza binti Awang Mat`** and a `FypProject` with `supervisor_name = "Tiliza Awang Mat - Ts."`; assert "My Students" currently shows **zero** students. This **locks the defect** and becomes the RED anchor.
2. [ ] Run the **§8 investigation queries** (distinct supervisor names vs supervisor users) to size the matching problem with real numbers.
3. [ ] Spike `SupervisorNameResolver` against that real list; confirm it matches the Tiliza pair and surfaces any ambiguities.

Everything else (migration → backfill → switch reads/writes) follows once the resolver and the failing test exist.

---

*Prepared as a Sprint 4 handoff for the FYP Dashboard System (UniKL MIIT). Documentation only — no application code was modified. Cross-references: `docs/reports/analytics-audit-report.md` (§2.2, R2/R3), `docs/reports/csv-data-quality-report-march-2026.md`, `docs/superpowers/plans/2026-06-08-fix-pairs-vs-students-metrics.md`.*
