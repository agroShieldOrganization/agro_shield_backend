<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class TomorrowIoTestController extends Controller
{
    public function test(): JsonResponse
    {
        // Coordinates for Abuja, Nigeria
        $latitude = 9.0765;
        $longitude = 7.3986;

        $response = Http::get('https://api.tomorrow.io/v4/timelines', [
            'location'  => "{$latitude},{$longitude}",
            // FIXED: Changed 'streamflow' to camelCase 'streamFlow'
            'fields'    => 'floodIndex,rainAccumulation,rainIntensity,precipitationProbability,soilMoistureVolumetric0To10,streamFlow',
            'timesteps' => '1h', 
            'units'     => 'metric',
            'apikey'    => env('TOMORROW_IO_API_KEY'),
        ]);

        return response()->json([
            'success'    => $response->successful(),
            'status'     => $response->status(),
            'data'       => $response->json(),
            'rawPayload' => $response->body(),
        ]);
    }
}