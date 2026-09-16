# Pharmacy POS - Acceptance Packet (2026-09-16)

## Goal
Add a pharmacy dispense/POS desk on top of the live medicines catalog: sell/dispense from medicine_batches, decrement stock, and record stock_movements.

## Current state
- Pharmacy.vue + PharmacyMedicineController: list/create medicines (closed @ 86358c2 lineage)
- medicines: 11 rows; medicine_batches / inventory / stock_movements / pharmacy_locations: all 0
- Schema ready: pharmacy_locations, medicine_batches (qty + selling_price), inventory (qty_on_hand), stock_movements (movement_type, batch_id, performed_by)
- No dedicated sales/dispense tables — use stock_movements (movement_type = dispense or sale)
- prescriptions exist (10) but POS v1 does not require Rx fulfillment

## Branch
- Work ONLY on feature/pharmacy-pos (from feature/nursing @ 2f5baf5)
- Do NOT commit onto closed tips (nursing, ipd-billing*, sidebar-*, opd-e2e, billing-collection, sidebar-v1)
- No git add -A

## Must ship (atomic)
### Seed
1. Ensure ≥1 active pharmacy_location for hospital
2. Seed/update medicine_batches with quantity > 0 and selling_price for a few existing medicines (synthetic batch numbers)
3. Keep inventory.quantity_on_hand in sync with batch totals for those rows
4. Short docs/hms/PHARMACY_POS_SEED.md with artisan seed command
5. No PHI

### API (api.auth)
1. GET /api/pharmacy/pos/stock — list sellable lines (medicine + batch + qty + selling_price + expiry); hospital-scoped via location; skip qty<=0
2. POST /api/pharmacy/pos/dispense — body: lines[{batch_id, quantity}], optional patient_id
   - Validate qty available; 422 if insufficient / unknown batch / other hospital
   - Decrement medicine_batches.quantity; update inventory.quantity_on_hand; insert stock_movements (movement_type=dispense, performed_by=auth user, negative or positive qty with clear convention — document it)
   - Return movement ids + remaining qty
3. Optional GET /api/pharmacy/pos/movements — recent dispenses (limit 50)
4. UuidBin + hospital scope; unauth 401

### UI
1. Extend Pharmacy.vue with a POS / Dispense panel (or tab): search/select stock line, qty, Dispense button; show remaining stock after success
2. Keep existing medicines list + Add Medicine working
3. Wire only pharmacy view (already in App.vue)

### Tests
- Feature PharmacyPosTest: stock list; dispense reduces batch+inventory and writes movement; oversell 422; 401; cross-hospital batch 404
- Synthetic fixtures only

### Docs
- This packet + POS seed note; FEATURE_MAP note if file on branch

## Out of scope
- Full Rx fulfillment / nursing indent / IPD pharmacy indent
- Purchase/GRN, transfers between locations, expiry quarantine workflows
- GST / invoice link to billing (may create stock_movement only)
- migrate:fresh / ALTER
- Rewriting medicines CRUD

## Done when
Verifier: Pharmacy UI can dispense a batch, stock drops, movement recorded; tests green; medicines catalog still works.

## Evidence anchors
- Base tip: 2f5baf5 (nursing closed)
- Live counts 2026-09-16: medicines 11; batches/inventory/movements/locations 0 before seed