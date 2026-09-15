# Pharmacy medicines seed

Synthetic catalog only (no PHI).

## Prerequisites

- Laragon MySQL with `hospital_db`
- At least one row in `hospitals`
- Tables `medicines` / `medicine_categories` already present (do **not** run `migrate:fresh`)

## Seed

```powershell
cd C:\laragon\www\HCS
php artisan db:seed --class=Database\Seeders\MedicineSeeder
```

## Verify

1. Log in at http://hcs.test
2. Open Pharmacy in the sidebar
3. Rows should come from `/api/pharmacy/medicines` (DB), not hardcoded mock Paracetamol rows
4. Use **+ Add Medicine** to create another row and confirm it appears after refresh

## API (auth session required)

- `GET /api/pharmacy/medicines`
- `POST /api/pharmacy/medicines`
- `GET /api/pharmacy/medicines/{id}`