# IPD Billing - Acceptance Packet (2026-09-16)

## Goal
Add interim → provisional → final IPD invoice flow linked to admissions via encounter_id, on top of closed IPD admit + OPD billing collection.

## Current state
- Billing: GET/POST pay on /api/billing/invoices*; Income.vue collects payment (OPD-style list)
- IPD: wards/beds/admissions API + IPDManagement bed board/Admit; admit creates encounter then admission
- invoices: encounter_id (nullable), invoice_type, status, totals — NO admission_id column
- invoice_items / payments exist; no charge/package master tables
- Live counts (~2026-09-16): invoices 20, payments 15, admissions 2, encounters 39
- Billing.vue is a stub; Insurance nav points at it

## Branch
- Work ONLY on feature/ipd-billing (from feature/sidebar-placeholders @ 21d2837)
- Do NOT commit onto closed tips (sidebar-v1, opd-e2e, billing-collection, ipd-admission, sidebar-placeholders)
- No git add -A

## Stage model (existing columns only — no ALTER)
- invoice_type = IPD
- status lifecycle for IPD invoices: interim → provisional → final
- final invoices may still use existing payment API (issued/paid balance rules) once status=final (set issued_at when entering final)
- Link: invoices.encounter_id = admissions.encounter_id; same patient_id + hospital_id

## Must ship (atomic)
### API (api.auth)
1. GET /api/ipd/admissions/{id}/invoices — list IPD invoices for that admission (via encounter)
2. POST /api/ipd/admissions/{id}/invoices — create interim invoice with ≥1 invoice_items (synthetic/manual lines OK: description, qty, unit_price); reject if admission not found / other hospital 404
3. POST /api/ipd/invoices/{id}/advance — advance status interim→provisional→final only (422 on illegal jump or non-IPD); on final set issued_at if null
4. Reuse existing POST /api/billing/invoices/{id}/payments for collecting on final (or allow pay on provisional if balance>0 — prefer final-only pay for v1; document choice)
5. UuidBin + hospital_id scope (Hospital::first residual OK if filtered)

### UI
1. On IPDManagement: select/open an admission → Bill panel: list invoices, add charge lines + create interim, Advance stage button, Collect payment when final (call billing pay)
2. Do not break bed board / Admit
3. Keep Income.vue as general collection desk (optional filter invoice_type=IPD is nice-to-have, not required)

### Tests
- Feature IpdBillingTest: create interim linked to admission encounter; advance to provisional then final; illegal advance 422; cross-hospital 404; unauth 401; pay on final reduces balance
- Synthetic fixtures only — no PHI

### Docs
- This packet only; brief note in FEATURE_MAP if file already on branch (ipd billing live)

## Out of scope
- Rate masters / ward-class package engine / auto bed-day charges
- TPA / AL / GST rebuild
- Full Caresoft print suite (interim/provisional/final PDFs)
- Discharge billing close-out
- migrate:fresh / ALTER invoices
- Rewriting OPD billing or pharmacy

## Done when
Verifier: from IPD UI, create interim bill on a live admission, advance to final, collect payment; tests green; bed board still works.

## Evidence anchors
- Base tip: 21d2837
- Link path: admissions.encounter_id ↔ invoices.encounter_id
- Closed billing tip 0ce57b5; closed IPD tip 010c867