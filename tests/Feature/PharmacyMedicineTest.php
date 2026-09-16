<?php

namespace Tests\Feature;

use App\Support\UuidBin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class PharmacyMedicineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPharmacySchema();
    }

    public function test_list_medicines_requires_auth(): void
    {
        $this->getJson('/api/pharmacy/medicines')->assertStatus(401);
    }

    public function test_create_medicine_requires_auth(): void
    {
        $this->postJson('/api/pharmacy/medicines', [
            'generic_name' => 'ShouldNotPersist',
        ])->assertStatus(401);

        $this->assertDatabaseCount('medicines', 0);
    }


    public function test_list_medicines_returns_seeded_rows(): void
    {
        $hospitalId = UuidBin::generate();
        DB::table('hospitals')->insert([
            'id' => $hospitalId,
            'name' => 'Test Hospital',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $medicineId = UuidBin::generate();
        DB::table('medicines')->insert([
            'id' => $medicineId,
            'hospital_id' => $hospitalId,
            'generic_name' => 'Ibuprofen',
            'brand_name' => 'IbuSynth',
            'strength' => '400 mg',
            'dosage_form' => 'tablet',
            'route' => 'oral',
            'manufacturer' => 'Synthetic Labs',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['auth_user_id' => 'test-user'])
            ->getJson('/api/pharmacy/medicines');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.generic_name', 'Ibuprofen')
            ->assertJsonPath('data.0.brand_name', 'IbuSynth');

        $name = $response->json('data.0.name');
        $this->assertStringNotContainsString('Paracetamol', (string) $name);
        $this->assertStringContainsString('Ibuprofen', (string) $name);
    }

    public function test_create_medicine(): void
    {
        $hospitalId = UuidBin::generate();
        DB::table('hospitals')->insert([
            'id' => $hospitalId,
            'name' => 'Test Hospital',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/pharmacy/medicines', [
                'generic_name' => 'Cetirizine',
                'brand_name' => 'CetiSynth',
                'strength' => '10 mg',
                'dosage_form' => 'tablet',
                'route' => 'oral',
                'manufacturer' => 'Synthetic Labs',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.generic_name', 'Cetirizine');

        $this->assertDatabaseCount('medicines', 1);
        $this->assertTrue(
            DB::table('medicines')->where('generic_name', 'Cetirizine')->exists()
        );
    }


    public function test_list_medicines_is_scoped_to_configured_hospital(): void
    {
        $hospitalA = UuidBin::generate();
        $hospitalB = UuidBin::generate();

        DB::table('hospitals')->insert([
            [
                'id' => $hospitalA,
                'name' => 'Hospital A',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $hospitalB,
                'name' => 'Hospital B',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('medicines')->insert([
            [
                'id' => UuidBin::generate(),
                'hospital_id' => $hospitalA,
                'generic_name' => 'VisibleToA',
                'brand_name' => 'BrandA',
                'strength' => '1 mg',
                'dosage_form' => 'tablet',
                'route' => 'oral',
                'manufacturer' => 'Synthetic',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => UuidBin::generate(),
                'hospital_id' => $hospitalB,
                'generic_name' => 'HiddenFromB',
                'brand_name' => 'BrandB',
                'strength' => '2 mg',
                'dosage_form' => 'tablet',
                'route' => 'oral',
                'manufacturer' => 'Synthetic',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->withSession(['auth_user_id' => 'test-user'])
            ->getJson('/api/pharmacy/medicines');

        $response->assertOk()->assertJsonPath('success', true);

        $names = collect($response->json('data'))->pluck('generic_name')->all();
        $this->assertContains('VisibleToA', $names);
        $this->assertNotContains('HiddenFromB', $names);
        $this->assertSame(1, $response->json('total'));
    }

    public function test_show_medicine_from_other_hospital_is_not_found(): void
    {
        $hospitalA = UuidBin::generate();
        $hospitalB = UuidBin::generate();

        DB::table('hospitals')->insert([
            [
                'id' => $hospitalA,
                'name' => 'Hospital A',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $hospitalB,
                'name' => 'Hospital B',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $otherId = UuidBin::generate();
        DB::table('medicines')->insert([
            'id' => $otherId,
            'hospital_id' => $hospitalB,
            'generic_name' => 'OtherHospitalDrug',
            'brand_name' => 'OtherBrand',
            'strength' => '5 mg',
            'dosage_form' => 'tablet',
            'route' => 'oral',
            'manufacturer' => 'Synthetic',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uuid = UuidBin::from($otherId);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->getJson('/api/pharmacy/medicines/'.$uuid)
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
    private function createPharmacySchema(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('medicine_categories', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16);
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16);
            $table->binary('category_id', 16)->nullable();
            $table->string('generic_name');
            $table->string('brand_name')->nullable();
            $table->string('strength', 100)->nullable();
            $table->string('dosage_form', 100)->nullable();
            $table->string('route', 100)->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('medicine_id', 16);
            $table->binary('pharmacy_location_id', 16);
            $table->string('batch_number', 100);
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamp('created_at')->useCurrent();
        });
    }
}