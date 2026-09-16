<?php

namespace Tests\Feature;

use App\Support\UuidBin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IpdAdmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    public function test_list_and_admit_require_auth(): void
    {
        $this->getJson('/api/ipd/admissions')->assertStatus(401);
        $this->postJson('/api/ipd/admissions', [
            'patient_id' => '00000000-0000-0000-0000-000000000001',
            'bed_id' => '00000000-0000-0000-0000-000000000002',
            'admission_type' => 'routine',
        ])->assertStatus(401);
    }

    public function test_admit_creates_encounter_admission_and_occupies_bed(): void
    {
        [$hospitalId, $patientId, $bedId] = $this->seedHospitalPatientAndBed('available');

        $response = $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/admissions', [
                'patient_id' => UuidBin::from($patientId),
                'bed_id' => UuidBin::from($bedId),
                'admission_type' => 'routine',
                'reason' => 'Synthetic admit',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'admitted');

        $this->assertNotEmpty($response->json('data.encounter_id'));
        $this->assertNotEmpty($response->json('data.bed_assignment.bed_id'));

        $this->assertDatabaseCount('encounters', 1);
        $this->assertDatabaseCount('admissions', 1);
        $this->assertDatabaseCount('bed_assignments', 1);

        $encounter = DB::table('encounters')->first();
        $this->assertSame('IPD', $encounter->encounter_type);
        $this->assertSame('open', $encounter->status);

        $admission = DB::table('admissions')->first();
        $this->assertSame($encounter->id, $admission->encounter_id);
        $this->assertSame('admitted', $admission->status);

        $bed = DB::table('beds')->where('id', $bedId)->first();
        $this->assertSame('occupied', $bed->status);
    }

    public function test_unavailable_bed_and_double_book_rejected(): void
    {
        [$hospitalId, $patientId, $bedId] = $this->seedHospitalPatientAndBed('maintenance');

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/admissions', [
                'patient_id' => UuidBin::from($patientId),
                'bed_id' => UuidBin::from($bedId),
                'admission_type' => 'routine',
            ])
            ->assertStatus(422);

        DB::table('beds')->where('id', $bedId)->update(['status' => 'available']);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/admissions', [
                'patient_id' => UuidBin::from($patientId),
                'bed_id' => UuidBin::from($bedId),
                'admission_type' => 'routine',
            ])
            ->assertCreated();

        $otherPatient = UuidBin::generate();
        DB::table('patients')->insert([
            'id' => $otherPatient,
            'hospital_id' => $hospitalId,
            'mrn' => 'HCS-IPD-2',
            'first_name' => 'Other',
            'last_name' => 'Patient',
            'gender' => 'M',
            'date_of_birth' => '1988-01-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/admissions', [
                'patient_id' => UuidBin::from($otherPatient),
                'bed_id' => UuidBin::from($bedId),
                'admission_type' => 'routine',
            ])
            ->assertStatus(422);
    }

    public function test_other_hospital_patient_or_bed_not_admittable(): void
    {
        $hospitalA = UuidBin::generate();
        $hospitalB = UuidBin::generate();
        DB::table('hospitals')->insert([
            ['id' => $hospitalA, 'name' => 'Hospital A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $hospitalB, 'name' => 'Hospital B', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $patientA = UuidBin::generate();
        $patientB = UuidBin::generate();
        DB::table('patients')->insert([
            [
                'id' => $patientA,
                'hospital_id' => $hospitalA,
                'mrn' => 'HCS-A',
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
                'mrn' => 'HCS-B',
                'first_name' => 'Beta',
                'last_name' => 'Patient',
                'gender' => 'M',
                'date_of_birth' => '1991-01-01',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $wardA = UuidBin::generate();
        $wardB = UuidBin::generate();
        DB::table('wards')->insert([
            ['id' => $wardA, 'hospital_id' => $hospitalA, 'name' => 'Ward A', 'ward_type' => 'general', 'status' => 'active', 'created_at' => now()],
            ['id' => $wardB, 'hospital_id' => $hospitalB, 'name' => 'Ward B', 'ward_type' => 'general', 'status' => 'active', 'created_at' => now()],
        ]);

        $roomA = UuidBin::generate();
        $roomB = UuidBin::generate();
        DB::table('rooms')->insert([
            ['id' => $roomA, 'ward_id' => $wardA, 'room_number' => 'A1', 'room_type' => 'general', 'status' => 'active'],
            ['id' => $roomB, 'ward_id' => $wardB, 'room_number' => 'B1', 'room_type' => 'general', 'status' => 'active'],
        ]);

        $bedA = UuidBin::generate();
        $bedB = UuidBin::generate();
        DB::table('beds')->insert([
            ['id' => $bedA, 'room_id' => $roomA, 'bed_number' => '1', 'status' => 'available'],
            ['id' => $bedB, 'room_id' => $roomB, 'bed_number' => '1', 'status' => 'available'],
        ]);

        // Configured hospital is first() = Hospital A
        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/admissions', [
                'patient_id' => UuidBin::from($patientB),
                'bed_id' => UuidBin::from($bedA),
                'admission_type' => 'routine',
            ])
            ->assertStatus(404);

        $this->withSession(['auth_user_id' => 'test-user'])
            ->postJson('/api/ipd/admissions', [
                'patient_id' => UuidBin::from($patientA),
                'bed_id' => UuidBin::from($bedB),
                'admission_type' => 'routine',
            ])
            ->assertStatus(404);

        $beds = $this->withSession(['auth_user_id' => 'test-user'])
            ->getJson('/api/ipd/beds');
        $beds->assertOk();
        $ids = collect($beds->json('data'))->pluck('id')->all();
        $this->assertContains(UuidBin::from($bedA), $ids);
        $this->assertNotContains(UuidBin::from($bedB), $ids);
    }

    private function seedHospitalPatientAndBed(string $bedStatus): array
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
            'mrn' => 'HCS-IPD-1',
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'F',
            'date_of_birth' => '1990-01-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wardId = UuidBin::generate();
        DB::table('wards')->insert([
            'id' => $wardId,
            'hospital_id' => $hospitalId,
            'name' => 'General Ward',
            'ward_type' => 'general',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $roomId = UuidBin::generate();
        DB::table('rooms')->insert([
            'id' => $roomId,
            'ward_id' => $wardId,
            'room_number' => '101',
            'room_type' => 'general',
            'status' => 'active',
        ]);

        $bedId = UuidBin::generate();
        DB::table('beds')->insert([
            'id' => $bedId,
            'room_id' => $roomId,
            'bed_number' => 'A',
            'status' => $bedStatus,
        ]);

        return [$hospitalId, $patientId, $bedId];
    }

    private function createSchema(): void
    {
        foreach ([
            'bed_assignments', 'admissions', 'encounters', 'beds', 'rooms', 'wards',
            'doctors', 'patients', 'hospitals',
        ] as $table) {
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

        Schema::create('wards', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('hospital_id', 16);
            $table->binary('branch_id', 16)->nullable();
            $table->binary('department_id', 16)->nullable();
            $table->string('name');
            $table->string('ward_type')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('ward_id', 16);
            $table->string('room_number');
            $table->string('room_type')->nullable();
            $table->string('status')->default('active');
        });

        Schema::create('beds', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('room_id', 16);
            $table->string('bed_number');
            $table->string('status')->default('available');
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

        Schema::create('bed_assignments', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('admission_id', 16);
            $table->binary('bed_id', 16);
            $table->dateTime('assigned_at');
            $table->dateTime('released_at')->nullable();
            $table->binary('assigned_by', 16)->nullable();
            $table->string('status')->default('active');
        });
    }
}
