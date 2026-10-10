<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CropCycle;
use App\Models\Farm;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\Validator;

class CropCycleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET USER CROP CYCLES
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): JsonResponse {

        $cropCycles = CropCycle::with([
                'farm',
                'section',
            ])
            ->where(
                'created_by_user_id',
                $request->user()->id
            )
            ->latest()
            ->get();

        return response()->json([
            'crop_cycles' => $cropCycles,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET CROPS FOR FARM
    |--------------------------------------------------------------------------
    */

    public function getCropCyclesForFarm(
        Request $request
    ): JsonResponse {

        $validated = $request->validate([
            'farm_id' => ['required', 'uuid', 'exists:farms,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($validated['user_id'] !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $farm = Farm::where('id', $validated['farm_id'])
            ->where('owner_user_id', $validated['user_id'])
            ->firstOrFail();

        $cropCycles = CropCycle::with([
                'farm',
                'section',
            ])
            ->where('farm_id', $farm->id)
            ->where('created_by_user_id', $validated['user_id'])
            ->orderByDesc('planting_date')
            ->get();

        return response()->json([
            'farm_id' => $farm->id,
            'crop_cycles' => $cropCycles,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE CROP CYCLE
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): JsonResponse {

        $validator = Validator::make(
            $request->all(),
            [

                'farm_id' =>
                    'required|uuid|exists:farms,id',

                'farm_section_id' =>
                    'nullable|uuid|exists:farm_sections,id',

                'crop_name' =>
                    'required|string|max:255',

                'season' =>
                    'nullable|string|max:50',

                'planting_date' =>
                    'required|date',

                'expected_harvest_date' =>
                    'nullable|date|after:planting_date',

                'expected_yield_kg_per_hectare' =>
                    'nullable|numeric',

                'expected_area_hectares' =>
                    'nullable|numeric',

                'notes' =>
                    'nullable|string',
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Ensure Farm Belongs To User
        |--------------------------------------------------------------------------
        */

        $farm = Farm::where(
                'id',
                $request->farm_id
            )
            ->where(
                'owner_user_id',
                $request->user()->id
            )
            ->first();

        if (!$farm) {

            return response()->json([
                'message' => 'Farm not found.',
            ], 404);
        }

        $cropCycle = CropCycle::create([

            ...$validator->validated(),

            'created_by_user_id'
                => $request->user()->id,

            'status' => 'planned',
        ]);

        return response()->json([
            'message' =>
                'Crop added successfully.',

            'crop_cycle' =>
                $cropCycle->load([
                    'farm',
                    'section',
                ]),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE CROP CYCLE
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        CropCycle $cropCycle
    ): JsonResponse {

        if (
            $cropCycle->created_by_user_id
            !== $request->user()->id
        ) {

            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validator = Validator::make(
            $request->all(),
            [

                'crop_name' =>
                    'sometimes|string|max:255',

                'season' =>
                    'nullable|string|max:50',

                'planting_date' =>
                    'sometimes|date',

                'expected_harvest_date' =>
                    'nullable|date',

                'expected_yield_kg_per_hectare' =>
                    'nullable|numeric',

                'expected_area_hectares' =>
                    'nullable|numeric',

                'actual_yield_kg' =>
                    'nullable|numeric',

                'status' =>
                    'nullable|string|max:50',

                'notes' =>
                    'nullable|string',
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cropCycle->update(
            $validator->validated()
        );

        return response()->json([
            'message' =>
                'Crop updated successfully.',

            'crop_cycle' =>
                $cropCycle->fresh()->load([
                    'farm',
                    'section',
                ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE CROP CYCLE
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        CropCycle $cropCycle
    ): JsonResponse {

        if (
            $cropCycle->created_by_user_id
            !== $request->user()->id
        ) {

            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $cropCycle->delete();

        return response()->json([
            'message' =>
                'Crop deleted successfully.',
        ]);
    }
}