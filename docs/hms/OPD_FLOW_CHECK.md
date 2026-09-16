# OPD flow check (2026-09-16)

## Smoke (session cookie auth on http://hcs.test)
- Login admin/admin: OK
- GET /api/me: OK
- GET /api/patients: OK (10)
- GET /api/opd/visits?date=today: OK, 0 rows
- GET /api/opd/visits?date=2026-08-23 (and nearby): OK, 1 closed row each
- GET /api/opd/visits unauthenticated: 401
- GET /api/appointments: OK (31)
- node --test tests/js/opd-queue.test.mjs: 3 passed

## What works
- Sidebar OPD → OpdDashboard
- Date filter + status/search/doctor/department filters (client-side)
- Queue reads encounters where encounter_type=OPD for selected date

## Gaps vs full OPD workflow (Caresoft-style)
- No API/UI to **start / check-in** an OPD visit today
- No status transitions (new → nurse → doctor → complete)
- All 20 OPD encounters in DB are **closed**; none open
- Default today view is empty (looks broken unless date is moved back to Aug 2026 seed days)
- OpdController / OpdDashboard still **untracked** on feature/sidebar-v1 locally (not in pharmacy commits)

## Verdict
OPD **read/queue path**: working.
OPD **end-to-end clinical flow**: incomplete / not ready for live ops.
