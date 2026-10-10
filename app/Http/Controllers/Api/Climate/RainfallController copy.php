<?php

namespace App\Http\Controllers\Api\Climate;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class RainfallController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | OPEN-METEO PRIMARY FORECAST ENDPOINT
    |--------------------------------------------------------------------------
    |
    | Delivers the high-resolution, bias-corrected multi-model timeline grid.
    |
    */
    private const OPEN_METEO_URL = 'https://api.open-meteo.com/v1/forecast';

    /**
     * Show Rainfall Analytics Viewport
     */
    public function show(Request $request, Farm $farm): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | AUTHORIZE FARM OWNERSHIP
        |--------------------------------------------------------------------------
        */
        if ((string) $farm->owner_user_id !== (string) $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to access this farm.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | TIMELINE CALCULATOR
        |--------------------------------------------------------------------------
        |
        | Fetch max high-resolution forecast buffer permitted (14 Days)
        |
        */
        $startDate = now()->format('Y-m-d');
        $endDate = now()->addDays(30)->format('Y-m-d');
        $cacheKey = "farm_rainfall_v2_{$farm->id}";

        /*
        |--------------------------------------------------------------------------
        | NETWORK ENGINE & CACHING
        |--------------------------------------------------------------------------
        */
        $weatherData = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($farm) {
            $response = Http::timeout(15)
                ->retry(3, 1000)
                ->get(self::OPEN_METEO_URL, [
                    'latitude'         => $farm->latitude,
                    'longitude'        => $farm->longitude,
                    'forecast_days'    => 14,
                    'hourly'           => implode(',', [
                        'rain',
                        'precipitation_probability',
                        'precipitation',
                        'relative_humidity_2m',
                    ]),
                    'timezone'         => 'auto',
                ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to resolve Open-Meteo forecast pipeline.');
            }

            return $response->json();
        });

        // Safe extraction fallbacks
        $hourly         = $weatherData['hourly'] ?? [];
        $times          = $hourly['time'] ?? [];
        $rainValues     = $hourly['rain'] ?? [];
        $probabilities  = $hourly['precipitation_probability'] ?? [];
        $precipitation  = $hourly['precipitation'] ?? [];
        $humidityValues = $hourly['relative_humidity_2m'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | CALCULATE KPI AGGREGATIONS
        |--------------------------------------------------------------------------
        */
        $nextRainEvent = $this->getNextRainEvent($times, $rainValues, $probabilities);

        // Next 24 Hours Metrics
        $next24HoursProbability = collect($probabilities)->take(24)->max() ?? 0;
        $dailyRainfall          = round(collect($rainValues)->take(24)->sum(), 1);

        // Next 7 Days Accumulation (168 Hours)
        $weeklyRainfall         = round(collect($rainValues)->take(168)->sum(), 1);

        // Next 14 Days Aggregated Total Model (Proxy for standard Monthly indicator)
        $monthlyRainfall        = round(collect($rainValues)->sum(), 1);

        // Capture closest current hour atmospheric humidity index
        $humidity = (int) round((float) ($humidityValues[0] ?? 0));

        /*
        |--------------------------------------------------------------------------
        | AGRICULTURAL INSIGHT GENERATOR
        |--------------------------------------------------------------------------
        */
        $advisory = $this->buildAdvisory($dailyRainfall, $weeklyRainfall, (int) $next24HoursProbability);

        /*
        |--------------------------------------------------------------------------
        | PARSE CHART SERIES (Optimized structural payload matching Recharts)
        |--------------------------------------------------------------------------
        */
        $chartSeries = [];
        foreach ($times as $index => $time) {
            // ISO8601 formatting for clean parsing in Recharts frontend
            $formattedTime = Carbon::parse($time)->toIso8601String();

            $chartSeries[] = [
                'time'          => $formattedTime,
                'rain_mm'       => round((float) ($rainValues[$index] ?? 0), 1),
                'probability'   => (int) ($probabilities[$index] ?? 0),
                'precipitation' => round((float) ($precipitation[$index] ?? 0), 1),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | JSON API CONTRACT DELIVERY
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'summary' => [
                'next_rain_event'  => $nextRainEvent,
                'rain_probability' => (int) $next24HoursProbability,
                'daily_rainfall'   => $dailyRainfall,
                'weekly_rainfall'  => $weeklyRainfall,
                'monthly_rainfall' => $monthlyRainfall, // Transmitted cleanly to top components
            ],
            'series'   => $chartSeries,
            'advisory' => $advisory,
            'humidity' => $humidity,
            'meta'     => [
                'farm_id'    => $farm->id,
                'farm_name'  => $farm->name,
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ],
        ]);
    }

    /**
     * Parse array loops to detect next meaningful rain timeline
     */
    private function getNextRainEvent(array $times, array $rainValues, array $probabilities): string
    {
        foreach ($times as $index => $time) {
            $rain        = (float) ($rainValues[$index] ?? 0);
            $probability = (int) ($probabilities[$index] ?? 0);

            // Metric thresholds: Minimum 0.2mm depth at a realistic 40% confidence mark
            if ($rain >= 0.2 && $probability >= 40) {
                $forecastDate = Carbon::parse($time);

                if ($forecastDate->isToday()) {
                    return 'Today at ' . $forecastDate->format('g:i A');
                }
                if ($forecastDate->isTomorrow()) {
                    return 'Tomorrow at ' . $forecastDate->format('g:i A');
                }
                return $forecastDate->format('l, M jS');
            }
        }

        return 'No rain expected within 14 days';
    }

    /**
     * Build rule-based agricultural telemetry alerts
     */
    private function buildAdvisory(float $dailyRainfall, float $weeklyRainfall, int $probability): array
    {
        $irrigationAdvice = match (true) {
            $dailyRainfall >= 12 => 'Rain supply looks strong today. Suspend automated irrigation and monitor drainage systems.',
            $weeklyRainfall >= 35 => 'Moderate rainfall is expected across the weekly window. Keep irrigation minimal.',
            default => 'Precipitation volume is tracking low. Ready supplemental irrigation layers to support target growth stages.',
        };

        $floodAdvice = match (true) {
            $dailyRainfall >= 25 || $probability >= 85 => 'CRITICAL ALERT: Flash runoff risks detected. Check blockages along main structural drainage tracks immediately.',
            $weeklyRainfall >= 70 => 'Soil profiling indicates structural saturation across this week. Avoid running heavy equipment in low fields.',
            default => 'No active structural flood alerts detected. Proceed with standard operations.',
        };

        return [
            'irrigation' => $irrigationAdvice,
            'flood'      => $floodAdvice,
        ];
    }
}