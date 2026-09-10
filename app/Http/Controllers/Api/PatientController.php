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
            'data' => $this->presentProfile($row),
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
            'data' => $this->presentProfile($row),
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
            'marital_status' => 'nullable|string|max:30',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'emergency_name' => 'nullable|string|max:200',
            'emergency_relationship' => 'nullable|string|max:100',
            'emergency_phone' => 'nullable|string|max:30',
            'id_type' => 'nullable|string|max:50',
            'id_number' => 'nullable|string|max:255',
            'allergies' => 'nullable|string',
            'conditions' => 'nullable|string',
            'medications' => 'nullable|string',
            'notes' => 'nullable|string',
            'id_proof' => 'nullable|array',
            'id_proof.name' => 'nullable|string|max:500',
            'id_proof.type' => 'nullable|string|max:255',
            'id_proof.content' => 'nullable|string',
        ]);

        $now = now();

        DB::table('patients')->where('id', $bin)->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'status' => $validated['status'] ?? $row->status,
            'blood_group' => $validated['blood_group'] ?? null,
            'marital_status' => $validated['marital_status'] ?? $row->marital_status,
            'updated_at' => $now,
        ]);

        $this->upsertContact($bin, 'phone', $validated['phone'] ?? null);
        $this->upsertContact($bin, 'email', $validated['email'] ?? null);
        $this->upsertAddress($bin, $validated, $now);
        $this->upsertEmergency($bin, $validated, $now);
        $this->upsertIdentifier($bin, $validated, $now);

        if (array_key_exists('allergies', $validated)) {
            $this->replaceAllergies($bin, $validated['allergies'] ?? null, $now);
        }
        if (array_key_exists('conditions', $validated)) {
            $this->replaceConditions($bin, $validated['conditions'] ?? null, $now);
        }
        if (array_key_exists('medications', $validated) || array_key_exists('notes', $validated)) {
            $this->upsertHistoryText($bin, $validated, $now);
        }

        $this->storeIdProof($row->hospital_id, $bin, $validated['id_proof'] ?? null, $now);

        $fresh = DB::table('patients')->where('id', $bin)->first();

        return response()->json([
            'success' => true,
            'message' => 'Patient updated successfully',
            'data' => $this->presentProfile($fresh),
        ]);
    }

    public function storeDocument(Request $request, string $id): JsonResponse
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
            'document_type' => 'nullable|string|max:100',
            'file' => 'required|array',
            'file.name' => 'required|string|max:500',
            'file.type' => 'nullable|string|max:255',
            'file.content' => 'required|string',
        ]);

        $proof = $validated['file'];
        $proofType = $validated['document_type'] ?? 'uploaded';
        $this->storeIdProof($row->hospital_id, $bin, $proof, now(), $proofType);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded',
            'data' => $this->presentProfile($row),
        ], 201);
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

    private function storeIdProof($hospitalId, string $patientId, ?array $proof, $now, string $documentType = 'id_proof'): void
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

        $key = 'patient-documents/'.bin2hex($patientId).'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        Storage::disk('local')->put($key, $binary);

        DB::table('documents')->insert([
            'id' => UuidBin::generate(),
            'hospital_id' => $hospitalId,
            'patient_id' => $patientId,
            'document_type' => $documentType ?: 'uploaded',
            'storage_provider' => 'local',
            'storage_key' => $key,
            'original_filename' => $name,
            'mime_type' => $proof['type'] ?? 'application/octet-stream',
            'file_size' => strlen($binary),
            'uploaded_by' => $uploader->id,
            'created_at' => $now,
        ]);
    }

    private function presentProfile(object $row): array
    {
        $id = $row->id;
        $phone = $this->primaryContact($id, 'phone');
        $email = $this->primaryContact($id, 'email');
        $base = $this->present($row, $phone, $email);

        $addressRow = DB::table('patient_addresses')->where('patient_id', $id)->orderByDesc('is_primary')->first();
        $address = null;
        if ($addressRow) {
            $address = [
                'address_line_1' => $addressRow->address_line_1,
                'address_line_2' => $addressRow->address_line_2,
                'city' => $addressRow->city,
                'state' => $addressRow->state,
                'postal_code' => $addressRow->postal_code,
                'country' => $addressRow->country,
            ];
        }

        $emergencyRow = DB::table('patient_emergency_contacts')->where('patient_id', $id)->orderByDesc('is_primary')->first();
        $emergency = $emergencyRow ? [
            'name' => $emergencyRow->name,
            'relationship' => $emergencyRow->relationship,
            'phone' => $emergencyRow->phone,
        ] : null;

        $identifierRow = DB::table('patient_identifiers')->where('patient_id', $id)->orderByDesc('is_primary')->first();
        $identifier = $identifierRow ? [
            'type' => $identifierRow->identifier_type,
            'number' => $identifierRow->identifier_value,
        ] : null;

        $allergies = DB::table('patient_allergies')->where('patient_id', $id)->orderBy('recorded_at')->pluck('allergen')->filter()->values()->all();
        $conditions = DB::table('patient_conditions')->where('patient_id', $id)->orderBy('created_at')->pluck('condition_name')->filter()->values()->all();

        $history = DB::table('patient_medical_history')->where('patient_id', $id)->orderBy('created_at')->get();
        $medications = [];
        $notes = [];
        foreach ($history as $item) {
            if (strcasecmp((string) $item->condition_name, 'Current medications') === 0) {
                $medications[] = $item->description ?: $item->notes;
            } elseif (strcasecmp((string) $item->condition_name, 'Registration notes') === 0) {
                $notes[] = $item->notes ?: $item->description;
            }
        }

        $visits = $this->profileVisits($id);
        $appointments = $this->profileAppointments($id);
        $documents = $this->profileDocuments($id);
        $invoices = $this->profileInvoices($id);

        $upcoming = collect($appointments)->filter(function ($item) {
            $status = strtolower((string) ($item['status'] ?? ''));
            if (in_array($status, ['cancelled', 'canceled', 'completed', 'closed'], true)) {
                return false;
            }
            $date = substr((string) ($item['appointment_date'] ?? ''), 0, 10);

            return $date === '' || $date >= now()->toDateString();
        })->count();

        $billed = collect($invoices)->sum(fn ($i) => (float) $i['amount']);
        $paid = collect($invoices)->sum(fn ($i) => (float) $i['amount_paid']);

        $formattedAddress = $this->formatAddress($address);

        return array_merge($base, [
            'nationality' => $address['country'] ?? 'India',
            'address' => $address,
            'address_text' => $formattedAddress,
            'emergency' => $emergency,
            'identifier' => $identifier,
            'allergies' => array_values($allergies),
            'conditions' => array_values($conditions),
            'medications' => array_values(array_filter($medications)),
            'notes' => trim(implode("\n", array_filter($notes))),
            'summary' => [
                'visits' => count($visits),
                'conditions' => count($conditions),
                'allergies' => count($allergies),
                'upcoming_appointments' => $upcoming,
            ],
            'visits' => $visits,
            'diagnoses' => $this->profileDiagnoses($id),
            'prescriptions' => $this->profilePrescriptions($id),
            'lab_reports' => $this->profileLab($id),
            'radiology' => $this->profileRadiology($id),
            'clinical_notes' => $this->profileNotes($id),
            'appointments' => $appointments,
            'documents' => $documents,
            'invoices' => $invoices,
            'billing' => [
                'total_billed' => $billed,
                'total_paid' => $paid,
                'outstanding' => max(0, $billed - $paid),
            ],
        ]);
    }

    private function formatAddress(?array $address): string
    {
        if (! $address) {
            return '';
        }
        $line = trim(implode(', ', array_filter([$address['address_line_1'] ?? null, $address['address_line_2'] ?? null])));
        $cityState = trim(implode(', ', array_filter([$address['city'] ?? null, $address['state'] ?? null])));
        $pin = trim((string) ($address['postal_code'] ?? ''));
        $head = trim(implode(', ', array_filter([$line, $cityState])));
        if ($head === '' && $pin === '') {
            return '';
        }

        return $pin !== '' && $head !== '' ? $head.' - '.$pin : ($head !== '' ? $head : $pin);
    }

    private function doctorName($doctorId): string
    {
        if (! $doctorId) {
            return '';
        }
        $row = DB::table('doctors as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.id', $doctorId)
            ->first(['u.first_name', 'u.last_name']);

        return $row ? trim(($row->first_name ?? '').' '.($row->last_name ?? '')) : '';
    }

    private function departmentName($departmentId): string
    {
        if (! $departmentId) {
            return '';
        }

        return (string) (DB::table('departments')->where('id', $departmentId)->value('name') ?? '');
    }

    private function profileVisits($patientId): array
    {
        $rows = DB::table('encounters as e')
            ->leftJoin('diagnoses as dg', function ($join) {
                $join->on('dg.encounter_id', '=', 'e.id')->where('dg.diagnosis_type', '=', 'primary');
            })
            ->where('e.patient_id', $patientId)
            ->orderByDesc('e.started_at')
            ->limit(100)
            ->get([
                'e.id',
                'e.encounter_number',
                'e.encounter_type',
                'e.status',
                'e.started_at',
                'e.doctor_id',
                'e.department_id',
                'dg.diagnosis_name',
                'dg.notes as diagnosis_notes',
            ]);

        return $rows->map(function ($row) {
            return [
                'id' => UuidBin::from($row->id),
                'date' => $row->started_at,
                'visit_type' => $row->encounter_type,
                'doctor' => $this->doctorName($row->doctor_id) ?: '—',
                'department' => $this->departmentName($row->department_id) ?: '—',
                'diagnosis' => $row->diagnosis_name ?: $row->diagnosis_notes,
                'status' => $row->status,
                'number' => $row->encounter_number,
            ];
        })->values()->all();
    }

    private function profileAppointments($patientId): array
    {
        $rows = DB::table('appointments')
            ->where('patient_id', $patientId)
            ->orderByDesc('appointment_date')
            ->orderByDesc('start_time')
            ->limit(100)
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => UuidBin::from($row->id),
                'appointment_number' => $row->appointment_number,
                'appointment_date' => $row->appointment_date,
                'start_time' => $row->start_time,
                'appointment_type' => $row->appointment_type,
                'status' => $row->status,
                'doctor' => $this->doctorName($row->doctor_id) ?: '—',
                'department' => $this->departmentName($row->department_id) ?: '—',
            ];
        })->values()->all();
    }

    private function profileDocuments($patientId): array
    {
        $rows = DB::table('documents')
            ->where('patient_id', $patientId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'document_type', 'original_filename', 'created_at', 'mime_type', 'file_size']);

        return $rows->map(function ($row) {
            return [
                'id' => UuidBin::from($row->id),
                'document_type' => $row->document_type,
                'title' => $row->original_filename,
                'created_at' => $row->created_at,
                'mime_type' => $row->mime_type,
                'file_size' => $row->file_size,
                'doctor' => '—',
                'department' => '—',
            ];
        })->values()->all();
    }

    private function profileInvoices($patientId): array
    {
        $rows = DB::table('invoices')
            ->where('patient_id', $patientId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return $rows->map(function ($row) {
            $service = DB::table('invoice_items')->where('invoice_id', $row->id)->value('description');

            return [
                'id' => UuidBin::from($row->id),
                'invoice_number' => $row->invoice_number,
                'date' => $row->issued_at ?: $row->created_at,
                'service' => $service ?: $row->invoice_type,
                'amount' => (float) $row->total,
                'amount_paid' => (float) $row->amount_paid,
                'status' => $row->status,
            ];
        })->values()->all();
    }

    private function profileDiagnoses($patientId): array
    {
        return DB::table('diagnoses')
            ->where('patient_id', $patientId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => UuidBin::from($row->id),
                    'date' => $row->created_at,
                    'name' => $row->diagnosis_name,
                    'status' => $row->status,
                    'notes' => $row->notes,
                ];
            })->values()->all();
    }

    private function profilePrescriptions($patientId): array
    {
        return DB::table('prescriptions')
            ->where('patient_id', $patientId)
            ->orderByDesc('issued_at')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => UuidBin::from($row->id),
                    'date' => $row->issued_at,
                    'number' => $row->prescription_number,
                    'status' => $row->status,
                    'doctor' => $this->doctorName($row->doctor_id) ?: '—',
                ];
            })->values()->all();
    }

    private function profileLab($patientId): array
    {
        return DB::table('lab_orders')
            ->where('patient_id', $patientId)
            ->orderByDesc('ordered_at')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => UuidBin::from($row->id),
                    'date' => $row->ordered_at,
                    'number' => $row->order_number,
                    'status' => $row->status,
                    'priority' => $row->priority,
                ];
            })->values()->all();
    }

    private function profileRadiology($patientId): array
    {
        return DB::table('radiology_orders')
            ->where('patient_id', $patientId)
            ->orderByDesc('ordered_at')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => UuidBin::from($row->id),
                    'date' => $row->ordered_at,
                    'number' => $row->order_number,
                    'status' => $row->status,
                    'priority' => $row->priority,
                ];
            })->values()->all();
    }

    private function profileNotes($patientId): array
    {
        return DB::table('clinical_notes as n')
            ->join('encounters as e', 'e.id', '=', 'n.encounter_id')
            ->where('e.patient_id', $patientId)
            ->orderByDesc('n.created_at')
            ->limit(100)
            ->get(['n.id', 'n.note_type', 'n.content', 'n.created_at'])
            ->map(function ($row) {
                return [
                    'id' => UuidBin::from($row->id),
                    'date' => $row->created_at,
                    'type' => $row->note_type,
                    'content' => $row->content,
                ];
            })->values()->all();
    }

    private function upsertAddress(string $patientId, array $validated, $now): void
    {
        $line1 = trim((string) ($validated['address_line_1'] ?? ''));
        if ($line1 === '') {
            return;
        }

        $existing = DB::table('patient_addresses')->where('patient_id', $patientId)->orderByDesc('is_primary')->first();
        $payload = [
            'address_line_1' => $line1,
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('patient_addresses')->where('id', $existing->id)->update($payload);

            return;
        }

        DB::table('patient_addresses')->insert(array_merge($payload, [
            'id' => UuidBin::generate(),
            'patient_id' => $patientId,
            'address_type' => 'home',
            'country' => 'India',
            'is_primary' => 1,
            'created_at' => $now,
        ]));
    }

    private function upsertEmergency(string $patientId, array $validated, $now): void
    {
        $name = trim((string) ($validated['emergency_name'] ?? ''));
        $phone = trim((string) ($validated['emergency_phone'] ?? ''));
        if ($name === '' || $phone === '') {
            return;
        }

        $existing = DB::table('patient_emergency_contacts')->where('patient_id', $patientId)->orderByDesc('is_primary')->first();
        $payload = [
            'name' => $name,
            'relationship' => $validated['emergency_relationship'] ?? null,
            'phone' => $phone,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('patient_emergency_contacts')->where('id', $existing->id)->update($payload);

            return;
        }

        DB::table('patient_emergency_contacts')->insert(array_merge($payload, [
            'id' => UuidBin::generate(),
            'patient_id' => $patientId,
            'is_primary' => 1,
            'created_at' => $now,
        ]));
    }

    private function upsertIdentifier(string $patientId, array $validated, $now): void
    {
        $type = trim((string) ($validated['id_type'] ?? ''));
        $number = trim((string) ($validated['id_number'] ?? ''));
        if ($type === '' || $number === '') {
            return;
        }

        $existing = DB::table('patient_identifiers')->where('patient_id', $patientId)->orderByDesc('is_primary')->first();
        $payload = [
            'identifier_type' => $type,
            'identifier_value' => $number,
        ];

        if ($existing) {
            DB::table('patient_identifiers')->where('id', $existing->id)->update($payload);

            return;
        }

        DB::table('patient_identifiers')->insert(array_merge($payload, [
            'id' => UuidBin::generate(),
            'patient_id' => $patientId,
            'is_primary' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    private function replaceAllergies(string $patientId, ?string $text, $now): void
    {
        DB::table('patient_allergies')->where('patient_id', $patientId)->delete();
        foreach ($this->lines($text) as $allergen) {
            DB::table('patient_allergies')->insert([
                'id' => UuidBin::generate(),
                'patient_id' => $patientId,
                'allergen' => $allergen,
                'status' => 'active',
                'recorded_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function replaceConditions(string $patientId, ?string $text, $now): void
    {
        DB::table('patient_conditions')->where('patient_id', $patientId)->delete();
        foreach ($this->lines($text) as $condition) {
            DB::table('patient_conditions')->insert([
                'id' => UuidBin::generate(),
                'patient_id' => $patientId,
                'condition_name' => $condition,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function upsertHistoryText(string $patientId, array $validated, $now): void
    {
        if (array_key_exists('medications', $validated)) {
            DB::table('patient_medical_history')
                ->where('patient_id', $patientId)
                ->where('condition_name', 'Current medications')
                ->delete();
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
        }

        if (array_key_exists('notes', $validated)) {
            DB::table('patient_medical_history')
                ->where('patient_id', $patientId)
                ->where('condition_name', 'Registration notes')
                ->delete();
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
        }
    }
}
