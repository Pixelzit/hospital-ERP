<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Support\UuidBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PatientController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = DB::table('patients')
            ->orderByDesc('created_at')
            ->limit(250)
            ->get([
                'id',
                'mrn',
                'first_name',
                'last_name',
                'gender',
                'date_of_birth',
                'blood_group',
                'marital_status',
                'occupation',
                'status',
                'created_at',
            ]);

        $contacts = DB::table('patient_contacts')
            ->whereIn('patient_id', $rows->pluck('id')->all())
            ->orderByDesc('is_primary')
            ->get(['patient_id', 'contact_type', 'value']);

        $phones = [];
        $emails = [];
        foreach ($contacts as $contact) {
            $key = UuidBin::from($contact->patient_id);
            if ($contact->contact_type === 'phone' && ! isset($phones[$key])) {
                $phones[$key] = $contact->value;
            }
            if ($contact->contact_type === 'email' && ! isset($emails[$key])) {
                $emails[$key] = $contact->value;
            }
        }

        $data = $rows->map(function ($row) use ($phones, $emails) {
            $id = UuidBin::from($row->id);

            return $this->present($row, $phones[$id] ?? null, $emails[$id] ?? null);
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => (int) DB::table('patients')->count(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $bin = UuidBin::to($id);

        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'Patient not found.'], 404);
        }

        $row = DB::table('patients')->where('id', $bin)->first();

        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Patient not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->present($row, $this->primaryContact($row->id, 'phone'), $this->primaryContact($row->id, 'email')),
        ]);
    }

    public function duplicates(Request $request): JsonResponse
    {
        $hospital = Hospital::query()->first();

        if (! $hospital) {
            return response()->json(['success' => true, 'data' => []]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->findDuplicates(
                $hospital->getRawOriginal('id'),
                (string) $request->input('first_name', ''),
                $request->input('last_name'),
                $request->input('date_of_birth'),
                $request->input('phone')
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'mrn' => 'nullable|string|max:50',
            'gender' => 'required|string|max:30',
            'date_of_birth' => 'required|date',
            'blood_group' => 'nullable|string|max:10',
            'marital_status' => 'nullable|string|max:30',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'emergency_name' => 'required|string|max:200',
            'emergency_relationship' => 'nullable|string|max:100',
            'emergency_phone' => 'required|string|max:30',
            'id_type' => 'nullable|string|max:50',
            'id_number' => 'nullable|string|max:255',
            'allergies' => 'nullable|string',
            'conditions' => 'nullable|string',
            'medications' => 'nullable|string',
            'notes' => 'nullable|string',
            'force' => 'nullable|boolean',
            'id_proof' => 'nullable|array',
            'id_proof.name' => 'nullable|string|max:500',
            'id_proof.type' => 'nullable|string|max:255',
            'id_proof.content' => 'nullable|string',
        ]);

        $hospital = Hospital::query()->first();

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'No hospital is configured in the database.',
            ], 422);
        }

        $hospitalId = $hospital->getRawOriginal('id');
        $phone = trim((string) $validated['phone']);
        $lastName = $validated['last_name'] ?? null;

        if (! $request->boolean('force')) {
            $duplicates = $this->findDuplicates(
                $hospitalId,
                $validated['first_name'],
                $lastName,
                $validated['date_of_birth'],
                $phone
            );

            if ($duplicates !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'A possible duplicate patient already exists.',
                    'duplicates' => $duplicates,
                ], 409);
            }
        }

        $mrn = trim((string) ($validated['mrn'] ?? ''));
        if ($mrn === '') {
            $mrn = $this->nextMrn($hospitalId);
        }

        $exists = DB::table('patients')
            ->where('hospital_id', $hospitalId)
            ->where('mrn', $mrn)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['mrn' => ['This MRN is already used.']],
            ], 422);
        }

        $patientId = UuidBin::generate();
        $now = now();

        DB::transaction(function () use ($validated, $hospitalId, $patientId, $mrn, $lastName, $phone, $now) {
            DB::table('patients')->insert([
                'id' => $patientId,
                'hospital_id' => $hospitalId,
                'mrn' => $mrn,
                'first_name' => $validated['first_name'],
                'last_name' => $lastName,
                'date_of_birth' => $validated['date_of_birth'],
                'gender' => $validated['gender'],
                'blood_group' => $validated['blood_group'] ?? null,
                'marital_status' => $validated['marital_status'] ?? null,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->insertContact($patientId, 'phone', $phone, $now);
            $this->insertContact($patientId, 'email', $validated['email'] ?? null, $now);

            DB::table('patient_addresses')->insert([
                'id' => UuidBin::generate(),
                'patient_id' => $patientId,
                'address_type' => 'home',
                'address_line_1' => $validated['address_line_1'],
                'address_line_2' => $validated['address_line_2'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'country' => 'India',
                'postal_code' => $validated['postal_code'] ?? null,
                'is_primary' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('patient_emergency_contacts')->insert([
                'id' => UuidBin::generate(),
                'patient_id' => $patientId,
                'name' => $validated['emergency_name'],
                'relationship' => $validated['emergency_relationship'] ?? null,
                'phone' => $validated['emergency_phone'],
                'is_primary' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $idType = trim((string) ($validated['id_type'] ?? ''));
            $idNumber = trim((string) ($validated['id_number'] ?? ''));
            if ($idType !== '' && $idNumber !== '') {
                DB::table('patient_identifiers')->insert([
                    'id' => UuidBin::generate(),
                    'patient_id' => $patientId,
                    'identifier_type' => $idType,
                    'identifier_value' => $idNumber,
                    'is_primary' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($this->lines($validated['allergies'] ?? null) as $allergen) {
                DB::table('patient_allergies')->insert([
                    'id' => UuidBin::generate(),
                    'patient_id' => $patientId,
                    'allergen' => $allergen,
                    'status' => 'active',
                    'recorded_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($this->lines($validated['conditions'] ?? null) as $condition) {
                DB::table('patient_conditions')->insert([
                    'id' => UuidBin::generate(),
                    'patient_id' => $patientId,
                    'condition_name' => $condition,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $medications = trim((string) ($validated['medications'] ?? ''));
            if ($medications !== '') {
                DB::table('patient_medical_history')->insert([
                    'id' => UuidBin::generate(),
                    'patient_id' => $patientId,
                    'condition_name' => 'Current medications',
                    'description' => $medications,
                    'created_at' => $now,
                ]);
            }

            $notes = trim((string) ($validated['notes'] ?? ''));
            if ($notes !== '') {
                DB::table('patient_medical_history')->insert([
                    'id' => UuidBin::generate(),
                    'patient_id' => $patientId,
                    'condition_name' => 'Registration notes',
                    'notes' => $notes,
                    'created_at' => $now,
                ]);
            }

            $this->storeIdProof($hospitalId, $patientId, $validated['id_proof'] ?? null, $now);
        });

        $row = DB::table('patients')->where('id', $patientId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Patient created successfully',
            'data' => $this->present($row, $this->primaryContact($patientId, 'phone'), $this->primaryContact($patientId, 'email')),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $bin = UuidBin::to($id);

        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'Patient not found.'], 404);
        }

        $row = DB::table('patients')->where('id', $bin)->first();

        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Patient not found.'], 404);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'gender' => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date',
            'status' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'blood_group' => 'nullable|string|max:10',
        ]);

        DB::table('patients')->where('id', $bin)->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'status' => $validated['status'] ?? $row->status,
            'blood_group' => $validated['blood_group'] ?? null,
            'updated_at' => now(),
        ]);

        $this->upsertContact($bin, 'phone', $validated['phone'] ?? null);
        $this->upsertContact($bin, 'email', $validated['email'] ?? null);

        $fresh = DB::table('patients')->where('id', $bin)->first();

        return response()->json([
            'success' => true,
            'message' => 'Patient updated successfully',
            'data' => $this->present($fresh, $this->primaryContact($bin, 'phone'), $this->primaryContact($bin, 'email')),
        ]);
    }

    private function present(object $row, ?string $phone = null, ?string $email = null): array
    {
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
            'blood_group' => $row->blood_group ?? null,
            'marital_status' => $row->marital_status ?? null,
            'occupation' => $row->occupation ?? null,
            'phone' => $phone,
            'email' => $email,
            'status' => $row->status,
            'created_at' => $row->created_at,
        ];
    }

    private function primaryContact($patientId, string $type): ?string
    {
        $value = DB::table('patient_contacts')
            ->where('patient_id', $patientId)
            ->where('contact_type', $type)
            ->orderByDesc('is_primary')
            ->value('value');

        return $value !== null && $value !== '' ? $value : null;
    }

    private function upsertContact(string $patientId, string $type, ?string $value): void
    {
        $existing = DB::table('patient_contacts')
            ->where('patient_id', $patientId)
            ->where('contact_type', $type)
            ->orderByDesc('is_primary')
            ->first();

        $trimmed = is_string($value) ? trim($value) : '';

        if ($trimmed === '') {
            if ($existing) {
                DB::table('patient_contacts')->where('id', $existing->id)->delete();
            }

            return;
        }

        if ($existing) {
            DB::table('patient_contacts')->where('id', $existing->id)->update([
                'value' => $trimmed,
                'is_primary' => 1,
                'updated_at' => now(),
            ]);

            return;
        }

        $this->insertContact($patientId, $type, $trimmed, now());
    }

    private function insertContact(string $patientId, string $type, ?string $value, $now): void
    {
        $trimmed = is_string($value) ? trim($value) : '';
        if ($trimmed === '') {
            return;
        }

        DB::table('patient_contacts')->insert([
            'id' => UuidBin::generate(),
            'patient_id' => $patientId,
            'contact_type' => $type,
            'value' => $trimmed,
            'is_primary' => 1,
            'is_verified' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function findDuplicates($hospitalId, string $firstName, ?string $lastName, ?string $dob, ?string $phone): array
    {
        $name = strtolower(trim($firstName.' '.trim((string) $lastName)));
        $dob = $dob ? substr((string) $dob, 0, 10) : '';
        $phoneDigits = preg_replace('/\D+/', '', (string) $phone);

        if ($name === '' || $dob === '' || $phoneDigits === '') {
            return [];
        }

        $rows = DB::table('patients as p')
            ->leftJoin('patient_contacts as c', function ($join) {
                $join->on('c.patient_id', '=', 'p.id')
                    ->where('c.contact_type', '=', 'phone');
            })
            ->where('p.hospital_id', $hospitalId)
            ->whereDate('p.date_of_birth', $dob)
            ->whereRaw('LOWER(TRIM(CONCAT(p.first_name, " ", COALESCE(p.last_name, "")))) = ?', [$name])
            ->get([
                'p.id',
                'p.mrn',
                'p.first_name',
                'p.last_name',
                'p.date_of_birth',
                'p.status',
                'c.value as phone',
            ]);

        return $rows->filter(function ($row) use ($phoneDigits) {
            return preg_replace('/\D+/', '', (string) $row->phone) === $phoneDigits;
        })->map(function ($row) {
            return [
                'id' => UuidBin::from($row->id),
                'mrn' => $row->mrn,
                'name' => trim($row->first_name.' '.($row->last_name ?? '')),
                'date_of_birth' => $row->date_of_birth,
                'phone' => $row->phone,
                'status' => $row->status,
            ];
        })->values()->all();
    }

    private function nextMrn($hospitalId): string
    {
        $prefix = 'HCS-'.now()->format('y').'-';
        $last = DB::table('patients')
            ->where('hospital_id', $hospitalId)
            ->where('mrn', 'like', $prefix.'%')
            ->orderByDesc('mrn')
            ->value('mrn');

        $n = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches)) {
            $n = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function lines(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $text) ?: [])));
    }

    private function storeIdProof($hospitalId, string $patientId, ?array $proof, $now): void
    {
        $content = $proof['content'] ?? null;
        $name = $proof['name'] ?? null;
        if (! is_string($content) || trim($content) === '' || ! is_string($name) || trim($name) === '') {
            return;
        }

        $binary = base64_decode($content, true);
        if ($binary === false || $binary === '') {
            return;
        }

        $uploader = DB::table('users')->where('hospital_id', $hospitalId)->orderBy('created_at')->first();
        if (! $uploader) {
            return;
        }

        $key = 'patient-id-proofs/'.bin2hex($patientId).'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        Storage::disk('local')->put($key, $binary);

        DB::table('documents')->insert([
            'id' => UuidBin::generate(),
            'hospital_id' => $hospitalId,
            'patient_id' => $patientId,
            'document_type' => 'id_proof',
            'storage_provider' => 'local',
            'storage_key' => $key,
            'original_filename' => $name,
            'mime_type' => $proof['type'] ?? 'application/octet-stream',
            'file_size' => strlen($binary),
            'uploaded_by' => $uploader->id,
            'created_at' => $now,
        ]);
    }
}
