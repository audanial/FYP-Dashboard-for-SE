# Post-Phase 5 Idea Bank

> **This is an idea bank, not a committed roadmap.** Nothing here is scheduled, estimated, or
> approved. It is a brainstorm to seed prioritisation conversations once the platform is on stable
> ground. Items are ranked only by **expected impact** (High / Medium / Low), not by effort or
> sequencing.

**Prepared:** 22 June 2026 · **Assumption:** Sprint 4 Phase 5 is complete.

---

## Starting Assumption: The Post-Phase 5 World

This document assumes the supervisor identity migration is **finished**:

- Every project is linked to a supervisor account through `supervisor_id` exclusively.
- The temporary dual-read fallback (`supervisor_id IS NULL AND supervisor_name = …`) is **removed**.
- `supervisor_name` is display-only (or already retired); supervisor identity is a real relation.
- Ownership queries are simple and trustworthy: `where('supervisor_id', $id)`.
- The "Unlinked" analytics bucket is empty or gone.

That clean foundation is what makes the ideas below worth building — they assume a *reliable*
supervisor→project relationship rather than fighting a fragile string match. For security/technical-debt
items (audit logging, role normalisation, account lifecycle, force-password-reset), see the companion
`docs/FUTURE_ENHANCEMENTS.md`; this document focuses on **product and experience**.

---

## How Impact Was Judged

- **High Impact** — changes a core workflow for a whole role, removes recurring manual effort, or
  unlocks a capability the system fundamentally lacks today.
- **Medium Impact** — meaningful quality-of-life or insight improvement, but workarounds exist.
- **Low Impact** — polish, nice-to-have, or narrow-audience features.

---

## 1. Features Worth Building Next

### 🟢 High Impact

- **FYP milestone & deadline tracking.** Model the FYP lifecycle as defined milestones (proposal,
  progress presentation, draft submission, final submission, viva) with per-cohort due dates. Today
  the system tracks *who* supervises *whom* and logbook entries, but not *where each project is in the
  process*. This is the missing backbone that most other ideas below hang off.
- **Supervisor approval of logbook entries.** Let supervisors acknowledge/sign-off student logbook
  entries (approve / request changes / comment). Now that `supervisor_id` reliably connects a
  supervisor to a student, the logbook can become a two-way interaction instead of a one-way student
  diary.
- **Document/deliverable submission.** Allow students to upload deliverables (proposal PDF, report
  drafts) against milestones, with supervisor visibility. Closes the loop between "logbook says work
  happened" and "here is the artifact."

### 🟡 Medium Impact

- **Meeting scheduling & records.** Lightweight record of supervisor–student meetings (date, notes,
  action items) tied to the project. Complements the logbook with a meeting-centric view.
- **Assessor / second-marker assignment.** The data model already carries `assessor_name`; promote it
  to a real linked role (mirroring what was done for supervisors) so assessors get their own scoped view.
- **Project status states.** A simple status field (On Track / At Risk / Blocked / Complete) that
  supervisors set, feeding the coordinator's at-a-glance cohort health.

### 🔵 Low Impact

- **Tagging / labels on projects** beyond the existing domain/application-type categories.
- **Saved searches / filters** for power users (coordinators) who repeat the same queries.

---

## 2. Product Improvements

### 🟢 High Impact

- **Role-specific landing dashboards.** Each role lands on a tailored home (coordinator cohort health;
  supervisor's assigned students + pending logbook reviews; student's milestones + next deadline).
  This is already the named "Sprint 5" direction and becomes far more valuable once supervisor links
  are trustworthy.
- **In-app notifications.** Surface the events that matter per role: reassignment, new logbook entry,
  approaching deadline, supervisor comment. Removes the current reliance on out-of-band chasing.

### 🟡 Medium Impact

- **Global search.** One search across students, supervisors, and projects for coordinators, instead of
  per-page search boxes.
- **Activity feed / timeline per project.** A chronological view combining logbook entries, status
  changes, submissions, and meetings — the project's story in one place.
- **Mobile-friendly / responsive review.** Supervisors often review on phones; ensure the key supervisor
  flows (My Students, logbook review) are comfortable on small screens.

### 🔵 Low Impact

- **Dark mode / theming.**
- **Per-user UI preferences** (default landing tab, page size).

---

## 3. Reporting Improvements

### 🟢 High Impact

- **Cohort progress report.** A coordinator report showing each project's milestone status across the
  whole cohort — who has submitted, who is overdue, who is at risk. This is the report a coordinator
  actually needs for department meetings, and it depends on milestone tracking (§1).

### 🟡 Medium Impact

- **Supervisor workload & coverage report.** Per-supervisor pair counts, load distribution, and outlier
  flags (zero students, overloaded). Clean and trustworthy now that grouping is purely by
  `supervisor_id` (no more "Unlinked" bucket muddying counts).
- **Exportable reports (CSV / PDF).** All analytics currently live only in the browser. Export turns
  them into shareable artifacts for faculty reporting. CSV first (cheap, high utility), PDF later.
- **Historical / semester-over-semester trends.** Compare domain distribution, completion rates, and
  workloads across semesters once multiple cohorts exist.

### 🔵 Low Impact

- **Scheduled email digests** of key metrics (gated on mail infrastructure — see `FUTURE_ENHANCEMENTS.md` S-5).
- **Printable single-project summary** sheet.

---

## 4. Student Experience Improvements

### 🟢 High Impact

- **Personal progress view.** A student home that answers "where am I?": current milestone, next
  deadline, supervisor feedback status, submission checklist. Today students only see their own logbook;
  they have no sense of overall progress.
- **Clear feedback loop.** Show supervisor responses/approvals on logbook entries directly to the
  student, so feedback is visible in-context rather than communicated separately.

### 🟡 Medium Impact

- **Deadline reminders.** Surface upcoming milestone deadlines to students (in-app first; email later).
- **Richer logbook entries.** Support attachments, links, and basic formatting so a logbook entry can
  reference the actual work.
- **Guided onboarding.** A short first-login walkthrough of how the logbook and milestones work.

### 🔵 Low Impact

- **Logbook entry templates** (e.g. weekly progress prompts).
- **Export own logbook** to PDF for personal records.

---

## 5. Supervisor Experience Improvements

### 🟢 High Impact

- **"Needs my attention" queue.** A single prioritised list for a supervisor: logbook entries awaiting
  review, students with no recent activity, approaching deadlines. Turns the supervisor view from a
  passive roster (today's "My Students") into an actionable worklist. Directly enabled by reliable
  `supervisor_id` ownership.
- **Inline logbook review & comments.** Let supervisors comment/approve from the logbook view without
  leaving the page (pairs with §1 logbook approval).

### 🟡 Medium Impact

- **Cross-student overview.** A compact dashboard of all the supervisor's students with status and
  last-activity-at, so they can spot the quiet/stuck student quickly.
- **Bulk actions.** Acknowledge multiple entries, or message all supervisees at once.
- **Meeting notes capture.** Quick per-student meeting log (pairs with §1 meetings).

### 🔵 Low Impact

- **Personal notes** on a student visible only to the supervisor.
- **Calendar export** of supervisee deadlines.

---

## 6. Coordinator Workflow Improvements

### 🟢 High Impact

- **Cohort health dashboard.** The coordinator's command center: projects by status, overdue
  milestones, supervisor load balance, and submission completeness — at a glance. The single most
  valuable coordinator artifact post-Phase 5.
- **Streamlined CSV import with diff preview.** Show what an import *will change* (new students,
  reassignments, removals) before committing, instead of importing blind. Reduces the risk of a bad
  spreadsheet silently mutating the cohort.

### 🟡 Medium Impact

- **Bulk supervisor provisioning & assignment.** Create/assign many supervisors in one flow rather than
  one modal at a time (carried over from `FUTURE_ENHANCEMENTS.md` SM-2 — still relevant for each new
  semester's intake).
- **Assessor management.** Assign and rebalance assessors the same way supervisors are managed (pairs
  with §1 assessor role).
- **Audit/history view.** See who changed what and when (depends on audit logging — `FUTURE_ENHANCEMENTS.md` S-4).

### 🔵 Low Impact

- **Configurable cohort settings** (milestone dates, deadlines per semester) in a settings screen
  rather than code.
- **Announcement banner** to broadcast a message to all users.

---

## Consolidated High-Impact Shortlist

If forced to name the handful most worth discussing first, these recur as enablers across multiple
roles:

1. **FYP milestone & deadline tracking** (§1) — the backbone most other high-impact ideas depend on.
2. **Role-specific landing dashboards** (§2) — coordinator cohort health, supervisor worklist, student progress.
3. **Supervisor logbook approval + feedback loop** (§1, §4, §5) — makes the logbook a two-way tool.
4. **Cohort progress reporting** (§3, §6) — the report coordinators actually need.
5. **In-app notifications** (§2) — ties the whole experience together once events exist to notify on.

Note the strong dependency chain: **milestones → status/deadlines → dashboards & reports →
notifications.** That ordering is a natural conversation starter, not a commitment.

---

## A Note for Whoever Reads This Later

This is deliberately a *wide* list, not a *deep* one — it captures possibilities so none are lost, and
expects most to be deferred or dropped. Before acting on anything here:

1. **Verify the starting assumption holds.** Confirm Phase 5 is actually complete and `supervisor_id`
   is the sole ownership path (`docs/PROJECT_STATUS.md`). Several ideas assume it.
2. **Check the companion doc.** `docs/FUTURE_ENHANCEMENTS.md` covers the security/technical-debt side
   and flags real latent issues (e.g. the `admin`/`coordinator` role drift) that may deserve priority
   over new features.
3. **This document is not authorization to build.** Pick items *with the project owner*, then follow
   the project's TDD and server-side-authorization rules (`CLAUDE.md`).

*Ideas grounded in the codebase as it stood on 22 June 2026. Impact rankings are opinions for
discussion, not measurements.*
