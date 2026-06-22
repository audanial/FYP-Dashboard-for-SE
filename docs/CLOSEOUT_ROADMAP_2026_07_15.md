# Internship Closeout Roadmap — to 15 July 2026

> **Goal:** leave the system **stable, secure, maintainable, and usable** for coordinators,
> supervisors, and students. **Not** to maximise features. Every recommendation below is filtered
> through one question: *does this make the system safer to hand off or genuinely more usable for
> real people?* If not, it is deferred — regardless of how interesting it is.

**Author's last day:** 15 July 2026 · **Plan written:** 19 June 2026 · **Window:** ~3.5 weeks.

---

## The One Thing That Matters Most (read this first)

**Right now the system does not actually work for most supervisors.** Only **1 supervisor account
exists**, but the imported data references **24 distinct supervisor names** across **137 projects**.
A supervisor can only see their students if they have an account *and* it is linked via
`supervisor_id`. Today that is true for essentially one person.

This is not a "Phase 5 cleanup" detail — it is the difference between a system that demonstrably
works for its primary users and one that does not. And the blocker is **organizational, not
technical**: you need the 24 supervisors' real names and email addresses from the department/
coordinator before you can provision their accounts. **That request has lead time. Send it today.**

Everything else in this plan is secondary to getting real supervisors logged in and linked.

---

## 1. "Before Internship Ends" Roadmap

The closeout has three tracks. They run partly in parallel, but their priority order is fixed:

1. **Make it usable** (highest value) — provision the 24 supervisor accounts, link every project,
   so supervisors and (via logbooks) students actually get a working system. *Gated on getting
   emails from the department — start now.*
2. **Make it safe to leave** — fix the one latent security landmine (role drift), confirm the test
   suite is green from a clean clone, and remove anything that could silently break after you go.
3. **Make it maintainable without you** — a short operational runbook the *coordinator* (non-technical)
   can follow, plus confirming the existing handoff docs are current.

Feature work is **out of scope** for the closeout. The companion idea banks
(`FUTURE_ENHANCEMENTS.md`, `POST_PHASE5_IDEAS.md`) capture future work; nothing in them is a closeout
task.

---

## 2. Must-Have Items

These are the items without which the system is either insecure, broken for real users, or
un-maintainable. Do these even if nothing else gets done.

| # | Item | Why it's must-have | Effort | Depends on |
|---|---|---|---|---|
| **M1** | **Provision the 24 supervisor accounts + link all projects** | Without this the system does not work for ~23 of its supervisors. This is the core deliverable of the whole project. | Organizational lead time + ~2h data entry | Department supplying real names/emails |
| **M2** | **Fix coordinator role drift (`admin` → `coordinator`) + sweep `admin` remnants** | Latent **total lockout**: the next coordinator who self-registers (`SE-PC-2026`) is created as `admin` but every gate checks `coordinator`. A landmine that detonates after you leave. ~1h fix. | ~1–2h | None |
| **M3** | **Confirm green test suite from a clean clone + document how to run it** | A maintainer who can't run the 137 tests can't safely change anything. Prove `composer install` → migrate → `pest` works from scratch. | ~0.5 day | None |
| **M4** | **Write a coordinator operations runbook** | The people left behind are not developers. They need step-by-step: how to import a CSV, provision a supervisor, run the backfill, and what the "Unlinked" panel means. | ~0.5 day | None |
| **M5** | **Verify the handoff doc is current on your last week** | `CLAUDE_HANDOFF_2026_06_19.md` and `PROJECT_STATUS.md` must reflect the *final* state (linked counts, whether Phase 5 happened). A stale handoff misleads the next reader. | ~1–2h | Done last |

**On M1's dependency:** if the department does not supply emails in time, M1 cannot be completed.
That is acceptable *as long as the dual-read fallback is left in place* (do not do Phase 5) and the
gap is documented loudly in M5. A half-linked system with a working fallback is stable; a half-linked
system with the fallback removed is broken. **Stability beats cleanliness.**

---

## 3. Nice-to-Have Items

Real improvements to the leave-behind, worth doing **only if M1–M5 are done and time remains.**
Ordered by value.

| # | Item | Value | Effort | Notes |
|---|---|---|---|---|
| **N1** | **Complete Phase 5 (remove dual-read)** | Cleaner, simpler ownership code; one less moving part for a maintainer. | ~0.5 day | **Only if M1 reaches null-count = 0 and you've verified it twice.** Otherwise leave the fallback — it is safe and conservative. |
| **N2** | **Extract the dual-read into one shared query scope (TD-1)** | The security-critical ownership query is currently copy-pasted in two files; dedup removes drift risk. | ~0.5 day | If doing N1, fold this in (the scope collapses to one line). If not doing N1, still worth deduping. |
| **N3** | **Migration-completion panel for the coordinator (A-1)** | Lets the coordinator *see* linking progress and finish any residual linking after you leave — supports M1's longevity. | ~0.5 day | Reuses existing queries. Genuinely supports maintainability. |
| **N4** | **Centralise the coordinator authz check (TD-4)** | Removes the duplication that hid the M2 drift in the first place. | ~0.5 day | Do after M2 so it codifies the correct role. |
| **N5** | **Friendly 403/404 + consistent empty states (UX-2)** | Makes the system feel finished and less confusing for non-technical users hitting a fail-closed gate. | ~0.5 day | Pure usability polish. |

---

## 4. Items to Defer (explicitly out of scope for closeout)

These are valuable but **wrong for a limited-time closeout** — too large, dependent on infrastructure
you don't have, or pure feature growth. Deferring them *is* the disciplined choice. They are already
captured in the idea banks; do not start them.

| Item | Why deferred |
|---|---|
| **All new features** (milestones, notifications, submissions, dashboards, approvals) — i.e. all of `POST_PHASE5_IDEAS.md` | Feature growth directly contradicts the stated goal. None are needed for stability/usability. |
| **Audit logging (S-4)**, **force password reset (S-2)**, **account lifecycle/soft-delete (S-3)** | Each is ~1 day and improves a system that already functions; none are *blocking* stability. Better left as a clean, documented backlog than half-built. |
| **Email verification + notifications + onboarding (S-5, UX-3)** | Hard-blocked on outbound mail infrastructure the project doesn't have. Setting that up is its own project. |
| **Fuzzy supervisor matching (SM-1)** | A deliberate Sprint 4 non-goal; a wrong auto-link is a wrong-access vector. Manual curation (M1) is the safe path for 24 names. |
| **Bulk provisioning UI (SM-2)** | Building it (~1 day) costs more than doing the 24 accounts by hand (~2h). Manual wins for a one-time closeout. |
| **Retiring `supervisor_name` / dropping the column (TD-2)** | Risky SQLite table rebuild; only safe well after Phase 5 and full display-read migration. Not a closeout-sized change. |
| **Exportable reports, trends, analytics expansion (A-2, A-3)** | Nice, but not stability or usability blockers. |

---

## 5. Recommended Priorities — Today (19 June) to 15 July

Anchored to the goal. The critical path runs through M1's organizational dependency, so it is kicked
off on day one and worked in the background while you do the things fully within your control.

### Now — Day 1 (19 June): unblock the long-lead item + kill the landmine
- [ ] **Send the request** to the coordinator/department for the 24 supervisors' real names + email
      addresses. This has lead time; it gates M1; nothing else you do matters as much. *(M1 kickoff)*
- [ ] **Fix the role drift (M2):** change `CreateNewUser.php:41` and `DatabaseSeeder.php:22` from
      `admin` to `coordinator`; add a RED→GREEN test that the coordinator access code yields
      `coordinator`; sweep remaining `admin` references. Confirm no live `admin` user exists. ~1–2h,
      pure win, no dependencies.

### Week 1 (≈23–29 June): make it runnable & provision what you can
- [ ] **M3 — Clean-clone verification:** from a fresh checkout, run `composer install`, migrate, and
      `php vendor/bin/pest`. Confirm 137 green. Note the exact commands for M4.
- [ ] **Start M1 provisioning as emails arrive:** for each supervisor whose details you have, use the
      Create Supervisor modal; run `php artisan fyp:clean-supervisor-encoding` (fixes the mojibake
      name) then `php artisan fyp:link-supervisors`; use the Unlinked panel for non-exact names.
- [ ] **(Optional, if it helps the coordinator self-serve) N3 — migration-completion panel.**

### Week 2 (≈30 June–6 July): drive linking to zero & write the runbook
- [ ] **Finish M1:** keep provisioning + linking until
      `FypProject::whereNull('supervisor_id')->count()` is **0** (or as low as the supplied data
      allows). Record the final number.
- [ ] **M4 — Operations runbook** (`docs/COORDINATOR_RUNBOOK.md`): CSV import, provisioning a
      supervisor, running the backfill, reading the Unlinked panel, and "what to do when a new
      supervisor joins." Written for a non-developer.
- [ ] **Spot-check usability:** log in as a real provisioned supervisor → confirm "My Students" shows
      their students and a logbook opens. This is the proof the system works.

### Week 3 (≈7–13 July): consolidate & (only if green) clean up
- [ ] **If and only if null-count = 0 and verified twice:** do **N1** (remove dual-read) + **N2**
      (extract scope), full suite green. If there's *any* doubt, skip — leave the fallback in.
- [ ] **N4 (centralise authz)** and **N5 (error/empty states)** if time remains.
- [ ] Re-run the full suite; fix anything red. No new work after this week.

### Final days (14–15 July): freeze, verify, hand off
- [ ] **Code freeze.** No new changes. Last commits are documentation only.
- [ ] **M5 — Update `PROJECT_STATUS.md` and `CLAUDE_HANDOFF_*.md`** to the true final state: final
      linked/unlinked counts, whether Phase 5 was completed, final test count, and a clear "known
      gaps" list (especially any supervisors still unprovisioned).
- [ ] **Final clean-clone test run** and record the result in the handoff.
- [ ] Confirm the runbook (M4) is discoverable from `PROJECT_STATUS.md`.

---

## If You Only Have a Few Hours Total

Do these, in this exact order, and stop wherever you run out of time:

1. **M2** — fix the role drift (1–2h). Removes a guaranteed future lockout.
2. **M1 kickoff** — send the email asking for the 24 supervisors' details. Costs minutes, has the
   highest ceiling.
3. **M4** — a one-page runbook so the coordinator can provision and link without you.
4. **M5** — make `PROJECT_STATUS.md` tell the truth about what's done and what isn't.

That sequence leaves the system **secure** (M2), **on a path to usable** (M1), and **maintainable**
(M4, M5) — even if you never touch a feature.

---

## Guiding Principle for Every Remaining Decision

> **Conservative over clever. Stable over clean. Documented over done-in-a-rush.**
>
> A working fallback you leave in place beats an elegant removal you can't fully verify. An honest
> "this isn't finished, here's exactly where it stands" beats a confident handoff that's subtly wrong.
> Your value in these weeks is *reducing the risk that this system breaks or confuses someone after
> you're gone* — not adding to it.

*Follows the project's rules in `CLAUDE.md`: TDD for every change, server-side authorization on every
action, never remove existing tests. Cross-references: `PROJECT_STATUS.md`, `CLAUDE_HANDOFF_2026_06_19.md`,
`FUTURE_ENHANCEMENTS.md`, `POST_PHASE5_IDEAS.md`.*
