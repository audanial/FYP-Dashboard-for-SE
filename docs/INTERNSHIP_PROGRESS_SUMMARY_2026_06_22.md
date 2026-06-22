# Internship Progress Summary

| | |
|---|---|
| **Student** | Amir Umar Danial |
| **Programme** | Bachelor of Software Engineering (Hons), UniKL MIIT |
| **Report Date** | 22 June 2026 |
| **Reporting Period** | March 2026 – June 2026 |

---

## 1. Project Title

**FYP Dashboard System — UniKL MIIT**

An internal web application for tracking and managing Final Year Projects (FYP) for Software Engineering students at Universiti Kuala Lumpur Malaysian Institute of Information Technology (UniKL MIIT).

---

## 2. Problem Statement

The FYP coordinator's office currently manages the FYP cohort through manual spreadsheets, with no centralised system for tracking project assignments, student logbooks, or supervisor workloads. The existing process creates several operational problems:

- **No single source of truth.** Project assignments are maintained in a CSV spreadsheet that is periodically updated. There is no enforcement of data consistency, and multiple versions of the file circulate simultaneously.
- **No digital logbook.** Students record their progress on paper or in personal documents; coordinators and supervisors have no visibility into student progress between formal meetings.
- **No supervisor visibility.** Supervisors have no way to view their list of assigned students or read their logbook entries without contacting the coordinator.
- **Unreliable supervisor matching.** The academic name format used in the CSV file (e.g., `Tiliza Awang Mat - Ts.`) differs significantly from the name format used in staff accounts (e.g., `Tiliza binti Awang Mat`), making automated supervisor-to-project matching unreliable.

This internship project addresses all four problems by developing a purpose-built, role-based web dashboard.

---

## 3. Objectives

1. Build a centralised FYP tracking system accessible to coordinators, supervisors, and students via a web browser.
2. Implement CSV import functionality so coordinators can load the existing master project spreadsheet without manual data entry.
3. Provide supervisors with a reliable view of their assigned students and those students' logbook entries.
4. Allow students to maintain a digital logbook of their FYP progress.
5. Provide coordinators with an analytics dashboard showing project metrics, supervisor workloads, and domain distributions.
6. Ensure the system is secure: role-based access control, server-side authorisation on every write action, and protection against common vulnerabilities.

---

## 4. Work Completed

### Sprint 1–2 — Core System (March – April 2026)

- Designed and implemented the database schema for users, FYP projects, and logbook entries.
- Built CSV import functionality that accepts the coordinator's existing spreadsheet format, including fuzzy header recognition to handle inconsistencies across semester exports.
- Implemented role-based authentication with three user roles: coordinator, supervisor, and student.
- Developed the student logbook module, allowing students to create and manage progress entries.
- Built the coordinator's user management panel for viewing, filtering, and reassigning students to supervisors.
- Deployed the application to Railway (cloud hosting) with PHP 8.4.

### Sprint 3 — Analytics Correctness (May 2026)

- Built the analytics dashboard with charts for domain distribution, application types, FYP phase breakdowns, and supervisor workloads.
- Identified and corrected a metric error in which "paired" students (two students sharing a single project) were being counted as individual projects, inflating project counts.
- Implemented "pair" semantics throughout: two students sharing the same semester and pair number are always treated as a unit for assignment and reporting purposes.
- Brought the automated test suite to 94 passing tests.

### Sprint 4 — Supervisor Identity (June 2026) *(primary work this period)*

Sprint 4 was undertaken to resolve a fundamental data-integrity defect: supervisors were unable to see their assigned students because the system matched supervisor accounts to projects using a raw string comparison between two different name formats.

**Phase 1 — Encoding Stabilisation**
Identified and repaired a character encoding problem caused by Windows-1252 encoded CSV files being imported as UTF-8. Implemented a sanitisation step in the import pipeline and an artisan command (`fyp:clean-supervisor-encoding`) to repair already-stored corrupted values.

**Phase 2 — Structural Foreign Key**
Added a `supervisor_id` foreign key column to the `fyp_projects` table, referencing `users.id`. This establishes a proper relational link between a project and its supervisor's user account, replacing the unreliable string match.

**Phase 3 — Reads and Security**
Switched all supervisor-facing queries to use the new foreign key while maintaining a temporary name fallback for rows not yet linked. Closed a real privilege-escalation vulnerability: previously, a supervisor whose account name coincidentally matched another supervisor's project `supervisor_name` could access that student's logbook. The new implementation uses the foreign key as the authoritative access check, making name coincidences harmless.

**Phase 4 — Provisioning, Mapping, and Backfill**
- Added a "Create Supervisor" modal for coordinators to provision supervisor accounts directly within the dashboard. Provisioned accounts are immediately verified and active; a one-time temporary password is shown to the coordinator and never stored in plaintext or logs.
- Added an "Unlinked Supervisors" panel showing all imported project entries that have no linked supervisor account, with student and pair counts, and a tool to map them to the correct account.
- Corrected the "Reassign Supervisor" action so that reassignment applies to both students in a pair simultaneously and writes the foreign key correctly.
- Updated the CSV import to set the foreign key automatically when an exact name match exists, while preserving manually curated links on re-import.
- Built an idempotent `fyp:link-supervisors` artisan command for backfilling existing rows, which links any project where `supervisor_name` exactly matches a supervisor account, skips already-linked rows, and reports unmatched names for coordinator review.

---

## 5. Technologies Used

| Technology | Purpose |
|---|---|
| **Laravel 11** | PHP web framework; routing, ORM (Eloquent), artisan commands, middleware |
| **Livewire Volt** | Reactive UI components using the functional API; coordinator and supervisor views |
| **Flux UI** | Component library providing accessible, pre-styled UI primitives (modals, tables, buttons) |
| **Tailwind CSS** | Utility-first CSS for layout and styling |
| **SQLite** | Relational database; used in both production and an in-memory instance for automated tests |
| **Pest** | PHP testing framework for feature and unit tests (BDD-style assertions) |
| **Playwright** | End-to-end browser testing for the CSV import workflow |
| **Git / GitHub** | Version control; feature branches, pull requests, commit history |
| **Railway** | Cloud hosting platform; PHP 8.4 deployment with environment variable configuration |

---

## 6. Key Achievements

- **Eliminated the core supervisor bug.** Supervisors can now reliably see their assigned students regardless of how their name appears in the CSV file, by using a proper database foreign key rather than a string comparison.
- **Closed a security vulnerability.** The logbook access gate now uses the foreign key as the authoritative check, preventing privilege escalation via name coincidence.
- **Built a complete provisioning workflow.** Coordinators can create supervisor accounts, link unmatched project entries to those accounts, and backfill existing data — all within the dashboard without touching the database directly.
- **137 automated tests passing with 328 assertions.** Every feature developed was covered by automated tests written before the implementation (Test-Driven Development). No existing test was removed or weakened throughout the sprint.
- **Measurable transition metric established.** The count of projects with a null `supervisor_id` (`fyp_projects` where `supervisor_id IS NULL`) serves as an objective metric for the completion of the supervisor identity migration. The baseline after Sprint 4 is 137 rows.

---

## 7. Current Status

The system is fully functional and deployed. Sprint 4 Phases 1 through 4 are complete. The primary outstanding item is a data-curation task: **24 supervisor names** imported from the CSV do not exactly match any supervisor account in the system. These names use academic honorific formats (e.g., `Juliana Jaafar - Ts. Dr.`) that differ from the names stored in user accounts. The coordinator must provision accounts for these individuals using the new "Create Supervisor" tool, after which the backfill command will automatically link all remaining projects.

| Metric | Value |
|---|---|
| Automated tests passing | **137** |
| Total test assertions | **328** |
| Projects with confirmed supervisor link | **5** (linked by first backfill run) |
| Projects awaiting supervisor link | **137** (24 distinct supervisor names) |
| Supervisor accounts provisioned | 1 (pre-existing) |
| Supervisor accounts still required | 24 |

---

## 8. Remaining Work

### Immediate — Coordinator Action (No Code Required)

The following steps must be completed by the FYP coordinator in the deployed application:

1. Run `php artisan fyp:clean-supervisor-encoding` to repair any remaining encoding corruption in stored names.
2. Use the "Create Supervisor" modal to provision user accounts for the 24 unmatched supervisor names.
3. Re-run `php artisan fyp:link-supervisors` to automatically link all newly created accounts.
4. Use the "Unlinked Supervisors" panel to manually map any names that still do not exactly match.

### Sprint 4 Phase 5 — Dual-Read Retirement (Code Change, Gated on Above)

Once the null `supervisor_id` count reaches zero:

- Remove the name-based fallback query branch from the "My Students" and logbook access components. At that point, the foreign key alone is sufficient and the fallback becomes dead code.
- Simplify the analytics effective-key grouping to use the foreign key exclusively.
- Add a final regression test pass and mark Sprint 4 complete.

### Future Roadmap (Post-Sprint 4)

- **Sprint 5 — Role-Based Dashboards:** tailored landing pages for each role showing contextually relevant information (coordinator overview, supervisor student summaries, student progress view).
- **Notifications:** surface assignment changes, logbook submissions, and milestone events to relevant users.
- **Rule-Based and AI-Assisted Project Classification:** automate the assignment of domain and application type categories to projects, with coordinator review and override.

---

## 9. Lessons Learned

**Relational integrity is not optional.** The supervisor name-matching defect existed because a relationship was modelled as a display string rather than a database foreign key. This is a fundamental database design lesson that became apparent only when working with real, messy production data. The fix required careful incremental migration (additive column → dual-read transition → retirement) rather than a single breaking change.

**Test-Driven Development prevents regressions.** Writing failing tests before implementing each feature — including for security and edge cases — meant that changes to one part of the system immediately surfaced any breakage in another. The 137-test suite caught several interaction bugs during Sprint 4 that would otherwise have reached production.

**Encoding problems are invisible until they are not.** The Windows-1252 to UTF-8 corruption in supervisor names was undetectable in a development environment that only used manually typed ASCII data. Introducing real CSV data exposed the problem immediately. This reinforced the value of using realistic test fixtures that mirror production input formats.

**Data curation requires tooling, not patches.** The 24 unmatched supervisor names cannot be auto-resolved without risk of wrong assignment. Building coordinator-facing tools (the Unlinked Supervisors panel, the backfill command) to handle this systematically — rather than writing one-off scripts — produces a maintainable, auditable process that the coordinator can repeat for future semester imports.

**Security belongs in the server layer, not the UI.** Several early implementations hid UI elements based on user role without enforcing the same check in the server-side Livewire action. A determined user could invoke the action directly. Enforcing `abort_unless(Auth::user()?->role === 'coordinator', 403)` at the action level, independently of the UI, ensures the security guarantee holds regardless of how the request arrives.

---

## 10. Next Steps

| Priority | Action | Owner |
|---|---|---|
| 1 | Provision supervisor accounts for 24 unmatched names via Create Supervisor modal | FYP Coordinator |
| 2 | Re-run `php artisan fyp:link-supervisors` after provisioning | Developer / Coordinator |
| 3 | Use Unlinked Supervisors panel to map any residual non-exact names | FYP Coordinator |
| 4 | Verify null `supervisor_id` count = 0 (Phase 5 trigger) | Developer |
| 5 | Remove dual-read name fallback (Phase 5 code change) | Developer |
| 6 | Begin Sprint 5 — Role-Based Dashboards | Developer |

---

*Submitted for university supervisor review. Application source: private GitHub repository. Deployment: Railway (PHP 8.4 / SQLite). All code changes are covered by automated tests.*
