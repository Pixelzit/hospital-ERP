<?php

namespace Tests\Feature;

use App\Support\UuidBin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NursingStationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    public function test_nursing_routes_require_auth(): void
    {
        $id = '00000000-0000-0000-0000-000000000001';
        $this->getJson('/api/nursing/admissions')->assertStatus(401);
        $this->getJson("/api/nursing/admissions/{$id}/vitals")->assertStatus(401);
        $this->postJson("/api/nursing/admissions/{$id}/vitals", ['pulse' => 72])->assertStatus(401);
        $this->getJson("/api/nursing/admissions/{$id}/notes")->assertStatus(401);
        $this->postJson("/api/nursing/admissions/{$id}/notes", ['note' => 'x'])->assertStatus(401);
    }

    public function test_list_admissions_and_post_vitals_notes_happy_path(): void
    {
        [$admissionId, $patientId, $encounterId, $hospitalId, $userId] = $this->seedAdmissionWithBed();
        $sessionUser = UuidBin::from($userId);
        $admissionUuid = UuidBin::from($admissionId);

        $list = $this->withSession(['auth_user_id' => $sessionUser])
            ->getJson('/api/nursing/admissions');
        $list->assertOk()->assertJsonPath('success', true);
        $this->assertCount(1, $list->json('data'));
        $this->assertSame($admissionUuid, $list->json('data.0.id'));
        $this->assertSame('General Ward', $list->json('data.0.ward_name'));
        $this->assertSame('A', $list->json('data.0.bed_number'));

        $vitals = $this->withSession(['auth_user_id' => $sessionUser])
            ->postJson("/api/nursing/admissions/{$admissionUuid}/vitals", [
                'temperature' => 36.8,
                'pulse' => 78,
                'respiratory_rate' => 16,
                'systolic_bp' => 120,
                'diastolic_bp' => 80,
                'spo2' => 98,
                'pain_score' => 2,
            ]);
        $vitals->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pulse', 78)
            ->assertJsonPath('data.temperature', 36.8);
        $this->assertSame($sessionUser, $vitals->json('data.recorded_by'));
        $this->assertDatabaseCount('vitals', 1);

        $notes = $this->withSession(['auth_user_id' => $sessionUser])
            ->postJson("/api/nursing/admissions/{$admissionUuid}/notes", [
                'note' => 'Synthetic nursing observation: patient resting quietly.',
            ]);
        $notes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.note', 'Synthetic nursing observation: patient resting quietly.');
        $this->assertSame($sessionUser, $notes->json('data.nurse_id'));
        $this->assertSame($admissionUuid, $notes->json('data.admission_id'));
        $this->assertSame(UuidBin::from($encounterId), $notes->json('data.encounter_id'));
        $this->assertSame(UuidBin::from($patientId), $notes->json('data.patient_id'));
        $this->assertDatabaseCount('nursing_notes', 1);

        $vitalsList = $this->withSession(['auth_user_id' => $sessionUser])
            ->getJson("/api/nursing/admissions/{$admissionUuid}/vitals");
        $vitalsList->assertOk();
        $this->assertCount(1, $vitalsList->json('data'));

        $notesList = $this->withSession(['auth_user_id' => $sessionUser])
            ->getJson("/api/nursing/admissions/{$admissionUuid}/notes");
        $notesList->assertOk();
        $this->assertCount(1, $notesList->json('data'));
    }

    public function test_other_hospital_admission_returns_404(): void
    {
        [$admissionA, , , , $userId] = $this->seedAdmissionWithBed();
        $sessionUser = UuidBin::from($userId);

        $hospitalB = UuidBin::generate();
        DB::table('hospitals')->insert([
            'id' => $hospitalB,
            'name' => 'Hospital B',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $patientB = UuidBin::generate();
        DB::table('patients')->insert([
            'id' => $patientB,
            'hospital_id' => $hospitalB,
            'mrn' => 'HCS-NURS-B',
            'first_name' => 'Beta',
            'last_name' => 'Synthetic',
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
            'encounter_number' => 'IPD-NURS-B-1',
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
            'admission_number' => 'ADM-NURS-B-1',
            'admission_type' => 'routine',
            'admitted_at' => now(),
            'status' => 'admitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $other = UuidBin::from($admissionB);
        $this->withSession(['auth_user_id' => $sessionUser])
            ->getJson("/api/nursing/admissions/{$other}/vitals")
            ->assertStatus(404);
        $this->withSession(['auth_user_id' => $sessionUser])
            ->postJson("/api/nursing/admissions/{$other}/vitals", ['pulse' => 70])
            ->assertStatus(404);
        $this->withSession(['auth_user_id' => $sessionUser])
            ->getJson("/api/nursing/admissions/{$other}/notes")
            ->assertStatus(404);
        $this->withSession(['auth_user_id' => $sessionUser])
            ->postJson("/api/nursing/admissions/{$other}/notes", ['note' => 'cross hospital'])
            ->assertStatus(404);

        $this->withSession(['auth_user_id' => $sessionUser])
            ->getJson('/api/nursing/admissions/not-a-uuid/vitals')
            ->assertStatus(404);

        $list = $this->withSession(['auth_user_id' => $sessionUser])
            ->getJson('/api/nursing/admissions');
        $list->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains(UuidBin::from($admissionA), $ids);
        $this->assertNotContains($other, $ids);
    }

    public function test_empty_note_and_empty_vitals_rejected(): void
    {
        [$admissionId, , , , $userId] = $this->seedAdmissionWithBed();
        $sessionUser = UuidBin::from($userId);
        $admissionUuid = UuidBin::from($admissionId);

        $this->withSession(['auth_user_id' => $sessionUser])
            ->postJson("/api/nursing/admissions/{$admissionUuid}/notes", ['note' => ''])
            ->assertStatus(422);

        $this->withSession(['auth_user_id' => $sessionUser])
            ->postJson("/api/nursing/admissions/{$admissionUuid}/notes", [])
            ->assertStatus(422);

        $this->withSession(['auth_user_id' => $sessionUser])
            ->postJson("/api/nursing/admissions/{$admissionUuid}/vitals", [])
            ->assertStatus(422);

        $this->withSession(['auth_user_id' => $sessionUser])
            ->postJson("/api/nursing/admissions/{$admissionUuid}/vitals", [
                'temperature' => null,
                'pulse' => null,
            ])
            ->assertStatus(422);
    }

    private function seedAdmissionWithBed(): array
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
            'name' => 'Synthetic Nurse',
            'email' => 'nurse.synthetic@example.test',
            'password' => 'hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $patientId = UuidBin::generate();
        DB::table('patients')->insert([
            'id' => $patientId,
            'hospital_id' => $hospitalId,
            'mrn' => 'HCS-NURS-1',
            'first_name' => 'Alpha',
            'last_name' => 'Synthetic',
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
            'status' => 'occupied',
        ]);

        $encounterId = UuidBin::generate();
        DB::table('encounters')->insert([
            'id' => $encounterId,
            'hospital_id' => $hospitalId,
            'patient_id' => $patientId,
            'encounter_number' => 'IPD-NURS-1',
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
            'admission_number' => 'ADM-NURS-1',
            'admission_type' => 'routine',
            'admitted_at' => now(),
            'status' => 'admitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('bed_assignments')->insert([
            'id' => UuidBin::generate(),
            'admission_id' => $admissionId,
            'bed_id' => $bedId,
            'assigned_at' => now(),
            'released_at' => null,
            'assigned_by' => null,
            'status' => 'active',
        ]);

        return [$admissionId, $patientId, $encounterId, $hospitalId, $userId];
    }

    private function createSchema(): void
    {
        foreach ([
            'nursing_notes', 'vitals', 'bed_assignments', 'admissions', 'encounters',
            'beds', 'rooms', 'wards', 'patients', 'users', 'hospitals',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('hospitals', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
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

        Schema::create('vitals', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('patient_id', 16);
            $table->binary('encounter_id', 16);
            $table->binary('recorded_by', 16);
            $table->decimal('temperature', 5, 2)->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->decimal('spo2', 5, 2)->nullable();
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->decimal('blood_glucose', 6, 2)->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->dateTime('recorded_at', 6);
        });

        Schema::create('nursing_notes', function (Blueprint $table) {
            $table->binary('id', 16)->primary();
            $table->binary('admission_id', 16);
            $table->binary('encounter_id', 16);
            $table->binary('patient_id', 16);
            $table->binary('nurse_id', 16);
            $table->longText('note');
            $table->timestamps();
        });
    }
}
