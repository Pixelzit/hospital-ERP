<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Medicine;
use App\Support\UuidBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PharmacyMedicineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hospital = Hospital::query()->first();

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'No hospital is configured in the database.',
            ], 422);
        }

        $hospitalId = $hospital->getRawOriginal('id');

        $rows = DB::table('medicines as m')
            ->leftJoin('medicine_batches as b', function ($join) {
                $join->on('b.medicine_id', '=', 'm.id')
                    ->where('b.status', '=', 'active');
            })
            ->where('m.hospital_id', $hospitalId)
            ->groupBy(
                'm.id',
                'm.generic_name',
                'm.brand_name',
                'm.strength',
                'm.dosage_form',
                'm.route',
                'm.manufacturer',
                'm.status',
                'm.created_at'
            )
            ->orderByDesc('m.created_at')
            ->limit(200)
            ->get([
                'm.id',
                'm.generic_name',
                'm.brand_name',
                'm.strength',
                'm.dosage_form',
                'm.route',
                'm.manufacturer',
                'm.status',
                DB::raw('COALESCE(SUM(b.quantity), 0) as stock_qty'),
                DB::raw('MIN(b.expiry_date) as nearest_expiry'),
            ]);

        $data = $rows->map(fn ($row) => $this->mapRow($row))->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => (int) DB::table('medicines')->where('hospital_id', $hospitalId)->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'generic_name' => 'required|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'strength' => 'nullable|string|max:100',
            'dosage_form' => 'nullable|string|max:100',
            'route' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        $hospital = Hospital::query()->first();

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'No hospital is configured in the database.',
            ], 422);
        }

        try {
            $medicine = new Medicine();
            $medicine->hospital_id = $hospital->getRawOriginal('id');
            $medicine->generic_name = $validated['generic_name'];
            $medicine->brand_name = $validated['brand_name'] ?? null;
            $medicine->strength = $validated['strength'] ?? null;
            $medicine->dosage_form = $validated['dosage_form'] ?? null;
            $medicine->route = $validated['route'] ?? null;
            $medicine->manufacturer = $validated['manufacturer'] ?? null;
            $medicine->status = $validated['status'] ?? 'active';
            $medicine->save();
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create medicine',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Medicine added successfully',
            'data' => $this->mapRow((object) [
                'id' => $medicine->getRawOriginal('id'),
                'generic_name' => $medicine->generic_name,
                'brand_name' => $medicine->brand_name,
                'strength' => $medicine->strength,
                'dosage_form' => $medicine->dosage_form,
                'route' => $medicine->route,
                'manufacturer' => $medicine->manufacturer,
                'status' => $medicine->status,
                'stock_qty' => 0,
                'nearest_expiry' => null,
            ]),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $hospital = Hospital::query()->first();

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'message' => 'No hospital is configured in the database.',
            ], 422);
        }

        $binaryId = UuidBin::to($id);

        if (! $binaryId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid medicine id',
            ], 422);
        }

        $row = DB::table('medicines as m')
            ->leftJoin('medicine_batches as b', function ($join) {
                $join->on('b.medicine_id', '=', 'm.id')
                    ->where('b.status', '=', 'active');
            })
            ->where('m.id', $binaryId)
            ->where('m.hospital_id', $hospital->getRawOriginal('id'))
            ->groupBy(
                'm.id',
                'm.generic_name',
                'm.brand_name',
                'm.strength',
                'm.dosage_form',
                'm.route',
                'm.manufacturer',
                'm.status',
                'm.created_at'
            )
            ->first([
                'm.id',
                'm.generic_name',
                'm.brand_name',
                'm.strength',
                'm.dosage_form',
                'm.route',
                'm.manufacturer',
                'm.status',
                DB::raw('COALESCE(SUM(b.quantity), 0) as stock_qty'),
                DB::raw('MIN(b.expiry_date) as nearest_expiry'),
            ]);

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Medicine not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->mapRow($row),
        ]);
    }

    private function mapRow(object $row): array
    {
        $stock = (float) ($row->stock_qty ?? 0);
        $expiry = $row->nearest_expiry ?? null;
        $statusLabel = $this->stockStatus($stock);
        $displayName = trim(($row->brand_name ?: '').(($row->brand_name && $row->generic_name) ? ' / ' : '').($row->generic_name ?? ''));

        return [
            'id' => UuidBin::from($row->id),
            'name' => $displayName !== '' ? $displayName : ($row->generic_name ?? 'Unknown'),
            'generic_name' => $row->generic_name,
            'brand_name' => $row->brand_name,
            'strength' => $row->strength,
            'dosage_form' => $row->dosage_form,
            'route' => $row->route,
            'manufacturer' => $row->manufacturer,
            'status' => $row->status,
            'stock' => $this->formatStock($stock),
            'stock_qty' => $stock,
            'expiry' => $expiry ? date('M Y', strtotime((string) $expiry)) : null,
            'expiry_date' => $expiry,
            'stock_status' => $statusLabel['label'],
            'stock_status_class' => $statusLabel['class'],
        ];
    }

    private function formatStock(float $qty): string
    {
        if ($qty <= 0) {
            return '0';
        }

        if (floor($qty) == $qty) {
            return number_format($qty, 0);
        }

        return rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
    }

    private function stockStatus(float $qty): array
    {
        if ($qty <= 0) {
            return ['label' => 'Out of Stock', 'class' => 'bg-slate-100 text-slate-600'];
        }

        if ($qty < 20) {
            return ['label' => 'Critical', 'class' => 'bg-red-50 text-red-600'];
        }

        if ($qty < 100) {
            return ['label' => 'Low', 'class' => 'bg-amber-50 text-amber-600'];
        }

        return ['label' => 'In Stock', 'class' => 'bg-emerald-50 text-emerald-600'];
    }
}