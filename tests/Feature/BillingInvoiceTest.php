<?php

namespace Tests\Feature;

use App\Support\UuidBin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    public function test_list_requires_auth(): void
    {
        $this->getJson('/api/billing/invoices')->assertStatus(401);
    }

    public function test_pay_requires_auth(): void
    {
        $this->postJson('/api/billing/invoices/00000000-0000-0000-0000-000000000001/payments', [
            'amount' => 10,
            'payment_method' => 'cash',
        ])->assertStatus(401);
    }

    public function test_list_returns_hospital_invoices(): void
    {
        [$hospitalId, $patientId, $invoiceId] = $this->seedIssuedInvoice(100, 0);

        $response = $this->withSession(['auth_user_id' => 'test-user'])
            ->getJson('/api/billing/invoices');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(UuidBin::from($invoiceId), $response->json('data.0.id'));
        $this->assertSame('INV-TEST-1', $response->json('data.0.invoice_number'));
        $this->assertArrayHasKey('today_collection', $response->json('summary'));
    }

    public function test_payment_reduces_balance_and_can_mark_paid(): void
    {
        [$hospitalId, $patientId, $invoiceId] = $this->seedIssuedInvoice(100, 40);
        $uuid = UuidBin::from($invoiceId);

        $response = $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/billing/invoices/'.$uuid.'/payments', [
                'amount' => 60,
                'payment_method' => 'upi',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount_paid', 100)
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseCount('payments', 1);
        $row = DB::table('invoices')->where('id', $invoiceId)->first();
        $this->assertSame('paid', $row->status);
        $this->assertEquals(0.0, (float) $row->balance);
    }

    public function test_overpay_and_paid_invoice_rejected(): void
    {
        [$hospitalId, $patientId, $invoiceId] = $this->seedIssuedInvoice(50, 0);
        $uuid = UuidBin::from($invoiceId);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/billing/invoices/'.$uuid.'/payments', [
                'amount' => 51,
                'payment_method' => 'cash',
            ])
            ->assertStatus(422);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/billing/invoices/'.$uuid.'/payments', [
                'amount' => 50,
                'payment_method' => 'cash',
            ])
            ->assertCreated();

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/billing/invoices/'.$uuid.'/payments', [
                'amount' => 1,
                'payment_method' => 'cash',
            ])
            ->assertStatus(422);
    }

    public function test_show_includes_items_and_payments(): void
    {
        [$hospitalId, $patientId, $invoiceId] = $this->seedIssuedInvoice(80, 20);
        $itemId = UuidBin::generate();
        DB::table('invoice_items')->insert([
            'id' => $itemId,
            'invoice_id' => $invoiceId,
            'item_type' => 'service',
            'description' => 'Consult',
            'quantity' => 1,
            'unit_price' => 80,
            'discount' => 0,
            'tax' => 0,
            'total' => 80,
            'created_at' => now(),
        ]);

        $uuid = UuidBin::from($invoiceId);
        $response = $this->withSession(['auth_user_id' => 'test-user'])
            ->getJson('/api/billing/invoices/'.$uuid);

        $response->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-TEST-1')
            ->assertJsonCount(1, 'data.items');
    }

    public function test_other_hospital_invoice_not_listed_or_payable(): void
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

        $patientA = UuidBin::generate();
        $patientB = UuidBin::generate();
        DB::table('patients')->insert([
            [
                'id' => $patientA,
                'hospital_id' => $hospitalA,
                'mrn' => 'HCS-BA-1',
                'first_name' => 'Alpha',
                'last_name' => 'Patient',
                'gender' => 'F',
                'date_of_birth' => '1990-01-01',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $patientB,
                'hospital_id' => $hospitalB,
                'mrn' => 'HCS-BB-1',
                'first_name' => 'Beta',
                'last_name' => 'Patient',
                'gender' => 'M',
                'date_of_birth' => '1991-01-01',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $invoiceA = UuidBin::generate();
        $invoiceB = UuidBin::generate();
        DB::table('invoices')->insert([
            [
                'id' => $invoiceA,
                'hospital_id' => $hospitalA,
                'patient_id' => $patientA,
                'invoice_number' => 'INV-A-1',
                'invoice_type' => 'OPD',
                'subtotal' => 100,
                'discount' => 0,
                'tax' => 0,
                'total' => 100,
                'amount_paid' => 0,
                'balance' => 100,
                'status' => 'issued',
                'issued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $invoiceB,
                'hospital_id' => $hospitalB,
                'patient_id' => $patientB,
                'invoice_number' => 'INV-B-1',
                'invoice_type' => 'OPD',
                'subtotal' => 200,
                'discount' => 0,
                'tax' => 0,
                'total' => 200,
                'amount_paid' => 0,
                'balance' => 200,
                'status' => 'issued',
                'issued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $list = $this->withSession(['auth_user_id' => 'test-user'])
            ->getJson('/api/billing/invoices');

        $list->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains(UuidBin::from($invoiceA), $ids);
        $this->assertNotContains(UuidBin::from($invoiceB), $ids);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->getJson('/api/billing/invoices/'.UuidBin::from($invoiceB))
            ->assertStatus(404);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/billing/invoices/'.UuidBin::from($invoiceB).'/payments', [
                'amount' => 10,
                'payment_method' => 'cash',
            ])
            ->assertStatus(404);
    }
    private function seedIssuedInvoice(float $total, float $paid): array
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
            'mrn' => 'HCS-BILL-1',
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'F',
            'date_of_birth' => '1990-01-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceId = UuidBin::generate();
        $balance = $total - $paid;
        DB::table('invoices')->insert([
            'id' => $invoiceId,
            'hospital_id' => $hospitalId,
            'patient_id' => $patientId,
            'invoice_number' => 'INV-TEST-1',
            'invoice_type' => 'OPD',
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'amount_paid' => $paid,
            'balance' => $balance,
            'status' => $balance <= 0 ? 'paid' : 'issued',
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$hospitalId, $patientId, $invoiceId];
    }

    private function createSchema(): void
    {
        foreach (['payments', 'invoice_items', 'invoices', 'patients', 'hospitals'] as $table) {
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
