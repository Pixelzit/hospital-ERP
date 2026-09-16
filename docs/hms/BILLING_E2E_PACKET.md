# Billing & Collection — Acceptance Packet (2026-09-16)

## Goal
Replace mock Income page with real billing collection against hospital_db invoices/payments.

## Current state
- Income.vue: hardcoded fake totals (mock)
- DB: invoices (~20), invoice_items (~20), payments (~10); statuses issued/paid
- Existing read APIs only: GET patients/{id}/invoices/{invoiceId} (+ print/receipt) in PatientWorkspaceController
- No list/create/pay APIs for billing desk

## Branch
- Work ONLY on `feature/billing-collection` (from feature/opd-e2e @ OPD-closed tip)
- Do NOT commit billing onto feature/sidebar-v1 or feature/opd-e2e
- No `git add -A`

## Must ship (atomic)
### API (api.auth)
1. `GET /api/billing/invoices` — list with filters: date (issued_at day or created day), status, search (invoice_number / patient name/MRN), pagination
2. `GET /api/billing/invoices/{id}` — detail with items + payments (reuse workspace shaping where sensible)
3. `POST /api/billing/invoices/{id}/payments` — record payment (amount, payment_method); update amount_paid, balance, status (issued→paid when balance<=0); reject overpay / paid invoices with 422
4. Optional summary for Income cards: today collection, month collection, outstanding balance (real aggregates — not TPA claims yet)

### UI (Income.vue → Billing & Collection)
1. Remove mock Lakh/Crore numbers
2. Show invoice table from GET list (number, patient, total, paid, balance, status, date)
3. Collect payment action for open/issued balances
4. Wire view/print using existing print routes when possible
5. Ensure `income` page remains in App.vue pages map (already is)

### Tests
- Feature tests: list, pay reduces balance, illegal pay 422, unauth 401
- No PHI in fixtures (synthetic patients only)

## Out of scope
- GST/TPA claims automation, insurance module, IPD package billing rebuild, pharmacy POS billing
- migrate:fresh

## Done when
Verifier: Income shows DB invoices; payment updates balance; tests green; UI not mock.
