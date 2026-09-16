<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Support\UuidBin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Synthetic IPD bed inventory only — no patient/PHI data.
 *
 * Idempotent for the configured hospital: re-run skips when the synthetic
 * General Ward already exists for that hospital_id.
 *
 * Run:
 *   php artisan db:seed --class=Database\\Seeders\\IpdBedInventorySeeder
 */
class IpdBedInventorySeeder extends Seeder
{
    public function run(): void
    {
        $hospital = Hospital::query()->first();

        if (! $hospital) {
            $this->command?->error('No hospital row found. Seed hospitals first.');

            return;
        }

        $hospitalId = $hospital->getRawOriginal('id');
        $wardName = 'General Ward (Synthetic)';

        DB::transaction(function () use ($hospitalId, $wardName) {
            $existingWard = DB::table('wards')
                ->where('hospital_id', $hospitalId)
                ->where('name', $wardName)
                ->lockForUpdate()
                ->first();

            if ($existingWard) {
                $wardId = $existingWard->id;
                $roomCount = (int) DB::table('rooms')->where('ward_id', $wardId)->count();
                $bedCount = (int) DB::table('beds as b')
                    ->join('rooms as r', 'r.id', '=', 'b.room_id')
                    ->where('r.ward_id', $wardId)
                    ->count();

                $this->command?->info("IPD synthetic inventory already present for this hospital (ward={$wardName}, rooms={$roomCount}, beds={$bedCount}). Skipping.");

                return;
            }

            $wardId = UuidBin::generate();
            DB::table('wards')->insert([
                'id' => $wardId,
                'hospital_id' => $hospitalId,
                'branch_id' => null,
                'department_id' => null,
                'name' => $wardName,
                'ward_type' => 'general',
                'status' => 'active',
                'created_at' => now(),
            ]);

            $rooms = [
                [
                    'room_number' => 'GW-101',
                    'room_type' => 'general',
                    'beds' => [
                        ['bed_number' => 'A', 'status' => 'available'],
                        ['bed_number' => 'B', 'status' => 'available'],
                        ['bed_number' => 'C', 'status' => 'available'],
                    ],
                ],
                [
                    'room_number' => 'GW-102',
                    'room_type' => 'semi_private',
                    'beds' => [
                        ['bed_number' => 'A', 'status' => 'available'],
                        ['bed_number' => 'B', 'status' => 'maintenance'],
                    ],
                ],
            ];

            $roomInserted = 0;
            $bedInserted = 0;

            foreach ($rooms as $roomDef) {
                $roomId = UuidBin::generate();
                DB::table('rooms')->insert([
                    'id' => $roomId,
                    'ward_id' => $wardId,
                    'room_number' => $roomDef['room_number'],
                    'room_type' => $roomDef['room_type'],
                    'status' => 'active',
                ]);
                $roomInserted++;

                foreach ($roomDef['beds'] as $bedDef) {
                    DB::table('beds')->insert([
                        'id' => UuidBin::generate(),
                        'room_id' => $roomId,
                        'bed_number' => $bedDef['bed_number'],
                        'status' => $bedDef['status'],
                    ]);
                    $bedInserted++;
                }
            }

            $this->command?->info("Seeded synthetic IPD inventory: 1 ward, {$roomInserted} rooms, {$bedInserted} beds (no PHI).");
        });

    }
}
