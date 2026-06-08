UX Review — Pair Display Table (3 personas)

Persona lenses

👔 FYP Coordinator — "I manage all 77 pairs. I need to trust the data and spot incomplete pairs fast."
The partner's row is blank across every project column, so Emily Wong looks like she has no project, no supervisor, no domain — when she actually shares Letchumi's project. At a glance I can't distinguish "shares the project above" from "missing data." I also can't see which pairs are incomplete (missing a partner or supervisor). And the import banner says "completed successfully" while showing Imported: 0 / Skipped: 148 — that directly undermines my trust in the numbers feeding "Total Pairs."

🧑‍🏫 Supervisor — "I only care about my pairs."
On a cohort-wide table I have to hunt down my name in a mid-table SUPERVISOR column; there's no "show only mine." When I find my pair, the blank partner row makes me unsure both students are actually mine. I think project-first: I want the project title + my two students together, not two near-identical rows where the second is empty.

🎨 UX Designer — "The pattern itself is the bug."
This is a rowspan/merged-cell table that uses blank cells to mean shared value. Empty cells are the universal signal for missing, so the design overloads one signal with two opposite meanings. The fix is to make the project the primary object and nest students inside it, show shared metadata once (labelled, not blank), and repair the hierarchy.

Findings & severity

#: F1
Finding: Blank partner row reads as "missing data," not "shared project."

#: F1
Finding: Blank partner row reads as "missing data," not "shared project."
  The merged-cell pattern conflates shared with empty.
Lens: All
Severity: 🔴 High
────────────────────────────────────────
#: F2
Finding: No "shared project" affordance / ownership is ambiguous — is it
  Letchumi's project Emily joined, or jointly owned? Nothing states the
  pair share one project.
Lens: Coord/Sup
Severity: 🟠 Medium
────────────────────────────────────────
#: F3
Finding: Weak pair grouping — a pair is signalled only by a faint indigo
  number + a thin divider. No container, banding, or bracket; pair
  boundaries are easy to lose while scrolling 149 rows.
Lens: All
Severity: 🟠 Medium
────────────────────────────────────────
#: F4
Finding: Inverted hierarchy: Student ID is bolder than Student Name. The
  12-digit ID is the heaviest element in the row; the name (the thing
  humans scan by) is lighter.
Lens: UX
Severity: 🟠 Medium
────────────────────────────────────────
#: F5
Finding: Inconsistent value styling. DOMAIN is a pill ("Business"), TYPE is
   italic grey ("Regular" — reads as disabled/placeholder), everything else
   is plain text. No system.
Lens: UX
Severity: 🟠 Medium
────────────────────────────────────────
#: F6
Finding: Over-dense, heavy wrapping. 9 columns force
  title/supervisor/assessor to wrap to 2–3 lines, producing tall rows + a
  "Swiss-cheese" of blanks → slow to scan.
Lens: All
Severity: 🟠 Medium
────────────────────────────────────────
#: F7
Finding: Poor supervisor scannability. Supervisor is a mid-table column
  with no group/sort/"mine" scoping. On the true My Students page the
  Supervisor column is redundant and should be dropped.
Lens: Sup
Severity: 🟠 Medium
────────────────────────────────────────
#: F8
Finding: IDs aren't tabular/aligned — long numeric IDs without tabular
  figures are hard to compare down the column.
Lens: UX
Severity: 🟡 Low
────────────────────────────────────────
#: F9
Finding: PAIR column is over-wide for a single digit, stealing width the
  wrapping columns badly need.
Lens: UX
Severity: 🟡 Low
────────────────────────────────────────
#: F10
Finding: Contradictory import feedback — "Import completed successfully"
  with Imported 0 / Skipped 148, crowding the toolbar. (Outside the
  pair-display focus, but a Coordinator's #1 trust issue on this screen.)
Lens: Coord
Severity: 🔴 High
────────────────────────────────────────
#: F11
Finding: Headers not obviously sortable — for 149 rows, sort by
  supervisor/domain/status is essential.
Lens: Coord
Severity: 🟡 Low

Recommended UI redesign

Option B (recommended) — project-centric grouped cards. Make the project the unit; nest the 1–2 students. Shared metadata shows once, labelled. Blanks disappear because there's nothing to leave blank.

┌─────────────────────────────────────────────────────────────────────────┐
│ PAIR 1   Development of Mobile App                      ● Complete  ▾     │
│          ──────────────────────────────────────────────────────────────  │
│          👤 Letchumi A/P Rajoo      522123161669   (lead)                 │
│          👤 Emily Wong Siew Mei     522120013514                          │
│          ──────────────────────────────────────────────────────────────  │
│          Supervisor  Dr. Syarifah Bahiyah Rahayu                          │
│          Assessor    Ts. Dell Cole II                                     │
│          [ Business ]  [ Mobile App ]  [ Regular ]                        │
└─────────────────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────────────────┐
│ PAIR 2   Development of …                            ⚠ Missing partner ▾ │
│          👤 Muhammad …               522…            (lead)               │
│          Supervisor  Ts. Azman …                                          │
│          [ AI ]  [ Web App ]  [ IFYP ]                                    │
└─────────────────────────────────────────────────────────────────────────┘

Why it fixes the findings:
- F1/F2 — both students sit visibly under one project; "(lead)" marks ownership; no blank cells.status badge (● Complete / ⚠ Missing partner / ⚠ No supervisor) gives the coordinator the at-a-glance completeness they lack (color-not-only: icon + text, not colour alone).
- F4 — name is the primary line (medium weight); ID is secondary/tabular grey.
- F5 — domain/platform/type become one consistent chip row; "Regular/IFYP" is a real chip, not greyed italic.
- F6 — collapsing the 1–2 students + shared fields into a card removes the wrapping/blank sprawl; optional ▾ expands assessor/extra detail (progressive-disclosure).

Option A (lighter touch) — keep the table, fix the pattern. If you want to preserve the grid:

PAIR │ STUDENTS                          │ PROJECT / SUPERVISOR              │ TAGS                    │ STATUS
─────┼───────────────────────────────────┼──────────────────────────────────┼─────────────────────────┼──────────
 ▎1  │ Letchumi A/P Rajoo  ·522123161669 │ Development of Mobile App         │ [Business][Mobile][Reg] │ ● Complete
 ▎   │ Emily Wong Siew Mei ·522120013514 │ Dr. Syarifah Bahiyah Rahayu       │                         │

Key moves: a left accent bar (▎) spanning the pair, both students stacked in one "Students" cell (no blank rows), shared project/supervisor merged into one column, a chips column, a status column, name > ID weight, number-tabular IDs, sortable headers, and move the import result into a dismissible banner/toast above the table (success-feedback should only say "successful" when rows actually imported; otherwise show error-clarity: "0 of 149 imported — 148 skipped (duplicate IDs). View details").

Both options need no DB/schema change — "Pair View" already groups by pair_number and the importer already fills the lead's metadata down to the partner, so the grouped data exists; this is a presentation-layer change.


Work item: Option A (table re-pattern: stacked students, accent bar, chips,
   status, weight/tabular fixes)
Complexity: Low–Medium
Effort: ~0.5–1 day
Notes: Blade + Tailwind + Flux badges only; reuse existing pair grouping.
  Pure CSS/markup.
────────────────────────────────────────
Work item: Option B (project-centric cards)
Complexity: Medium
Effort: ~1–2 days
Notes: Restructure the Pair-View partial into grouped cards; add
  pair-status computed (complete / missing partner / no supervisor);
  responsive stacking. No schema change.
Notes: Restructure the Pair-View partial into grouped cards; add
  pair-status computed (complete / missing partner / no supervisor);
  responsive stacking. No schema change.
────────────────────────────────────────
Work item: Pair-completeness badge logic
Complexity: Low
Effort: ~1–2 hrs
Notes: Derive from row count per pair_number + non-null supervisor.
────────────────────────────────────────
Work item: F7 supervisor scoping ("My Students" = drop Supervisor col +
  pre-filter to Auth::user())
Complexity: Low
Effort: ~1–2 hrs
Notes: Ties into roadmap Step 3 (supervisor-identity FK) — do it there for
  a reliable join.
────────────────────────────────────────
Work item: F10 import-feedback fix (honest message + relocate)
Complexity: Low
Effort: ~2–3 hrs
Notes: Logic/microcopy change; the "0 imported / 148 skipped" itself is a
  separate data bug worth investigating.
────────────────────────────────────────
Work item: F11 sortable headers
Complexity: Low–Medium
Effort: ~0.5 day
Notes: Livewire sortBy/sortDir + aria-sort; valuable at 149 rows.

Priority order: F1 (🔴, via Option A or B) → F10 (🔴 coordinator trust) → F3/F4/F5/F6 (bundled into the same redesign) → F7 (fold into Step 3) → F8/F9/F11 (polish).

My recommendation: Option B — it's the only one that makes project ownership unambiguous for the supervisor persona, and it's still presentation-only (Medium effort, no migration). Want me to turn this into a formal design spec / implementation plan when you're ready to build it?