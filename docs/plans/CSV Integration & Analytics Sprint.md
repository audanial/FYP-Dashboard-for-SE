# FYP Dashboard — CSV Integration & Analytics Sprint

**Project:** FYP Dashboard System (SE Department)
**Sprint Duration:** 5 June 2026 – 7 June 2026
**Owner:** Amir Umar Danial
**Goal:** Replace hard-coded dashboard assumptions with real CSV-driven data and ensure analytics reflect imported records.

---

# Sprint Objectives

## Primary Goal

Make the system work with the supervisor's real CSV format.

## Success Criteria

* [ ] Real CSV imports successfully
* [ ] Domain analytics use imported data
* [ ] Platform analytics use imported data
* [ ] Industrial analytics use imported data
* [ ] Pair and Solo students are clearly visible
* [ ] Dashboard metrics are database-driven
* [ ] No hard-coded analytics remain

---

# Current Context

Supervisor Feedback (4 June 2026)

### Requirements

* CSV import must work with the supervisor's actual CSV
* Analytics must not be hard-coded
* Pair and Solo students must be distinguishable in one view
* Domain, Platform, and Industrial analytics must remain
* Missing analytics fields will be added to the CSV before import

### CSV Structure

Current CSV:

* Group
* No.
* Student ID
* Student Name
* FYP Title
* Contact No.
* Supervisor
* Assessor
* Domain
* Platform
* Type

Additional Context:

* Semester = March 2026
* Phase = FYP 1
* One Coordinator only

---

# Friday — 5 June 2026

## Sprint 1: Discovery & Test Locking

### Goal

Understand the CSV completely and lock expected behaviour with tests before implementation.

---

### 9:00 AM – 9:30 AM

#### Resume Session

```bash
claude --resume f74b1747-b87e-42b7-ab2d-420ebd5f9777
```

Review:

* Supervisor requirements
* CSV analysis
* Import audit findings

---

### 9:30 AM – 10:30 AM

#### CSV Validation Session

Tasks:

* Verify Group format
* Verify Pair structure
* Verify Solo structure
* Verify Domain values
* Verify Platform values
* Verify Type values

Output:

* Approved CSV Format v1

---

### 10:30 AM – 11:00 AM

#### Documentation

Record:

* CSV decisions
* Analytics decisions
* Import assumptions

---

### 11:00 AM – 1:00 PM

#### Task A — Test Creation

Claude Code:

* Create CSV fixture
* Create failing tests
* Stop after tests

Do NOT modify import logic.

---

### 2:00 PM – 4:00 PM

#### Test Review

Review:

* Failed tests
* Missing mappings
* Expected behaviour

---

### 4:00 PM – 5:00 PM

#### Git Checkpoint

```bash
git add .
git commit -m "Add tests for supervisor CSV format"
```

---

### Friday Deliverables

* [ ] CSV format finalized
* [ ] Test coverage added
* [ ] Implementation plan validated

---

# Saturday — 6 June 2026

## Sprint 2: Import Pipeline Sprint

### Goal

Make the supervisor CSV import successfully into the database.

---

### 9:00 AM – 10:00 AM

#### Planning Review

Review implementation order.

Focus only on import mapping.

---

### 10:00 AM – 12:00 PM

#### Implementation Block 1

Implement:

* Group → pair_number
* FYP Title → title
* Domain → domain

---

### 12:00 PM – 1:00 PM

#### Lunch Break

---

### 2:00 PM – 4:00 PM

#### Implementation Block 2

Implement:

* Platform → application_type
* Type → is_ifyp
* normalizeIfyp()

---

### 4:00 PM – 5:00 PM

#### Import Verification

Import:

* March 2026 FYP1 CSV

Verify:

* Student records
* Supervisor records
* Assessor records
* Pair numbers
* Domain values
* Platform values
* Industrial values

---

### 8:00 PM – 9:00 PM

#### Audit Session

Ask Claude Code:

* Audit imported records
* Highlight mismatches

---

### Git Checkpoint

```bash
git add .
git commit -m "Support March 2026 FYP1 CSV import"
```

---

### Saturday Deliverables

* [ ] CSV imports successfully
* [ ] Database populated correctly
* [ ] Import mappings complete

---

# Sunday — 7 June 2026

## Sprint 3: Analytics & Pair Visibility Sprint

### Goal

Make dashboard analytics reflect imported data.

---

### 9:00 AM – 10:00 AM

#### Analytics Audit

Review:

* Dashboard cards
* Analytics charts
* Data sources

Identify remaining hard-coded values.

---

### 10:00 AM – 12:00 PM

#### Analytics Implementation

Verify:

* Total Students
* FYP1 Count
* FYP2 Count
* Supervisor Workload

All values must come from database queries.

---

### 12:00 PM – 1:00 PM

#### Lunch Break

---

### 2:00 PM – 4:00 PM

#### Chart Verification

Verify:

* Projects by Domain
* Projects by Platform
* Regular vs Industrial

All charts must use imported records.

---

### 4:00 PM – 5:00 PM

#### Pair / Solo Sprint

Requirement:

Coordinator can immediately identify:

* Pair projects
* Solo projects

Implementation:

* Pair badge
* Solo badge

Based on:

```text
pair_number
```

---

### 5:00 PM – 6:00 PM

#### Full Smoke Test

Coordinator:

* Import CSV
* View Dashboard
* View Analytics
* View Users

Supervisor:

* My Students
* Student Logbook

Student:

* Logbook CRUD

---

### 8:00 PM – 9:00 PM

#### Supervisor Simulation

Ask Claude Code:

"Act as my internship supervisor and review the project."

Review:

* Completed features
* Weak areas
* Remaining risks

---

### Git Checkpoint

```bash
git add .
git commit -m "Complete CSV analytics sprint"
```

---

# Sprint Backlog

## Must Complete

1. CSV Import
2. Domain Analytics
3. Platform Analytics
4. Industrial Analytics
5. Pair/Solo Visibility

## Should Complete

6. Dashboard Verification
7. Smoke Testing

## Nice To Have

8. UI Polish
9. Minor Refactoring
10. Documentation Cleanup

---

# Current Claude Session

```bash
claude --resume f74b1747-b87e-42b7-ab2d-420ebd5f9777
```

---

# End-of-Sprint Definition of Done

The sprint is considered complete when:

* Real supervisor CSV imports successfully
* Analytics are database-driven
* No hard-coded dashboard metrics remain
* Pair/Solo students are clearly visible
* Coordinator workflow is fully functional
* System is ready for supervisor review
