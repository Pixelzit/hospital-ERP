<?php

use App\Models\Hospital;
use App\Support\UuidBin;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hospital = Hospital::query()->first();

if (! $hospital) {
    fwrite(STDERR, "No hospital row found.\n");
    exit(1);
}

$hospitalId = $hospital->getRawOriginal('id');

$patients = [
    ['mrn' => 'HCS-26-101', 'first_name' => 'Priya', 'last_name' => 'Sharma', 'gender' => 'Female', 'dob' => '1996-04-18', 'blood' => 'B+', 'status' => 'active', 'phone' => '9876543210', 'email' => 'priya.sharma@email.com', 'created' => '2026-01-15 09:20:00', 'occupation' => 'Teacher', 'marital' => 'Married'],
    ['mrn' => 'HCS-26-102', 'first_name' => 'Rohan', 'last_name' => 'Mehta', 'gender' => 'Male', 'dob' => '1958-11-02', 'blood' => 'O+', 'status' => 'inactive', 'phone' => '9810012345', 'email' => 'rohan.mehta@email.com', 'created' => '2025-11-02 11:05:00', 'occupation' => 'Retired', 'marital' => 'Married'],
    ['mrn' => 'HCS-26-103', 'first_name' => 'Aarav', 'last_name' => 'Singh', 'gender' => 'Male', 'dob' => '2018-07-21', 'blood' => 'A+', 'status' => 'active', 'phone' => '9900112233', 'email' => 'neha.singh@email.com', 'created' => '2026-03-20 08:40:00', 'occupation' => null, 'marital' => 'Single'],
    ['mrn' => 'HCS-26-104', 'first_name' => 'Neha', 'last_name' => 'Kapoor', 'gender' => 'Female', 'dob' => '1988-02-09', 'blood' => 'AB+', 'status' => 'active', 'phone' => '9821098765', 'email' => 'neha.kapoor@email.com', 'created' => '2026-02-04 14:12:00', 'occupation' => 'Accountant', 'marital' => 'Married'],
    ['mrn' => 'HCS-26-105', 'first_name' => 'Vikram', 'last_name' => 'Patel', 'gender' => 'Male', 'dob' => '1974-09-30', 'blood' => 'B-', 'status' => 'active', 'phone' => '9765432109', 'email' => 'vikram.patel@email.com', 'created' => '2025-12-18 16:45:00', 'occupation' => 'Business', 'marital' => 'Married'],
    ['mrn' => 'HCS-26-106', 'first_name' => 'Ananya', 'last_name' => 'Iyer', 'gender' => 'Female', 'dob' => '2001-06-14', 'blood' => 'O-', 'status' => 'active', 'phone' => '9845123678', 'email' => 'ananya.iyer@email.com', 'created' => '2026-04-11 10:08:00', 'occupation' => 'Student', 'marital' => 'Single'],
    ['mrn' => 'HCS-26-107', 'first_name' => 'Suresh', 'last_name' => 'Nair', 'gender' => 'Male', 'dob' => '1949-01-05', 'blood' => 'A-', 'status' => 'inactive', 'phone' => '9898989898', 'email' => 'suresh.nair@email.com', 'created' => '2025-08-22 09:30:00', 'occupation' => 'Retired', 'marital' => 'Widowed'],
    ['mrn' => 'HCS-26-108', 'first_name' => 'Meera', 'last_name' => 'Joshi', 'gender' => 'Female', 'dob' => '2012-12-01', 'blood' => 'B+', 'status' => 'active', 'phone' => '9711122233', 'email' => 'meera.joshi@email.com', 'created' => '2026-05-03 13:22:00', 'occupation' => null, 'marital' => 'Single'],
    ['mrn' => 'HCS-26-109', 'first_name' => 'Arjun', 'last_name' => 'Malhotra', 'gender' => 'Male', 'dob' => '1983-03-27', 'blood' => 'O+', 'status' => 'active', 'phone' => '9812345670', 'email' => 'arjun.malhotra@email.com', 'created' => '2026-01-28 17:55:00', 'occupation' => 'Engineer', 'marital' => 'Married'],
    ['mrn' => 'HCS-26-110', 'first_name' => 'Fatima', 'last_name' => 'Khan', 'gender' => 'Female', 'dob' => '1979-08-16', 'blood' => 'A+', 'status' => 'active', 'phone' => '9933445566', 'email' => 'fatima.khan@email.com', 'created' => '2025-10-09 08:15:00', 'occupation' => 'Nurse', 'marital' => 'Married'],
    ['mrn' => 'HCS-26-111', 'first_name' => 'Kabir', 'last_name' => 'Das', 'gender' => 'Male', 'dob' => '1999-05-08', 'blood' => 'AB-', 'status' => 'inactive', 'phone' => '9876501234', 'email' => 'kabir.das@email.com', 'created' => '2026-06-19 11:41:00', 'occupation' => 'Designer', 'marital' => 'Single'],
    ['mrn' => 'HCS-26-112', 'first_name' => 'Lakshmi', 'last_name' => 'Rao', 'gender' => 'Female', 'dob' => '1964-10-23', 'blood' => 'B+', 'status' => 'active', 'phone' => '9848011223', 'email' => 'lakshmi.rao@email.com', 'created' => '2025-09-14 15:18:00', 'occupation' => 'Homemaker', 'marital' => 'Married'],
    ['mrn' => 'HCS-26-113', 'first_name' => 'Ishaan', 'last_name' => 'Gupta', 'gender' => 'Male', 'dob' => '2016-02-11', 'blood' => 'O+', 'status' => 'active', 'phone' => '9818090909', 'email' => 'ishaangupta.family@email.com', 'created' => '2026-07-07 09:05:00', 'occupation' => null, 'marital' => 'Single'],
    ['mrn' => 'HCS-26-114', 'first_name' => 'Sunita', 'last_name' => 'Yadav', 'gender' => 'Female', 'dob' => '1971-12-29', 'blood' => 'A+', 'status' => 'active', 'phone' => '9723456781', 'email' => 'sunita.yadav@email.com', 'created' => '2026-02-22 12:36:00', 'occupation' => 'Shop owner', 'marital' => 'Married'],
    ['mrn' => 'HCS-26-115', 'first_name' => 'Rahul', 'last_name' => 'Verma', 'gender' => 'Male', 'dob' => '1992-07-03', 'blood' => 'B+', 'status' => 'active', 'phone' => '9911223344', 'email' => 'rahul.verma@email.com', 'created' => '2026-08-16 18:02:00', 'occupation' => 'Software engineer', 'marital' => 'Single'],
    ['mrn' => 'HCS-26-116', 'first_name' => 'Kavya', 'last_name' => 'Menon', 'gender' => 'Female', 'dob' => '2008-09-19', 'blood' => 'O+', 'status' => 'active', 'phone' => '9895012345', 'email' => 'kavya.menon@email.com', 'created' => '2026-03-08 10:50:00', 'occupation' => 'Student', 'marital' => 'Single'],
    ['mrn' => 'HCS-26-117', 'first_name' => 'Harish', 'last_name' => 'Chandra', 'gender' => 'Male', 'dob' => '1955-04-12', 'blood' => 'A+', 'status' => 'inactive', 'phone' => '9811776655', 'email' => 'harish.chandra@email.com', 'created' => '2025-07-30 07:44:00', 'occupation' => 'Retired', 'marital' => 'Married'],
    ['mrn' => 'HCS-26-118', 'first_name' => 'Pooja', 'last_name' => 'Bansal', 'gender' => 'Female', 'dob' => '1985-11-25', 'blood' => 'AB+', 'status' => 'active', 'phone' => '9813131313', 'email' => 'pooja.bansal@email.com', 'created' => '2026-09-01 09:12:00', 'occupation' => 'Lawyer', 'marital' => 'Married'],
];

$inserted = 0;

foreach ($patients as $item) {
    $exists = DB::table('patients')
        ->where('hospital_id', $hospitalId)
        ->where('mrn', $item['mrn'])
        ->exists();

    if ($exists) {
        continue;
    }

    $id = UuidBin::generate();

    DB::table('patients')->insert([
        'id' => $id,
        'hospital_id' => $hospitalId,
        'mrn' => $item['mrn'],
        'first_name' => $item['first_name'],
        'last_name' => $item['last_name'],
        'date_of_birth' => $item['dob'],
        'gender' => $item['gender'],
        'blood_group' => $item['blood'],
        'marital_status' => $item['marital'],
        'occupation' => $item['occupation'],
        'status' => $item['status'],
        'created_at' => $item['created'],
        'updated_at' => $item['created'],
    ]);

    DB::table('patient_contacts')->insert([
        [
            'id' => UuidBin::generate(),
            'patient_id' => $id,
            'contact_type' => 'phone',
            'value' => $item['phone'],
            'is_primary' => 1,
            'is_verified' => 0,
            'created_at' => $item['created'],
            'updated_at' => $item['created'],
        ],
        [
            'id' => UuidBin::generate(),
            'patient_id' => $id,
            'contact_type' => 'email',
            'value' => $item['email'],
            'is_primary' => 1,
            'is_verified' => 0,
            'created_at' => $item['created'],
            'updated_at' => $item['created'],
        ],
    ]);

    $inserted++;
}

echo "Inserted {$inserted} patients.\n";
