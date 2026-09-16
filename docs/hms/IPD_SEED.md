# IPD bed inventory seed

Synthetic ward / room / bed rows for local `hospital_db` only. No patient or PHI data.

## Command

```bash
php artisan db:seed --class=Database\\Seeders\\IpdBedInventorySeeder
```

Uses `Hospital::first()` (same residual pattern as pharmacy/OPD). Requires at least one hospital row.

## What it creates

- 1 ward: `General Ward (Synthetic)` (`ward_type=general`)
- 2 rooms: `GW-101` (3 beds available), `GW-102` (1 available + 1 maintenance)
- Total: 5 beds

## Re-run safety

Idempotent per hospital: if a ward named `General Ward (Synthetic)` already exists for that hospital, the seeder skips and prints counts. Inserts run inside a DB transaction with `lockForUpdate` on the ward lookup so concurrent seeders do not double-insert. It does not delete or recreate inventory.

## Out of scope

Admissions, bed assignments, admit API, and IPD UI are later atomics.
