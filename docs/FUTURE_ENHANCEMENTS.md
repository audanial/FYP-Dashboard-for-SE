# Future Enhancements & Opportunities

> **Status: NOT an official roadmap.** This document captures ideas, opportunities, and known
> debt for consideration *after* Sprint 4 Phase 5 (dual-read retirement) is complete. Nothing
> here is committed or scheduled. It exists to give a future developer a grounded starting point
> for prioritisation discussions with the project owner.

**Prepared:** 22 June 2026 · **Branch context:** `claude/laravel-fyp-dashboard-ot8rR`

---

## How to Read This Document

If you have never seen this project, read these first, in order:

1. `docs/CLAUDE_HANDOFF_2026_06_19.md` — complete project handoff (architecture, history, glossary).
2. `docs/PROJECT_STATUS.md` — single source of truth for current state.
3. `CLAUDE.md` — project rules (TDD mandatory, server-side authz mandatory, never remove tests).

**One-paragraph context.** This is a Laravel 11 + Livewire Volt dashboard for tracking Final Year
Projects at UniKL MIIT, used by coordinators, supervisors, and students. Sprint 4 replaced fragile
supervisor name-string matching with a real `supervisor_id` foreign key. The system is mid-transition:
a temporary "dual-read" (prefer `supervisor_id`, fall back to `supervisor_name`) is live until all
rows are linked, at which point Phase 5 removes the fallback. Most items below assume Phase 5 is done.

Each enhancement is tagged with a **Type** (Future Enhancement / Technical Debt / Security
Improvement / UX Improvement), a **Complexity** (S/M/L), and a **Priority** (Low/Medium/High).

---

## Category: Security

### S-1. Fix coordinator role drift (`admin` vs `coordinator`)

- **Type:** Security Improvement (also Technical Debt)
- **Problem:** Registration assigns the FYP coordinator access code to role **`admin`**
  (`app/Actions/Fortify/CreateNewUser.php:41`) and the seeder does the same
  (`database/seeders/DatabaseSeeder.php:22`). But **every authorization gate checks
  `'coordinator'`**: the route middleware via `hasRole('coordinator')`
  (`app/Http/Middleware/EnsureUserHasRole.php`) and every Livewire write action via
  `abort_unless(Auth::user()?->role === 'coordinator', 403)` (e.g. `user-management.blade.php:99,
  143, 189, 214, 254`). A new coordinator who self-registers with the `SE-PC-2026` code would be
  created as `admin` and then **locked out of every coordinator feature** (user management, CSV
  import, curation). It is dormant today only because the live coordinator account already carries
  `coordinator` and no `admin` users exist.
- **Business value:** Prevents a silent, total lockout the next time a coordinator is onboarded
  through the normal registration path. Removes a latent inconsistency that will confuse any
  future developer reading the authorization code.
- **Complexity:** **S** (~0.5–1h). Two one-line changes plus a regression test.
- **Dependencies:** None. Independent of the Sprint 4 transition.
- **Suggested approach:**
  1. RED test: register with `staff_access_code = 'SE-PC-2026'` → assert resulting `role` is
     `coordinator`; and assert the seeder yields `coordinator`.
  2. Change `'SE-PC-2026' => 'admin'` to `'coordinator'` in `CreateNewUser.php:41`.
  3. Change `'role' => 'admin'` to `'coordinator'` in `DatabaseSeeder.php:22`.
  4. One-off data check: confirm no existing production user has `role = 'admin'`; if any exist,
     migrate them to `coordinator`.
- **Note:** Already documented as backlog in the Sprint 4 roadmap (§Backlog). This is the
  highest-value lowest-effort item in the document.

### S-2. Force password reset on first login for provisioned accounts

- **Type:** Security Improvement
- **Problem:** When a coordinator creates a supervisor, a 16-char temp password is generated and
  shown once (`user-management.blade.php:222-240`). There is no mechanism to **force the supervisor
  to change it** on first login, so the coordinator-known temp password can remain valid
  indefinitely. The `users` table has no `force_password_reset` (or `password_changed_at`) column.
- **Business value:** Standard credential-hygiene control. The coordinator should not retain
  knowledge of a working password after handoff. Reduces shared-secret risk.
- **Complexity:** **M** (~0.5–1 day). Needs a migration, middleware, and a change-password screen.
- **Dependencies:** None hard, but pairs naturally with S-3 (account lifecycle) and S-4 (audit log).
- **Suggested approach:**
  1. Migration: add `force_password_reset` boolean (default false) to `users`.
  2. Set it `true` in `createSupervisor` (`user-management.blade.php:224`).
  3. Middleware: if the authenticated user has the flag set, redirect all routes (except the
     change-password route and logout) to a forced change-password screen.
  4. Clear the flag on successful password change.
  5. TDD: flagged user is redirected; unflagged user is not; changing the password clears the flag.
- **Note:** Explicitly deferred in Sprint 4 ("force-password-reset flag (needs a `users` migration)").

### S-3. Account lifecycle management (deactivation, soft delete, orphan handling)

- **Type:** Security Improvement (also Technical Debt)
- **Problem:** Three gaps:
  1. **Hard delete.** `deleteUser` calls `->delete()` (`user-management.blade.php:197`) — a
     permanent delete with no recovery and no record of who deleted whom or why.
  2. **`is_active` may not be enforced at login.** The column exists and is editable, but it is
     not confirmed to block authentication. A deactivated supervisor might still be able to log in.
  3. **Silent orphaning.** `supervisor_id` is `nullOnDelete`. Deleting a supervisor silently nulls
     `supervisor_id` on all their projects, dropping those rows back into the "Unlinked" bucket
     with no warning to the coordinator.
- **Business value:** Auditable, reversible account management; prevents accidental data loss;
  ensures deactivated accounts cannot act. Important once real staff turnover occurs.
- **Complexity:** **M** (~1 day).
- **Dependencies:** Benefits from S-4 (audit logging). Orphan-warning interacts with the Unlinked
  Supervisors panel.
- **Suggested approach:**
  1. Add Laravel `SoftDeletes` to `User` (migration + trait) so deletes are reversible.
  2. Enforce `is_active` at login (Fortify `authenticate` customisation or a login response check);
     add a test that an inactive user cannot authenticate.
  3. Before deleting a supervisor, warn the coordinator of the count of projects that will be
     orphaned (reuse the `unlinkedSupervisors`-style query) and require confirmation.
  4. TDD each branch.

### S-4. Audit logging for coordinator actions

- **Type:** Security Improvement
- **Problem:** None of the privileged coordinator actions leave a trail. Create supervisor, reassign
  supervisor, link unlinked name, edit user, and delete user
  (`user-management.blade.php`) all mutate data with no record of *who* did *what* and *when*. In a
  system where a wrong supervisor link is a wrong-access vector, the absence of an audit trail makes
  incident investigation impossible.
- **Business value:** Accountability and traceability; supports incident response; expected control
  for any system holding student records. Also helps debugging ("why is this student linked to that
  supervisor?").
- **Complexity:** **M** (~1 day).
- **Dependencies:** None, but most valuable alongside S-3.
- **Suggested approach:**
  1. Add an `audit_logs` table (actor_id, action, subject_type, subject_id, metadata JSON, created_at),
     or adopt a maintained package (e.g. `owen-it/laravel-auditing`) if dependency policy allows.
  2. Record an entry inside each coordinator action after the mutation succeeds.
  3. Never log secrets — in particular the temp password must stay out of the audit metadata
     (it is currently correctly kept out of session/logs; preserve that).
  4. TDD: each action writes exactly one audit row with the expected actor and subject.

### S-5. Real email verification flow

- **Type:** Security Improvement
- **Problem:** `MustVerifyEmail` is commented out on the `User` model (`app/Models/User.php:5`).
  Coordinator-provisioned accounts are force-verified (`email_verified_at = now()`,
  `user-management.blade.php:236`), which is acceptable because the coordinator vouches for the
  address — but self-registered students are not held to any verification step. Unverified email
  ownership is unproven.
- **Business value:** Confirms students control the email they registered with; prerequisite for
  trustworthy notifications (see UX-3) and password-reset emails.
- **Complexity:** **M** (~1 day, dominated by mail infrastructure setup, which the project does not
  yet have).
- **Dependencies:** Requires outbound mail configuration (currently none — temp passwords are
  shown on-screen precisely because there is no mail infra). This is the real blocker.
- **Suggested approach:** Configure a mail transport; implement `MustVerifyEmail` on `User`; keep
  the coordinator force-verify path for provisioned accounts; gate student features behind
  verification only if the project owner wants it. TDD the verified/unverified branches.

---

## Category: Supervisor Management

### SM-1. Fuzzy / normalised supervisor matching (assisted, not automatic)

- **Type:** Future Enhancement
- **Problem:** Backfill and import link a project to a supervisor **only on exact string equality**
  (`fyp:link-supervisors`, `LinkSupervisors.php:31`). Real CSV names carry honorifics and ordering
  variants (`Juliana Jaafar - Ts. Dr.` vs an account `Juliana Jaafar`), so 24 names require manual
  provisioning + linking. A normalisation resolver (strip/relocate `Ts./Dr./Prof./Pn./En.`, fold
  `binti/bin`, lowercase, trim, repair mojibake) could *suggest* matches for coordinator approval.
- **Business value:** Cuts the manual curation burden each semester as new CSVs arrive with the same
  formatting quirks. Speeds the path to the Phase 5 "null-count = 0" milestone on every re-import.
- **Complexity:** **M** (~1 day for a well-tested resolver + a suggestion UI hook).
- **Dependencies:** Builds on the Unlinked Supervisors panel (`user-management.blade.php:67-82,
  246-269`). Best done *after* Phase 5 so the resolver is purely an assist, never a silent writer.
- **Suggested approach:**
  1. Build `app/Services/SupervisorNameResolver.php` as a **pure suggestion engine**: given a raw
     name, return ranked candidate accounts + a confidence score. **Never auto-link** — the wrong
     link is a wrong-access vector (this was the locked Sprint 4 decision).
  2. Surface suggestions in the Unlinked panel as pre-filled, coordinator-confirmable options.
  3. Unit-test the resolver hard: the `Tiliza` pair matches; ambiguous names return multiple
     candidates (never a silent pick); no-match returns empty.
- **Caution:** This was **explicitly out of scope** for Sprint 4 by deliberate decision. Treat as an
  *assist* layer only. Do not let it write `supervisor_id` without human confirmation.

### SM-2. Bulk supervisor provisioning

- **Type:** Future Enhancement (also UX Improvement)
- **Problem:** Supervisors are created one at a time through a modal
  (`user-management.blade.php:213-242`). The current backlog of 24 unmatched names means 24 separate
  modal interactions before Phase 5 can even begin.
- **Business value:** Turns a tedious 24-step task into one action; lowers the friction that is
  currently the main thing blocking Phase 5.
- **Complexity:** **M** (~1 day).
- **Dependencies:** Naturally pairs with SM-1 (suggest accounts to create from the unmatched list).
- **Suggested approach:** Add a "Provision all unlinked" flow that takes the distinct unmatched names,
  lets the coordinator confirm/edit name + email per row in one screen, creates the accounts in a
  transaction (each with its show-once temp password and `force_password_reset` from S-2), then runs
  the exact-match backfill automatically. TDD the batch-create + dedup-by-email behaviour.

### SM-3. Streamlined link workflow (search, preview, undo)

- **Type:** UX Improvement
- **Problem:** The link modal is a bare dropdown of all supervisors
  (`user-management.blade.php:951-959`). With many supervisors it is hard to find the right one, there
  is no preview of *which/how many* students will be affected at the moment of confirming, and there
  is no undo if the coordinator links the wrong account.
- **Business value:** Reduces mis-links (a wrong-access vector) and makes the highest-stakes coordinator
  action safer and faster.
- **Complexity:** **M** (~1 day).
- **Dependencies:** Undo is far easier once S-4 (audit log) exists to record the prior state.
- **Suggested approach:** Add a searchable/type-ahead supervisor selector; show the affected
  student+pair count inside the confirm step (the panel already computes these counts at
  `user-management.blade.php:67-82`); add an "undo last link" backed by the audit trail.

---

## Category: Analytics

### A-1. Migration completion dashboard (Phase 5 readiness)

- **Type:** Future Enhancement
- **Problem:** The single most important transition metric — `FypProject::whereNull('supervisor_id')
  ->count()` — is only visible by running `php artisan fyp:link-supervisors` or `tinker`. The
  coordinator (who must drive the provisioning that reaches null-count = 0) has no in-app view of
  progress.
- **Business value:** Gives the coordinator a clear "X of Y projects linked — N names left" progress
  indicator, making the Phase 5 prerequisite self-service instead of developer-assisted.
- **Complexity:** **S** (~0.5 day). The underlying queries already exist.
- **Dependencies:** None. Reuses `unlinkedSupervisors` and a simple count.
- **Suggested approach:** A small card/panel on the coordinator dashboard: linked vs total projects,
  percentage, count of distinct unlinked names, and a direct link to the Unlinked Supervisors panel.
  TDD the counts.

### A-2. Supervisor coverage & workload metrics

- **Type:** Future Enhancement
- **Problem:** Analytics groups workload by an effective key with an "Unlinked" bucket
  (`analytics-charts.blade.php`), but there is no view of *coverage* (which supervisors have no
  students, which are over/under-loaded relative to a target pair count).
- **Business value:** Helps the coordinator balance supervision load and spot supervisors with zero
  or excessive assignments before the semester gets underway.
- **Complexity:** **M** (~1 day).
- **Dependencies:** Cleaner once Phase 5 removes the "Unlinked" bucket so grouping is purely by
  `supervisor_id`.
- **Suggested approach:** Add per-supervisor pair counts with min/max/average and outlier flags;
  consider a configurable target load. TDD the aggregation against seeded fixtures.

### A-3. Exportable coordinator reports

- **Type:** Future Enhancement
- **Problem:** All analytics live only in the browser. Coordinators frequently need to share figures
  with the department or include them in semester reports, and currently must transcribe manually.
- **Business value:** Saves manual reporting effort; produces a shareable artifact for meetings.
- **Complexity:** **M** (~1 day for CSV/PDF export of the existing metrics).
- **Dependencies:** Best built after A-1/A-2 so the export covers the full metric set.
- **Suggested approach:** Add CSV export first (cheap, high utility) for project lists and workload;
  add PDF later if needed. Reuse existing computed properties as the data source. TDD the export
  contents.

---

## Category: User Experience

### UX-1. Role-specific dashboards

- **Type:** UX Improvement (also Future Enhancement)
- **Problem:** Per the roadmap, each role currently lands on a generic dashboard rather than a view
  tailored to what that role needs (coordinator cohort overview + curation entry points; supervisor
  at-a-glance assigned students + recent logbook activity; student own-project + progress).
- **Business value:** Faster task completion for each role; surfaces the right entry points (e.g.
  the coordinator's path to provisioning/curation, which today is buried in Manage Users).
- **Complexity:** **L** (~1.5–2 days across three roles).
- **Dependencies:** Coordinator dashboard should incorporate A-1 (migration completion). This is
  already named as "Sprint 5" direction in `PROJECT_STATUS.md`.
- **Suggested approach:** Design per-role landing components; pull from existing computed queries;
  TDD each role sees only its own data and the right widgets.

### UX-2. Consistent error handling and empty/error states

- **Type:** UX Improvement
- **Problem:** Feedback is uneven. Some failures flash session messages, some abort with bare 403/404,
  some validation errors render inline. There is no consistent pattern for "you don't have access"
  or "something went wrong" pages, and the fail-closed `firstOrFail()` logbook gate
  (`supervisor-student-logbook.blade.php:40`) produces a raw 404 with no guidance.
- **Business value:** Less user confusion, fewer "it's broken" support questions, more professional
  feel for a system being demoed to faculty.
- **Complexity:** **M** (~1 day).
- **Dependencies:** None.
- **Suggested approach:** Standardise flash-message styling (already partly done in
  `user-management.blade.php:296-321`), add friendly 403/404 pages, and ensure every list has a clear
  empty state (My Students already has one at `my-students.blade.php:126-131`). TDD where logic
  branches.

### UX-3. Onboarding & notifications

- **Type:** UX Improvement (also Future Enhancement)
- **Problem:** A newly provisioned supervisor receives a temp password out-of-band (the coordinator
  reads it off-screen and shares it manually) with no welcome guidance, and no one is notified of
  events like reassignment or logbook submission.
- **Business value:** Smoother supervisor onboarding; supervisors and students stay informed without
  polling; reduces coordinator chase-up work.
- **Complexity:** **L** (~2 days; gated on mail/notification infrastructure).
- **Dependencies:** Requires S-5's mail infrastructure. Pairs with S-2 (first-login password change
  as part of onboarding).
- **Suggested approach:** Add Laravel notifications for key events; build a minimal first-login
  onboarding screen (welcome + forced password change from S-2). Defer until mail infra exists.

---

## Category: Technical Debt

### TD-1. Extract the dual-read predicate into a shared query scope

- **Type:** Technical Debt
- **Problem:** The dual-read ownership closure is **copy-pasted verbatim** in two places:
  `my-students.blade.php:24-36` and `supervisor-student-logbook.blade.php:26-39`. Identical logic in
  two files means a change (especially the security-critical ID-precedence rule) must be made twice
  and can drift.
- **Business value:** Single source of truth for a security-critical query; eliminates drift risk;
  makes Phase 5 retirement a one-line change in one place instead of two.
- **Complexity:** **S** (~0.5 day).
- **Dependencies:** **Sequence with Phase 5.** Either extract now and have Phase 5 simplify the single
  scope, or fold the extraction into Phase 5 itself. Do not do it in a way that fights the Phase 5
  edit.
- **Suggested approach:** Add a query scope to `FypProject`, e.g.
  `scopeOwnedBySupervisor($query, int $id, string $name)`, encapsulating the
  `supervisor_id = ? OR (supervisor_id IS NULL AND supervisor_name = ?)` predicate. Both callers use
  the scope. When Phase 5 retires the fallback, the scope collapses to `where('supervisor_id', $id)`
  in one edit. Existing tests in `SupervisorIdentityTest` / `SupervisorStudentLogbookTest` guard the
  refactor — keep them green.

### TD-2. Retire `supervisor_name` as a write/identity field (post-Phase 5)

- **Type:** Technical Debt
- **Problem:** `supervisor_name` is a denormalised string kept for display and as the transition
  fallback. After Phase 5 it is display-only, but it will still be written by import/reassign and
  could drift from the linked account's canonical name.
- **Business value:** Removes a second, divergent source of truth for supervisor identity; simplifies
  reasoning about the schema.
- **Complexity:** **M** (~1 day; SQLite column drop forces a table rebuild, so stage carefully).
- **Dependencies:** **Hard-gated on Phase 5 being complete** and on all display code reading the
  relation (`$project->supervisor->name`) rather than the string. Do not drop the column until then.
- **Suggested approach:** First switch all display reads to the `supervisor()` relation; keep
  `supervisor_name` populated as provenance for one more cycle; only then schedule a migration to
  drop it. The Sprint 4 roadmap explicitly defers this — honour that ordering.

### TD-3. Convert seeders/factories and remove `admin` remnants

- **Type:** Technical Debt
- **Problem:** Related to S-1: once roles are normalised to `coordinator`, any lingering `'admin'`
  references in seeders/factories/tests should be swept so no path can recreate the drift.
- **Business value:** Prevents regression of S-1; keeps the role vocabulary to exactly the three
  documented roles.
- **Complexity:** **S** (~1–2h).
- **Dependencies:** Do together with or immediately after S-1.
- **Suggested approach:** Grep for `'admin'` across `database/` and `tests/`; replace with
  `coordinator`; add an assertion that the only roles present after seeding are
  `{student, supervisor, coordinator}`.

### TD-4. Centralise the coordinator authorization check

- **Type:** Technical Debt
- **Problem:** `abort_unless(Auth::user()?->role === 'coordinator', 403)` is repeated in ~8 actions
  in `user-management.blade.php` (and elsewhere). The literal `'coordinator'` and the pattern are
  duplicated; this duplication is exactly what made the S-1 drift invisible.
- **Business value:** One place to change the role gate; harder to introduce an inconsistent check;
  makes role changes (or adding a Gate/Policy) a single edit.
- **Complexity:** **S** (~0.5 day).
- **Dependencies:** Do **after** S-1 so the centralised check codifies the correct role.
- **Suggested approach:** Introduce a Laravel Gate or Policy (`Gate::define('coordinator', …)`) or a
  small helper, and replace the inline `abort_unless` calls. Keep the server-side enforcement —
  CLAUDE.md requires authorization in the action, not just the UI. TDD that each action still 403s
  for non-coordinators.

---

## Summary: Quick Wins, Strategic Improvements, Not Recommended

### 🟢 Quick Wins (high value, low effort — do these first)

| ID | Title | Type | Complexity | Why now |
|---|---|---|---|---|
| **S-1** | Fix coordinator role drift | Security / Debt | S | Latent total lockout for the next coordinator; trivial fix. |
| **A-1** | Migration completion dashboard | Enhancement | S | Makes the Phase 5 prerequisite self-service; queries already exist. |
| **TD-1** | Extract dual-read into a query scope | Debt | S | Single source of truth for a security-critical query; eases Phase 5. |
| **TD-3** | Sweep `admin` remnants | Debt | S | Locks in S-1; prevents regression. |
| **TD-4** | Centralise coordinator authz check | Debt | S | Removes the duplication that hid S-1. |

### 🔵 Strategic Improvements (high value, higher effort — plan deliberately)

| ID | Title | Type | Complexity | Notes |
|---|---|---|---|---|
| **S-3** | Account lifecycle (soft delete, inactive-login, orphan warning) | Security / Debt | M | Real risk once staff turnover begins. |
| **S-4** | Audit logging | Security | M | Prerequisite for safe undo (SM-3) and incident response. |
| **SM-1** | Assisted fuzzy matching (suggest-only) | Enhancement | M | Cuts curation effort each semester; must never auto-link. |
| **SM-2** | Bulk provisioning | Enhancement / UX | M | Directly unblocks the 24-name Phase 5 backlog. |
| **UX-1** | Role-specific dashboards | UX | L | Already the named "Sprint 5" direction. |
| **S-2** | Force password reset | Security | M | Credential hygiene; needs a migration. |
| **S-5 / UX-3** | Mail infra → email verification + notifications + onboarding | Security / UX | M–L | Gated on outbound mail; unlocks several items at once. |

### 🔴 Not Recommended (keep out of scope unless requirements change)

| Idea | Why not |
|---|---|
| **Automatic (silent) fuzzy supervisor linking** | A wrong link is a wrong-access vector. Sprint 4 deliberately chose coordinator-curated, exact-match-only linking. Any fuzzy logic must remain a *suggestion* requiring human confirmation (SM-1), never an automatic writer. |
| **Auto-creating supervisor accounts from CSV import** | Explicitly rejected in Sprint 4. Coordinators must vouch for accounts; auto-creation risks duplicate/unverified accounts and orphaned credentials. |
| **`supervisor_aliases` table / multi-name mapping** | Out of scope by decision. The `supervisor_id` FK plus curated linking already solves identity; an alias table adds a second mapping layer to maintain. |
| **`pairs` / `groups` schema refactor** | The current `semester` + `pair_number` convention works and is well-tested. A structural pairs refactor is a large change with no current driver. |
| **Dropping `supervisor_name` before Phase 5 / before display migration** | Removing it prematurely breaks display and search and removes the transition fallback. Strictly gated on TD-2's sequencing. |
| **Building notifications before mail infrastructure exists** | Notifications (UX-3) depend on outbound mail (S-5). Building them first produces dead features. Sequence the infra first. |

---

## A Note on Process (for whoever picks this up)

This project mandates **Test-Driven Development** (RED → GREEN) and **server-side authorization on
every write action** (`CLAUDE.md`). Existing tests must never be removed or weakened. Before starting
any item above:

1. Re-read `docs/PROJECT_STATUS.md` — the transition state may have moved on (e.g. Phase 5 may be done,
   the null-count may be 0, the `admin` drift may already be fixed). **Verify current reality before
   acting on anything here.**
2. Confirm with the project owner that the item is wanted and correctly prioritised — *this document
   is not authorization to build.*
3. Write the failing test first.

*This is an ideas document, not a commitment. Priorities are suggestions for discussion, grounded in
the codebase as it stood on 22 June 2026.*
