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