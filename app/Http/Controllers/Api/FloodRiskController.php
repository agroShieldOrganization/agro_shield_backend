<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\Farm;
use Carbon\Carbon;

class FloodRiskController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CONSTANTS
    |--------------------------------------------------------------------------
    */

    private const FLOOD_API_URL   = 'https://flood-api.open-meteo.com/v1/flood';
    private const FORECAST_DAYS   = 7;

    /*
    |--------------------------------------------------------------------------
    | How long we cache the long-term flood threshold per farm.
    | The 1984–present p95 barely shifts week to week, so 7 days is safe.
    |--------------------------------------------------------------------------
    */

    private const THRESHOLD_CACHE_TTL_HOURS = 168; // 7 days

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | DATE RANGE FROM FRONTEND
        |--------------------------------------------------------------------------
        |
        | Expects:  ?start_date=2024-01-01&end_date=2024-12-31
        | Defaults: last 90 days if not provided.
        |
        */

        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->format('Y-m-d')
            : Carbon::now()->subDays(90)->format('Y-m-d');

        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->format('Y-m-d')
            : Carbon::now()->format('Y-m-d');

        $farms = Farm::where('owner_user_id', $request->user()->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $results = [];

        foreach ($farms as $farm) {

            /*
            |--------------------------------------------------------------
            | STEP 1 — LONG-TERM FLOOD THRESHOLD (cached)
            |--------------------------------------------------------------
            |
            | Fetch 1984–today ONCE per farm and cache the p95 discharge.
            | This is the level at which THIS river has historically been
            | at extreme high-flow, used as our flood threshold.
            |
            */

            $threshold = $this->getFloodThreshold($farm);

            if ($threshold === null) {
                continue;
            }

            /*
            |--------------------------------------------------------------
            | STEP 2 — DATE-RANGE SERIES (for frontend graph)
            |--------------------------------------------------------------
            */

            $seriesResponse = $this->fetchDischargeSeries(
                lat:       (float) $farm->latitude,
                lng:       (float) $farm->longitude,
                startDate: $startDate,
                endDate:   $endDate,
            );

            if ($seriesResponse === null) {
                continue;
            }

            /*
            |--------------------------------------------------------------
            | STEP 3 — 7-DAY FORECAST (for risk prediction)
            |--------------------------------------------------------------
            */

            $forecastResponse = $this->fetchForecast(
                lat: (float) $farm->latitude,
                lng: (float) $farm->longitude,
            );

            if ($forecastResponse === null) {
                continue;
            }

            /*
            |--------------------------------------------------------------
            | FLOOD RISK — compare forecast peak against long-term p95
            |--------------------------------------------------------------
            */

            $forecastDaily    = $forecastResponse['daily']                    ?? [];
            $forecastSeries   = $forecastDaily['river_discharge']              ?? [];
            $forecastMaxSeries= $forecastDaily['river_discharge_max']          ?? [];
            $forecastTimes    = $forecastDaily['time']                         ?? [];

            $peakDischarge = $this->maxValue(
                count($forecastMaxSeries) > 0 ? $forecastMaxSeries : $forecastSeries
            );

            /*
            |--------------------------------------------------------------
            | CORE LOGIC
            |
            | "Is forecast discharge > the level at which THIS river has
            |  historically flooded?"
            |
            | threshold.p95  = river's extreme high-flow level (flood proxy)
            | threshold.p75  = river's elevated-flow level (elevated risk)
            | threshold.mean = river's long-term average
            |
            */

            $floodThresholdP95 = $threshold['p95'];
            $floodThresholdP75 = $threshold['p75'];

            $riskRatio = $floodThresholdP95 > 0
                ? $peakDischarge / $floodThresholdP95
                : 0;

            [$floodRisk, $riskLevel] = $this->classifyRisk($riskRatio);

            /*
            |--------------------------------------------------------------
            | PEAK DATE
            |--------------------------------------------------------------
            */

            $peakIndex = $this->maxIndex(
                count($forecastMaxSeries) > 0 ? $forecastMaxSeries : $forecastSeries
            );

            $peakDate = $forecastTimes[$peakIndex] ?? null;

            /*
            |--------------------------------------------------------------
            | GRID CELL DRIFT
            |--------------------------------------------------------------
            */

            $resolvedLat     = $seriesResponse['latitude']  ?? $farm->latitude;
            $resolvedLng     = $seriesResponse['longitude'] ?? $farm->longitude;
            $coordinateDrift = round(
                $this->haversineDistance(
                    (float) $farm->latitude,
                    (float) $farm->longitude,
                    (float) $resolvedLat,
                    (float) $resolvedLng,
                ),
                2
            );

            /*
            |--------------------------------------------------------------
            | BUILD GRAPH SERIES
            |--------------------------------------------------------------
            |
            | Returns time + discharge arrays so the frontend can plot a
            | line graph. Also includes the threshold lines so the chart
            | can render a "flood level" reference line.
            |
            */

            $seriesDaily = $seriesResponse['daily'] ?? [];

            $results[] = [
                'farm_id'  => $farm->id,
                'name'     => $farm->name,
                'latitude' => $farm->latitude,
                'longitude'=> $farm->longitude,

                /*
                |----------------------------------------------------------
                | RISK SUMMARY
                |----------------------------------------------------------
                */
                'flood_risk'           => round($floodRisk, 2),
                'risk_level'           => $riskLevel,
                'provider'             => 'open-meteo-flood',
                'forecast_window_days' => self::FORECAST_DAYS,
                'risk_date'            => $peakDate,

                /*
                |----------------------------------------------------------
                | DISCHARGE DETAILS
                |----------------------------------------------------------
                */
                'weather' => [
                    'peak_discharge'      => round($peakDischarge, 2),
                    'flood_threshold_p95' => round($floodThresholdP95, 2),
                    'flood_threshold_p75' => round($floodThresholdP75, 2),
                    'long_term_mean'      => round($threshold['mean'], 2),
                    'risk_ratio'          => round($riskRatio, 3),
                ],

                /*
                |----------------------------------------------------------
                | TIME SERIES — for the frontend line graph
                |----------------------------------------------------------
                |
                | `series`     → requested date-range discharge values
                | `forecast`   → 7-day forward discharge values
                | `thresholds` → horizontal reference lines for the chart
                |
                */
                'graph' => [
                    'series' => [
                        'dates'     => $seriesDaily['time']             ?? [],
                        'discharge' => $seriesDaily['river_discharge']  ?? [],
                    ],
                    'forecast' => [
                        'dates'     => $forecastTimes,
                        'discharge' => $forecastSeries,
                        'discharge_max' => $forecastMaxSeries,
                    ],
                    'thresholds' => [
                        'flood_level_p95'  => round($floodThresholdP95, 2),
                        'elevated_p75'     => round($floodThresholdP75, 2),
                        'long_term_mean'   => round($threshold['mean'], 2),
                    ],
                ],

                /*
                |----------------------------------------------------------
                | GRID CELL
                |----------------------------------------------------------
                */
                'grid_cell' => [
                    'resolved_latitude'  => $resolvedLat,
                    'resolved_longitude' => $resolvedLng,
                    'drift_km'           => $coordinateDrift,
                ],
            ];
        }

        return response()->json([
            'flood_risks' => $results,
            'meta' => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FLOOD THRESHOLD — fetch 1984→today, compute p95/p75/mean, cache it
    |--------------------------------------------------------------------------
    */

    private function getFloodThreshold(Farm $farm): ?array
    {
        $cacheKey = "flood_threshold_{$farm->id}";

        return Cache::remember(
            $cacheKey,
            now()->addHours(self::THRESHOLD_CACHE_TTL_HOURS),
            function () use ($farm) {

                $response = Http::acceptJson()
                    ->timeout(30) // longer timeout — this is a big request
                    ->retry(2, 1000)
                    ->get(self::FLOOD_API_URL, [
                        'latitude'       => $farm->latitude,
                        'longitude'      => $farm->longitude,
                        'daily'          => 'river_discharge',
                        'start_date'     => '1984-01-01',
                        'end_date'       => Carbon::now()->format('Y-m-d'),
                        'cell_selection' => 'nearest',
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                $discharge = $response->json('daily.river_discharge') ?? [];

                if (count($discharge) === 0) {
                    return null;
                }

                return [
                    'p95'  => $this->percentile($discharge, 95),
                    'p75'  => $this->percentile($discharge, 75),
                    'mean' => $this->averageValue($discharge),
                ];
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DATE-RANGE SERIES — for the frontend graph
    |--------------------------------------------------------------------------
    */

    private function fetchDischargeSeries(
        float $lat,
        float $lng,
        string $startDate,
        string $endDate,
    ): ?array {

        $response = Http::acceptJson()
            ->timeout(15)
            ->retry(2, 500)
            ->get(self::FLOOD_API_URL, [
                'latitude'       => $lat,
                'longitude'      => $lng,
                'daily'          => 'river_discharge',
                'start_date'     => $startDate,
                'end_date'       => $endDate,
                'cell_selection' => 'nearest',
            ]);

        return $response->successful() ? $response->json() : null;
    }

    /*
    |--------------------------------------------------------------------------
    | 7-DAY FORECAST
    |--------------------------------------------------------------------------
    */

    private function fetchForecast(float $lat, float $lng): ?array
    {
        $response = Http::acceptJson()
            ->timeout(15)
            ->retry(2, 500)
            ->get(self::FLOOD_API_URL, [
                'latitude'       => $lat,
                'longitude'      => $lng,
                'daily'          => implode(',', [
                    'river_discharge',
                    'river_discharge_max',
                ]),
                'forecast_days'  => self::FORECAST_DAYS,
                'cell_selection' => 'nearest',
            ]);

        return $response->successful() ? $response->json() : null;
    }

    /*
    |--------------------------------------------------------------------------
    | RISK CLASSIFICATION
    |--------------------------------------------------------------------------
    |
    | Now anchored to the long-term p95 flood threshold:
    |
    | ratio >= 1.0  → forecast exceeds the historical flood level  → HIGH
    | ratio >= 0.75 → forecast approaching the flood level         → MEDIUM
    | ratio <  0.75 → forecast well below the flood level          → LOW
    |
    */

    private function classifyRisk(float $ratio): array
    {
        $score = match (true) {
            $ratio >= 1.00 => min(100, 70 + (($ratio - 1.00) * 60)),
            $ratio >= 0.75 => 40 + (($ratio - 0.75) / 0.25) * 30,
            $ratio >= 0.50 => 15 + (($ratio - 0.50) / 0.25) * 25,
            default        => max(5, $ratio * 30),
        };

        $level = match (true) {
            $score >= 70 => 'high',
            $score >= 40 => 'medium',
            default      => 'low',
        };

        return [round($score, 2), $level];
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function maxValue(array $values): float
    {
        if (count($values) === 0) return 0.0;
        return (float) max(array_map('floatval', $values));
    }

    private function averageValue(array $values): float
    {
        if (count($values) === 0) return 0.0;
        $normalized = array_map('floatval', $values);
        return array_sum($normalized) / count($normalized);
    }

    private function maxIndex(array $values): int
    {
        if (count($values) === 0) return 0;
        $normalized = array_map('floatval', $values);
        $index = array_search(max($normalized), $normalized, true);
        return is_int($index) ? $index : 0;
    }

    private function percentile(array $values, int $percentile): float
    {
        if (count($values) === 0) return 0.0;
        $sorted = array_map('floatval', $values);
        sort($sorted);
        $index = (int) ceil(($percentile / 100) * count($sorted)) - 1;
        return $sorted[max(0, $index)];
    }

    private function haversineDistance(
        float $lat1, float $lng1,
        float $lat2, float $lng2,
    ): float {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}