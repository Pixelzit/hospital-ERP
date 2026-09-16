<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Support\UuidBin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Synthetic pharmacy location + batches + inventory for POS dispense.
 * No PHI. Tied to existing medicines for the first hospital.
 *
 * Idempotent: re-run skips when the synthetic Main Pharmacy location already
 * has medicine_batches for that hospital.
 *
 * Run:
 *   php artisan db:seed --class=Database\\Seeders\\PharmacyPosInventorySeeder
 */
class PharmacyPosInventorySeeder extends Seeder
{
    private const LOCATION_NAME = 'Main Pharmacy (Synthetic)';

    public function run(): void
    {
        $hospital = Hospital::query()->first();
        if (! $hospital) {
            $this->command?->error('No hospital row found. Seed hospitals first.');

            return;
        }

        $hospitalId = $hospital->getRawOriginal('id');

        DB::transaction(function () use ($hospitalId) {
            $location = DB::table('pharmacy_locations')
                ->where('hospital_id', $hospitalId)
                ->where('name', self::LOCATION_NAME)
                ->lockForUpdate()
                ->first();

            if ($location) {
                $batchCount = (int) DB::table('medicine_batches')
                    ->where('pharmacy_location_id', $location->id)
                    ->count();
                $invCount = (int) DB::table('inventory')
                    ->where('pharmacy_location_id', $location->id)
                    ->count();

                if ($batchCount > 0 && $invCount > 0) {
                    $this->command?->info(
                        'Pharmacy POS synthetic inventory already present '
                        ."(location={$location->name}, batches={$batchCount}, inventory={$invCount}). Skipping."
                    );

                    return;
                }

                $locationId = $location->id;
            } else {
                $locationId = UuidBin::generate();
                DB::table('pharmacy_locations')->insert([
                    'id' => $locationId,
                    'hospital_id' => $hospitalId,
                    'branch_id' => null,
                    'name' => self::LOCATION_NAME,
                    'status' => 'active',
                    'created_at' => now(),
                ]);
            }

            $preferred = [
                'Amoxicillin',
                'Atorvastatin',
                'Metformin',
                'Omeprazole',
            ];

            $medicines = DB::table('medicines')
                ->where('hospital_id', $hospitalId)
                ->where('status', 'active')
                ->whereIn('generic_name', $preferred)
                ->orderBy('generic_name')
                ->limit(4)
                ->get(['id', 'generic_name']);

            if ($medicines->isEmpty()) {
                $medicines = DB::table('medicines')
                    ->where('hospital_id', $hospitalId)
                    ->where('status', 'active')
                    ->orderBy('generic_name')
                    ->limit(4)
                    ->get(['id', 'generic_name']);
            }

            if ($medicines->isEmpty()) {
                $this->command?->error('No active medicines for this hospital. Seed medicines first.');

                return;
            }

            $defs = [
                ['qty' => 100, 'selling' => 25.00, 'purchase' => 15.00, 'suffix' => 'A'],
                ['qty' => 50, 'selling' => 40.00, 'purchase' => 22.00, 'suffix' => 'B'],
                ['qty' => 75, 'selling' => 18.50, 'purchase' => 10.00, 'suffix' => 'C'],
                ['qty' => 120, 'selling' => 12.00, 'purchase' => 6.50, 'suffix' => 'D'],
            ];

            $batchInserted = 0;
            $invUpserted = 0;
            $now = now();
            $expiry = $now->copy()->addYear()->toDateString();
            $mfg = $now->copy()->subMonths(3)->toDateString();

            foreach ($medicines->values() as $i => $med) {
                $def = $defs[$i % count($defs)];
                $safe = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', (string) $med->generic_name) ?: 'MED');
                $batchNumber = 'POS-SYNTH-'.$safe.'-'.$def['suffix'];

                $existingBatch = DB::table('medicine_batches')
                    ->where('pharmacy_location_id', $locationId)
                    ->where('medicine_id', $med->id)
                    ->where('batch_number', $batchNumber)
                    ->first();

                if (! $existingBatch) {
                    DB::table('medicine_batches')->insert([
                        'id' => UuidBin::generate(),
                        'medicine_id' => $med->id,
                        'pharmacy_location_id' => $locationId,
                        'batch_number' => $batchNumber,
                        'manufacturing_date' => $mfg,
                        'expiry_date' => $expiry,
                        'purchase_price' => $def['purchase'],
                        'selling_price' => $def['selling'],
                        'quantity' => $def['qty'],
                        'status' => 'active',
                        'created_at' => $now,
                    ]);
                    $batchInserted++;
                }

                $qtyOnHand = (float) DB::table('medicine_batches')
                    ->where('pharmacy_location_id', $locationId)
                    ->where('medicine_id', $med->id)
                    ->sum('quantity');

                $inv = DB::table('inventory')
                    ->where('pharmacy_location_id', $locationId)
                    ->where('medicine_id', $med->id)
                    ->first();

                if ($inv) {
                    DB::table('inventory')
                        ->where('id', $inv->id)
                        ->update([
                            'quantity_on_hand' => $qtyOnHand,
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('inventory')->insert([
                        'id' => UuidBin::generate(),
                        'medicine_id' => $med->id,
                        'pharmacy_location_id' => $locationId,
                        'quantity_on_hand' => $qtyOnHand,
                        'reorder_level' => 10,
                        'updated_at' => $now,
                    ]);
                }
                $invUpserted++;
            }

            $this->command?->info(
                "Seeded Pharmacy POS synthetic inventory: location=".self::LOCATION_NAME
                .", batches_inserted={$batchInserted}, inventory_rows={$invUpserted} (no PHI)."
            );
        });
    }
}
