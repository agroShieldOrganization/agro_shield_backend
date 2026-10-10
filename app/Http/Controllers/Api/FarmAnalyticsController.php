<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\YieldRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmAnalyticsController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    | GET /api/yield-records
    | Fetch all yield records belonging to the authenticated user's farms.
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id'      => ['nullable', 'uuid'],
            'crop_name'    => ['nullable', 'string'],
            'from_date'    => ['nullable', 'date'],
            'to_date'      => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $records = YieldRecord::query()
            ->with([
                'farm:id,name',
                'cropCycle:id,crop_name',
            ])
            ->whereHas('farm', function ($query) use ($request) {
                $query->where('owner_user_id', $request->user()->id);
            })
            ->when(
                isset($validated['farm_id']),
                fn ($q) => $q->where('farm_id', $validated['farm_id'])
            )
            ->when(
                isset($validated['crop_name']),
                fn ($q) => $q->where('crop_name', 'ilike', '%' . $validated['crop_name'] . '%')
            )
            ->when(
                isset($validated['from_date']),
                fn ($q) => $q->whereDate('harvest_date', '>=', $validated['from_date'])
            )
            ->when(
                isset($validated['to_date']),
                fn ($q) => $q->whereDate('harvest_date', '<=', $validated['to_date'])
            )
            ->orderByDesc('harvest_date')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($records);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    | POST /api/yield-records
    | Create a new yield record for a farm owned by the authenticated user.
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id'         => ['required', 'uuid'],
            'farm_section_id' => ['nullable', 'uuid'],
            'crop_cycle_id'   => ['nullable', 'uuid'],
            'crop_name'       => ['required', 'string', 'max:255'],
            'harvest_date'    => ['required', 'date', 'before_or_equal:today'],
            'quantity_kg'     => ['required', 'numeric', 'min:0.01'],
            'loss_kg'         => ['nullable', 'numeric', 'min:0', 'lte:quantity_kg'],
            'revenue'         => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ]);

        /*
        |----------------------------------------------------------------------
        | Ownership gate — farm must belong to the authenticated user
        |----------------------------------------------------------------------
        */

        $farm = Farm::where('id', $validated['farm_id'])
            ->where('owner_user_id', $request->user()->id)
            ->firstOrFail();

       

        $record = YieldRecord::create([
            ...$validated,
            'recorded_by_user_id' => $request->user()->id,
            'loss_kg'             => $validated['loss_kg'] ?? 0.00,
        ]);

        return response()->json([
            'message' => 'Yield record created successfully.',
            'data'    => $record->load([
                'farm:id,name',
                'farmSection:id,name',
            ]),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    | GET /api/yield-records/{yieldRecord}
    | Fetch a single yield record — must belong to the authenticated user.
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, YieldRecord $yieldRecord): JsonResponse
    {
        $this->authorizeOwnership($request, $yieldRecord);

        return response()->json([
            'data' => $yieldRecord->load([
                'farm:id,name',
                'farmSection:id,name',
                'cropCycle:id,crop_name',
                'recordedBy:id,name,email',
            ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    | PUT /api/yield-records/{yieldRecord}
    | Update an existing yield record owned by the authenticated user.
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, YieldRecord $yieldRecord): JsonResponse
    {
        $this->authorizeOwnership($request, $yieldRecord);

        $validated = $request->validate([
            'farm_section_id' => ['nullable', 'uuid'],
            'crop_cycle_id'   => ['nullable', 'uuid'],
            'crop_name'       => ['sometimes', 'string', 'max:255'],
            'harvest_date'    => ['sometimes', 'date', 'before_or_equal:today'],
            'quantity_kg'     => ['sometimes', 'numeric', 'min:0.01'],
            'loss_kg'         => ['nullable', 'numeric', 'min:0'],
            'revenue'         => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ]);

        /*
        |----------------------------------------------------------------------
        | Re-validate loss_kg does not exceed quantity_kg after partial update
        |----------------------------------------------------------------------
        */

        $resolvedQuantity = $validated['quantity_kg'] ?? $yieldRecord->quantity_kg;
        $resolvedLoss     = $validated['loss_kg']     ?? $yieldRecord->loss_kg;

        if ($resolvedLoss > $resolvedQuantity) {
            return response()->json([
                'message' => 'Yield loss cannot exceed total yield quantity.',
                'errors'  => [
                    'loss_kg' => ['Loss cannot exceed quantity.'],
                ],
            ], 422);
        }

        

        $yieldRecord->update($validated);

        return response()->json([
            'message' => 'Yield record updated successfully.',
            'data'    => $yieldRecord->fresh()->load([
                'farm:id,name',
                'farmSection:id,name',
            ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    | DELETE /api/yield-records/{yieldRecord}
    | Permanently delete a yield record owned by the authenticated user.
    |--------------------------------------------------------------------------
    */

    public function destroy(Request $request, YieldRecord $yieldRecord): JsonResponse
    {
        $this->authorizeOwnership($request, $yieldRecord);

        $yieldRecord->delete();

        return response()->json([
            'message' => 'Yield record deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ANALYTICS SUMMARY
    | GET /api/yield-records/summary
    | Aggregate stats for the authenticated user's yield records.
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id'   => ['nullable', 'uuid'],
            'from_date' => ['nullable', 'date'],
            'to_date'   => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        $base = YieldRecord::query()
            ->whereHas('farm', function ($query) use ($request) {
                $query->where('owner_user_id', $request->user()->id);
            })
            ->when(
                isset($validated['farm_id']),
                fn ($q) => $q->where('farm_id', $validated['farm_id'])
            )
            ->when(
                isset($validated['from_date']),
                fn ($q) => $q->whereDate('harvest_date', '>=', $validated['from_date'])
            )
            ->when(
                isset($validated['to_date']),
                fn ($q) => $q->whereDate('harvest_date', '<=', $validated['to_date'])
            );

        $totals = $base->clone()->selectRaw('
            COALESCE(SUM(quantity_kg), 0)           AS total_yield_kg,
            COALESCE(SUM(net_yield_kg), 0)          AS total_net_yield_kg,
            COALESCE(SUM(loss_kg), 0)               AS total_loss_kg,
            COALESCE(SUM(revenue), 0)               AS total_revenue,
            COALESCE(AVG(efficiency_percentage), 0) AS avg_efficiency
        ')->first();

        $byFarm = $base->clone()
            ->select('farm_id')
            ->selectRaw('
                SUM(quantity_kg)           AS total_yield_kg,
                SUM(net_yield_kg)          AS total_net_yield_kg,
                COALESCE(SUM(revenue), 0)  AS total_revenue,
                AVG(efficiency_percentage) AS avg_efficiency
            ')
            ->with('farm:id,name')
            ->groupBy('farm_id')
            ->orderByRaw('SUM(quantity_kg) DESC')
            ->get();

        $byCrop = $base->clone()
            ->select('crop_name')
            ->selectRaw('
                SUM(quantity_kg)           AS total_yield_kg,
                COALESCE(SUM(revenue), 0)  AS total_revenue,
                AVG(efficiency_percentage) AS avg_efficiency,
                COUNT(*)                   AS record_count
            ')
            ->groupBy('crop_name')
            ->orderByRaw('SUM(quantity_kg) DESC')
            ->get();

        return response()->json([
            'data' => [
                'totals'   => $totals,
                'by_farm'  => $byFarm,
                'by_crop'  => $byCrop,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE — Ownership Authorization
    |--------------------------------------------------------------------------
    */

    private function authorizeOwnership(Request $request, YieldRecord $record): void
    {
        if ($record->farm->owner_user_id !== $request->user()->id) {
            abort(403, 'You do not have permission to access this yield record.');
        }
    }


}
