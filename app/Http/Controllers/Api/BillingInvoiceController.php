<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Support\UuidBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class BillingInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $hospitalId = $hospital->getRawOriginal('id');
        $date = $this->parseDate($request->query('date'));
        if ($date instanceof JsonResponse) {
            return $date;
        }

        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $query = DB::table('invoices as i')
            ->leftJoin('patients as p', 'p.id', '=', 'i.patient_id')
            ->where('i.hospital_id', $hospitalId);

        if ($date) {
            $query->where(function ($q) use ($date) {
                $q->whereDate('i.issued_at', $date)
                    ->orWhere(function ($q2) use ($date) {
                        $q2->whereNull('i.issued_at')->whereDate('i.created_at', $date);
                    });
            });
        }

        if ($status !== '' && strtolower($status) !== 'all') {
            $query->where('i.status', $status);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('i.invoice_number', 'like', $like)
                    ->orWhere('p.mrn', 'like', $like)
                    ->orWhere('p.first_name', 'like', $like)
                    ->orWhere('p.last_name', 'like', $like)
                    ->orWhereRaw("CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,'')) like ?", [$like]);
            });
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc(DB::raw('COALESCE(i.issued_at, i.created_at)'))
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'i.id',
                'i.invoice_number',
                'i.invoice_type',
                'i.status',
                'i.total',
                'i.amount_paid',
                'i.balance',
                'i.issued_at',
                'i.created_at',
                'i.patient_id',
                'p.first_name',
                'p.last_name',
                'p.mrn',
            ]);

        $data = $rows->map(fn ($row) => $this->mapListRow($row))->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'summary' => $this->summary($hospitalId),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $bin = UuidBin::to($id);
        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        $row = DB::table('invoices')
            ->where('id', $bin)
            ->where('hospital_id', $hospital->getRawOriginal('id'))
            ->first();

        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->mapDetail($row),
        ]);
    }

    public function storePayment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|string|max:50',
            'transaction_reference' => 'nullable|string|max:100',
        ]);

        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $bin = UuidBin::to($id);
        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        try {
            $result = DB::transaction(function () use ($bin, $hospital, $validated) {
                $invoice = DB::table('invoices')
                    ->where('id', $bin)
                    ->where('hospital_id', $hospital->getRawOriginal('id'))
                    ->lockForUpdate()
                    ->first();

                if (! $invoice) {
                    return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
                }

                if (strtolower((string) $invoice->status) === 'paid' || (float) $invoice->balance <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invoice is already paid.',
                        'errors' => ['amount' => ['Cannot collect payment on a paid invoice.']],
                    ], 422);
                }

                $amount = round((float) $validated['amount'], 2);
                $balance = round((float) $invoice->balance, 2);

                if ($amount > $balance) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment exceeds outstanding balance.',
                        'errors' => ['amount' => ['Amount cannot exceed balance of '.$balance.'.']],
                    ], 422);
                }

                $paymentId = UuidBin::generate();
                DB::table('payments')->insert([
                    'id' => $paymentId,
                    'invoice_id' => $invoice->id,
                    'patient_id' => $invoice->patient_id,
                    'amount' => $amount,
                    'payment_method' => $validated['payment_method'],
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'status' => 'completed',
                    'paid_at' => now(),
                    'received_by' => null,
                ]);

                $amountPaid = round((float) $invoice->amount_paid + $amount, 2);
                $newBalance = round((float) $invoice->total - $amountPaid, 2);
                if ($newBalance < 0) {
                    $newBalance = 0.0;
                }
                $status = $newBalance <= 0 ? 'paid' : $invoice->status;

                DB::table('invoices')->where('id', $invoice->id)->update([
                    'amount_paid' => $amountPaid,
                    'balance' => $newBalance,
                    'status' => $status,
                    'updated_at' => now(),
                ]);

                $fresh = DB::table('invoices')->where('id', $invoice->id)->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Payment recorded',
                    'data' => $this->mapDetail($fresh),
                ], 201);
            });
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to record payment.',
            ], 500);
        }

        return $result;
    }

    private function hospitalOrFail()
    {
        $hospital = Hospital::query()->first();
        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'No hospital is configured in the database.',
            ], 422);
        }

        return $hospital;
    }

    private function parseDate($date)
    {
        if ($date === null || $date === '') {
            return null;
        }

        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid filter: date must be YYYY-MM-DD.',
            ], 422);
        }

        return $date;
    }

    private function summary($hospitalId): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $todayCollection = (float) DB::table('payments as pay')
            ->join('invoices as i', 'i.id', '=', 'pay.invoice_id')
            ->where('i.hospital_id', $hospitalId)
            ->whereDate('pay.paid_at', $today)
            ->sum('pay.amount');

        $monthCollection = (float) DB::table('payments as pay')
            ->join('invoices as i', 'i.id', '=', 'pay.invoice_id')
            ->where('i.hospital_id', $hospitalId)
            ->whereDate('pay.paid_at', '>=', $monthStart)
            ->sum('pay.amount');

        $outstanding = (float) DB::table('invoices')
            ->where('hospital_id', $hospitalId)
            ->where('balance', '>', 0)
            ->sum('balance');

        return [
            'today_collection' => round($todayCollection, 2),
            'month_collection' => round($monthCollection, 2),
            'outstanding_balance' => round($outstanding, 2),
        ];
    }

    private function mapListRow($row): array
    {
        return [
            'id' => UuidBin::from($row->id),
            'invoice_number' => $row->invoice_number,
            'invoice_type' => $row->invoice_type,
            'status' => $row->status,
            'total' => (float) $row->total,
            'amount_paid' => (float) $row->amount_paid,
            'balance' => (float) $row->balance,
            'date' => $row->issued_at ?: $row->created_at,
            'patient' => [
                'id' => $row->patient_id ? UuidBin::from($row->patient_id) : null,
                'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
                'mrn' => $row->mrn,
            ],
            'print_url' => ($row->patient_id && $row->id)
                ? '/api/patients/'.UuidBin::from($row->patient_id).'/invoices/'.UuidBin::from($row->id).'/print'
                : null,
        ];
    }

    private function mapDetail($row): array
    {
        $patient = DB::table('patients')->where('id', $row->patient_id)->first();

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
            'patient' => $patient ? [
                'id' => UuidBin::from($patient->id),
                'name' => trim($patient->first_name.' '.($patient->last_name ?? '')),
                'mrn' => $patient->mrn,
            ] : null,
            'items' => $items,
            'payments' => $payments,
            'print_url' => $patient
                ? '/api/patients/'.UuidBin::from($patient->id).'/invoices/'.UuidBin::from($row->id).'/print'
                : null,
            'receipt_url' => $patient
                ? '/api/patients/'.UuidBin::from($patient->id).'/invoices/'.UuidBin::from($row->id).'/receipt'
                : null,
        ];
    }
}
