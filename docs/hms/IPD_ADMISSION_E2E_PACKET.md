# IPD Admission - Acceptance Packet (2026-09-16)

## Goal
Replace placeholder IPD UI with a live bed board + admit flow against existing hospital_db wards/rooms/beds/admissions/bed_assignments.

## Current state
- IPDManagement.vue: placeholder copy only ("coming soon")
- App.vue pages map: income / opd / pharmacy only — ipd not wired
- DB tables exist, all empty (0 rows): wards, rooms, beds, bed_assignments, admissions
- Schema (live): ward → room → bed; admission requires patient_id + encounter_id; bed_assignments link admission↔bed
- No IPD API / models / tests

## Branch
- Work ONLY on feature/ipd-admission (from feature/billing-collection @ 0ce57b5)
- Do NOT commit onto feature/sidebar-v1, feature/opd-e2e, or feature/billing-collection
- No git add -A (dirty untracked docs/nav on disk — leave them alone)

## Must ship (atomic)
### Seed
1. Hospital-scoped seeder: at least 1 ward, 1 room, several beds (mix available)
2. Synthetic only — no real PHI
3. Document seed command in docs/hms/IPD_SEED.md (short)

### API (api.auth)
1. GET /api/ipd/wards — list wards for hospital
2. GET /api/ipd/beds — bed board (ward/room/bed + status; optional ward_id filter)
3. GET /api/ipd/admissions — list active admissions (hospital-scoped)
4. GET /api/ipd/admissions/{id} — show admission + active bed assignment
5. POST /api/ipd/admissions — admit:
   - Inputs: patient_id, bed_id, admitting_doctor_id (optional attending), admission_type, reason optional, admitted_at optional
   - Create IPD encounters row (encounter_type ipd/inpatient) then admissions (required encounter_id FK) + bed_assignments (active)
   - Mark bed occupied (or equivalent status already used in schema)
   - Reject: unauth 401; unknown/other-hospital patient or bed 404; bed not available 422; double-book same bed 422
6. Use existing UuidBin + hospital_id scoping patterns from Billing/OPD (Hospital::first() residual OK if filtered)

### UI
1. Replace placeholder: evolve IPDManagement.vue OR add IpdAdmission.vue — bed board + Admit modal (patient, bed, doctor)
2. Wire ipd into App.vue pages map (and nav entry if needed) so IPD opens the real view — same lesson as OPD wire
3. Reuse existing GET /api/patients and GET /api/doctors for pickers

### Tests
- Feature: list beds/admissions scoped; admit happy path; bed occupied after admit; double-book 422; cross-hospital 404; unauth 401
- No PHI in fixtures

### Docs
- This packet + short IPD_SEED.md only
- Update FEATURE_MAP_V0.md: ipd → live if that file is already on the branch; otherwise note in PR body

## Out of scope
- IPD billing (interim/provisional/final)
- TPA / AL / insurance / consent prints
- Nursing / Digital IPD / pharmacy indent
- Discharge + discharge summary
- Bed transfer history UI beyond single assign
- migrate:fresh / ALTER live hospital tables
- GST / multi-pay deposit at admit

## Done when
Verifier: bed board shows DB beds; admit creates admission+assignment and updates bed status; tests green; App.vue actually opens IPD (not placeholder).

## Evidence anchors
- Base tip: 0ce57b5
- Live DDL confirmed 2026-09-16: wards, rooms, beds, admissions, bed_assignments
- Counts before seed: all 0