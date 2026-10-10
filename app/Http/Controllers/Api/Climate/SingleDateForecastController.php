<?php

namespace App\Http\Controllers\Api\Climate;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SingleDateForecastController extends Controller
{
    private const FORECAST_API_URL =
        'https://api.open-meteo.com/v1/forecast';

    private const SEASONAL_API_URL =
        'https://seasonal-api.open-meteo.com/v1/seasonal';

    public function getSnapshot(Request $request): JsonResponse
    {
        $request->validate([
            'farm_id' => 'required|exists:farms,id',
            'date'    => 'required|date|after_or_equal:today',
        ]);

        $farm = Farm::findOrFail($request->farm_id);
        
        // Enforce secure ownership checks
        if ((string) $farm->owner_user_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Unauthorized farm access.'], 403);
        }

        $targetDate = Carbon::parse($request->date);
        $daysAhead =
            now()->startOfDay()
                ->diffInDays(
                    $targetDate->copy()->startOfDay()
                );

        // Open-Meteo's general forecast API supports short-range deterministic
        // weather forecasts, while longer date-specific lookups should use the
        // seasonal forecast API rather than the climate change projection API.
        $isWithinForecastWindow =
            $daysAhead <= 16;

        if ($isWithinForecastWindow) {
            // Use the standard daily forecast endpoint for precise short-range
            // snapshots.
            $url = self::FORECAST_API_URL;
            $params = [
                'latitude'  => $farm->latitude,
                'longitude' => $farm->longitude,
                'start_date' => $targetDate->format('Y-m-d'),
                'end_date'   => $targetDate->format('Y-m-d'),
                'daily'      => implode(',', [
                    'precipitation_sum',
                    'precipitation_probability_max',
                ]),
                'timezone'   => 'auto'
            ];
        } else {
            // Open-Meteo's climate API is for climate-change projections and
            // also requires explicit models. For date-specific long-range
            // weather outlooks, the seasonal forecast API is the correct fit.
            $url = self::SEASONAL_API_URL;
            $params = [
                'latitude'   => $farm->latitude,
                'longitude'  => $farm->longitude,
                'start_date' => $targetDate->format('Y-m-d'),
                'end_date'   => $targetDate->format('Y-m-d'),
                'daily'      => 'precipitation_sum',
                'models'     => 'ecmwf_seamless',
                'timezone'   => 'auto'
            ];
        }

        $response = Http::timeout(15)
            ->retry(2, 800)
            ->get($url, $params);

        if (!$response->successful()) {
            return response()->json([
                'message' =>
                    'Failed to reach Open-Meteo engines.',
                'upstream_status' =>
                    $response->status(),
                'upstream_body' =>
                    $response->body(),
            ], 502);
        }

        $data = $response->json();

        // Parse structures based on the target API endpoint hit
        if ($isWithinForecastWindow) {
            $daily = $data['daily'] ?? [];
            $rainMm = round(
                (float) ($daily['precipitation_sum'][0] ?? 0),
                1
            );
            $prob = (int) (
                $daily['precipitation_probability_max'][0] ?? 0
            );
        } else {
            $daily  = $data['daily'] ?? [];
            $rainMm = round((float)($daily['precipitation_sum'][0] ?? 0), 1);
            $prob   = $rainMm > 0.1 ? 65 : 0;
        }

        return response()->json([
            'date' => $targetDate->format('Y-m-d'),
            'rain_mm' => $rainMm,
            'probability' => (int) $prob,
            'source' => $isWithinForecastWindow ? 'forecast_model' : 'seasonal_model'
        ]);
    }
}
