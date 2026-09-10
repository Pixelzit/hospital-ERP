<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Support\UuidBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class DoctorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $departmentId = UuidBin::to((string) $request->query('department_id', ''));

        return response()->json([
            'success' => true,
            'data' => $this->listDoctors($departmentId),
            'total' => (int) DB::table('doctors')->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'registration_number' => 'required|string|max:100',
            'registration_authority' => 'nullable|string|max:150',
            'specialization' => 'nullable|string|max:150',
            'qualification' => 'nullable|string|max:500',
            'experience_years' => 'nullable|numeric',
            'consultation_fee' => 'nullable|numeric',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
        ]);

        $hospital = Hospital::query()->first();

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'No hospital is configured in the database.',
            ], 422);
        }

        $hospitalId = $hospital->getRawOriginal('id');

        $duplicateReg = DB::table('doctors')
            ->where('hospital_id', $hospitalId)
            ->where('registration_number', $validated['registration_number'])
            ->exists();

        if ($duplicateReg) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['registration_number' => ['This registration number is already used.']],
            ], 422);
        }

        $username = $this->uniqueUsername(
            $hospitalId,
            $validated['first_name'],
            $validated['last_name'] ?? ''
        );

        $email = $validated['email'] ?? null;

        try {
            $userId = UuidBin::generate();
            $doctorId = UuidBin::generate();

            DB::transaction(function () use ($validated, $hospitalId, $username, $email, $userId, $doctorId) {
                DB::table('users')->insert([
                    'id' => $userId,
                    'hospital_id' => $hospitalId,
                    'username' => $username,
                    'email' => $email,
                    'phone' => $validated['phone'] ?? null,
                    'password_hash' => Hash::make(Str::random(16)),
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'] ?? null,
                    'status' => 'active',
                    'mfa_enabled' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('doctors')->insert([
                    'id' => $doctorId,
                    'user_id' => $userId,
                    'hospital_id' => $hospitalId,
                    'registration_number' => $validated['registration_number'],
                    'registration_authority' => $validated['registration_authority'] ?? null,
                    'specialization' => $validated['specialization'] ?? null,
                    'qualification' => $validated['qualification'] ?? null,
                    'experience_years' => $validated['experience_years'] ?? null,
                    'consultation_fee' => $validated['consultation_fee'] ?? null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create doctor',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Doctor added successfully',
            'data' => [
                'id' => UuidBin::from($doctorId),
                'name' => trim($validated['first_name'].' '.($validated['last_name'] ?? '')),
                'specialization' => $validated['specialization'] ?? null,
                'registration_number' => $validated['registration_number'],
                'status' => 'active',
                'available' => true,
            ],
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $row = $this->findMapped($id);

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $row,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $binaryId = UuidBin::to($id);

        if (! $binaryId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid doctor id',
            ], 422);
        }

        $doctor = DB::table('doctors')->where('id', $binaryId)->first();

        if (! $doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'registration_number' => 'required|string|max:100',
            'registration_authority' => 'nullable|string|max:150',
            'specialization' => 'nullable|string|max:150',
            'qualification' => 'nullable|string|max:500',
            'experience_years' => 'nullable|numeric',
            'consultation_fee' => 'nullable|numeric',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        $duplicateReg = DB::table('doctors')
            ->where('hospital_id', $doctor->hospital_id)
            ->where('registration_number', $validated['registration_number'])
            ->where('id', '!=', $binaryId)
            ->exists();

        if ($duplicateReg) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['registration_number' => ['This registration number is already used.']],
            ], 422);
        }

        try {
            DB::transaction(function () use ($validated, $doctor, $binaryId) {
                DB::table('users')->where('id', $doctor->user_id)->update([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'updated_at' => now(),
                ]);

                DB::table('doctors')->where('id', $binaryId)->update([
                    'registration_number' => $validated['registration_number'],
                    'registration_authority' => $validated['registration_authority'] ?? null,
                    'specialization' => $validated['specialization'] ?? null,
                    'qualification' => $validated['qualification'] ?? null,
                    'experience_years' => $validated['experience_years'] ?? null,
                    'consultation_fee' => $validated['consultation_fee'] ?? null,
                    'status' => $validated['status'] ?? $doctor->status,
                    'updated_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update doctor',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Doctor updated successfully',
            'data' => $this->findMapped($id),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $binaryId = UuidBin::to($id);

        if (! $binaryId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid doctor id',
            ], 422);
        }

        $doctor = DB::table('doctors')->where('id', $binaryId)->first();

        if (! $doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        $inUse = DB::table('appointments')->where('doctor_id', $binaryId)->exists()
            || DB::table('encounters')->where('doctor_id', $binaryId)->exists()
            || DB::table('admissions')->where('admitting_doctor_id', $binaryId)->exists()
            || DB::table('admissions')->where('attending_doctor_id', $binaryId)->exists();

        if ($inUse) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove this doctor because appointments or encounters exist.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($binaryId, $doctor) {
                DB::table('doctor_departments')->where('doctor_id', $binaryId)->delete();
                DB::table('doctors')->where('id', $binaryId)->delete();

                $userStillUsed = DB::table('staff')->where('user_id', $doctor->user_id)->exists()
                    || DB::table('doctors')->where('user_id', $doctor->user_id)->exists();

                if (! $userStillUsed) {
                    DB::table('user_roles')->where('user_id', $doctor->user_id)->delete();
                    DB::table('user_sessions')->where('user_id', $doctor->user_id)->delete();
                    DB::table('users')->where('id', $doctor->user_id)->delete();
                }
            });
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove doctor',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Doctor removed successfully',
        ]);
    }

    private function listDoctors($departmentId = null)
    {
        $query = DB::table('doctors as d')
            ->join('users as u', 'u.id', '=', 'd.user_id');

        if ($departmentId) {
            $query->join('doctor_departments as dd', 'dd.doctor_id', '=', 'd.id')
                ->where('dd.department_id', $departmentId);
        }

        $rows = $query
            ->orderByDesc('d.created_at')
            ->limit(100)
            ->get([
                'd.id',
                'u.first_name',
                'u.last_name',
                'u.phone',
                'u.email',
                'u.username',
                'd.specialization',
                'd.qualification',
                'd.registration_number',
                'd.registration_authority',
                'd.experience_years',
                'd.consultation_fee',
                'd.status',
            ]);

        return $rows->map(fn ($row) => $this->mapRow($row))->values();
    }

    private function findMapped(string $id): ?array
    {
        $binaryId = UuidBin::to($id);

        if (! $binaryId) {
            return null;
        }

        $row = DB::table('doctors as d')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.id', $binaryId)
            ->first([
                'd.id',
                'u.first_name',
                'u.last_name',
                'u.phone',
                'u.email',
                'u.username',
                'd.specialization',
                'd.qualification',
                'd.registration_number',
                'd.registration_authority',
                'd.experience_years',
                'd.consultation_fee',
                'd.status',
            ]);

        return $row ? $this->mapRow($row) : null;
    }

    private function mapRow($row): array
    {
        return [
            'id' => UuidBin::from($row->id),
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
            'username' => $row->username ?? null,
            'phone' => $row->phone,
            'email' => $row->email,
            'specialization' => $row->specialization,
            'qualification' => $row->qualification,
            'registration_number' => $row->registration_number,
            'registration_authority' => $row->registration_authority,
            'experience_years' => $row->experience_years,
            'consultation_fee' => $row->consultation_fee,
            'status' => $row->status,
            'available' => ($row->status ?? '') === 'active',
        ];
    }

    private function uniqueUsername(string $hospitalId, string $firstName, string $lastName): string
    {
        $base = Str::slug(trim($firstName.' '.$lastName), '.') ?: 'doctor';
        $username = $base;
        $i = 1;

        while (DB::table('users')->where('hospital_id', $hospitalId)->where('username', $username)->exists()) {
            $username = $base.$i;
            $i++;
        }

        return $username;
    }
}
