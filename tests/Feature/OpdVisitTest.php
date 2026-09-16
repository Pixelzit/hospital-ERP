<?php

namespace Tests\Feature;

use App\Support\UuidBin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class OpdVisitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    public function test_list_visits_requires_auth(): void
    {
        $this->getJson('/api/opd/visits?date='.now()->toDateString())->assertStatus(401);
    }

    public function test_check_in_requires_auth(): void
    {
        $this->postJson('/api/opd/visits', [
            'patient_id' => '00000000-0000-0000-0000-000000000001',
        ])->assertStatus(401);
    }

    public function test_check_in_creates_todays_opd_visit(): void
    {
        [$hospitalId, $patientId] = $this->seedHospitalAndPatient();

        $response = $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/opd/visits', [
                'patient_id' => UuidBin::from($patientId),
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.visit_type', 'OPD')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.status_key', 'new_patient');

        $this->assertDatabaseCount('encounters', 1);
        $row = DB::table('encounters')->first();
        $this->assertSame('OPD', $row->encounter_type);
        $this->assertSame('open', $row->status);
        $this->assertSame(now()->toDateString(), substr((string) $row->started_at, 0, 10));

        $list = $this->withSession(['auth_user_id' => 'test-user'])
            ->getJson('/api/opd/visits?date='.now()->toDateString());

        $list->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertSame(1, $list->json('counts.new_patient'));
    }

    public function test_status_transitions_and_rejects_illegal(): void
    {
        [$hospitalId, $patientId] = $this->seedHospitalAndPatient();
        $visitId = UuidBin::generate();
        DB::table('encounters')->insert([
            'id' => $visitId,
            'hospital_id' => $hospitalId,
            'patient_id' => $patientId,
            'encounter_number' => 'OPD-TEST-0001',
            'encounter_type' => 'OPD',
            'status' => 'open',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $uuid = UuidBin::from($visitId);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->patchJson('/api/opd/visits/'.$uuid.'/status', ['status' => 'nurse_seen'])
            ->assertOk()
            ->assertJsonPath('data.status', 'nurse_seen');

        $this->withSession(['auth_user_id' => 'test-user'])
            ->patchJson('/api/opd/visits/'.$uuid.'/status', ['status' => 'doctor_seen'])
            ->assertOk()
            ->assertJsonPath('data.status', 'doctor_seen');

        $this->withSession(['auth_user_id' => 'test-user'])
            ->patchJson('/api/opd/visits/'.$uuid.'/status', ['status' => 'visit_complete'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.status_key', 'visit_complete');

        $this->withSession(['auth_user_id' => 'test-user'])
            ->patchJson('/api/opd/visits/'.$uuid.'/status', ['status' => 'nurse_seen'])
            ->assertStatus(422);
    }

    public function test_other_hospital_visit_not_listed_or_patchable(): void
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
                'mrn' => 'HCS-A-1',
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
                'mrn' => 'HCS-B-1',
                'first_name' => 'Beta',
                'last_name' => 'Patient',
                'gender' => 'M',
                'date_of_birth' => '1991-01-01',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $visitA = UuidBin::generate();
        $visitB = UuidBin::generate();
        DB::table('encounters')->insert([
            [
                'id' => $visitA,
                'hospital_id' => $hospitalA,
                'patient_id' => $patientA,
                'encounter_number' => 'OPD-A-0001',
                'encounter_type' => 'OPD',
                'status' => 'open',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $visitB,
                'hospital_id' => $hospitalB,
                'patient_id' => $patientB,
                'encounter_number' => 'OPD-B-0001',
                'encounter_type' => 'OPD',
                'status' => 'open',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $list = $this->withSession(['auth_user_id' => 'test-user'])
            ->getJson('/api/opd/visits?date='.now()->toDateString());

        $list->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains(UuidBin::from($visitA), $ids);
        $this->assertNotContains(UuidBin::from($visitB), $ids);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->patchJson('/api/opd/visits/'.UuidBin::from($visitB).'/status', ['status' => 'nurse_seen'])
            ->assertStatus(404);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/opd/visits', ['patient_id' => UuidBin::from($patientB)])
            ->assertStatus(404);
    }
    private function seedHospitalAndPatient(): array
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
            'mrn' => 'HCS-TEST-1',
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'F',
            'date_of_birth' => '1990-01-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$hospitalId, $patientId];
    }

    private function createSchema(): void
    {
        foreach (['encounters','patient_contacts','appointments','departments','doctors','patients','users','hospitals'] as $table) {
            Schema::dropIfExists($table);
        }

        foreach (['encounters','patient_contacts','appointments','departments','doctors','patients','users','hospitals'] as $table) {
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

        Schema::create('doctors', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16);
            $table->binary('user_id', 16)->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16)->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16);
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16)->nullable();
            $table->string('appointment_number')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_contacts', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('patient_id', 16);
            $table->string('contact_type');
            $table->string('value')->nullable();
            $table->boolean('is_primary')->default(false);
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
    }
}
