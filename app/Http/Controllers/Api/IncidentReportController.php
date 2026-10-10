<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\IncidentReport;

use Illuminate\Http\JsonResponse;

class IncidentReportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CREATE REPORT
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): JsonResponse {

        $validated =
            $request->validate([

                'incident_type' =>
                    'required|string',

                'severity' =>
                    'required|string',

                'title' =>
                    'nullable|string',

                'location_name' =>
                    'required|string',

                'latitude' =>
                    'nullable|numeric',

                'longitude' =>
                    'nullable|numeric',

                'description' =>
                    'required|string',

                'affected_area_hectares' =>
                    'nullable|numeric',

                'contact_phone' =>
                    'nullable|string',

                'public_visibility' =>
                    'nullable|string',

                'occurred_at' =>
                    'nullable|date',
            ]);

        $report =
            IncidentReport::create([

                ...$validated,

                'reporter_user_id' =>
                    $request->user()?->id,

                'verification_status' =>
                    'pending',

                'submitted_at' =>
                    now(),
            ]);

        return response()->json([

            'message' =>
                'Incident report submitted',

            'report' =>
                $report,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET REPORTS
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return response()->json([

            'reports' =>

                IncidentReport::with([
                    'reporter',
                    'files',
                ])
                ->latest()
                ->paginate(20),
        ]);
    }
}