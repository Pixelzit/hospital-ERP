<?php

use App\Models\Hospital;
use App\Support\UuidBin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hospital = Hospital::query()->first();
if (! $hospital) {
    fwrite(STDERR, "No hospital row found.\n");
    exit(1);
}

$hospitalId = $hospital->getRawOriginal('id');
$now = now();

$doctors = DB::table('doctors')->where('hospital_id', $hospitalId)->get();
$users = DB::table('users')->where('hospital_id', $hospitalId)->orderBy('created_at')->get();
if ($doctors->isEmpty() || $users->isEmpty()) {
    fwrite(STDERR, "Need at least one doctor and one user.\n");
    exit(1);
}

$userId = $users->first()->id;

$departments = DB::table('departments')->where('hospital_id', $hospitalId)->get();
if ($departments->isEmpty()) {
    $defs = [
        ['name' => 'General Medicine', 'code' => 'GM'],
        ['name' => 'Radiology', 'code' => 'RAD'],
        ['name' => 'Laboratory', 'code' => 'LAB'],
    ];
    foreach ($defs as $def) {
        DB::table('departments')->insert([
            'id' => UuidBin::generate(),
            'hospital_id' => $hospitalId,
            'name' => $def['name'],
            'code' => $def['code'],
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    $departments = DB::table('departments')->where('hospital_id', $hospitalId)->get();
}

$gm = $departments->firstWhere('code', 'GM') ?: $departments->first();

foreach ($doctors as $doctor) {
    $exists = DB::table('doctor_departments')
        ->where('doctor_id', $doctor->id)
        ->where('department_id', $gm->id)
        ->exists();
    if (! $exists) {
        DB::table('doctor_departments')->insert([
            'doctor_id' => $doctor->id,
            'department_id' => $gm->id,
            'is_primary' => 1,
        ]);
    }
}

$radTest = DB::table('radiology_tests')->where('hospital_id', $hospitalId)->first();
if (! $radTest) {
    $radId = UuidBin::generate();
    DB::table('radiology_tests')->insert([
        'id' => $radId,
        'hospital_id' => $hospitalId,
        'name' => 'Chest X-Ray',
        'code' => 'CXR',
        'modality' => 'X-Ray',
        'status' => 'active',
        'created_at' => $now,
    ]);
    $radTest = DB::table('radiology_tests')->where('id', $radId)->first();
}

$diagnoses = ['Hypertension follow-up', 'Acute bronchitis', 'Type 2 diabetes review', 'Viral fever', 'Gastritis', 'Asthma review'];
$allergens = ['Penicillin', 'Dust', 'Peanuts', 'Sulfa drugs', 'None known'];
$conditions = ['Hypertension', 'Diabetes', 'Asthma', 'Hypothyroidism', 'Migraine'];
$notes = [
    'Patient advised follow-up in 2 weeks.',
    'Vitals stable. Continue current plan.',
    'Counseled on diet and medication adherence.',
];

$patients = DB::table('patients')->where('hospital_id', $hospitalId)->orderBy('created_at')->get();
$seeded = 0;

foreach ($patients as $index => $patient) {
    $pid = $patient->id;
    $slug = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', (string) $patient->mrn) ?: 'P'.($index + 1));
    $doctor = $doctors[$index % $doctors->count()];
    $dept = $departments[$index % $departments->count()];
    $name = trim($patient->first_name.' '.($patient->last_name ?? ''));

    $phone = DB::table('patient_contacts')->where('patient_id', $pid)->where('contact_type', 'phone')->value('value');
    if (! $phone) {
        DB::table('patient_contacts')->insert([
            'id' => UuidBin::generate(),
            'patient_id' => $pid,
            'contact_type' => 'phone',
            'value' => '98'.str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
            'is_primary' => 1,
            'is_verified' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $email = DB::table('patient_contacts')->where('patient_id', $pid)->where('contact_type', 'email')->value('value');
    if (! $email) {
        $local = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $name) ?: 'patient'.$index);
        DB::table('patient_contacts')->insert([
            'id' => UuidBin::generate(),
            'patient_id' => $pid,
            'contact_type' => 'email',
            'value' => $local.'@email.com',
            'is_primary' => 1,
            'is_verified' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    if (! DB::table('patient_addresses')->where('patient_id', $pid)->exists()) {
        DB::table('patient_addresses')->insert([
            'id' => UuidBin::generate(),
            'patient_id' => $pid,
            'address_type' => 'home',
            'address_line_1' => (12 + $index).' MG Road',
            'city' => 'Indore',
            'state' => 'Madhya Pradesh',
            'country' => 'India',
            'postal_code' => '45200'.($index % 10),
            'is_primary' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    if (! DB::table('patient_emergency_contacts')->where('patient_id', $pid)->exists()) {
        DB::table('patient_emergency_contacts')->insert([
            'id' => UuidBin::generate(),
            'patient_id' => $pid,
            'name' => 'Relative of '.$patient->first_name,
            'relationship' => $index % 2 ? 'Spouse' : 'Parent',
            'phone' => '97'.str_pad((string) (20000000 + $index), 8, '0', STR_PAD_LEFT),
            'is_primary' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    if (! DB::table('patient_allergies')->where('patient_id', $pid)->exists()) {
        $allergen = $allergens[$index % count($allergens)];
        if ($allergen !== 'None known') {
            DB::table('patient_allergies')->insert([
                'id' => UuidBin::generate(),
                'patient_id' => $pid,
                'allergen' => $allergen,
                'status' => 'active',
                'recorded_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    if (! DB::table('patient_conditions')->where('patient_id', $pid)->exists()) {
        DB::table('patient_conditions')->insert([
            'id' => UuidBin::generate(),
            'patient_id' => $pid,
            'condition_name' => $conditions[$index % count($conditions)],
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    if (DB::table('appointments')->where('patient_id', $pid)->exists()) {
        continue;
    }

    $completedId = UuidBin::generate();
    $upcomingId = UuidBin::generate();
    $cancelledId = UuidBin::generate();
    $pastDate = $now->copy()->subDays(18 + $index)->toDateString();
    $futureDate = $now->copy()->addDays(5 + $index)->toDateString();
    $cancelDate = $now->copy()->subDays(4)->toDateString();

    DB::table('appointments')->insert([
        [
            'id' => $completedId,
            'hospital_id' => $hospitalId,
            'patient_id' => $pid,
            'doctor_id' => $doctor->id,
            'department_id' => $dept->id,
            'appointment_number' => 'APT-'.$slug.'-1',
            'appointment_date' => $pastDate,
            'start_time' => '10:00:00',
            'end_time' => '10:20:00',
            'appointment_type' => 'OPD',
            'reason' => 'Follow-up',
            'status' => 'completed',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $upcomingId,
            'hospital_id' => $hospitalId,
            'patient_id' => $pid,
            'doctor_id' => $doctor->id,
            'department_id' => $dept->id,
            'appointment_number' => 'APT-'.$slug.'-2',
            'appointment_date' => $futureDate,
            'start_time' => '11:30:00',
            'end_time' => '11:50:00',
            'appointment_type' => 'OPD',
            'reason' => 'Review',
            'status' => 'scheduled',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $cancelledId,
            'hospital_id' => $hospitalId,
            'patient_id' => $pid,
            'doctor_id' => $doctor->id,
            'department_id' => $dept->id,
            'appointment_number' => 'APT-'.$slug.'-3',
            'appointment_date' => $cancelDate,
            'start_time' => '16:00:00',
            'end_time' => '16:20:00',
            'appointment_type' => 'OPD',
            'reason' => 'Patient cancelled',
            'status' => 'cancelled',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $enc1 = UuidBin::generate();
    $enc2 = UuidBin::generate();
    $started1 = $pastDate.' 10:05:00';
    $started2 = $now->copy()->subDays(40 + $index)->format('Y-m-d H:i:s');

    DB::table('encounters')->insert([
        [
            'id' => $enc1,
            'hospital_id' => $hospitalId,
            'patient_id' => $pid,
            'doctor_id' => $doctor->id,
            'department_id' => $dept->id,
            'appointment_id' => $completedId,
            'encounter_number' => 'ENC-'.$slug.'-1',
            'encounter_type' => 'OPD',
            'status' => 'closed',
            'started_at' => $started1,
            'ended_at' => $pastDate.' 10:25:00',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $enc2,
            'hospital_id' => $hospitalId,
            'patient_id' => $pid,
            'doctor_id' => $doctor->id,
            'department_id' => $dept->id,
            'appointment_id' => null,
            'encounter_number' => 'ENC-'.$slug.'-2',
            'encounter_type' => 'OPD',
            'status' => 'closed',
            'started_at' => $started2,
            'ended_at' => $now->copy()->subDays(40 + $index)->addMinutes(25)->format('Y-m-d H:i:s'),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('diagnoses')->insert([
        [
            'id' => UuidBin::generate(),
            'patient_id' => $pid,
            'encounter_id' => $enc1,
            'diagnosis_name' => $diagnoses[$index % count($diagnoses)],
            'diagnosis_type' => 'primary',
            'status' => 'active',
            'notes' => $notes[$index % count($notes)],
            'recorded_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => UuidBin::generate(),
            'patient_id' => $pid,
            'encounter_id' => $enc2,
            'diagnosis_name' => $diagnoses[($index + 2) % count($diagnoses)],
            'diagnosis_type' => 'primary',
            'status' => 'resolved',
            'notes' => 'Earlier visit',
            'recorded_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('prescriptions')->insert([
        'id' => UuidBin::generate(),
        'hospital_id' => $hospitalId,
        'patient_id' => $pid,
        'encounter_id' => $enc1,
        'doctor_id' => $doctor->id,
        'prescription_number' => 'RX-'.$slug,
        'status' => 'active',
        'issued_at' => $started1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('lab_orders')->insert([
        'id' => UuidBin::generate(),
        'hospital_id' => $hospitalId,
        'patient_id' => $pid,
        'encounter_id' => $enc1,
        'order_number' => 'LAB-'.$slug,
        'priority' => 'routine',
        'status' => 'completed',
        'ordered_by' => $userId,
        'ordered_at' => $started1,
    ]);

    DB::table('radiology_orders')->insert([
        'id' => UuidBin::generate(),
        'hospital_id' => $hospitalId,
        'patient_id' => $pid,
        'encounter_id' => $enc2,
        'radiology_test_id' => $radTest->id,
        'order_number' => 'RAD-'.$slug,
        'priority' => 'routine',
        'status' => 'completed',
        'ordered_by' => $userId,
        'ordered_at' => $started2,
    ]);

    DB::table('clinical_notes')->insert([
        'id' => UuidBin::generate(),
        'encounter_id' => $enc1,
        'note_type' => 'progress',
        'content' => $notes[$index % count($notes)].' Patient: '.$name.'.',
        'author_id' => $userId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $docTypes = [
        ['type' => 'prescription', 'title' => 'Prescription '.$slug.'.pdf'],
        ['type' => 'lab_report', 'title' => 'CBC report '.$slug.'.pdf'],
        ['type' => 'uploaded', 'title' => 'Consent form '.$slug.'.pdf'],
    ];
    foreach ($docTypes as $doc) {
        $key = 'patient-documents/dummy-'.bin2hex($pid).'-'.$doc['type'].'.txt';
        Storage::disk('local')->put($key, 'Dummy '.$doc['type'].' for '.$name);
        DB::table('documents')->insert([
            'id' => UuidBin::generate(),
            'hospital_id' => $hospitalId,
            'patient_id' => $pid,
            'encounter_id' => $enc1,
            'document_type' => $doc['type'],
            'storage_provider' => 'local',
            'storage_key' => $key,
            'original_filename' => $doc['title'],
            'mime_type' => 'text/plain',
            'file_size' => 64,
            'uploaded_by' => $userId,
            'created_at' => $now,
        ]);
    }

    $invPaid = UuidBin::generate();
    $invOpen = UuidBin::generate();
    $paidTotal = 1500 + ($index * 50);
    $openTotal = 2800 + ($index * 75);

    DB::table('invoices')->insert([
        [
            'id' => $invPaid,
            'hospital_id' => $hospitalId,
            'patient_id' => $pid,
            'encounter_id' => $enc1,
            'invoice_number' => 'INV-'.$slug.'-1',
            'invoice_type' => 'patient',
            'subtotal' => $paidTotal,
            'discount' => 0,
            'tax' => 0,
            'total' => $paidTotal,
            'amount_paid' => $paidTotal,
            'balance' => 0,
            'status' => 'paid',
            'issued_at' => $started1,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $invOpen,
            'hospital_id' => $hospitalId,
            'patient_id' => $pid,
            'encounter_id' => $enc2,
            'invoice_number' => 'INV-'.$slug.'-2',
            'invoice_type' => 'patient',
            'subtotal' => $openTotal,
            'discount' => 0,
            'tax' => 0,
            'total' => $openTotal,
            'amount_paid' => 0,
            'balance' => $openTotal,
            'status' => 'issued',
            'issued_at' => $started2,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('invoice_items')->insert([
        [
            'id' => UuidBin::generate(),
            'invoice_id' => $invPaid,
            'item_type' => 'consultation',
            'description' => 'OPD consultation',
            'quantity' => 1,
            'unit_price' => $paidTotal,
            'discount' => 0,
            'tax' => 0,
            'total' => $paidTotal,
            'created_at' => $now,
        ],
        [
            'id' => UuidBin::generate(),
            'invoice_id' => $invOpen,
            'item_type' => 'investigation',
            'description' => 'Lab and radiology package',
            'quantity' => 1,
            'unit_price' => $openTotal,
            'discount' => 0,
            'tax' => 0,
            'total' => $openTotal,
            'created_at' => $now,
        ],
    ]);

    DB::table('payments')->insert([
        'id' => UuidBin::generate(),
        'invoice_id' => $invPaid,
        'patient_id' => $pid,
        'amount' => $paidTotal,
        'payment_method' => 'upi',
        'transaction_reference' => 'UPI-'.$slug,
        'status' => 'completed',
        'paid_at' => $started1,
        'received_by' => $userId,
    ]);

    $seeded++;
}

echo "Seeded workspace dummy data for {$seeded} patients (skipped those who already had appointments).\n";
echo 'patients='.DB::table('patients')->count()
    .' appointments='.DB::table('appointments')->count()
    .' encounters='.DB::table('encounters')->count()
    .' invoices='.DB::table('invoices')->count()
    .' documents='.DB::table('documents')->count()
    ."\n";
