# OPD E2E Operations — Acceptance Packet (2026-09-16)

## Goal
Make OPD usable for live ops today: check in a patient, see them on today’s queue, move status through nurse → doctor → complete.

## Out of scope (this packet)
- Full clinical notes / Rx / lab order entry UI (patient workspace may already show existing data)
- IPD, billing rebuild, pharmacy changes
- migrate:fresh / dropping tables

## Must ship
### API (api.auth)
1. `POST /api/opd/visits` — check-in / start OPD encounter
   - Inputs: patient_id (required), optional appointment_id, doctor_id, department_id
   - Creates `encounters` row: encounter_type=OPD, status in {open|new|arrived} mapped to queue "new_patient", started_at=now, hospital_id set
   - Returns visit DTO compatible with GET /api/opd/visits
2. `PATCH /api/opd/visits/{id}/status` — lifecycle
   - Allowed: new_patient/open → nurse_seen → doctor_seen → visit_complete/closed
   - Reject illegal transitions with 422
3. Keep `GET /api/opd/visits?date=` working

### UI (OpdDashboard)
1. Check-in / Walk-in control (pick patient + optional doctor/dept) that calls POST
2. Per-row actions to advance status (or a clear status control)
3. Today’s date shows the new visit without changing date to August seed days

### Data / verify
1. Feature tests: check-in creates today’s OPD; status transitions; unauth 401; bad transition 422
2. Manual: login → OPD → check-in → see row today → advance to complete
3. Commit currently untracked OPD files (OpdController, OpdDashboard, opd/, tests) as part of this work — do not `git add -A` (avoid unrelated dirty WT)

## Done when
Verifier can claim: today queue non-empty after check-in; status lifecycle works; tests green.
