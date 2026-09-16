<?php

namespace Tests\Feature;

use App\Support\UuidBin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class PharmacyPosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    public function test_unauthenticated_pos_routes_return_401(): void
    {
        $this->getJson('/api/pharmacy/pos/stock')->assertStatus(401);
        $this->postJson('/api/pharmacy/pos/dispense', [
            'lines' => [['batch_id' => '00000000-0000-0000-0000-000000000001', 'quantity' => 1]],
        ])->assertStatus(401);
        $this->getJson('/api/pharmacy/pos/movements')->assertStatus(401);
    }

    public function test_stock_list_and_dispense_reduces_batch_inventory_and_writes_movement(): void
    {
        $fx = $this->seedStock();
        $sessionUser = UuidBin::from($fx['user_id']);

        $stock = $this->withSession(['auth_user_id' => $sessionUser])
            ->getJson('/api/pharmacy/pos/stock');

        $stock->assertOk()
            ->assertJsonPath('success', true);
        $this->assertCount(1, $stock->json('data'));
        $this->assertSame(100.0, (float) $stock->json('data.0.quantity'));

        $batchId = UuidBin::from($fx['batch_id']);
        $dispense = $this->withSession(['auth_user_id' => $sessionUser])
            ->postJson('/api/pharmacy/pos/dispense', [
                'lines' => [
                    ['batch_id' => $batchId, 'quantity' => 3],
                ],
            ]);

        $dispense->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.remaining.0.quantity', 97)
            ->assertJsonPath('data.movements.0.quantity', -3);

        $this->assertSame(97.0, (float) DB::table('medicine_batches')->where('id', $fx['batch_id'])->value('quantity'));
        $this->assertSame(97.0, (float) DB::table('inventory')->where('id', $fx['inventory_id'])->value('quantity_on_hand'));
        $this->assertSame(1, DB::table('stock_movements')->where('movement_type', 'dispense')->count());
        $this->assertSame(-3.0, (float) DB::table('stock_movements')->value('quantity'));

        $movements = $this->withSession(['auth_user_id' => $sessionUser])
            ->getJson('/api/pharmacy/pos/movements');
        $movements->assertOk();
        $this->assertCount(1, $movements->json('data'));
    }

    public function test_oversell_returns_422(): void
    {
        $fx = $this->seedStock();
        $sessionUser = UuidBin::from($fx['user_id']);
        $batchId = UuidBin::from($fx['batch_id']);

        $this->withSession(['auth_user_id' => $sessionUser])
            ->postJson('/api/pharmacy/pos/dispense', [
                'lines' => [['batch_id' => $batchId, 'quantity' => 999]],
            ])
            ->assertStatus(422);

        $this->assertSame(100.0, (float) DB::table('medicine_batches')->where('id', $fx['batch_id'])->value('quantity'));
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_cross_hospital_batch_returns_404(): void
    {
        $fx = $this->seedStock();
        $sessionUser = UuidBin::from($fx['user_id']);

        $hospitalB = UuidBin::generate();
        DB::table('hospitals')->insert([
            'id' => $hospitalB,
            'name' => 'Other Hospital',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $locB = UuidBin::generate();
        DB::table('pharmacy_locations')->insert([
            'id' => $locB,
            'hospital_id' => $hospitalB,
            'branch_id' => null,
            'name' => 'Other Pharmacy',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $medB = UuidBin::generate();
        DB::table('medicines')->insert([
            'id' => $medB,
            'hospital_id' => $hospitalB,
            'category_id' => null,
            'generic_name' => 'OtherMed',
            'brand_name' => 'OtherBrand',
            'strength' => '10mg',
            'dosage_form' => 'tab',
            'route' => 'oral',
            'manufacturer' => 'Synth',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $batchB = UuidBin::generate();
        DB::table('medicine_batches')->insert([
            'id' => $batchB,
            'medicine_id' => $medB,
            'pharmacy_location_id' => $locB,
            'batch_number' => 'OTHER-1',
            'manufacturing_date' => now()->subMonth()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_price' => 5,
            'selling_price' => 10,
            'quantity' => 20,
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Hospital::first() is hospital A; other-hospital batch must 404
        $this->withSession(['auth_user_id' => $sessionUser])
            ->postJson('/api/pharmacy/pos/dispense', [
                'lines' => [['batch_id' => UuidBin::from($batchB), 'quantity' => 1]],
            ])
            ->assertStatus(404);

        $this->assertSame(20.0, (float) DB::table('medicine_batches')->where('id', $batchB)->value('quantity'));
    }

    private function seedStock(): array
    {
        $hospitalId = UuidBin::generate();
        DB::table('hospitals')->insert([
            'id' => $hospitalId,
            'name' => 'Test Hospital',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = UuidBin::generate();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Synthetic Pharmacist',
            'email' => 'pharm.synthetic@example.test',
            'password' => 'hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = UuidBin::generate();
        DB::table('pharmacy_locations')->insert([
            'id' => $locationId,
            'hospital_id' => $hospitalId,
            'branch_id' => null,
            'name' => 'Main Pharmacy (Synthetic)',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $medicineId = UuidBin::generate();
        DB::table('medicines')->insert([
            'id' => $medicineId,
            'hospital_id' => $hospitalId,
            'category_id' => null,
            'generic_name' => 'Amoxicillin',
            'brand_name' => 'AmoxiSynth',
            'strength' => '500mg',
            'dosage_form' => 'capsule',
            'route' => 'oral',
            'manufacturer' => 'Synth Labs',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = UuidBin::generate();
        DB::table('medicine_batches')->insert([
            'id' => $batchId,
            'medicine_id' => $medicineId,
            'pharmacy_location_id' => $locationId,
            'batch_number' => 'POS-SYNTH-AMOX-A',
            'manufacturing_date' => now()->subMonths(2)->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_price' => 15,
            'selling_price' => 25,
            'quantity' => 100,
            'status' => 'active',
            'created_at' => now(),
        ]);

        $inventoryId = UuidBin::generate();
        DB::table('inventory')->insert([
            'id' => $inventoryId,
            'medicine_id' => $medicineId,
            'pharmacy_location_id' => $locationId,
            'quantity_on_hand' => 100,
            'reorder_level' => 10,
            'updated_at' => now(),
        ]);

        return [
            'hospital_id' => $hospitalId,
            'user_id' => $userId,
            'location_id' => $locationId,
            'medicine_id' => $medicineId,
            'batch_id' => $batchId,
            'inventory_id' => $inventoryId,
        ];
    }

    private function createSchema(): void
    {
        foreach ([
            'stock_movements', 'inventory', 'medicine_batches', 'medicines',
            'pharmacy_locations', 'users', 'hospitals',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('hospitals', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->string('name');
            $table->string('status', 20);
            $table->timestamps(6);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamps(6);
        });

        Schema::create('pharmacy_locations', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16);
            $table->binary('branch_id', 16)->nullable();
            $table->string('name', 150);
            $table->string('status', 20);
            $table->dateTime('created_at', 6);
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
            $table->string('status', 20);
            $table->timestamps(6);
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
            $table->decimal('quantity', 14, 3);
            $table->string('status', 20);
            $table->dateTime('created_at', 6);
        });

        Schema::create('inventory', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('medicine_id', 16);
            $table->binary('pharmacy_location_id', 16);
            $table->decimal('quantity_on_hand', 14, 3);
            $table->decimal('reorder_level', 14, 3);
            $table->dateTime('updated_at', 6);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('medicine_id', 16);
            $table->binary('pharmacy_location_id', 16);
            $table->binary('batch_id', 16)->nullable();
            $table->string('movement_type', 50);
            $table->decimal('quantity', 14, 3);
            $table->string('reference_type', 50)->nullable();
            $table->binary('reference_id', 16)->nullable();
            $table->binary('performed_by', 16);
            $table->dateTime('created_at', 6);
        });
    }
}
