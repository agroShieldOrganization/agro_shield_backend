<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmSection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class FarmSectionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CREATE SECTION
    |--------------------------------------------------------------------------
    */

    public function store(
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

            'name' => 'required|string|max:255',

            'area_hectares' =>
                'nullable|numeric',

            'latitude' =>
                'nullable|numeric',

            'longitude' =>
                'nullable|numeric',

            'soil_type' =>
                'nullable|string|max:255',
        ]);

        if ($validator->fails()) {

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $section = $farm->sections()->create(
            $validator->validated()
        );

        return response()->json([
            'message' =>
                'Farm section created successfully.',

            'section' => $section,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE SECTION
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        FarmSection $section
    ): JsonResponse {

        if (
            $section->farm->owner_user_id !==
            $request->user()->id
        ) {

            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [

            'name' => 'sometimes|string|max:255',

            'area_hectares' =>
                'nullable|numeric',

            'latitude' =>
                'nullable|numeric',

            'longitude' =>
                'nullable|numeric',

            'soil_type' =>
                'nullable|string|max:255',
        ]);

        if ($validator->fails()) {

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $section->update(
            $validator->validated()
        );

        return response()->json([
            'message' =>
                'Farm section updated successfully.',

            'section' => $section,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE SECTION
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        FarmSection $section
    ): JsonResponse {

        if (
            $section->farm->owner_user_id !==
            $request->user()->id
        ) {

            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $section->delete();

        return response()->json([
            'message' =>
                'Farm section deleted successfully.',
        ]);
    }

    // Controller method to fetch all farm sections for the add
    // Analytics dialog
    /**
 * Fetch sections belonging to a farm that is owned by the authenticated user.
 * The farm_id from the request must match a farm tied to the user.
 */
public function getSectionsForFarm(Request $request): JsonResponse
{
    $validated = $request->validate([
        'farm_id' => ['required', 'uuid'],
    ]);

    $farm = Farm::where('id', $validated['farm_id'])
        ->where('owner_user_id', $request->user()->id)
        ->firstOrFail();

    $sections = FarmSection::where('farm_id', $farm->id)
        ->select(['id', 'name'])
        ->orderBy('name')
        ->get();

    return response()->json([
        'farm_id'  => $farm->id,
        'sections' => $sections,
    ]);
}

}