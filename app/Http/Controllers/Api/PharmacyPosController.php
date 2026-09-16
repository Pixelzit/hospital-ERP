<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Support\UuidBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Pharmacy POS stock + dispense.
 *
 * stock_movements.quantity convention for movement_type=dispense:
 * negative values mean units leaving on-hand stock (e.g. -2 for two packs dispensed).
 */
class PharmacyPosController extends Controller
{
    public function stock(): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $hospitalId = $hospital->getRawOriginal('id');

        $rows = DB::table('medicine_batches as b')
            ->join('pharmacy_locations as l', 'l.id', '=', 'b.pharmacy_location_id')
            ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
            ->where('l.hospital_id', $hospitalId)
            ->where('l.status', 'active')
            ->where('b.status', 'active')
            ->where('b.quantity', '>', 0)
            ->orderBy('m.generic_name')
            ->orderBy('b.expiry_date')
            ->limit(200)
            ->get([
                'b.id as batch_id',
                'b.batch_number',
                'b.quantity',
                'b.selling_price',
                'b.expiry_date',
                'b.medicine_id',
                'b.pharmacy_location_id',
                'm.generic_name',
                'm.brand_name',
                'm.strength',
                'm.dosage_form',
                'l.name as location_name',
            ]);

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => [
                'batch_id' => UuidBin::from($row->batch_id),
                'batch_number' => $row->batch_number,
                'quantity' => (float) $row->quantity,
                'selling_price' => $row->selling_price !== null ? (float) $row->selling_price : null,
                'expiry_date' => $row->expiry_date,
                'medicine' => [
                    'id' => UuidBin::from($row->medicine_id),
                    'generic_name' => $row->generic_name,
                    'brand_name' => $row->brand_name,
                    'strength' => $row->strength,
                    'dosage_form' => $row->dosage_form,
                ],
                'location' => [
                    'id' => UuidBin::from($row->pharmacy_location_id),
                    'name' => $row->location_name,
                ],
            ])->values(),
        ]);
    }

    public function dispense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.batch_id' => 'required|string',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'patient_id' => 'nullable|string',
        ]);

        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $userBin = $this->authUserBin($request);
        if ($userBin instanceof JsonResponse) {
            return $userBin;
        }

        $hospitalId = $hospital->getRawOriginal('id');
        $patientBin = null;
        if (! empty($validated['patient_id'])) {
            $patientBin = UuidBin::to($validated['patient_id']);
            if (! $patientBin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid patient_id.',
                    'errors' => ['patient_id' => ['Patient not found.']],
                ], 422);
            }
        }

        try {
            $result = DB::transaction(function () use ($validated, $hospitalId, $userBin, $patientBin) {
                $planned = [];

                foreach ($validated['lines'] as $line) {
                    $batchId = UuidBin::to($line['batch_id']);
                    if (! $batchId) {
                        throw new RuntimeException('BATCH_NOT_FOUND');
                    }

                    $batch = DB::table('medicine_batches as b')
                        ->join('pharmacy_locations as l', 'l.id', '=', 'b.pharmacy_location_id')
                        ->where('b.id', $batchId)
                        ->where('l.hospital_id', $hospitalId)
                        ->lockForUpdate()
                        ->first([
                            'b.id',
                            'b.medicine_id',
                            'b.pharmacy_location_id',
                            'b.quantity',
                            'b.status',
                        ]);

                    if (! $batch) {
                        throw new RuntimeException('BATCH_NOT_FOUND');
                    }

                    $qty = round((float) $line['quantity'], 3);
                    $onHand = (float) $batch->quantity;
                    if ($qty > $onHand) {
                        throw new RuntimeException('INSUFFICIENT_STOCK');
                    }

                    $planned[] = [
                        'batch' => $batch,
                        'qty' => $qty,
                        'new_qty' => round($onHand - $qty, 3),
                    ];
                }

                $movements = [];
                $remaining = [];

                foreach ($planned as $item) {
                    $batch = $item['batch'];
                    $qty = $item['qty'];
                    $newQty = $item['new_qty'];

                    DB::table('medicine_batches')
                        ->where('id', $batch->id)
                        ->update(['quantity' => $newQty]);

                    $inv = DB::table('inventory')
                        ->where('medicine_id', $batch->medicine_id)
                        ->where('pharmacy_location_id', $batch->pharmacy_location_id)
                        ->lockForUpdate()
                        ->first();

                    $invOnHand = $inv ? (float) $inv->quantity_on_hand : 0.0;
                    $invNew = round(max(0, $invOnHand - $qty), 3);
                    if ($inv) {
                        DB::table('inventory')
                            ->where('id', $inv->id)
                            ->update([
                                'quantity_on_hand' => $invNew,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('inventory')->insert([
                            'id' => UuidBin::generate(),
                            'medicine_id' => $batch->medicine_id,
                            'pharmacy_location_id' => $batch->pharmacy_location_id,
                            'quantity_on_hand' => $invNew,
                            'reorder_level' => 0,
                            'updated_at' => now(),
                        ]);
                    }

                    $movementId = UuidBin::generate();
                    DB::table('stock_movements')->insert([
                        'id' => $movementId,
                        'medicine_id' => $batch->medicine_id,
                        'pharmacy_location_id' => $batch->pharmacy_location_id,
                        'batch_id' => $batch->id,
                        'movement_type' => 'dispense',
                        'quantity' => -1 * $qty,
                        'reference_type' => $patientBin ? 'patient' : null,
                        'reference_id' => $patientBin,
                        'performed_by' => $userBin,
                        'created_at' => now(),
                    ]);

                    $movements[] = [
                        'id' => UuidBin::from($movementId),
                        'batch_id' => UuidBin::from($batch->id),
                        'quantity' => -1 * $qty,
                        'movement_type' => 'dispense',
                    ];
                    $remaining[] = [
                        'batch_id' => UuidBin::from($batch->id),
                        'quantity' => $newQty,
                        'inventory_quantity_on_hand' => $invNew,
                    ];
                }

                return [
                    'movements' => $movements,
                    'remaining' => $remaining,
                ];
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'BATCH_NOT_FOUND') {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch not found.',
                ], 404);
            }
            if ($e->getMessage() === 'INSUFFICIENT_STOCK') {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock for batch.',
                    'errors' => [
                        'quantity' => ['Requested quantity exceeds available batch stock.'],
                    ],
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to dispense stock.',
            ], 500);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to dispense stock.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dispense recorded',
            'data' => $result,
        ], 201);
    }

    public function movements(): JsonResponse
    {
        $hospital = $this->hospitalOrFail();
        if ($hospital instanceof JsonResponse) {
            return $hospital;
        }

        $hospitalId = $hospital->getRawOriginal('id');

        $rows = DB::table('stock_movements as sm')
            ->join('pharmacy_locations as l', 'l.id', '=', 'sm.pharmacy_location_id')
            ->join('medicines as m', 'm.id', '=', 'sm.medicine_id')
            ->leftJoin('medicine_batches as b', 'b.id', '=', 'sm.batch_id')
            ->where('l.hospital_id', $hospitalId)
            ->where('sm.movement_type', 'dispense')
            ->orderByDesc('sm.created_at')
            ->limit(50)
            ->get([
                'sm.id',
                'sm.quantity',
                'sm.movement_type',
                'sm.created_at',
                'sm.batch_id',
                'sm.medicine_id',
                'm.generic_name',
                'b.batch_number',
            ]);

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => [
                'id' => UuidBin::from($row->id),
                'movement_type' => $row->movement_type,
                'quantity' => (float) $row->quantity,
                'created_at' => $row->created_at,
                'batch_id' => $row->batch_id ? UuidBin::from($row->batch_id) : null,
                'batch_number' => $row->batch_number,
                'medicine' => [
                    'id' => UuidBin::from($row->medicine_id),
                    'generic_name' => $row->generic_name,
                ],
            ])->values(),
        ]);
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

    private function authUserBin(Request $request)
    {
        $sessionUser = $request->session()->get('auth_user_id');
        $userBin = is_string($sessionUser) ? UuidBin::to($sessionUser) : null;
        if (! $userBin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! DB::table('users')->where('id', $userBin)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $userBin;
    }
}
