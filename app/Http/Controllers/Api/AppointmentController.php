<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\UuidBin;
use Illuminate\Http\JsonResponse;
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
}
