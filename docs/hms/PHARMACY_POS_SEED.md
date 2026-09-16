# Pharmacy POS seed (synthetic)

Creates stock so POS can dispense against existing medicines.

## What it seeds

- One active `pharmacy_locations` row: **Main Pharmacy (Synthetic)** for the first hospital
- `medicine_batches` with quantity > 0 and selling_price for up to 4 active medicines (prefers Amoxicillin / Atorvastatin / Metformin / Omeprazole)
- Matching `inventory.quantity_on_hand` totals per medicine at that location

No PHI. No `stock_movements` (those are written on dispense).

## Command

```bash
php artisan db:seed --class=Database\\Seeders\\PharmacyPosInventorySeeder
```

## Idempotency

Re-run is safe. If the synthetic location already has batches and inventory rows, the seeder skips.

## Verify counts

```bash
php artisan tinker --execute="echo DB::table('pharmacy_locations')->count().' '.DB::table('medicine_batches')->count().' '.DB::table('inventory')->count();"
```

Expect locations >= 1, batches > 0, inventory > 0 after a successful seed.
