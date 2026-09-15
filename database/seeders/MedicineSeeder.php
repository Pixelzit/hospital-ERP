<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use Illuminate\Database\Seeder;

/**
 * Synthetic pharmacy catalog only — no patient/PHI data.
 *
 * Run:
 *   php artisan db:seed --class=Database\\Seeders\\MedicineSeeder
 */
class MedicineSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = Hospital::query()->first();

        if (! $hospital) {
            $this->command?->error('No hospital row found. Seed hospitals first.');

            return;
        }

        $hospitalId = $hospital->getRawOriginal('id');

        $category = MedicineCategory::query()
            ->where('hospital_id', $hospitalId)
            ->where('name', 'General')
            ->first();

        if (! $category) {
            $category = new MedicineCategory();
            $category->hospital_id = $hospitalId;
            $category->name = 'General';
            $category->description = 'Synthetic seed category';
            $category->status = 'active';
            $category->save();
        }

        $items = [
            [
                'generic_name' => 'Paracetamol',
                'brand_name' => 'Synthacet',
                'strength' => '500 mg',
                'dosage_form' => 'tablet',
                'route' => 'oral',
                'manufacturer' => 'Synthetic Labs',
            ],
            [
                'generic_name' => 'Amoxicillin',
                'brand_name' => 'AmoxiSynth',
                'strength' => '250 mg',
                'dosage_form' => 'capsule',
                'route' => 'oral',
                'manufacturer' => 'Synthetic Labs',
            ],
            [
                'generic_name' => 'Atorvastatin',
                'brand_name' => 'AtorSynth',
                'strength' => '10 mg',
                'dosage_form' => 'tablet',
                'route' => 'oral',
                'manufacturer' => 'Synthetic Pharma',
            ],
            [
                'generic_name' => 'Metformin',
                'brand_name' => 'MetaSynth',
                'strength' => '500 mg',
                'dosage_form' => 'tablet',
                'route' => 'oral',
                'manufacturer' => 'Synthetic Pharma',
            ],
            [
                'generic_name' => 'Salbutamol',
                'brand_name' => 'SalbuSynth',
                'strength' => '100 mcg',
                'dosage_form' => 'inhaler',
                'route' => 'inhalation',
                'manufacturer' => 'Synthetic Respiratory',
            ],
        ];

        $created = 0;

        foreach ($items as $item) {
            $exists = Medicine::query()
                ->where('hospital_id', $hospitalId)
                ->where('generic_name', $item['generic_name'])
                ->where('strength', $item['strength'])
                ->exists();

            if ($exists) {
                continue;
            }

            $medicine = new Medicine();
            $medicine->hospital_id = $hospitalId;
            $medicine->category_id = $category->getRawOriginal('id');
            $medicine->generic_name = $item['generic_name'];
            $medicine->brand_name = $item['brand_name'];
            $medicine->strength = $item['strength'];
            $medicine->dosage_form = $item['dosage_form'];
            $medicine->route = $item['route'];
            $medicine->manufacturer = $item['manufacturer'];
            $medicine->status = 'active';
            $medicine->save();
            $created++;
        }

        $this->command?->info("MedicineSeeder: created {$created} synthetic medicine(s).");
    }
}