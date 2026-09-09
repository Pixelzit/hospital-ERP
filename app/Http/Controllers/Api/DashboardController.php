<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->toDateString();

        $bedsOccupied = (int) DB::table('bed_assignments')
            ->where('status', 'active')
            ->whereNull('released_at')
            ->count();

        if ($bedsOccupied === 0) {
            $bedsOccupied = (int) DB::table('beds')->where('status', 'occupied')->count();
        }

        $icuPatients = (int) DB::table('admissions as a')
            ->leftJoin('bed_assignments as ba', function ($join) {
                $join->on('ba.admission_id', '=', 'a.id')
                    ->where('ba.status', '=', 'active');
            })
            ->leftJoin('beds as b', 'b.id', '=', 'ba.bed_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'b.room_id')
            ->leftJoin('wards as w', 'w.id', '=', 'r.ward_id')
            ->where('a.status', 'admitted')
            ->where(function ($q) {
                $q->where('w.ward_type', 'like', '%icu%')
                    ->orWhere('w.name', 'like', '%icu%');
            })
            ->distinct()
            ->count('a.id');

        if ($icuPatients === 0) {
            $icuPatients = (int) DB::table('admissions')->where('status', 'admitted')->count();
        }

        $appointmentsToday = (int) DB::table('appointments')
            ->whereDate('appointment_date', $today)
            ->count();

        $todayOp = (int) DB::table('appointments')
            ->whereDate('appointment_date', $today)
            ->where('appointment_type', 'OPD')
            ->count();

        if ($todayOp === 0) {
            $todayOp = (int) DB::table('encounters')
                ->whereDate('started_at', $today)
                ->where('encounter_type', 'OPD')
                ->count();
        }

        $patients = DB::table('patients')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['first_name', 'last_name', 'status', 'created_at', 'date_of_birth', 'gender']);

        $doctors = DB::table('doctors as d')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->orderBy('u.first_name')
            ->limit(4)
            ->get([
                'u.first_name',
                'u.last_name',
                'd.specialization',
                'd.status',
            ]);

        $invoiceByMonth = DB::table('invoices')
            ->selectRaw('MONTH(COALESCE(issued_at, created_at)) as month_num, SUM(total) as total')
            ->whereRaw('YEAR(COALESCE(issued_at, created_at)) = ?', [now()->year])
            ->groupByRaw('MONTH(COALESCE(issued_at, created_at))')
            ->pluck('total', 'month_num');

        $income = [];
        for ($m = 1; $m <= 7; $m++) {
            $income[] = (float) ($invoiceByMonth[$m] ?? 0);
        }

        $movementByDow = DB::table('stock_movements')
            ->selectRaw('DAYOFWEEK(created_at) as dow, COUNT(*) as c')
            ->where('created_at', '>=', now()->startOfWeek())
            ->groupByRaw('DAYOFWEEK(created_at)')
            ->pluck('c', 'dow');

        $maxMove = max(1, (int) collect($movementByDow)->max());
        $medicine = [];
        foreach ([2, 3, 4, 5, 6, 7, 1] as $dow) {
            $count = (int) ($movementByDow[$dow] ?? 0);
            $medicine[] = [
                'count' => $count,
                'pct' => (int) round(($count / $maxMove) * 100),
            ];
        }

        return response()->json([
            'success' => true,
            'kpis' => [
                'beds_occupied' => $bedsOccupied,
                'icu_patients' => $icuPatients,
                'appointments' => $appointmentsToday,
                'today_op' => $todayOp,
                'patients_total' => (int) DB::table('patients')->count(),
                'doctors_total' => (int) DB::table('doctors')->count(),
            ],
            'patients' => $patients->map(function ($row) {
                return [
                    'name' => trim($row->first_name.' '.($row->last_name ?? '')),
                    'status' => $row->status ?: 'active',
                    'datetime' => $row->created_at,
                    'age' => $this->age($row->date_of_birth),
                    'dept' => $row->gender ?: '—',
                ];
            })->values(),
            'doctors' => $doctors->map(function ($row) {
                return [
                    'name' => trim('Dr. '.$row->first_name.' '.($row->last_name ?? '')),
                    'role' => $row->specialization ?: 'Doctor',
                    'available' => ($row->status ?? 'active') === 'active',
                ];
            })->values(),
            'income' => $income,
            'medicine' => $medicine,
        ]);
    }

    private function age($dateOfBirth): string
    {
        if (! $dateOfBirth) {
            return '—';
        }

        try {
            return (string) \Carbon\Carbon::parse($dateOfBirth)->age;
        } catch (\Throwable $e) {
            return '—';
        }
    }
}
