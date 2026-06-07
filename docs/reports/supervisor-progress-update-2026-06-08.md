# Supervisor Progress Update

| | |
|---|---|
| **Prepared by** | Amir Umar Danial |
| **Date** | 8 June 2026 |
| **Project** | FYP Dashboard System (UniKL MIIT) |
| **Period** | Weekend of 6–8 June 2026 |
| **Meeting** | Weekly supervisor check-in |

---

## 1. Work completed this weekend

- **CSV Import Hardening Sprint — completed.** The import pipeline now handles the real supervisor file format (forward-filled groups, lead-row metadata fill-down, studentless-row skip, duplicate handling).
- **Real March 2026 FYP1 CSV imported successfully** — **142 valid student records** loaded.
- **Data quality investigation — completed.** Traced exactly why the count was 142 instead of the expected 145.
- **CSV Data Quality Report — completed** (`docs/reports/csv-data-quality-report-march-2026.md`).
- **Analytics validation audit — completed.** Verified whether the dashboard is genuinely database-driven.
- **Analytics Audit Report — completed** (`docs/reports/analytics-audit-report.md`).

## 2. Key findings

- The **import pipeline is working correctly** — independently verified two ways (source parse + database audit), both agreeing at 142.
- The **dashboard analytics are genuinely database-driven** — no hardcoded or demo data.
- **Role-based access control is correct** at the route and middleware level.
- The **3-record shortfall (142 vs 145) is a source-data issue, not an import bug.**

## 3. Issues discovered

**In the source CSV (for the data owner to fix):**
- 1 missing student (Group 13 lead — blank ID & name).
- 2 genuine duplicate student IDs.
- A duplicated Groups 76–77 block; one truncated and one suspicious ID.
- Missing DOMAIN / PLATFORM / TYPE columns; legacy encoding (mojibake).

**In the dashboard analytics (for the next sprint):**
- "Total Pairs" actually counts **students (142)**, not pairs (**77**).
- Supervisor → student linkage uses a fragile **name-string match** — real supervisors may see zero students.
- Dashboard shows **whole-cohort analytics to every role**, including students.

## 4. Evidence the import pipeline is working correctly

- **142 imported = 142 importable** in the source file — exact match.
- Independent CSV re-parse and live database query returned **identical** counts.
- Skipped rows are fully accounted for: **1 blank ID + 6 duplicate IDs = 7**, leaving 142.
- No placeholder/blank-ID rows were inserted (**0** in database).
- Automated import tests pass; no regressions.

## 5. Current dashboard status

- **Database-driven and live:** KPI cards, charts, semester/phase filter, and access control all work against real data.
- **Accurate today:** Total SE Students = 142; 77 groups; 25 supervisor entries.
- **Known gaps:** "Pairs" metric mislabel; supervisor name-match fragility; category charts look near-empty because the source CSV lacked DOMAIN/PLATFORM/TYPE.
- **Verdict:** functional and trustworthy for headline counts; needs correctness fixes before it is report-grade.

## 6. Next Sprint — Analytics Correctness & Trust

**Goal:** make every dashboard number provably correct, role-appropriate, and test-covered, using test-driven development throughout.

## 7. Planned implementation tasks for this week

| Step | Task | Priority |
|------|------|----------|
| 1 | Add analytics test suite that locks current behavior and exposes the pairs bug | 🔴 |
| 2 | Fix "Pairs" metrics to count groups (expect **77**), not students | 🔴 |
| 3 | Add a stable supervisor link (`supervisor_id`) to replace the name-string match | 🔴 |
| 4 | Centralize "current semester" so KPI cards and filters stay in sync | 🟠 |
| 5 | Decide and apply role scoping for the dashboard (esp. students) | 🟠 |
| 6 | Move chart aggregation into SQL; minor cleanup | 🟡 |

**Open decisions for the supervisor:**
1. Should "Total Pairs" be corrected to **77 pairs** or relabelled **142 students**?
2. Should students see cohort-wide analytics, or a scoped view?
3. When can a corrected CSV (with DOMAIN/PLATFORM/TYPE) be re-imported to reach 145?

---

### 30-second verbal summary

> "The import sprint is done and the real March 2026 file is loaded — 142 students. We proved the importer is correct; the 3-record gap is a source-data issue, not a bug, and it's documented for correction. I also audited the analytics: they're genuinely database-driven and access control is solid, but I found a labelling bug ('pairs' is really counting students) and a fragile supervisor link. Next sprint fixes those with test coverage first."
