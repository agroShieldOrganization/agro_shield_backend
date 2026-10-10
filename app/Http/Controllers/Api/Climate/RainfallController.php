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
    | OPEN-METEO CLIMATE ENDPOINT
    |--------------------------------------------------------------------------
    |
    | Delivers long-range, daily-aggregated reanalysis models up to 3 months out.
    |
    */
    private const OPEN_METEO_URL = 'https://climate-api.open-meteo.com/v1/climate';

    /**
     * Show 30-Day Daily Aggregated Rainfall Analytics
     */
    public function show(Request $request, Farm $farm): JsonResponse
    {
        if ((string) $farm->owner_user_id !== (string) $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to access this farm.',
            ], 403);
        }

        $startDate = now()->format('Y-m-d');
        $endDate   = now()->addDays(30)->format('Y-m-d');
        $cacheKey  = "farm_rainfall_daily_v3_{$farm->id}";

        $weatherData = Cache::remember($cacheKey, now()->addHours(6), function () use ($farm, $startDate, $endDate) {
            $response = Http::timeout(15)
                ->retry(3, 1000)
                ->get(self::OPEN_METEO_URL, [
                    'latitude'   => $farm->latitude,
                    'longitude'  => $farm->longitude,
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'daily'      => 'precipitation_sum',
                    'timezone'   => 'auto',
                ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to resolve Open-Meteo Climate pipeline.');
            }

            return $response->json();
        });

        // Parse Climate API structural responses
        $daily            = $weatherData['daily'] ?? [];
        $days             = $daily['time'] ?? [];
        $dailyRainSums    = $daily['precipitation_sum'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | ADVANCED METRIC AGGREGATIONS
        |--------------------------------------------------------------------------
        */
        $nextRainEvent = $this->getNextDailyRainEvent($days, $dailyRainSums);

        // Daily Rain is today's absolute sum, Weekly is the sum of the first 7 array indices
        $dailyRainfall   = round((float) ($dailyRainSums[0] ?? 0.0), 1);
        $weeklyRainfall  = round(collect($dailyRainSums)->take(7)->sum(), 1);
        $monthlyRainfall = round(collect($dailyRainSums)->sum(), 1);

        /*
        |--------------------------------------------------------------------------
        | COMPATIBILITY FALLBACKS
        |--------------------------------------------------------------------------
        | Long-range daily climate APIs do not calculate hourly attributes like 
        | localized humidity or hourly rain probability. We set stable fallbacks.
        |
        */
        $simulatedProbability = $dailyRainfall > 0.1 ? 80 : 0; 
        $humidityFallback     = 72; // Baseline reference percentage

        $advisory = $this->buildAdvisory($dailyRainfall, $weeklyRainfall, $simulatedProbability);

        /*
        |--------------------------------------------------------------------------
        | PARSE CHART SERIES (Optimized for Recharts Daily View)
        |--------------------------------------------------------------------------
        */
        $chartSeries = [];
        foreach ($days as $index => $dayString) {
            $chartSeries[] = [
                // Returns clean shorthand names like "May 28" for chart readabilities
                'time'          => Carbon::parse($dayString)->format('M d'),
                'rain_mm'       => round((float) ($dailyRainSums[$index] ?? 0), 1),
                'probability'   => $dailyRainSums[$index] > 0.2 ? 75 : 0, 
                'precipitation' => round((float) ($dailyRainSums[$index] ?? 0), 1),
            ];
        }

        return response()->json([
            'summary' => [
                'next_rain_event'  => $nextRainEvent,
                'rain_probability' => $simulatedProbability,
                'daily_rainfall'   => $dailyRainfall,
                'weekly_rainfall'  => $weeklyRainfall,
                'monthly_rainfall' => $monthlyRainfall,
            ],
            'series'   => $chartSeries,
            'advisory' => $advisory,
            'humidity' => $humidityFallback,
            'meta'     => [
                'farm_id'    => $farm->id,
                'farm_name'  => $farm->name,
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ],
        ]);
    }

    private function getNextDailyRainEvent(array $days, array $dailyRainSums): string
    {
        foreach ($days as $index => $dayString) {
            $sum = (float) ($dailyRainSums[$index] ?? 0);

            // Meaningful rain accumulation threshold (e.g., > 1.0mm aggregate day sum)
            if ($sum >= 1.0) {
                $targetDate = Carbon::parse($dayString);

                if ($targetDate->isToday()) {
                    return 'Today';
                }
                if ($targetDate->isTomorrow()) {
                    return 'Tomorrow';
                }
                return $targetDate->format('l, M jS');
            }
        }

        return 'No heavy rain within 30 days';
    }

    private function buildAdvisory(float $dailyRainfall, float $weeklyRainfall, int $probability): array
    {
        $irrigationAdvice = match (true) {
            $dailyRainfall >= 12 => 'Heavy rain expected today. Suspend automated irrigation.',
            $weeklyRainfall >= 35 => 'Consistent weekly rain levels detected. Keep irrigation minimal.',
            default => 'Precipitation is tracking low over this window. Prepare supplemental irrigation routines.',
        };

        $floodAdvice = match (true) {
            $dailyRainfall >= 30 => 'CRITICAL WATCH: High daily accumulation pattern detected. Check clearing paths across drainage arrays.',
            $weeklyRainfall >= 80 => 'Sustained seasonal rainfall accumulation. Soils may track toward saturation.',
            default => 'No critical regional flood patterns monitored. Proceed normally.',
        };

        return ['irrigation' => $irrigationAdvice, 'flood' => $floodAdvice];
    }
}