<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SessionUser;
use App\Support\UuidBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = DB::table('appointments as a')
            ->leftJoin('patients as p', 'p.id', '=', 'a.patient_id')
            ->leftJoin('doctors as d', 'd.id', '=', 'a.doctor_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->orderByDesc('a.appointment_date')
            ->orderByDesc('a.start_time')
            ->limit(100)
            ->get([
                'a.id',
                'a.appointment_number',
                'a.appointment_date',
                'a.start_time',
                'a.appointment_type',
                'a.status',
                'p.first_name as patient_first',
                'p.last_name as patient_last',
                'u.first_name as doctor_first',
                'u.last_name as doctor_last',
            ]);

        $data = $rows->map(function ($row) {
            return [
                'id' => UuidBin::from($row->id),
                'appointment_number' => $row->appointment_number,
                'patient' => trim(($row->patient_first ?? '').' '.($row->patient_last ?? '')) ?: '—',
                'doctor' => trim(($row->doctor_first ?? '').' '.($row->doctor_last ?? '')) ?: '—',
                'appointment_date' => $row->appointment_date,
                'start_time' => $row->start_time,
                'appointment_type' => $row->appointment_type,
                'status' => $row->status,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => (int) DB::table('appointments')->count(),
        ]);
    }

    public function departments(): JsonResponse
    {
        $rows = DB::table('departments')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => [
                'id' => UuidBin::from($row->id),
                'name' => $row->name,
                'code' => $row->code,
            ])->values(),
        ]);
    }

    public function slots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => 'required|string',
            'date' => 'required|date',
            'ignore_appointment_id' => 'nullable|string',
        ]);

        $doctorBin = UuidBin::to($validated['doctor_id']);
        if (! $doctorBin || ! DB::table('doctors')->where('id', $doctorBin)->exists()) {
            return response()->json(['success' => false, 'message' => 'Doctor not found.'], 404);
        }

        $ignore = UuidBin::to($validated['ignore_appointment_id'] ?? null);
        $booked = DB::table('appointments')
            ->where('doctor_id', $doctorBin)
            ->whereDate('appointment_date', $validated['date'])
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))
            ->pluck('start_time')
            ->map(fn ($t) => substr((string) $t, 0, 5))
            ->all();

        $slots = [];
        $today = now()->toDateString();
        $nowMinutes = now()->hour * 60 + now()->minute;
        for ($minutes = 9 * 60; $minutes <= 16 * 60 + 40; $minutes += 20) {
            $label = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            if (in_array($label, $booked, true)) {
                continue;
            }
            if ($validated['date'] === $today && $minutes <= $nowMinutes) {
                continue;
            }
            $slots[] = $label;
        }

        return response()->json([
            'success' => true,
            'data' => $slots,
            'booked' => array_values($booked),
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $patient = $this->patient($id);
        if ($patient instanceof JsonResponse) {
            return $patient;
        }

        $validated = $request->validate([
            'department_id' => 'required|string',
            'doctor_id' => 'required|string',
            'appointment_date' => 'required|date',
            'start_time' => 'required|string',
            'appointment_type' => 'required|string|max:50',
            'reason' => 'nullable|string|max:500',
        ]);

        $deptBin = UuidBin::to($validated['department_id']);
        $doctorBin = UuidBin::to($validated['doctor_id']);
        if (! $deptBin || ! DB::table('departments')->where('id', $deptBin)->exists()) {
            return response()->json(['success' => false, 'message' => 'Department not found.'], 422);
        }
        if (! $doctorBin || ! DB::table('doctors')->where('id', $doctorBin)->exists()) {
            return response()->json(['success' => false, 'message' => 'Doctor not found.'], 422);
        }

        $linked = DB::table('doctor_departments')
            ->where('doctor_id', $doctorBin)
            ->where('department_id', $deptBin)
            ->exists();
        if (! $linked) {
            return response()->json(['success' => false, 'message' => 'Doctor is not assigned to this department.'], 422);
        }

        $start = $this->normalizeTime($validated['start_time']);
        if (! $start) {
            return response()->json(['success' => false, 'message' => 'Time slot is invalid.'], 422);
        }

        if ($this->slotTaken($doctorBin, $validated['appointment_date'], $start)) {
            return response()->json(['success' => false, 'message' => 'That time slot is no longer available.'], 409);
        }

        $idBin = UuidBin::generate();
        $number = $this->nextNumber($patient->hospital_id);
        $now = now();
        $end = $this->endTime($start);

        DB::table('appointments')->insert([
            'id' => $idBin,
            'hospital_id' => $patient->hospital_id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorBin,
            'department_id' => $deptBin,
            'appointment_number' => $number,
            'appointment_date' => $validated['appointment_date'],
            'start_time' => $start,
            'end_time' => $end,
            'appointment_type' => $validated['appointment_type'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'scheduled',
            'created_by' => SessionUser::actorBinary(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->statusHistory($idBin, null, 'scheduled', 'Booked');

        return response()->json([
            'success' => true,
            'message' => 'Appointment booked',
            'data' => $this->present($idBin),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $row = $this->appointment($id);
        if ($row instanceof JsonResponse) {
            return $row;
        }

        $status = strtolower((string) $row->status);
        if (in_array($status, ['cancelled', 'canceled', 'completed'], true)) {
            return response()->json(['success' => false, 'message' => 'This appointment cannot be rescheduled.'], 422);
        }

        $validated = $request->validate([
            'department_id' => 'nullable|string',
            'doctor_id' => 'nullable|string',
            'appointment_date' => 'required|date',
            'start_time' => 'required|string',
            'appointment_type' => 'nullable|string|max:50',
            'reason' => 'nullable|string|max:500',
        ]);

        $doctorBin = UuidBin::to($validated['doctor_id'] ?? '') ?: $row->doctor_id;
        $deptBin = UuidBin::to($validated['department_id'] ?? '') ?: $row->department_id;
        $start = $this->normalizeTime($validated['start_time']);
        if (! $start) {
            return response()->json(['success' => false, 'message' => 'Time slot is invalid.'], 422);
        }

        if ($this->slotTaken($doctorBin, $validated['appointment_date'], $start, $row->id)) {
            return response()->json(['success' => false, 'message' => 'That time slot is no longer available.'], 409);
        }

        DB::table('appointments')->where('id', $row->id)->update([
            'doctor_id' => $doctorBin,
            'department_id' => $deptBin,
            'appointment_date' => $validated['appointment_date'],
            'start_time' => $start,
            'end_time' => $this->endTime($start),
            'appointment_type' => $validated['appointment_type'] ?? $row->appointment_type,
            'reason' => $validated['reason'] ?? $row->reason,
            'updated_at' => now(),
        ]);

        $this->statusHistory($row->id, $row->status, $row->status, 'Rescheduled');

        return response()->json([
            'success' => true,
            'message' => 'Appointment rescheduled',
            'data' => $this->present($row->id),
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $row = $this->appointment($id);
        if ($row instanceof JsonResponse) {
            return $row;
        }

        $status = strtolower((string) $row->status);
        if (in_array($status, ['cancelled', 'canceled'], true)) {
            return response()->json(['success' => false, 'message' => 'Appointment is already cancelled.'], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::table('appointments')->where('id', $row->id)->update([
            'status' => 'cancelled',
            'reason' => $validated['reason'],
            'updated_at' => now(),
        ]);

        $this->statusHistory($row->id, $row->status, 'cancelled', $validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Appointment cancelled',
            'data' => $this->present($row->id),
        ]);
    }

    private function patient(string $id)
    {
        $bin = UuidBin::to($id);
        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'Patient not found.'], 404);
        }
        $row = DB::table('patients')->where('id', $bin)->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Patient not found.'], 404);
        }

        return $row;
    }

    private function appointment(string $id)
    {
        $bin = UuidBin::to($id);
        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }
        $row = DB::table('appointments')->where('id', $bin)->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }

        return $row;
    }

    private function present($idBin): array
    {
        $row = DB::table('appointments')->where('id', $idBin)->first();

        return [
            'id' => UuidBin::from($row->id),
            'appointment_number' => $row->appointment_number,
            'appointment_date' => $row->appointment_date,
            'start_time' => $row->start_time,
            'end_time' => $row->end_time,
            'appointment_type' => $row->appointment_type,
            'status' => $row->status,
            'reason' => $row->reason,
            'doctor_id' => UuidBin::from($row->doctor_id),
            'department_id' => UuidBin::from($row->department_id),
            'patient_id' => UuidBin::from($row->patient_id),
        ];
    }

    private function slotTaken($doctorBin, string $date, string $start, $ignoreId = null): bool
    {
        return DB::table('appointments')
            ->where('doctor_id', $doctorBin)
            ->whereDate('appointment_date', $date)
            ->where('start_time', $start)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function normalizeTime(string $time): ?string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($time), $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        return null;
    }

    private function endTime(string $start): string
    {
        [$h, $m] = array_map('intval', explode(':', $start));
        $total = $h * 60 + $m + 20;

        return sprintf('%02d:%02d:00', intdiv($total, 60), $total % 60);
    }

    private function nextNumber($hospitalId): string
    {
        $prefix = 'APT-'.now()->format('ymd').'-';
        $last = DB::table('appointments')
            ->where('hospital_id', $hospitalId)
            ->where('appointment_number', 'like', $prefix.'%')
            ->orderByDesc('appointment_number')
            ->value('appointment_number');
        $n = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $m)) {
            $n = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function statusHistory($appointmentId, ?string $old, string $new, string $reason): void
    {
        DB::table('appointment_status_history')->insert([
            'id' => UuidBin::generate(),
            'appointment_id' => $appointmentId,
            'old_status' => $old,
            'new_status' => $new,
            'changed_by' => SessionUser::actorBinary(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
