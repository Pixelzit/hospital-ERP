# HCS Feature Map v0 (updated 2026-09-16)

| View key | Status | Notes |
|----------|--------|-------|
| overview | live | Dashboard API |
| patient | live | Patients + workspace |
| appointments | live | Appointments API |
| opd | live | OPD visits (local WIP may exist outside pharmacy commit) |
| doctors | live | Doctors API |
| income | mock | invoices exist in DB; UI still mock |
| pharmacy | live | DB-backed medicines API+UI+seed (commit 79be692+); replaces mock Pharmacy.vue |
| settings, help | partial/static | |
| emergency, ipd, nursing, ot, procedure, laboratory, radiology, insurance, inventory, blood-bank, ambulance, linen, cssd, discharge*, reports*, mis*, departments, security, templates, system | placeholder | many tables already in DB |

Auth: Login.vue + /api/login|/api/logout|/api/me + api.auth middleware
App URL: http://hcs.test - Vite: http://localhost:5173

## Pharmacy medicines (mock to DB-backed)
- Was: hardcoded Paracetamol/Amoxicillin rows in Pharmacy.vue
- Now: Pharmacy.vue + AddMedicineModal.vue load/create via resources/js/api.js
- API inside Route::middleware('api.auth'): GET/POST /api/pharmacy/medicines, GET /api/pharmacy/medicines/{id}
- Models: Medicine, MedicineCategory, MedicineBatch (binary UUID); hospital_id scoped like doctors
- Seed (synthetic, no PHI): php artisan db:seed --class=Database\Seeders\MedicineSeeder - see docs/hms/PHARMACY_SEED.md
- Verify: after login, Pharmacy shows DB brands (e.g. Synthacet), not mock stock strings like 1,240