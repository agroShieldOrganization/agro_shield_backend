<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class FarmController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET USER FARMS
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $farms = $request->user()
            ->farms()
            ->latest()
            ->get();

        return response()->json([
            'farms' => $farms,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE FARM
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [

            'name' => 'required|string|max:255',

            'location_name' =>
                'required|string|max:255',

            'country' =>
                'nullable|string|max:255',

            'state_region' =>
                'nullable|string|max:255',

            'address_text' =>
                'nullable|string',

            'latitude' =>
                'required|numeric',

            'longitude' =>
                'required|numeric',

            'size_hectares' =>
                'nullable|numeric',

            'primary_crop_name' =>
                'nullable|string|max:255',
        ]);

        if ($validator->fails()) {

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $farm = Farm::create([

            ...$validator->validated(),

            'owner_user_id' =>
                $request->user()->id,

            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Farm created successfully.',
            'farm' => $farm,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE FARM
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Farm $farm
    ): JsonResponse {

        if (
            $farm->owner_user_id !==
            $request->user()->id
        ) {

            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [

            'name' => 'sometimes|string|max:255',

            'location_name' =>
                'sometimes|string|max:255',

            'country' =>
                'nullable|string|max:255',

            'state_region' =>
                'nullable|string|max:255',

            'address_text' =>
                'nullable|string',

            'latitude' =>
                'sometimes|numeric',

            'longitude' =>
                'sometimes|numeric',

            'size_hectares' =>
                'nullable|numeric',

            'primary_crop_name' =>
                'nullable|string|max:255',

            'status' =>
                'nullable|string|max:50',
        ]);

        if ($validator->fails()) {

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $farm->update(
            $validator->validated()
        );

        return response()->json([
            'message' => 'Farm updated successfully.',
            'farm' => $farm,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE FARM
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        Farm $farm
    ): JsonResponse {

        if (
            $farm->owner_user_id !==
            $request->user()->id
        ) {

            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $farm->delete();

        return response()->json([
            'message' => 'Farm deleted successfully.',
        ]);
    }

    // Fetch Farms Map data for the climate-map page
        public function mapData(
                Request $request
            ): JsonResponse {

                $farms = Farm::where(
                        'owner_user_id',
                        $request->user()->id
                    )
                    ->select([
                        'id',
                        'name',
                        'latitude',
                        'longitude',
                        'status',
                        'primary_crop_name',
                        'location_name',
                    ])
                    ->get();

                return response()->json([
                    'farms' => $farms,
                ]);
        }
}