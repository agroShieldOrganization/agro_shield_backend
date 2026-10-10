<?php

namespace App\Http\Controllers\Api\Climate;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SoilAnalyticsController extends Controller
{
    private const OPEN_METEO_URL = 'https://api.open-meteo.com/v1/forecast';

    /**
     * Map Open-Meteo Valid Hourly Soil Data to UI View Layers
     */
    public function soilanalytics(Request $request, Farm $farm): JsonResponse
    {
        // Security Context Check
        if ((string) $farm->owner_user_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Unauthorized farm context access.'], 403);
        }

        $cacheKey = "farm_soil_telemetry_v2_{$farm->id}";

        $weatherData = Cache::remember($cacheKey, now()->addHours(3), function () use ($farm) {
            $response = Http::timeout(15)
                ->retry(2, 1000)
                ->get(self::OPEN_METEO_URL, [
                    'latitude'  => $farm->latitude,
                    'longitude' => $farm->longitude,
                    'hourly'    => implode(',', [
                        'soil_temperature_0cm',
                        'soil_temperature_6cm',
                        'soil_temperature_18cm',
                        'soil_temperature_54cm',
                        'soil_moisture_0_to_1cm',
                        'soil_moisture_1_to_3cm',
                        'soil_moisture_3_to_9cm',
                        'soil_moisture_9_to_27cm',
                        'soil_moisture_27_to_81cm'
                    ]),
                    'forecast_days' => 7,
                    'timezone'      => 'auto',
                ]);

            if (!$response->successful()) {
                throw new \Exception('Open-Meteo validated hourly soil pipeline query failed.');
            }

            return $response->json();
        });

        $hourly = $weatherData['hourly'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | DATA AGGREGATION PIPELINE (Current Day / 24 hour snapshot)
        |--------------------------------------------------------------------------
        */
        $dayRange = 24;

        $surfaceTemp = count($hourly['soil_temperature_0cm'] ?? []) ? max(array_slice($hourly['soil_temperature_0cm'], 0, $dayRange)) : 20.0;
        $shallowTemp = count($hourly['soil_temperature_6cm'] ?? []) ? max(array_slice($hourly['soil_temperature_6cm'], 0, $dayRange)) : 17.0;
        $midTemp     = count($hourly['soil_temperature_18cm'] ?? []) ? max(array_slice($hourly['soil_temperature_18cm'], 0, $dayRange)) : 14.0;
        $deepTemp    = count($hourly['soil_temperature_54cm'] ?? []) ? max(array_slice($hourly['soil_temperature_54cm'], 0, $dayRange)) : 10.0;

        $moisture0_1  = count($hourly['soil_moisture_0_to_1cm'] ?? []) ? array_sum(array_slice($hourly['soil_moisture_0_to_1cm'], 0, $dayRange)) / $dayRange : 0.20;
        $moisture1_3  = count($hourly['soil_moisture_1_to_3cm'] ?? []) ? array_sum(array_slice($hourly['soil_moisture_1_to_3cm'], 0, $dayRange)) / $dayRange : 0.22;
        $moisture3_9  = count($hourly['soil_moisture_3_to_9cm'] ?? []) ? array_sum(array_slice($hourly['soil_moisture_3_to_9cm'], 0, $dayRange)) / $dayRange : 0.25;
        $moisture9_27 = count($hourly['soil_moisture_9_to_27cm'] ?? []) ? array_sum(array_slice($hourly['soil_moisture_9_to_27cm'], 0, $dayRange)) / $dayRange : 0.28;
        $moisture27_8 = count($hourly['soil_moisture_27_to_81cm'] ?? []) ? array_sum(array_slice($hourly['soil_moisture_27_to_81cm'], 0, $dayRange)) / $dayRange : 0.32;

        $uiSurfaceMoisture = ($moisture0_1 + $moisture1_3 + $moisture3_9) / 3;
        $uiShallowMoisture = $moisture9_27;
        $uiMidMoisture     = $moisture27_8;
        $uiDeepMoisture    = $moisture27_8 * 1.05;

        /*
        |--------------------------------------------------------------------------
        | COMPILING THE 7-DAY DAILY TREND FOR FRONTEND GRAPHING
        |--------------------------------------------------------------------------
        | Chunks the 168 hourly entries down into 7 daily chunks.
        */
        $dailyTrends = [];
        $timeEntries = $hourly['time'] ?? [];
        
        for ($i = 0; $i < 7; $i++) {
            $offset = $i * 24;
            
            // If arrays contain partial data bounds, break out early safely
            if (!isset($timeEntries[$offset])) break;

            // Formulate date label (e.g., "May 26")
            $dateLabel = date('M d', strtotime($timeEntries[$offset]));

            // Slice out 24-hour sub-segments
            $daySurfaceTemps = array_slice($hourly['soil_temperature_0cm'] ?? [], $offset, 24);
            $dayMoisture0_1  = array_slice($hourly['soil_moisture_0_to_1cm'] ?? [], $offset, 24);
            $dayMoisture1_3  = array_slice($hourly['soil_moisture_1_to_3cm'] ?? [], $offset, 24);
            $dayMoisture3_9  = array_slice($hourly['soil_moisture_3_to_9cm'] ?? [], $offset, 24);

            // Calculate daily averages
            $avgTemp = count($daySurfaceTemps) ? (array_sum($daySurfaceTemps) / count($daySurfaceTemps)) : 0;
            
            $avgM0_1 = count($dayMoisture0_1) ? (array_sum($dayMoisture0_1) / count($dayMoisture0_1)) : 0;
            $avgM1_3 = count($dayMoisture1_3) ? (array_sum($dayMoisture1_3) / count($dayMoisture1_3)) : 0;
            $avgM3_9 = count($dayMoisture3_9) ? (array_sum($dayMoisture3_9) / count($dayMoisture3_9)) : 0;
            
            // Combine volumetric water content points, convert to an integer percentage (%)
            $combinedMoistureAvg = (($avgM0_1 + $avgM1_3 + $avgM3_9) / 3) * 100;

            $dailyTrends[] = [
                'day'         => $dateLabel,
                'moisture'    => round($combinedMoistureAvg, 0),
                'temperature' => round($avgTemp, 1),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | AGRONOMIC RULE ENGINES
        |--------------------------------------------------------------------------
        */
        $irrigationNeed = match (true) {
            $uiSurfaceMoisture < 0.15 => 'Critical / High',
            $uiSurfaceMoisture < 0.24 => 'Moderate',
            default                   => 'Optimal',
        };

        $soilHealthStatus = ($surfaceTemp > 38.0 || $uiSurfaceMoisture < 0.10) ? 'Thermal Stress' : 'Optimal';

        $rootCondition = ($uiMidMoisture >= 0.24)
            ? 'Mid-zone subsoil moisture reserves remain highly favorable for deep root development and active expansion.'
            : 'Subsoil profile moisture levels are declining. Root elongation rates may slow down without moisture penetration.';

        $dryingRisk = ($uiSurfaceMoisture < 0.20)
            ? 'Topsoil drying risk is active due to surface evapotranspiration. Light irrigation loop advised within 48 hours.'
            : 'Surface evaporation is balanced. Topsoil retains acceptable hydration layers.';

        return response()->json([
            'soilMetrics' => [
                'moisture' => [
                    'surface' => round($uiSurfaceMoisture, 2),
                    'shallow' => round($uiShallowMoisture, 2),
                    'mid'     => round($uiMidMoisture, 2),
                    'deep'    => round(min($uiDeepMoisture, 1.0), 2),
                ],
                'temperature' => [
                    'surface' => round($surfaceTemp, 1),
                    'shallow' => round($shallowTemp, 1),
                    'mid'     => round($midTemp, 1),
                    'deep'    => round($deepTemp, 1),
                ],
                'soilHealth'     => $soilHealthStatus,
                'irrigationNeed' => $irrigationNeed,
            ],
            'rootZoneAnalysis' => [
                'expansionCondition' => $rootCondition,
                'surfaceDryingRisk'  => $dryingRisk,
            ],
            'dailyTrends' => $dailyTrends, // Pre-processed and structured for Recharts!
            'responseData' => [
                'rawHourlyData' => $hourly,
            ],
        ]);
    }
}