# Analytics Audit Report — FYP Dashboard System

| | |
|---|---|
| **Report title** | Analytics Validation Audit — Dashboard KPIs, Charts & Role Filtering |
| **System** | FYP Dashboard System (UniKL MIIT) |
| **Programme / cohort** | BSE — FYP 1, Semester **MARCH 2026** |
| **Prepared by** | Amir Umar Danial |
| **Date** | 7 June 2026 |
| **Sprint context** | Analytics Validation Sprint (pre–Sprint 3) |
| **Scope** | KPI cards, charts/graphs, supervisor load, semester filtering, role-based filtering |
| **Status** | Read-only audit — no application code was modified |

---

## Executive Summary

The dashboard analytics were audited to determine whether they are **genuinely driven by database data** or rely on placeholder/demo logic. Every analytics-bearing file was inspected, and each claim was cross-checked against the **live database** using read-only queries.

**Headline finding:** the analytics are **genuinely database-driven** — there are no hardcoded or fabricated data arrays anywhere in the KPI cards or charts. The route- and middleware-level **role-based access control is correct**. However, the audit identified **three real correctness defects** and several risks that must be addressed before the analytics can be trusted for reporting.

The three most important issues are:

1. **"Total Pairs" actually counts individual students, not pairs** (shows 142 instead of 77) — and the same flaw affects "Avg Pairs / Supervisor" and the Supervisor Workload chart.
2. **Supervisor identity is matched by raw name string**, which is fragile against the encoding/prefix inconsistencies already documented in the source CSV — real supervisors may see zero students.
3. **The dashboard serves identical whole-cohort analytics to every role**, including students, with no data-level scoping.

A secondary but visible issue is that the **Domain, Platform, and Industrial charts currently render near-empty** — not because of a code fault, but because the imported March 2026 CSV contained no DOMAIN/PLATFORM/TYPE columns, so all records defaulted to a single category.

### Live database state at time of audit

Confirmed via read-only `php artisan tinker` queries against the imported data:

| Metric | Value |
|---|---|
| `MARCH 2026` records | **142** (all `FYP 1`) |
| `MARCH 2026` `FYP 2` records | 0 |
| `OCTOBER 2025` records (previous semester) | **0** |
| Distinct `pair_number` (groups) | **77** |
| Distinct `supervisor_name` strings | 25 |
| Industrial (`is_ifyp = true`) | 0 |
| Distinct `domain` values | 1 (all "Others") |
| Distinct `application_type` values | 1 |

These numbers ground every finding in this report.

---

## 1. Working Components

The following are implemented correctly and require no remedial work in this sprint.

| Area | File | Verdict |
|---|---|---|
| KPI card counts | `resources/views/dashboard.blade.php` | Live `FypProject` count queries; arithmetically correct for the selected semester. "Total SE Students" label is accurate (1 row = 1 student). |
| Charts data source | `resources/views/livewire/analytics-charts.blade.php` | All six computed properties query the database in real time. **No demo/hardcoded arrays.** Charts redraw from real results. |
| Semester / phase filter | `analytics-charts.blade.php` | `wire:model.live` correctly re-queries and re-renders the charts on change. |
| Route & middleware RBAC | `routes/web.php`, `app/Http/Middleware/EnsureUserHasRole.php` | `role:` middleware aborts with 403; `admin.users` performs a second server-side role check; access control is sound. |
| Role-aware navigation | `resources/views/layouts/app/sidebar.blade.php` | Sidebar items are correctly gated per role (Manage Users / My Students / My Logbook). |
| Student logbook scoping | `resources/views/livewire/my-logbook.blade.php` | Scoped by `user_id = Auth::id()`; students see only their own entries. |
| Supervisor logbook gating | `resources/views/pages/supervisor-student-logbook.blade.php` | `firstOrFail` prevents access to students not belonging to the supervisor. |

**Summary:** the analytics architecture is fundamentally sound and data-driven. The defects below are correctness and scoping issues layered on top of a working foundation — not a rewrite.

---

## 2. Identified Defects

Severity legend: 🔴 High (incorrect/broken on real data) · 🟠 Medium · 🟡 Low.

### 2.1 🔴 "Pairs" metrics count students, not pairs
**File:** `resources/views/livewire/analytics-charts.blade.php`

`summaryStats['total']` is computed as `$projects->count()` — the number of **student records (142)** — but is displayed on a card titled **"Total Pairs"**. The actual number of pairs/groups is **77** (distinct `pair_number`).

The same root cause affects:
- **"Avg Pairs / Supervisor"** — divides total *students* by supervisor count, not pairs.
- **"Supervisor Workload Distribution"** chart — the dataset is labelled `Pairs` but plots *student counts* per supervisor.

**Impact:** the headline cohort figure shown to the coordinator is roughly double the true pair count. Any report built on this number will be wrong.

### 2.2 🔴 Supervisor identity matched by raw name string
**Files:** `resources/views/livewire/my-students.blade.php`, `resources/views/pages/supervisor-student-logbook.blade.php`, `analytics-charts.blade.php` (`supervisorWorkload`)

Supervisor → student linkage relies on an **exact string match** between `users.name` and `fyp_projects.supervisor_name`:

```php
FypProject::where('supervisor_name', Auth::user()->name)
```

The CSV Data Quality Report already documented that supervisor strings in the source contain academic prefixes (`Ts.`, `Dr.`) and mojibake (`–` rendered as `�`). Because the match is byte-for-byte:

- A real supervisor logging in may see **zero assigned students**.
- The workload chart counts **25 distinct name strings**, which may not equal 25 distinct people (the same supervisor can split across encoding variants).

**Impact:** the supervisor role's primary screens can silently show no data on real imports.

### 2.3 🟠 No data-level role scoping on the dashboard
**Files:** `routes/web.php` (single `dashboard` route), `resources/views/dashboard.blade.php`, `analytics-charts.blade.php`

A single `dashboard` route renders the **same whole-cohort KPIs and analytics to all three roles**. While *navigation* and *route access* are role-aware, the *data shown* is not: a **student** sees full-cohort enrollment totals, domain/platform breakdowns, and every supervisor's workload.

**Impact:** potential information-scope concern, and the analytics are not tailored to the supervisor/student perspective.

### 2.4 🟠 Category charts render near-empty on real data
**File:** `analytics-charts.blade.php` (`domainStats`, `platformStats`, `ifypStats`)

On the current import: Domain = 1 bar ("Others" ×142), Platform = 1 slice, Industrial = 0. The charts **work correctly** — but the imported CSV lacked DOMAIN/PLATFORM/TYPE columns, so all records defaulted to one category.

**Impact:** the analytics *look* like unfinished placeholders to a viewer, despite being correct. This is a **data** gap, not a code defect, and is cross-referenced in the CSV Data Quality Report.

### 2.5 🟡 Hardcoded semester with no single source of truth
**Files:** `dashboard.blade.php` (two literals), `analytics-charts.blade.php` (dropdown options)

`'MARCH 2026'` / `'OCTOBER 2025'` appear as string literals in **three** locations. There is no shared configuration.

**Impact:** rolling to the next intake requires editing multiple files; drift between the KPI cards and the analytics filter is likely.

### 2.6 🟡 KPI cards ignore the analytics filter
**Files:** `dashboard.blade.php`, `analytics-charts.blade.php`

The top KPI cards are pinned to `MARCH 2026`, while the semester/phase dropdown lives only in the analytics component below. Changing the dropdown updates the charts but **not** the cards, desyncing the page.

### 2.7 🟡 Empty previous-semester column
**File:** `dashboard.blade.php`

With no `OCTOBER 2025` rows in the database, every "Previous" KPI value renders **0**, and FYP 2 = 0. Two-thirds of the card area currently shows zeros, which reads as broken.

---

## 3. Risks

| # | Risk | Severity | Notes |
|---|------|----------|-------|
| R1 | Coordinator reports cite the wrong cohort size | 🔴 High | "Total Pairs" = 142 vs true 77; flows into avg-per-supervisor. |
| R2 | Supervisors see zero students on real data | 🔴 High | Brittle name-string join (§2.2); blocks a whole role. |
| R3 | No `supervisor_id` foreign key | 🟠 Medium | All supervisor linkage hinges on an unstable string; no referential integrity. |
| R4 | Students view whole-cohort analytics | 🟠 Medium | No data-level scoping (§2.3); decide intended visibility. |
| R5 | In-PHP aggregation over full table loads | 🟠 Medium | Each computed runs `->get()` then filters in PHP (~6 full-table loads per render). Fine at 142 rows; technical debt at scale. |
| R6 | Zero automated test coverage for analytics | 🟠 Medium | No test asserts any computed value; regressions would go unnoticed. |
| R7 | Semester drift / manual rollover | 🟡 Low | Hardcoded literals in 3 places (§2.5). |
| R8 | Chart.js loaded from public CDN | 🟡 Low | Offline/CSP fragility; consider self-hosting. |
| R9 | Degenerate category charts mislead reviewers | 🟡 Low | Caused by missing CSV columns (§2.4), not code. |

---

## 4. Recommended Sprint 3 Roadmap — "Analytics Correctness & Trust"

**Goal:** make every number on the dashboard provably correct, role-appropriate, and test-covered.

**Method:** Test-Driven Development throughout (red → green), one concern per step, run `./vendor/bin/pest` after each step. No assertion-weakening; no removal of existing tests.

| Step | Title | Outcome | Priority |
|------|-------|---------|----------|
| 1 | **Lock current behavior** | New `tests/Feature/AnalyticsChartsTest.php` asserts each computed (domain/platform/ifyp/pairs/workload) against a known fixture. These tests intentionally expose the pairs bug first (RED). | 🔴 |
| 2 | **Fix pairs counting** | `summaryStats['total']` → distinct `pair_number`; `avg_per_supervisor` → pairs ÷ supervisors; correct the Supervisor Workload dataset label. Green the Step-1 tests. Expect **77** on the real import. | 🔴 |
| 3 | **Supervisor identity** | Add nullable `supervisor_id` FK to `fyp_projects`; backfill via name→user mapping; switch `my-students`, supervisor logbook, and `supervisorWorkload` to the FK. Tests: "supervisor sees only their students" on messy names. | 🔴 |
| 4 | **Semester source of truth** | Introduce `config('fyp.current_semester')` (or a settings row); refactor dashboard + analytics to read it; build the dropdown from `distinct semester`. Test rollover. | 🟠 |
| 5 | **Dashboard role scoping** | Per the decision in §2.3: gate dashboard analytics to coordinator, or add role-scoped variants. Tests per role (coordinator / supervisor / student). | 🟠 |
| 6 | **Performance & cleanup** | Convert computeds to SQL aggregation (`selectRaw` / `groupBy` / `count`); de-duplicate the `updatedSemester` / `updatedPhase` dispatch payload; consider self-hosting Chart.js. | 🟡 |

**Exit criteria**
- "Total Pairs" reads **77** on the real March 2026 import.
- Supervisors see their real assigned students (verified by test on prefixed/mojibake names).
- Each role's dashboard visibility is asserted by automated tests.
- `./vendor/bin/pest` is green with the new analytics test suite in place.

**Open decisions for the supervisor/data owner**
1. Should **students** see cohort-wide analytics at all, or a scoped view? (§2.3)
2. Should "Total Pairs" be **corrected to count pairs (77)** or **relabelled "Total Students" (142)**? The cohort is 142 students across 77 groups — which figure should headline the dashboard?
3. Re-import once the source CSV includes **DOMAIN / PLATFORM / TYPE** so the category charts become meaningful (see CSV Data Quality Report §5).

---

## Sign-off

- The dashboard analytics were confirmed **database-driven**, not placeholder logic.
- Route- and middleware-level role-based access control was confirmed **correct**.
- Three high/medium correctness defects were identified, with a prioritised remediation roadmap.
- No application code was modified during this audit.

*Prepared for internship supervisor review — FYP Dashboard System, UniKL MIIT.*
