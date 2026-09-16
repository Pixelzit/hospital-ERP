<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Support\UuidBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NursingController extends Controller
{
    public function admissions(): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $hospitalId = $hospital->getRawOriginal('id');

        $rows = DB::table('admissions as a')
            ->leftJoin('patients as p', 'p.id', '=', 'a.patient_id')
            ->leftJoin('bed_assignments as ba', function ($join) {
                $join->on('ba.admission_id', '=', 'a.id')
                    ->where('ba.status', 'active')
                    ->whereNull('ba.released_at');
            })
            ->leftJoin('beds as b', 'b.id', '=', 'ba.bed_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'b.room_id')
            ->leftJoin('wards as w', 'w.id', '=', 'r.ward_id')
            ->where('a.hospital_id', $hospitalId)
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
                'b.bed_number',
                'r.room_number',
                'w.name as ward_name',
            ]);

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => [
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
                'ward_name' => $row->ward_name,
                'room_number' => $row->room_number,
                'bed_number' => $row->bed_number,
            ])->values(),
        ]);
    }

    public function vitalsIndex(string $id): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $admission = $this->findAdmission($id, $hospital->getRawOriginal('id'));
        if ($admission instanceof JsonResponse) {
            return $admission;
        }

        $rows = DB::table('vitals')
            ->where('encounter_id', $admission->encounter_id)
            ->orderByDesc('recorded_at')
            ->limit(200)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => $this->mapVital($row))->values(),
        ]);
    }

    public function vitalsStore(Request $request, string $id): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $admission = $this->findAdmission($id, $hospital->getRawOriginal('id'));
        if ($admission instanceof JsonResponse) {
            return $admission;
        }

        $userBin = $this->authUserBin($request);
        if ($userBin instanceof JsonResponse) {
            return $userBin;
        }

        $fields = [
            'temperature',
            'pulse',
            'respiratory_rate',
            'systolic_bp',
            'diastolic_bp',
            'spo2',
            'pain_score',
        ];

        $payload = [];
        foreach ($fields as $field) {
            if ($request->exists($field) && $request->input($field) !== null && $request->input($field) !== '') {
                $payload[$field] = $request->input($field);
            }
        }

        if ($payload === []) {
            return response()->json([
                'success' => false,
                'message' => 'At least one measurable vital field is required.',
                'errors' => ['vitals' => ['Provide at least one of: '.implode(', ', $fields).'.']],
            ], 422);
        }

        $recordedAt = $request->input('recorded_at') ?: now();

        $vitalId = UuidBin::generate();
        DB::table('vitals')->insert([
            'id' => $vitalId,
            'patient_id' => $admission->patient_id,
            'encounter_id' => $admission->encounter_id,
            'recorded_by' => $userBin,
            'temperature' => $payload['temperature'] ?? null,
            'pulse' => $payload['pulse'] ?? null,
            'respiratory_rate' => $payload['respiratory_rate'] ?? null,
            'systolic_bp' => $payload['systolic_bp'] ?? null,
            'diastolic_bp' => $payload['diastolic_bp'] ?? null,
            'spo2' => $payload['spo2'] ?? null,
            'pain_score' => $payload['pain_score'] ?? null,
            'recorded_at' => $recordedAt,
        ]);

        $row = DB::table('vitals')->where('id', $vitalId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Vitals recorded',
            'data' => $this->mapVital($row),
        ], 201);
    }

    public function notesIndex(string $id): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $admission = $this->findAdmission($id, $hospital->getRawOriginal('id'));
        if ($admission instanceof JsonResponse) {
            return $admission;
        }

        $rows = DB::table('nursing_notes')
            ->where('admission_id', $admission->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => $this->mapNote($row))->values(),
        ]);
    }

    public function notesStore(Request $request, string $id): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $admission = $this->findAdmission($id, $hospital->getRawOriginal('id'));
        if ($admission instanceof JsonResponse) {
            return $admission;
        }

        $userBin = $this->authUserBin($request);
        if ($userBin instanceof JsonResponse) {
            return $userBin;
        }

        $note = trim((string) $request->input('note', ''));
        if ($note === '') {
            return response()->json([
                'success' => false,
                'message' => 'Note is required.',
                'errors' => ['note' => ['The note field is required.']],
            ], 422);
        }

        $noteId = UuidBin::generate();
        $now = now();
        DB::table('nursing_notes')->insert([
            'id' => $noteId,
            'admission_id' => $admission->id,
            'encounter_id' => $admission->encounter_id,
            'patient_id' => $admission->patient_id,
            'nurse_id' => $userBin,
            'note' => $note,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table('nursing_notes')->where('id', $noteId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Nursing note created',
            'data' => $this->mapNote($row),
        ], 201);
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

    private function findAdmission(string $admissionId, $hospitalId)
    {
        $bin = UuidBin::to($admissionId);
        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'Admission not found.'], 404);
        }

        $row = DB::table('admissions')
            ->where('id', $bin)
            ->where('hospital_id', $hospitalId)
            ->first();

        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Admission not found.'], 404);
        }

        return $row;
    }

    private function authUserBin(Request $request)
    {
        $sessionUser = $request->session()->get('auth_user_id');
        $userBin = is_string($sessionUser) ? UuidBin::to($sessionUser) : null;
        if (! $userBin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $exists = DB::table('users')->where('id', $userBin)->exists();
        if (! $exists) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $userBin;
    }

    private function mapVital($row): array
    {
        return [
            'id' => UuidBin::from($row->id),
            'patient_id' => UuidBin::from($row->patient_id),
            'encounter_id' => UuidBin::from($row->encounter_id),
            'recorded_by' => UuidBin::from($row->recorded_by),
            'temperature' => $row->temperature !== null ? (float) $row->temperature : null,
            'pulse' => $row->pulse !== null ? (int) $row->pulse : null,
            'respiratory_rate' => $row->respiratory_rate !== null ? (int) $row->respiratory_rate : null,
            'systolic_bp' => $row->systolic_bp !== null ? (int) $row->systolic_bp : null,
            'diastolic_bp' => $row->diastolic_bp !== null ? (int) $row->diastolic_bp : null,
            'spo2' => $row->spo2 !== null ? (float) $row->spo2 : null,
            'pain_score' => $row->pain_score !== null ? (int) $row->pain_score : null,
            'recorded_at' => $row->recorded_at,
        ];
    }

    private function mapNote($row): array
    {
        return [
            'id' => UuidBin::from($row->id),
            'admission_id' => UuidBin::from($row->admission_id),
            'encounter_id' => UuidBin::from($row->encounter_id),
            'patient_id' => UuidBin::from($row->patient_id),
            'nurse_id' => UuidBin::from($row->nurse_id),
            'note' => $row->note,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
