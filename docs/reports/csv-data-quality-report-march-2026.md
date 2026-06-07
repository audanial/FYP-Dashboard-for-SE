# CSV Data Quality Report — March 2026 FYP1 Name List

| | |
|---|---|
| **Report title** | Data Quality Assessment of the FYP1 Evaluation Name List |
| **Source file** | `Grp No_BSE FYP1 Evaluation Form March 2026(NAME LIST).csv` |
| **Programme / cohort** | BSE — FYP 1, Semester **MARCH 2026** |
| **Prepared by** | Amir Umar Danial |
| **Date** | 7 June 2026 |
| **System** | FYP Dashboard System (UniKL MIIT) |
| **Status** | Investigation only — no data was altered |

---

## Executive Summary

The supervisor's source CSV was imported into the FYP Dashboard and produced **142 valid student records**. The expected figure was **145** (9 solo projects + 68 paired projects).

The **3-record shortfall is caused entirely by data-quality issues in the source CSV**, not by a fault in the import system. The import pipeline was independently verified and is working correctly: it correctly skipped one row with no student identity and six rows carrying duplicate student IDs.

This report documents every defect found, the exact rows affected, and the corrections required from the data owner (supervisor) before a clean re-import can reach 145 records.

**Key numbers**

| Metric | Value |
|---|---|
| Records in CSV (data rows) | 149 |
| Rows containing a Student ID | 148 |
| Unique, importable students | **142** |
| Records actually imported (database) | **142** ✅ matches |
| Expected students | 145 |
| Shortfall to investigate | **3** |

---

## Verification Methodology

Findings were confirmed two independent ways and cross-checked for agreement:

1. **Source parse** — the CSV was read with the same multiline-safe parser the import uses (`fgetcsv`), so quoted titles spanning multiple lines are counted as single records.
2. **Database audit** — the imported `fyp_projects` table was queried directly (read-only).

Both methods returned identical results (142 importable / 142 imported), confirming the import system is faithful to the source data.

---

## 1. Missing Student Records

Three project groups ended with only **one** imported student instead of the expected two. Each corresponds to one missing record.

| # | Group | CSV row (No.) | File line | Student ID | Student Name | FYP Title | Cause |
|---|-------|---------------|-----------|-----------|--------------|-----------|-------|
| 1 | **13** | 16 | 18–19 | *(blank)* | *(blank)* | Phish Guard: AI-Based Phishing Email Detection System | Lead row has **no Student ID and no Name** |
| 2 | **42** | 75 | 79 | `52213225076` | ADAM HAKEEMI BIN MOHAMAD | *(shares "UniKL Book Hub" with partner)* | **Duplicate Student ID** (see §2) |
| 3 | **77** | 145 | 149 | `52213125648` | ARISSA BINTI AMAR AZ | *(shares "Echora…" with partner)* | **Duplicate Student ID** (see §2) |

**Reconciliation:** 9 solos + (65 complete pairs × 2 = 130) + (3 incomplete groups × 1 = 3) = **142**.

> Note on Group 13: the project title, supervisor, and assessor are present, but the student who owns the row was never entered. The system intentionally does **not** create a placeholder record with an empty Student ID, so this project currently has only its partner (No.17, MUHAMAD DANIEL ABQARI BIN BAKRI) attached.

---

## 2. Duplicate Student IDs

Six rows carry a Student ID that already appeared earlier in the file. The system keeps the **first** occurrence and skips later ones (the Student ID column is a unique key). Two are genuine integrity problems; four belong to a duplicated block (see §3).

### 2.1 Genuine ID collisions (different intent, same ID)

| Student ID | Kept (imported) | Skipped (lost) | Nature of problem |
|---|---|---|---|
| `52213225076` | No.65 **UTHSMAN BIN SHUHAIMI** — Group 37 (line 69) | No.75 **ADAM HAKEEMI BIN MOHAMAD** — Group 42 (line 79) | **Two different students share one ID.** One ID is mistyped; the second student cannot be imported until corrected. |
| `52213125648` | No.2 **ARISSA BINTI AMAR AZ** — Group 2 (line 4) | No.145 **ARISSA BINTI AMAR AZ** — Group 77 (line 149) | **Same student listed in two groups.** Needs a decision on which group is correct (see §3.1). |

### 2.2 Duplicate IDs from the repeated block (see §3)

| Student ID | First / kept | Duplicate / skipped |
|---|---|---|
| `52213118392` | No.142 AIMAN HANEEF BIN BORHAN (line 146) | line 150 |
| `52213123636` | No.143 MUHAMMAD SYAZWAN BIN HAIRI (line 147) | line 151 |
| `52213125855` | No.144 AHMAD NOR THAQIEF BIN MUHAMMAD KHATIB (line 148) | line 152 |
| `52213125648` | No.2 ARISSA (line 4) | line 153 |

---

## 3. Duplicate Group / Block Issues

### 3.1 Repeated Groups 76 & 77 block
File lines **150–153 are an exact duplicate of lines 146–149** — Groups 76 and 77 (rows No.142–145) appear twice. This is consistent with an accidental copy-paste when the file was assembled. These four rows added no new students (all duplicate IDs) but should be removed to avoid confusion.

### 3.2 "Echora" project duplicated across Group 2 and Group 77
The project **"Echora — A Therapeutic Soundscape System…"** appears in two places:

- **Group 2** — ARISSA BINTI AMAR AZ (No.2), listed as a solo.
- **Group 77** — AHMAD NOR THAQIEF BIN MUHAMMAD KHATIB (No.144) **+** ARISSA BINTI AMAR AZ (No.145).

This strongly suggests ARISSA and AHMAD NOR THAQIEF are intended to be a **pair on Echora (Group 77)**, and the Group 2 ARISSA row is a leftover/duplicate. Because the system keeps the first occurrence, ARISSA was imported under **Group 2 (solo)** and AHMAD NOR THAQIEF was left **alone in Group 77**. The data owner must confirm the correct arrangement.

---

## 4. Truncated or Suspicious IDs

These rows imported successfully (their IDs are non-blank and unique) but the ID values look incorrect and will cause downstream problems (e.g., student login, logbook linkage).

| CSV row (No.) | File line | Student Name | Student ID | Issue |
|---|---|---|---|---|
| 90 | 94 | SITI FARHANA YASMIN BINTI RAHMAT | `522132240` | **Only 9 digits** (valid IDs are 11). Appears truncated. |
| 89 | 93 | NUR AERENA BINTI ABDUL MUTALLID | `12213224017` | Starts with **`1`** while all other IDs start with `5`. Possible typo (length is valid). |

---

## 5. Encoding Issues

The file is not clean UTF-8 — several special characters (en-dashes, smart quotes, accented letters) were saved in a legacy encoding and now display as the replacement character `�` (mojibake). This is **cosmetic** (it does not affect record counts) but degrades the displayed names and titles.

Representative examples:

| Location | Stored text | Likely intended |
|---|---|---|
| Supervisor column (multiple rows, e.g. lines 27, 90, 92, 94, 120, 128, 130) | `Rohaya Abu Hassan � Ts.` | `Rohaya Abu Hassan – Ts.` |
| Title — No.18 (line 21) | `LearnHub � An AI-Guided…` | `LearnHub – An AI-Guided…` |
| Title — No.64 (line 68) | `�Heal� AI-Based Personal Health Assistant…` | `"Heal" AI-Based Personal Health Assistant…` |
| Title — No.108 (line 112) | `…Integrated Caf� Management Platform…` | `…Integrated Café Management Platform…` |

**Recommendation:** export/save the file as **UTF-8** from the source spreadsheet.

> Separately: the source file contains only **8 columns** (`Group, No., Student ID, STUDENT NAME, FYP TITLE, CONTACT NO., SUPERVISOR, ASSESSOR`). The analytics columns **DOMAIN, PLATFORM, and TYPE are absent**, so all imported projects defaulted (Domain = "Others", Platform = "Web App", Type = Regular). These columns must be added before domain/platform/industrial analytics will be meaningful.

---

## 6. Final Verified Import Counts

| Category | Count | Notes |
|---|---|---|
| Total CSV data records | **149** | After header; multiline titles collapsed |
| Rows with a Student ID | 148 | |
| — Blank-ID rows skipped | 1 | Group 13 lead (Phish Guard) |
| — Duplicate-ID rows skipped | 6 | §2 (2 genuine + 4 from repeated block) |
| **Unique students imported** | **142** | Confirmed in database |
| Distinct groups present | 77 | All group numbers represented |
| Solo groups (1–9) | 9 | Complete |
| Complete pairs (groups 10–77) | 65 | 130 students |
| Incomplete groups | 3 | Groups 13, 42, 77 — 1 student each |
| Records with blank Student ID in database | **0** | No placeholder rows inserted |

**Conclusion:** 142 is the correct count for the file **as supplied**. Reaching 145 requires corrections to the **source data**, listed below.

---

## 7. Recommended Corrections for the Supervisor

To be applied to the **source CSV** (the dashboard and import logic require no changes):

| Priority | Issue | Row(s) | Action required |
|---|---|---|---|
| 🔴 High | Missing student (Group 13 lead) | No.16 | Enter the **Student ID and Name** of the student who owns the "Phish Guard" project. |
| 🔴 High | Duplicate ID `52213225076` | No.65 & No.75 | Confirm the **correct, distinct ID** for ADAM HAKEEMI BIN MOHAMAD (Group 42); one ID is mistyped. |
| 🔴 High | ARISSA in two groups | No.2 & No.145 | Decide ARISSA's **correct group** (likely Group 77 with AHMAD NOR THAQIEF on Echora) and remove the duplicate listing. |
| 🟠 Medium | Repeated Groups 76 & 77 block | lines 150–153 | **Delete** the duplicated block. |
| 🟠 Medium | Truncated ID | No.90 | Correct `522132240` to the full **11-digit** ID for SITI FARHANA YASMIN. |
| 🟡 Low | Suspicious ID | No.89 | Verify `12213224017` for NUR AERENA (starts with `1`, not `5`). |
| 🟡 Low | Encoding (mojibake `�`) | multiple | Re-save the source file as **UTF-8**. |
| 🟡 Low | Missing analytics columns | header | Add **DOMAIN, PLATFORM, TYPE** columns so analytics are accurate. |

After the three **High** items are fixed, a re-import is expected to produce **145** student records.

---

## Sign-off

- The import pipeline was tested and verified correct; it is not the source of the shortfall.
- No source data was modified during this investigation.
- This report is the basis for the source-data corrections to be performed by the data owner.

*Prepared for internship supervisor review — FYP Dashboard System, UniKL MIIT.*
