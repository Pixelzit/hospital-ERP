# Nursing - Acceptance Packet (2026-09-16)

## Goal
Replace the Nursing sidebar placeholder with a live station: active IPD admissions, vitals charting, and nursing notes against existing hospital_db tables.

## Current state
- App.vue nursing key is placeholderPage ("coming soon")
- No nursing API / models / Vue
- Tables exist, empty: nursing_notes (0), nursing_observations (0), vitals (0)
- Live admissions: 2 (from IPD admit)
- vitals: patient_id + encounter_id + recorded_by (users.id) + T/P/RR/BP/SpO2/etc
- nursing_notes: admission_id + encounter_id + patient_id + nurse_id (users.id) + note
- IPD admit already creates encounter then admission — reuse that link; no ALTER

## Branch
- Work ONLY on feature/nursing (from feature/ipd-billing-toast-fix @ ea60c33)
- Do NOT commit onto closed tips (ipd-billing, toast-fix, sidebar-placeholders, ipd-admission, billing-collection, opd-e2e, sidebar-v1)
- No git add -A

## Must ship (atomic)
### API (api.auth)
1. GET /api/nursing/admissions — active admissions for hospital (ward/bed/patient summary OK; reuse IPD patterns)
2. GET /api/nursing/admissions/{id}/vitals — list vitals for that admission's encounter (newest first)
3. POST /api/nursing/admissions/{id}/vitals — record vitals (subset of columns OK: temperature, pulse, respiratory_rate, systolic_bp, diastolic_bp, spo2, pain_score); recorded_by = auth user; recorded_at now if omitted
4. GET /api/nursing/admissions/{id}/notes — list nursing_notes
5. POST /api/nursing/admissions/{id}/notes — create note; nurse_id = auth user; copy admission/encounter/patient ids
6. Reject: unauth 401; unknown/other-hospital admission 404; empty note 422; vitals with no measurable fields 422
7. UuidBin + hospital_id scope (Hospital::first residual OK if filtered)

### UI
1. Replace nursing placeholder: new Nursing.vue (or NursingStation.vue)
2. Wire App.vue pages.nursing to the real component (not placeholderPage)
3. Station: list active admissions; select one; vitals table + record form; notes list + add note
4. No PHI in fixtures/screenshots for verify

### Tests
- Feature NursingStationTest: list admissions; post vitals+notes happy path; 401; 404 cross-hospital; 422 empty note
- Synthetic only

### Docs
- This packet only; FEATURE_MAP nursing → live if file is on branch

## Out of scope
- Digital IPD full chart, MAR / medication admin, pharmacy indent
- Generic nursing_observations / observations / clinical_notes tables (use vitals + nursing_notes only)
- Care plans, handover, intake/output specialized forms
- Nurse role RBAC (any authenticated user may chart in v1)
- Discharge, IPD billing, migrate:fresh / ALTER

## Done when
Verifier: Nursing sidebar opens real station; can chart vitals and a note on a live admission; tests green; IPD bed board still works.

## Evidence anchors
- Base tip: ea60c33
- Closed IPD admit 010c867; IPD billing 57a9e64; toast ea60c33