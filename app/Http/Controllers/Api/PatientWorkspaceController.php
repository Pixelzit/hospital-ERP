<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\UuidBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientWorkspaceController extends Controller
{
    public function showEncounter(string $id, string $encounterId): JsonResponse
    {
        $patient = $this->patient($id);
        if ($patient instanceof JsonResponse) {
            return $patient;
        }

        $encBin = UuidBin::to($encounterId);
        if (! $encBin) {
            return response()->json(['success' => false, 'message' => 'Visit not found.'], 404);
        }

        $row = DB::table('encounters')
            ->where('id', $encBin)
            ->where('patient_id', $patient->id)
            ->first();

        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Visit not found.'], 404);
        }

        $diagnoses = DB::table('diagnoses')->where('encounter_id', $row->id)->orderBy('created_at')->get()
            ->map(fn ($item) => [
                'id' => UuidBin::from($item->id),
                'name' => $item->diagnosis_name,
                'type' => $item->diagnosis_type,
                'status' => $item->status,
                'notes' => $item->notes,
            ])->values()->all();

        $prescriptions = DB::table('prescriptions')->where('encounter_id', $row->id)->orderByDesc('issued_at')->get()
            ->map(fn ($item) => [
                'id' => UuidBin::from($item->id),
                'number' => $item->prescription_number,
                'status' => $item->status,
                'issued_at' => $item->issued_at,
            ])->values()->all();

        $lab = DB::table('lab_orders')->where('encounter_id', $row->id)->orderByDesc('ordered_at')->get()
            ->map(fn ($item) => [
                'id' => UuidBin::from($item->id),
                'number' => $item->order_number,
                'priority' => $item->priority,
                'status' => $item->status,
                'ordered_at' => $item->ordered_at,
            ])->values()->all();

        $radiology = DB::table('radiology_orders as ro')
            ->leftJoin('radiology_tests as rt', 'rt.id', '=', 'ro.radiology_test_id')
            ->where('ro.encounter_id', $row->id)
            ->orderByDesc('ro.ordered_at')
            ->get(['ro.id', 'ro.order_number', 'ro.priority', 'ro.status', 'ro.ordered_at', 'rt.name as test_name'])
            ->map(fn ($item) => [
                'id' => UuidBin::from($item->id),
                'number' => $item->order_number,
                'test' => $item->test_name,
                'priority' => $item->priority,
                'status' => $item->status,
                'ordered_at' => $item->ordered_at,
            ])->values()->all();

        $notes = DB::table('clinical_notes')->where('encounter_id', $row->id)->orderBy('created_at')->get()
            ->map(fn ($item) => [
                'id' => UuidBin::from($item->id),
                'type' => $item->note_type,
                'content' => $item->content,
                'created_at' => $item->created_at,
            ])->values()->all();

        $vitals = DB::table('vitals')->where('encounter_id', $row->id)->orderByDesc('recorded_at')->get()
            ->map(function ($item) {
                return [
                    'id' => UuidBin::from($item->id),
                    'recorded_at' => $item->recorded_at,
                    'temperature' => $item->temperature,
                    'pulse' => $item->pulse,
                    'respiratory_rate' => $item->respiratory_rate,
                    'systolic_bp' => $item->systolic_bp,
                    'diastolic_bp' => $item->diastolic_bp,
                    'spo2' => $item->spo2,
                    'height_cm' => $item->height_cm,
                    'weight_kg' => $item->weight_kg,
                    'bmi' => $item->bmi,
                ];
            })->values()->all();

        $followUp = DB::table('appointments')
            ->where('patient_id', $patient->id)
            ->whereNotIn('status', ['cancelled', 'canceled', 'completed'])
            ->whereDate('appointment_date', '>=', now()->toDateString())
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => UuidBin::from($row->id),
                'number' => $row->encounter_number,
                'visit_type' => $row->encounter_type,
                'status' => $row->status,
                'started_at' => $row->started_at,
                'ended_at' => $row->ended_at,
                'doctor' => $this->doctorName($row->doctor_id) ?: '—',
                'department' => $this->departmentName($row->department_id) ?: '—',
                'diagnoses' => $diagnoses,
                'prescriptions' => $prescriptions,
                'lab_reports' => $lab,
                'radiology' => $radiology,
                'clinical_notes' => $notes,
                'vitals' => $vitals,
                'follow_up' => $followUp ? [
                    'date' => $followUp->appointment_date,
                    'time' => $followUp->start_time,
                    'status' => $followUp->status,
                    'number' => $followUp->appointment_number,
                ] : null,
            ],
        ]);
    }

    public function showDocumentFile(Request $request, string $id, string $documentId)
    {
        $patient = $this->patient($id);
        if ($patient instanceof JsonResponse) {
            return $patient;
        }

        $docBin = UuidBin::to($documentId);
        if (! $docBin) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }

        $doc = DB::table('documents')
            ->where('id', $docBin)
            ->where('patient_id', $patient->id)
            ->first();

        if (! $doc || ! $doc->storage_key || ! Storage::disk('local')->exists($doc->storage_key)) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }

        $download = $request->boolean('download');
        $mime = $doc->mime_type ?: 'application/octet-stream';
        $name = $doc->original_filename ?: 'document';
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.str_replace('"', '', $name).'"';

        return new StreamedResponse(function () use ($doc) {
            echo Storage::disk('local')->get($doc->storage_key);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function showInvoice(string $id, string $invoiceId): JsonResponse
    {
        $patient = $this->patient($id);
        if ($patient instanceof JsonResponse) {
            return $patient;
        }

        $invoice = $this->invoice($patient->id, $invoiceId);
        if ($invoice instanceof JsonResponse) {
            return $invoice;
        }

        return response()->json(['success' => true, 'data' => $invoice]);
    }

    public function printInvoice(string $id, string $invoiceId)
    {
        $patient = $this->patient($id);
        if ($patient instanceof JsonResponse) {
            return $patient;
        }

        $invoice = $this->invoice($patient->id, $invoiceId);
        if ($invoice instanceof JsonResponse) {
            return $invoice;
        }

        return response()->view('print.invoice', [
            'invoice' => $invoice,
            'kind' => 'bill',
        ]);
    }

    public function printReceipt(string $id, string $invoiceId)
    {
        $patient = $this->patient($id);
        if ($patient instanceof JsonResponse) {
            return $patient;
        }

        $invoice = $this->invoice($patient->id, $invoiceId);
        if ($invoice instanceof JsonResponse) {
            return $invoice;
        }

        if (empty($invoice['payments'])) {
            return response()->json(['success' => false, 'message' => 'No payment receipt for this invoice.'], 404);
        }

        return response()->view('print.invoice', [
            'invoice' => $invoice,
            'kind' => 'receipt',
        ]);
    }

    private function invoice($patientId, string $invoiceId)
    {
        $bin = UuidBin::to($invoiceId);
        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        $row = DB::table('invoices')->where('id', $bin)->where('patient_id', $patientId)->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        $patient = DB::table('patients')->where('id', $patientId)->first();
        $items = DB::table('invoice_items')->where('invoice_id', $row->id)->orderBy('created_at')->get()
            ->map(fn ($item) => [
                'id' => UuidBin::from($item->id),
                'item_type' => $item->item_type,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
                'tax' => (float) $item->tax,
                'total' => (float) $item->total,
            ])->values()->all();

        $payments = DB::table('payments')->where('invoice_id', $row->id)->orderBy('paid_at')->get()
            ->map(fn ($item) => [
                'id' => UuidBin::from($item->id),
                'amount' => (float) $item->amount,
                'payment_method' => $item->payment_method,
                'transaction_reference' => $item->transaction_reference,
                'status' => $item->status,
                'paid_at' => $item->paid_at,
            ])->values()->all();

        return [
            'id' => UuidBin::from($row->id),
            'invoice_number' => $row->invoice_number,
            'invoice_type' => $row->invoice_type,
            'date' => $row->issued_at ?: $row->created_at,
            'status' => $row->status,
            'subtotal' => (float) $row->subtotal,
            'discount' => (float) $row->discount,
            'tax' => (float) $row->tax,
            'total' => (float) $row->total,
            'amount_paid' => (float) $row->amount_paid,
            'balance' => (float) $row->balance,
            'patient' => [
                'id' => UuidBin::from($patient->id),
                'name' => trim($patient->first_name.' '.($patient->last_name ?? '')),
                'mrn' => $patient->mrn,
            ],
            'items' => $items,
            'payments' => $payments,
        ];
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
}
