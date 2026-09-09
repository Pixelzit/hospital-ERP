<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Patient;
use App\Support\UuidBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = DB::table('patients')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get([
                'id',
                'mrn',
                'first_name',
                'last_name',
                'gender',
                'date_of_birth',
                'status',
                'created_at',
            ]);

        $data = $rows->map(function ($row) {
            $age = null;
            if ($row->date_of_birth) {
                try {
                    $age = \Carbon\Carbon::parse($row->date_of_birth)->age;
                } catch (\Throwable $e) {
                    $age = null;
                }
            }

            return [
                'id' => UuidBin::from($row->id),
                'mrn' => $row->mrn,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'name' => trim($row->first_name.' '.($row->last_name ?? '')),
                'gender' => $row->gender,
                'date_of_birth' => $row->date_of_birth,
                'age' => $age,
                'status' => $row->status,
                'created_at' => $row->created_at,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => (int) DB::table('patients')->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'mrn' => 'required|string|max:50',
            'gender' => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date',
        ]);

        $hospital = Hospital::query()->first();

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'No hospital is configured in the database.',
            ], 422);
        }

        $exists = DB::table('patients')
            ->where('hospital_id', $hospital->getRawOriginal('id'))
            ->where('mrn', $validated['mrn'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['mrn' => ['This MRN is already used.']],
            ], 422);
        }

        $patient = Patient::create([
            'id' => UuidBin::generate(),
            'hospital_id' => $hospital->getRawOriginal('id'),
            'mrn' => $validated['mrn'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'gender' => $validated['gender'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Patient created successfully',
            'data' => [
                'id' => $patient->id,
                'mrn' => $patient->mrn,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'status' => $patient->status,
            ],
        ], 201);
    }
}
