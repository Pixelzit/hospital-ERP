<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Support\UuidBin;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class IpdAdmissionController extends Controller
{
    public function wards(): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $rows = DB::table('wards')
            ->where('hospital_id', $hospital->getRawOriginal('id'))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'ward_type', 'status', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => [
                'id' => UuidBin::from($row->id),
                'name' => $row->name,
                'ward_type' => $row->ward_type,
                'status' => $row->status,
            ])->values(),
        ]);
    }

    public function beds(Request $request): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $hospitalId = $hospital->getRawOriginal('id');
        $wardId = $this->optionalBinary($request->query('ward_id'));

        $query = DB::table('beds as b')
            ->join('rooms as r', 'r.id', '=', 'b.room_id')
            ->join('wards as w', 'w.id', '=', 'r.ward_id')
            ->where('w.hospital_id', $hospitalId)
            ->orderBy('w.name')
            ->orderBy('r.room_number')
            ->orderBy('b.bed_number');

        if ($wardId) {
            $query->where('w.id', $wardId);
        }

        $rows = $query->get([
            'b.id',
            'b.bed_number',
            'b.status',
            'r.id as room_id',
            'r.room_number',
            'r.room_type',
            'w.id as ward_id',
            'w.name as ward_name',
        ]);

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => [
                'id' => UuidBin::from($row->id),
                'bed_number' => $row->bed_number,
                'status' => $row->status,
                'room' => [
                    'id' => UuidBin::from($row->room_id),
                    'room_number' => $row->room_number,
                    'room_type' => $row->room_type,
                ],
                'ward' => [
                    'id' => UuidBin::from($row->ward_id),
                    'name' => $row->ward_name,
                ],
            ])->values(),
        ]);
    }

    public function index(): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $rows = DB::table('admissions as a')
            ->leftJoin('patients as p', 'p.id', '=', 'a.patient_id')
            ->where('a.hospital_id', $hospital->getRawOriginal('id'))
            ->where('a.status', 'admitted')
            ->orderByDesc('a.admitted_at')
            ->limit(100)
            ->get([
                'a.id',
                'a.admission_number',
                'a.admission_type',
                'a.status',
                'a.admitted_at',
                'a.encounter_id',
                'a.patient_id',
                'p.first_name',
                'p.last_name',
                'p.mrn',
            ]);

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => $this->mapAdmissionListRow($row))->values(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $bin = UuidBin::to($id);
        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'Admission not found.'], 404);
        }

        $row = DB::table('admissions')
            ->where('id', $bin)
            ->where('hospital_id', $hospital->getRawOriginal('id'))
            ->first();

        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Admission not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->mapAdmissionDetail($row),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'bed_id' => 'required|string',
            'admission_type' => 'required|string|max:50',
            'admitting_doctor_id' => 'nullable|string',
            'attending_doctor_id' => 'nullable|string',
            'reason' => 'nullable|string|max:2000',
            'admitted_at' => 'nullable|date',
        ]);

        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $hospitalId = $hospital->getRawOriginal('id');
        $patientId = UuidBin::to($validated['patient_id']);
        $bedId = UuidBin::to($validated['bed_id']);
        $admittingDoctorId = $this->optionalBinary($validated['admitting_doctor_id'] ?? null);
        $attendingDoctorId = $this->optionalBinary($validated['attending_doctor_id'] ?? null);

        if (! $patientId || ! $bedId) {
            return response()->json(['success' => false, 'message' => 'Invalid patient or bed id.'], 422);
        }

        $patient = DB::table('patients')
            ->where('id', $patientId)
            ->where('hospital_id', $hospitalId)
            ->first();
        if (! $patient) {
            return response()->json(['success' => false, 'message' => 'Patient not found for this hospital.'], 404);
        }

        if ($admittingDoctorId && ! DB::table('doctors')->where('id', $admittingDoctorId)->where('hospital_id', $hospitalId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Admitting doctor not found.'], 404);
        }
        if ($attendingDoctorId && ! DB::table('doctors')->where('id', $attendingDoctorId)->where('hospital_id', $hospitalId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Attending doctor not found.'], 404);
        }

        $admittedAt = isset($validated['admitted_at'])
            ? Carbon::parse($validated['admitted_at'])
            : now();

        try {
            $result = DB::transaction(function () use (
                $hospitalId,
                $patientId,
                $bedId,
                $admittingDoctorId,
                $attendingDoctorId,
                $validated,
                $admittedAt
            ) {
                $bed = DB::table('beds as b')
                    ->join('rooms as r', 'r.id', '=', 'b.room_id')
                    ->join('wards as w', 'w.id', '=', 'r.ward_id')
                    ->where('b.id', $bedId)
                    ->where('w.hospital_id', $hospitalId)
                    ->lockForUpdate()
                    ->first(['b.id', 'b.status', 'b.bed_number', 'r.room_number', 'w.name as ward_name']);

                if (! $bed) {
                    return response()->json(['success' => false, 'message' => 'Bed not found for this hospital.'], 404);
                }

                if (strtolower((string) $bed->status) !== 'available') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bed is not available.',
                        'errors' => ['bed_id' => ['Bed status must be available.']],
                    ], 422);
                }

                $activeAssign = DB::table('bed_assignments')
                    ->where('bed_id', $bedId)
                    ->where('status', 'active')
                    ->whereNull('released_at')
                    ->lockForUpdate()
                    ->exists();

                if ($activeAssign) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bed is already assigned.',
                        'errors' => ['bed_id' => ['Cannot double-book an occupied bed.']],
                    ], 422);
                }

                $encounterId = UuidBin::generate();
                $admissionId = UuidBin::generate();
                $assignmentId = UuidBin::generate();
                $encounterNumber = $this->nextEncounterNumber($hospitalId, $admittedAt, 'IPD');
                $admissionNumber = $this->nextAdmissionNumber($hospitalId, $admittedAt);

                DB::table('encounters')->insert([
                    'id' => $encounterId,
                    'hospital_id' => $hospitalId,
                    'branch_id' => null,
                    'patient_id' => $patientId,
                    'doctor_id' => $admittingDoctorId,
                    'department_id' => null,
                    'appointment_id' => null,
                    'encounter_number' => $encounterNumber,
                    'encounter_type' => 'IPD',
                    'status' => 'open',
                    'started_at' => $admittedAt,
                    'ended_at' => null,
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('admissions')->insert([
                    'id' => $admissionId,
                    'hospital_id' => $hospitalId,
                    'branch_id' => null,
                    'patient_id' => $patientId,
                    'encounter_id' => $encounterId,
                    'admission_number' => $admissionNumber,
                    'admission_type' => $validated['admission_type'],
                    'admitted_at' => $admittedAt,
                    'expected_discharge_at' => null,
                    'discharged_at' => null,
                    'admitting_doctor_id' => $admittingDoctorId,
                    'attending_doctor_id' => $attendingDoctorId,
                    'reason' => $validated['reason'] ?? null,
                    'status' => 'admitted',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('bed_assignments')->insert([
                    'id' => $assignmentId,
                    'admission_id' => $admissionId,
                    'bed_id' => $bedId,
                    'assigned_at' => $admittedAt,
                    'released_at' => null,
                    'assigned_by' => null,
                    'status' => 'active',
                ]);

                DB::table('beds')->where('id', $bedId)->update([
                    'status' => 'occupied',
                ]);

                $fresh = DB::table('admissions')->where('id', $admissionId)->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Patient admitted',
                    'data' => $this->mapAdmissionDetail($fresh),
                ], 201);
            });
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to admit patient.',
            ], 500);
        }

        return $result;
    }

    private function hospitalOrFail()
    {
        $hospital = Hospital::query()->first();
        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'No hospital is configured in the database.',
            ], 422);
        }

        return $hospital;
    }

    private function optionalBinary(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return UuidBin::to($value);
    }

    private function nextEncounterNumber($hospitalId, $startedAt, string $kind): string
    {
        $day = Carbon::parse($startedAt)->format('Ymd');
        $prefix = $kind.'-'.$day.'-';
        $count = DB::table('encounters')
            ->where('hospital_id', $hospitalId)
            ->where('encounter_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    private function nextAdmissionNumber($hospitalId, $admittedAt): string
    {
        $day = Carbon::parse($admittedAt)->format('Ymd');
        $prefix = 'ADM-'.$day.'-';
        $count = DB::table('admissions')
            ->where('hospital_id', $hospitalId)
            ->where('admission_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    private function mapAdmissionListRow($row): array
    {
        return [
            'id' => UuidBin::from($row->id),
            'admission_number' => $row->admission_number,
            'admission_type' => $row->admission_type,
            'status' => $row->status,
            'admitted_at' => $row->admitted_at,
            'encounter_id' => UuidBin::from($row->encounter_id),
            'patient' => [
                'id' => $row->patient_id ? UuidBin::from($row->patient_id) : null,
                'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
                'mrn' => $row->mrn ?? null,
            ],
        ];
    }

    private function mapAdmissionDetail($row): array
    {
        $patient = DB::table('patients')->where('id', $row->patient_id)->first();
        $assignment = DB::table('bed_assignments as ba')
            ->leftJoin('beds as b', 'b.id', '=', 'ba.bed_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'b.room_id')
            ->leftJoin('wards as w', 'w.id', '=', 'r.ward_id')
            ->where('ba.admission_id', $row->id)
            ->where('ba.status', 'active')
            ->orderByDesc('ba.assigned_at')
            ->first([
                'ba.id',
                'ba.bed_id',
                'ba.assigned_at',
                'ba.status',
                'b.bed_number',
                'b.status as bed_status',
                'r.room_number',
                'w.name as ward_name',
            ]);

        return [
            'id' => UuidBin::from($row->id),
            'admission_number' => $row->admission_number,
            'admission_type' => $row->admission_type,
            'status' => $row->status,
            'admitted_at' => $row->admitted_at,
            'reason' => $row->reason,
            'encounter_id' => UuidBin::from($row->encounter_id),
            'admitting_doctor_id' => $row->admitting_doctor_id ? UuidBin::from($row->admitting_doctor_id) : null,
            'attending_doctor_id' => $row->attending_doctor_id ? UuidBin::from($row->attending_doctor_id) : null,
            'patient' => $patient ? [
                'id' => UuidBin::from($patient->id),
                'name' => trim($patient->first_name.' '.($patient->last_name ?? '')),
                'mrn' => $patient->mrn,
            ] : null,
            'bed_assignment' => $assignment ? [
                'id' => UuidBin::from($assignment->id),
                'bed_id' => UuidBin::from($assignment->bed_id),
                'assigned_at' => $assignment->assigned_at,
                'status' => $assignment->status,
                'bed_number' => $assignment->bed_number,
                'bed_status' => $assignment->bed_status,
                'room_number' => $assignment->room_number,
                'ward_name' => $assignment->ward_name,
            ] : null,
        ];
    }
}
