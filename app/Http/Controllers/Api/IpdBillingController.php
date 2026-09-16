<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Support\UuidBin;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class IpdBillingController extends Controller
{
    private const STAGES = ['interim', 'provisional', 'final'];

    public function index(string $admissionId): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $admission = $this->findAdmission($admissionId, $hospital->getRawOriginal('id'));
        if ($admission instanceof JsonResponse) {
            return $admission;
        }

        $rows = DB::table('invoices')
            ->where('hospital_id', $hospital->getRawOriginal('id'))
            ->where('encounter_id', $admission->encounter_id)
            ->where('invoice_type', 'IPD')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => $this->mapInvoice($row))->values(),
        ]);
    }

    public function store(Request $request, string $admissionId): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|gte:0',
            'discount' => 'nullable|numeric|gte:0',
            'tax' => 'nullable|numeric|gte:0',
        ]);

        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $hospitalId = $hospital->getRawOriginal('id');
        $admission = $this->findAdmission($admissionId, $hospitalId);
        if ($admission instanceof JsonResponse) {
            return $admission;
        }

        $discount = round((float) ($validated['discount'] ?? 0), 2);
        $tax = round((float) ($validated['tax'] ?? 0), 2);
        $subtotal = 0.0;
        $lines = [];
        foreach ($validated['items'] as $item) {
            $qty = round((float) $item['quantity'], 2);
            $unit = round((float) $item['unit_price'], 2);
            $lineTotal = round($qty * $unit, 2);
            $subtotal += $lineTotal;
            $lines[] = [
                'description' => $item['description'],
                'quantity' => $qty,
                'unit_price' => $unit,
                'total' => $lineTotal,
            ];
        }
        $subtotal = round($subtotal, 2);
        $total = round($subtotal - $discount + $tax, 2);
        if ($total < 0) {
            $total = 0.0;
        }

        try {
            $invoiceId = UuidBin::generate();
            DB::transaction(function () use ($invoiceId, $hospitalId, $admission, $lines, $subtotal, $discount, $tax, $total) {
                DB::table('invoices')->insert([
                    'id' => $invoiceId,
                    'hospital_id' => $hospitalId,
                    'branch_id' => null,
                    'patient_id' => $admission->patient_id,
                    'encounter_id' => $admission->encounter_id,
                    'invoice_number' => $this->nextInvoiceNumber($hospitalId),
                    'invoice_type' => 'IPD',
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'total' => $total,
                    'amount_paid' => 0,
                    'balance' => $total,
                    'status' => 'interim',
                    'issued_at' => null,
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($lines as $line) {
                    DB::table('invoice_items')->insert([
                        'id' => UuidBin::generate(),
                        'invoice_id' => $invoiceId,
                        'item_type' => 'service',
                        'reference_id' => null,
                        'description' => $line['description'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'discount' => 0,
                        'tax' => 0,
                        'total' => $line['total'],
                        'created_at' => now(),
                    ]);
                }
            });
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to create IPD invoice.',
            ], 500);
        }

        $row = DB::table('invoices')->where('id', $invoiceId)->first();

        return response()->json([
            'success' => true,
            'message' => 'IPD interim invoice created',
            'data' => $this->mapInvoice($row, true),
        ], 201);
    }

    public function advance(string $id): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $bin = UuidBin::to($id);
        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        try {
            $result = DB::transaction(function () use ($bin, $hospital) {
                $invoice = DB::table('invoices')
                    ->where('id', $bin)
                    ->where('hospital_id', $hospital->getRawOriginal('id'))
                    ->lockForUpdate()
                    ->first();

                if (! $invoice) {
                    return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
                }

                if (strtoupper((string) $invoice->invoice_type) !== 'IPD') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only IPD invoices can be advanced on this endpoint.',
                    ], 422);
                }

                $from = strtolower((string) $invoice->status);
                $next = match ($from) {
                    'interim' => 'provisional',
                    'provisional' => 'final',
                    default => null,
                };

                if ($next === null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Illegal status transition.',
                        'errors' => ['status' => ["Cannot advance from {$from}."]],
                    ], 422);
                }

                $update = [
                    'status' => $next,
                    'updated_at' => now(),
                ];
                if ($next === 'final' && empty($invoice->issued_at)) {
                    $update['issued_at'] = now();
                }

                DB::table('invoices')->where('id', $invoice->id)->update($update);
                $fresh = DB::table('invoices')->where('id', $invoice->id)->first();

                return response()->json([
                    'success' => true,
                    'message' => 'IPD invoice advanced',
                    'data' => $this->mapInvoice($fresh, true),
                ]);
            });
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to advance invoice.',
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

    private function findAdmission(string $admissionId, $hospitalId)
    {
        $bin = UuidBin::to($admissionId);
        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'Admission not found.'], 404);
        }

        $row = DB::table('admissions')
            ->where('id', $bin)
            ->where('hospital_id', $hospitalId)
            ->first();

        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Admission not found.'], 404);
        }

        return $row;
    }

    private function nextInvoiceNumber($hospitalId): string
    {
        $day = Carbon::now()->format('Ymd');
        $prefix = 'IPD-'.$day.'-';
        $count = DB::table('invoices')
            ->where('hospital_id', $hospitalId)
            ->where('invoice_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    private function mapInvoice($row, bool $withItems = false): array
    {
        $data = [
            'id' => UuidBin::from($row->id),
            'invoice_number' => $row->invoice_number,
            'invoice_type' => $row->invoice_type,
            'status' => $row->status,
            'subtotal' => (float) $row->subtotal,
            'discount' => (float) $row->discount,
            'tax' => (float) $row->tax,
            'total' => (float) $row->total,
            'amount_paid' => (float) $row->amount_paid,
            'balance' => (float) $row->balance,
            'issued_at' => $row->issued_at,
            'encounter_id' => $row->encounter_id ? UuidBin::from($row->encounter_id) : null,
            'patient_id' => $row->patient_id ? UuidBin::from($row->patient_id) : null,
            'next_status' => match (strtolower((string) $row->status)) {
                'interim' => 'provisional',
                'provisional' => 'final',
                default => null,
            },
        ];

        if ($withItems) {
            $data['items'] = DB::table('invoice_items')
                ->where('invoice_id', $row->id)
                ->orderBy('created_at')
                ->get()
                ->map(fn ($item) => [
                    'id' => UuidBin::from($item->id),
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total' => (float) $item->total,
                ])->values()->all();
        }

        return $data;
    }
}
