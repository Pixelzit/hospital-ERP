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

class OpdController extends Controller
{
    public function visits(Request $request): JsonResponse
    {
        $date = $this->parseDate($request->query('date', now()->toDateString()));
        if ($date instanceof JsonResponse) {
            return $date;
        }

        $hospital = Hospital::query()->first();
        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'No hospital is configured in the database.',
            ], 422);
        }

        $rows = $this->visitQuery($hospital->getRawOriginal('id'))
            ->whereDate('e.started_at', $date)
            ->orderBy('e.started_at')
            ->get($this->visitSelect());

        $data = $this->mapRows($rows);
        $counts = $this->countStatuses($data);

        return response()->json([
            'success' => true,
            'data' => $data,
            'counts' => $counts,
            'date' => $date,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'appointment_id' => 'nullable|string',
            'doctor_id' => 'nullable|string',
            'department_id' => 'nullable|string',
        ]);

        $hospital = Hospital::query()->first();
        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'No hospital is configured in the database.',
            ], 422);
        }

        $hospitalId = $hospital->getRawOriginal('id');
        $patientId = UuidBin::to($validated['patient_id']);
        if (! $patientId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid patient_id',
            ], 422);
        }

        $patient = DB::table('patients')
            ->where('id', $patientId)
            ->where('hospital_id', $hospitalId)
            ->first();

        if (! $patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found for this hospital.',
            ], 404);
        }

        $doctorId = $this->optionalBinary($validated['doctor_id'] ?? null);
        $departmentId = $this->optionalBinary($validated['department_id'] ?? null);
        $appointmentId = $this->optionalBinary($validated['appointment_id'] ?? null);

        if (($validated['doctor_id'] ?? null) && ! $doctorId) {
            return response()->json(['success' => false, 'message' => 'Invalid doctor_id'], 422);
        }
        if (($validated['department_id'] ?? null) && ! $departmentId) {
            return response()->json(['success' => false, 'message' => 'Invalid department_id'], 422);
        }
        if (($validated['appointment_id'] ?? null) && ! $appointmentId) {
            return response()->json(['success' => false, 'message' => 'Invalid appointment_id'], 422);
        }

        if ($doctorId && ! DB::table('doctors')->where('id', $doctorId)->where('hospital_id', $hospitalId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Doctor not found'], 404);
        }
        if ($departmentId && ! DB::table('departments')->where('id', $departmentId)->where('hospital_id', $hospitalId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Department not found'], 404);
        }
        if ($appointmentId && ! DB::table('appointments')->where('id', $appointmentId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Appointment not found'], 404);
        }

        $encounterId = UuidBin::generate();
        $startedAt = now();
        $number = $this->nextEncounterNumber($hospitalId, $startedAt);

        try {
            DB::table('encounters')->insert([
                'id' => $encounterId,
                'hospital_id' => $hospitalId,
                'branch_id' => null,
                'patient_id' => $patientId,
                'doctor_id' => $doctorId,
                'department_id' => $departmentId,
                'appointment_id' => $appointmentId,
                'encounter_number' => $number,
                'encounter_type' => 'OPD',
                'status' => 'open',
                'started_at' => $startedAt,
                'ended_at' => null,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check in OPD visit',
                'error' => $e->getMessage(),
            ], 500);
        }

        $row = $this->visitQuery($hospitalId)
            ->where('e.id', $encounterId)
            ->first($this->visitSelect());

        return response()->json([
            'success' => true,
            'message' => 'OPD visit checked in',
            'data' => $this->mapRows(collect([$row]))->first(),
        ], 201);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|max:50',
        ]);

        $hospital = Hospital::query()->first();
        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'No hospital is configured in the database.',
            ], 422);
        }

        $binaryId = UuidBin::to($id);
        if (! $binaryId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid visit id',
            ], 422);
        }

        $encounter = DB::table('encounters')
            ->where('id', $binaryId)
            ->where('hospital_id', $hospital->getRawOriginal('id'))
            ->where('encounter_type', 'OPD')
            ->first();

        if (! $encounter) {
            return response()->json([
                'success' => false,
                'message' => 'OPD visit not found',
            ], 404);
        }

        $fromKey = $this->statusKey($encounter->status);
        $toKey = $this->statusKey($validated['status']);
        $allowed = $this->allowedTransitions();

        if (! isset($allowed[$fromKey]) || ! in_array($toKey, $allowed[$fromKey], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Illegal status transition',
                'errors' => [
                    'status' => ["Cannot move from {$fromKey} to {$toKey}."],
                ],
            ], 422);
        }

        $dbStatus = $this->dbStatusForKey($toKey);
        $endedAt = $toKey === 'visit_complete' ? now() : null;

        DB::table('encounters')->where('id', $binaryId)->update([
            'status' => $dbStatus,
            'ended_at' => $endedAt,
            'updated_at' => now(),
        ]);

        $row = $this->visitQuery($hospital->getRawOriginal('id'))
            ->where('e.id', $binaryId)
            ->first($this->visitSelect());

        return response()->json([
            'success' => true,
            'message' => 'OPD visit status updated',
            'data' => $this->mapRows(collect([$row]))->first(),
        ]);
    }

    private function parseDate($date)
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid filter: date must be YYYY-MM-DD.',
            ], 422);
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->toDateString();
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid filter: date is not valid.',
            ], 422);
        }
    }

    private function visitQuery($hospitalId)
    {
        return DB::table('encounters as e')
            ->join('patients as p', 'p.id', '=', 'e.patient_id')
            ->leftJoin('doctors as d', 'd.id', '=', 'e.doctor_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('departments as dep', 'dep.id', '=', 'e.department_id')
            ->leftJoin('appointments as a', 'a.id', '=', 'e.appointment_id')
            ->where('e.encounter_type', 'OPD')
            ->where('e.hospital_id', $hospitalId);
    }

    private function visitSelect(): array
    {
        return [
            'e.id',
            'e.patient_id',
            'e.doctor_id',
            'e.department_id',
            'e.appointment_id',
            'e.encounter_number',
            'e.encounter_type',
            'e.status',
            'e.started_at',
            'p.mrn',
            'p.first_name',
            'p.last_name',
            'p.date_of_birth',
            'p.gender',
            'u.first_name as doctor_first',
            'u.last_name as doctor_last',
            'dep.name as department_name',
            'a.appointment_number',
        ];
    }

    private function mapRows($rows)
    {
        $phones = $this->phones($rows->pluck('patient_id')->all());

        return $rows->map(function ($row) use ($phones) {
            $patientId = UuidBin::from($row->patient_id);

            return [
                'id' => UuidBin::from($row->id),
                'patient_id' => $patientId,
                'visit_number' => $row->encounter_number,
                'appointment_number' => $row->appointment_number ?: null,
                'started_at' => $row->started_at,
                'department' => $row->department_name ?: '-',
                'department_id' => UuidBin::from($row->department_id),
                'uhid' => $row->mrn,
                'patient_name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: '-',
                'age' => $this->age($row->date_of_birth),
                'gender' => $row->gender,
                'assigned_user' => trim(($row->doctor_first ?? '').' '.($row->doctor_last ?? '')) ?: '-',
                'doctor' => trim(($row->doctor_first ?? '').' '.($row->doctor_last ?? '')) ?: '-',
                'doctor_id' => UuidBin::from($row->doctor_id),
                'status' => $row->status,
                'status_key' => $this->statusKey($row->status),
                'next_status' => $this->nextStatusKey($row->status),
                'patient_type' => 'Out Patient',
                'phone' => $phones[$patientId] ?? null,
                'visit_type' => $row->encounter_type,
            ];
        })->values();
    }

    private function countStatuses($data): array
    {
        $counts = [
            'new_patient' => 0,
            'nurse_seen' => 0,
            'doctor_seen' => 0,
            'visit_complete' => 0,
        ];
        foreach ($data as $row) {
            $key = $this->statusKey($row['status']);
            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        return $counts;
    }

    private function phones(array $patientBins): array
    {
        $ids = array_values(array_filter($patientBins));
        if (! $ids) {
            return [];
        }

        $rows = DB::table('patient_contacts')
            ->whereIn('patient_id', $ids)
            ->where('contact_type', 'phone')
            ->orderByDesc('is_primary')
            ->get(['patient_id', 'value']);

        $map = [];
        foreach ($rows as $row) {
            $key = UuidBin::from($row->patient_id);
            if ($key && ! isset($map[$key]) && $row->value) {
                $map[$key] = $row->value;
            }
        }

        return $map;
    }

    private function age($dob): ?int
    {
        if (! $dob) {
            return null;
        }
        try {
            return Carbon::parse($dob)->age;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function statusKey($status): string
    {
        $s = strtolower(trim((string) $status));
        if (in_array($s, ['nurse_seen', 'nurse seen', 'nursing'], true)) {
            return 'nurse_seen';
        }
        if (in_array($s, ['doctor_seen', 'doctor seen'], true)) {
            return 'doctor_seen';
        }
        if (in_array($s, ['closed', 'completed', 'complete', 'visit_complete', 'fulfilled'], true)) {
            return 'visit_complete';
        }
        if (in_array($s, ['open', 'scheduled', 'new', 'new_patient', 'arrived', 'active'], true)) {
            return 'new_patient';
        }

        return $s;
    }

    private function nextStatusKey($status): ?string
    {
        $from = $this->statusKey($status);
        $allowed = $this->allowedTransitions();

        return $allowed[$from][0] ?? null;
    }

    private function allowedTransitions(): array
    {
        return [
            'new_patient' => ['nurse_seen'],
            'nurse_seen' => ['doctor_seen'],
            'doctor_seen' => ['visit_complete'],
        ];
    }

    private function dbStatusForKey(string $key): string
    {
        return match ($key) {
            'nurse_seen' => 'nurse_seen',
            'doctor_seen' => 'doctor_seen',
            'visit_complete' => 'closed',
            default => 'open',
        };
    }

    private function optionalBinary(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return UuidBin::to($value);
    }

    private function nextEncounterNumber($hospitalId, $startedAt): string
    {
        $day = Carbon::parse($startedAt)->format('Ymd');
        $prefix = 'OPD-'.$day.'-';
        $count = DB::table('encounters')
            ->where('hospital_id', $hospitalId)
            ->where('encounter_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}