<?php

namespace Tests\Feature;

use App\Support\UuidBin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IpdBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    public function test_ipd_invoice_routes_require_auth(): void
    {
        $this->getJson('/api/ipd/admissions/00000000-0000-0000-0000-000000000001/invoices')
            ->assertStatus(401);
        $this->postJson('/api/ipd/admissions/00000000-0000-0000-0000-000000000001/invoices', [
            'items' => [['description' => 'Bed', 'quantity' => 1, 'unit_price' => 10]],
        ])->assertStatus(401);
        $this->postJson('/api/ipd/invoices/00000000-0000-0000-0000-000000000001/advance')
            ->assertStatus(401);
    }

    public function test_create_interim_and_advance_to_final(): void
    {
        [$admissionId] = $this->seedAdmission();

        $create = $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/admissions/'.UuidBin::from($admissionId).'/invoices', [
                'items' => [
                    ['description' => 'Ward bed day', 'quantity' => 2, 'unit_price' => 500],
                    ['description' => 'Nursing', 'quantity' => 1, 'unit_price' => 200],
                ],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', 'interim')
            ->assertJsonPath('data.invoice_type', 'IPD')
            ->assertJsonPath('data.total', 1200)
            ->assertJsonPath('data.balance', 1200);

        $invoiceId = $create->json('data.id');
        $this->assertNotEmpty($invoiceId);
        $this->assertDatabaseCount('invoice_items', 2);

        $list = $this->withSession(['auth_user_id' => 'test-user'])
            ->getJson('/api/ipd/admissions/'.UuidBin::from($admissionId).'/invoices');
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/invoices/'.$invoiceId.'/advance')
            ->assertOk()
            ->assertJsonPath('data.status', 'provisional');

        $final = $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/invoices/'.$invoiceId.'/advance');
        $final->assertOk()
            ->assertJsonPath('data.status', 'final');
        $this->assertNotEmpty($final->json('data.issued_at'));

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/invoices/'.$invoiceId.'/advance')
            ->assertStatus(422);
    }

    public function test_pay_before_final_rejected_and_pay_on_final_works(): void
    {
        [$admissionId] = $this->seedAdmission();

        $create = $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/admissions/'.UuidBin::from($admissionId).'/invoices', [
                'items' => [['description' => 'Consult', 'quantity' => 1, 'unit_price' => 100]],
            ]);
        $invoiceId = $create->json('data.id');

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/billing/invoices/'.$invoiceId.'/payments', [
                'amount' => 50,
                'payment_method' => 'cash',
            ])
            ->assertStatus(422);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/invoices/'.$invoiceId.'/advance')->assertOk();
        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/invoices/'.$invoiceId.'/advance')->assertOk();

        $pay = $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/billing/invoices/'.$invoiceId.'/payments', [
                'amount' => 100,
                'payment_method' => 'upi',
            ]);
        $pay->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.balance', 0);
    }

    public function test_other_hospital_admission_not_billable(): void
    {
        $hospitalA = UuidBin::generate();
        $hospitalB = UuidBin::generate();
        DB::table('hospitals')->insert([
            ['id' => $hospitalA, 'name' => 'Hospital A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $hospitalB, 'name' => 'Hospital B', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $patientB = UuidBin::generate();
        DB::table('patients')->insert([
            'id' => $patientB,
            'hospital_id' => $hospitalB,
            'mrn' => 'HCS-B',
            'first_name' => 'Beta',
            'last_name' => 'Patient',
            'gender' => 'M',
            'date_of_birth' => '1991-01-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $encounterB = UuidBin::generate();
        DB::table('encounters')->insert([
            'id' => $encounterB,
            'hospital_id' => $hospitalB,
            'patient_id' => $patientB,
            'encounter_number' => 'IPD-B-1',
            'encounter_type' => 'IPD',
            'status' => 'open',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admissionB = UuidBin::generate();
        DB::table('admissions')->insert([
            'id' => $admissionB,
            'hospital_id' => $hospitalB,
            'patient_id' => $patientB,
            'encounter_id' => $encounterB,
            'admission_number' => 'ADM-B-1',
            'admission_type' => 'routine',
            'admitted_at' => now(),
            'status' => 'admitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/admissions/'.UuidBin::from($admissionB).'/invoices', [
                'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 10]],
            ])
            ->assertStatus(404);
    }

    private function seedAdmission(): array
    {
        $hospitalId = UuidBin::generate();
        DB::table('hospitals')->insert([
            'id' => $hospitalId,
            'name' => 'Test Hospital',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $patientId = UuidBin::generate();
        DB::table('patients')->insert([
            'id' => $patientId,
            'hospital_id' => $hospitalId,
            'mrn' => 'HCS-IPDB-1',
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'F',
            'date_of_birth' => '1990-01-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $encounterId = UuidBin::generate();
        DB::table('encounters')->insert([
            'id' => $encounterId,
            'hospital_id' => $hospitalId,
            'patient_id' => $patientId,
            'encounter_number' => 'IPD-TEST-1',
            'encounter_type' => 'IPD',
            'status' => 'open',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admissionId = UuidBin::generate();
        DB::table('admissions')->insert([
            'id' => $admissionId,
            'hospital_id' => $hospitalId,
            'patient_id' => $patientId,
            'encounter_id' => $encounterId,
            'admission_number' => 'ADM-TEST-1',
            'admission_type' => 'routine',
            'admitted_at' => now(),
            'status' => 'admitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$admissionId, $patientId, $encounterId, $hospitalId];
    }

    private function createSchema(): void
    {
        foreach (['payments', 'invoice_items', 'invoices', 'admissions', 'encounters', 'patients', 'hospitals'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('hospitals', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16);
            $table->string('mrn')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('encounters', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16);
            $table->binary('branch_id', 16)->nullable();
            $table->binary('patient_id', 16);
            $table->binary('doctor_id', 16)->nullable();
            $table->binary('department_id', 16)->nullable();
            $table->binary('appointment_id', 16)->nullable();
            $table->string('encounter_number');
            $table->string('encounter_type');
            $table->string('status')->default('open');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->binary('created_by', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('admissions', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16);
            $table->binary('branch_id', 16)->nullable();
            $table->binary('patient_id', 16);
            $table->binary('encounter_id', 16);
            $table->string('admission_number');
            $table->string('admission_type');
            $table->dateTime('admitted_at');
            $table->dateTime('expected_discharge_at')->nullable();
            $table->dateTime('discharged_at')->nullable();
            $table->binary('admitting_doctor_id', 16)->nullable();
            $table->binary('attending_doctor_id', 16)->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('admitted');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16);
            $table->binary('branch_id', 16)->nullable();
            $table->binary('patient_id', 16)->nullable();
            $table->binary('encounter_id', 16)->nullable();
            $table->string('invoice_number');
            $table->string('invoice_type')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('status')->default('issued');
            $table->dateTime('issued_at')->nullable();
            $table->binary('created_by', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('invoice_id', 16);
            $table->string('item_type')->nullable();
            $table->binary('reference_id', 16)->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('invoice_id', 16);
            $table->binary('patient_id', 16)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method');
            $table->string('transaction_reference')->nullable();
            $table->string('status')->default('completed');
            $table->dateTime('paid_at')->nullable();
            $table->binary('received_by', 16)->nullable();
        });
    }
}
