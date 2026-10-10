<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Http;

use App\Models\Farm;

use Carbon\Carbon;

class FloodZoneController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CONSTANTS
    |--------------------------------------------------------------------------
    */

    private const FLOOD_API_URL =
        'https://flood-api.open-meteo.com/v1/flood';

    private const FORECAST_DAYS = 7;

    private const THRESHOLD_CACHE_TTL_HOURS = 168;

    private const ZONE_CACHE_TTL_MINUTES = 120;

    /*
    |--------------------------------------------------------------------------
    | RIVER BASINS
    |--------------------------------------------------------------------------
    */

    private const RIVER_BASINS = [

        [
            'name'    => 'Upper & Basin Niger (North/Central)',
            'river'   => 'Niger',
            'lat_min' => 7.5,
            'lat_max' => 12.0,
            'lng_min' => 2.5,
            'lng_max' => 7.0,
        ],

        [
            'name'    => 'Sokoto-Rima Basin',
            'river'   => 'Sokoto',
            'lat_min' => 10.5,
            'lat_max' => 14.0,
            'lng_min' => 3.5,
            'lng_max' => 7.5,
        ],

        [
            'name'    => 'Kaduna River Basin',
            'river'   => 'Kaduna',
            'lat_min' => 8.5,
            'lat_max' => 11.5,
            'lng_min' => 5.5,
            'lng_max' => 8.5,
        ],

        [
            'name'    => 'Benue River Basin',
            'river'   => 'Benue',
            'lat_min' => 6.5,
            'lat_max' => 10.0,
            'lng_min' => 6.8,
            'lng_max' => 14.0,
        ],

        [
            'name'    => 'Hadejia-Jama\'are-Chad Basin',
            'river'   => 'Hadejia',
            'lat_min' => 11.0,
            'lat_max' => 14.0,
            'lng_min' => 8.0,
            'lng_max' => 14.5,
        ],

        [
            'name'    => 'Cross River Basin',
            'river'   => 'Cross River',
            'lat_min' => 4.5,
            'lat_max' => 7.0,
            'lng_min' => 7.8,
            'lng_max' => 9.5,
        ],

        [
            'name'    => 'Niger Delta & Anambra-Imo Basin',
            'river'   => 'Niger',
            'lat_min' => 4.0,
            'lat_max' => 7.5,
            'lng_min' => 5.0,
            'lng_max' => 8.5,
        ],

        [
            'name'    => 'Ogun-Osun-Owena Basin',
            'river'   => 'Ogun',
            'lat_min' => 6.0,
            'lat_max' => 9.0,
            'lng_min' => 2.5,
            'lng_max' => 6.0,
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): JsonResponse {

        $userId =
            $request->user()->id;

        $cacheKey =
            "flood_zones_user_{$userId}";

        $floodZones = Cache::remember(

            $cacheKey,

            now()->addMinutes(
                self::ZONE_CACHE_TTL_MINUTES
            ),

            function () use ($userId) {

                return $this->buildFloodZones(
                    $userId
                );
            }
        );

        return response()->json([

            'flood_zones' =>
                $floodZones,

            'meta' => [

                'total_zones' =>

                    count($floodZones),

                'critical_zones' =>

                    collect($floodZones)
                        ->where(
                            'risk_level',
                            'critical'
                        )
                        ->count(),

                'high_zones' =>

                    collect($floodZones)
                        ->where(
                            'risk_level',
                            'high'
                        )
                        ->count(),

                'generated_at' =>

                    now()->toIso8601String(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD FLOOD ZONES
    |--------------------------------------------------------------------------
    */

    private function buildFloodZones(
        $userId
    ): array {

        /*
        |--------------------------------------------------------------------------
        | LOAD USER FARMS
        |--------------------------------------------------------------------------
        */

        $farms = Farm::where(
                'owner_user_id',
                $userId
            )
            ->whereNotNull(
                'latitude'
            )
            ->whereNotNull(
                'longitude'
            )
            ->get();

        if ($farms->isEmpty()) {

            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | GROUP FARMS INTO BASINS
        |--------------------------------------------------------------------------
        */

        $basinGroups = [];

        foreach ($farms as $farm) {

            $basin =
                $this->resolveBasin(
                    (float) $farm->latitude,
                    (float) $farm->longitude
                );

            $basinKey =
                $basin['name'];

            /*
            |--------------------------------------------------------------------------
            | PREVENT DUPLICATES
            |--------------------------------------------------------------------------
            */

            $basinGroups[$basinKey]['basin'] =
                $basin;

            $basinGroups[$basinKey]['farms'][$farm->id] =
                $farm;
        }

        /*
        |--------------------------------------------------------------------------
        | BUILD ZONES
        |--------------------------------------------------------------------------
        */

        $zones = [];

        foreach (
            $basinGroups
            as $basinKey => $group
        ) {

            /*
            |--------------------------------------------------------------------------
            | NORMALIZE ARRAY KEYS
            |--------------------------------------------------------------------------
            */

            $basinFarms =
                array_values(
                    $group['farms']
                );

            $basin =
                $group['basin'];

            $farmResults = [];

            foreach ($basinFarms as $farm) {

                /*
                |--------------------------------------------------------------------------
                | THRESHOLD
                |--------------------------------------------------------------------------
                */

                $threshold =
                    $this->getFloodThreshold(
                        $farm
                    );

                if (!$threshold) {

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | FORECAST
                |--------------------------------------------------------------------------
                */

                $forecast =
                    $this->fetchForecast(
                        (float) $farm->latitude,
                        (float) $farm->longitude
                    );

                if (!$forecast) {

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDATE DAILY DATA
                |--------------------------------------------------------------------------
                */

                $daily =
                    $forecast['daily']
                    ?? null;

                if (!$daily) {

                    continue;
                }

                $forecastSeries =
                    $daily['river_discharge']
                    ?? [];

                $forecastMaxSeries =
                    $daily['river_discharge_max']
                    ?? [];

                /*
                |--------------------------------------------------------------------------
                | VALIDATE SERIES
                |--------------------------------------------------------------------------
                */

                $dischargeSeries =

                    count(
                        $forecastMaxSeries
                    ) > 0

                        ? $forecastMaxSeries

                        : $forecastSeries;

                if (
                    count(
                        $dischargeSeries
                    ) === 0
                ) {

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | PEAK DISCHARGE
                |--------------------------------------------------------------------------
                */

                $peakDischarge =
                    $this->maxValue(
                        $dischargeSeries
                    );

                /*
                |--------------------------------------------------------------------------
                | RISK RATIO
                |--------------------------------------------------------------------------
                */

                $riskRatio =
                    $threshold['p95'] > 0

                        ? (
                            $peakDischarge
                            /
                            $threshold['p95']
                        )

                        : 0;

                [
                    $floodRisk,
                    $riskLevel
                ] =
                    $this->classifyRisk(
                        $riskRatio
                    );

                /*
                |--------------------------------------------------------------------------
                | HISTORICAL COMPARISON
                |--------------------------------------------------------------------------
                */

                $historicalMean =
                    $threshold['mean'];

                $anomalyPercent =

                    $historicalMean > 0

                        ? round(
                            (
                                (
                                    $peakDischarge
                                    -
                                    $historicalMean
                                )
                                /
                                $historicalMean
                            ) * 100
                        )

                        : 0;

                $farmResults[] = [

                    'farm_id' =>
                        $farm->id,

                    'risk_level' =>
                        $riskLevel,

                    'flood_risk' =>
                        $floodRisk,

                    'peak_discharge' =>
                        $peakDischarge,

                    'historical_mean' =>
                        $historicalMean,

                    'anomaly_percent' =>
                        $anomalyPercent,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | EMPTY RESULTS
            |--------------------------------------------------------------------------
            */

            if (empty($farmResults)) {

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | SORT WORST FIRST
            |--------------------------------------------------------------------------
            */

            usort(

                $farmResults,

                fn($a, $b) =>

                    $b['flood_risk']
                    <=>
                    $a['flood_risk']
            );

            $worstFarm =
                $farmResults[0];

            /*
            |--------------------------------------------------------------------------
            | BASIN METRICS
            |--------------------------------------------------------------------------
            */

            $basinPeakDischarge =
                $worstFarm['peak_discharge'];

            $basinProbability =
                round(
                    $worstFarm['flood_risk']
                );

            $basinRiskLevel =
                $worstFarm['risk_level'];

            /*
            |--------------------------------------------------------------------------
            | CRITICAL OVERRIDE
            |--------------------------------------------------------------------------
            */

            if (
                $basinProbability >= 85
            ) {

                $basinRiskLevel =
                    'critical';
            }

            /*
            |--------------------------------------------------------------------------
            | HISTORICAL MEAN
            |--------------------------------------------------------------------------
            */

            $basinHistoricalMean =
                round(

                    array_sum(
                        array_column(
                            $farmResults,
                            'historical_mean'
                        )
                    )

                    /

                    count($farmResults),

                    2
                );

            $basinAnomalyPercent =

                $basinHistoricalMean > 0

                    ? round(
                        (
                            (
                                $basinPeakDischarge
                                -
                                $basinHistoricalMean
                            )
                            /
                            $basinHistoricalMean
                        ) * 100
                    )

                    : 0;

            /*
            |--------------------------------------------------------------------------
            | UNIQUE FARM COUNTS
            |--------------------------------------------------------------------------
            */

            $highRiskFarmIds = [];

            $mediumRiskFarmIds = [];

            foreach (
                $farmResults
                as $result
            ) {

                if (
                    $result['risk_level']
                    === 'high'
                ) {

                    $highRiskFarmIds[] =
                        $result['farm_id'];
                }

                if (
                    $result['risk_level']
                    === 'medium'
                ) {

                    $mediumRiskFarmIds[] =
                        $result['farm_id'];
                }
            }

            $highRiskCount =
                count(
                    array_unique(
                        $highRiskFarmIds
                    )
                );

            $mediumRiskCount =
                count(
                    array_unique(
                        $mediumRiskFarmIds
                    )
                );

            $affectedCount =
                count(
                    array_unique(
                        array_merge(
                            $highRiskFarmIds,
                            $mediumRiskFarmIds
                        )
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | DISCHARGE STATUS
            |--------------------------------------------------------------------------
            */

            $dischargeStatus =
                $this->buildDischargeStatus(

                    peakDischarge:
                        round(
                            $basinPeakDischarge,
                            1
                        ),

                    historicalMean:
                        $basinHistoricalMean,

                    anomalyPercent:
                        $basinAnomalyPercent,
                );

            /*
            |--------------------------------------------------------------------------
            | EVACUATION STATUS
            |--------------------------------------------------------------------------
            */

            $evacuationStatus =
                $this->evacuationStatus(
                    $basinRiskLevel
                );

            /*
            |--------------------------------------------------------------------------
            | FINAL ZONE
            |--------------------------------------------------------------------------
            */

            $zones[] = [

                'location' =>
                    $basin['name'],

                'river' =>
                    $basin['river']
                    ?? null,

                'risk_level' =>
                    $basinRiskLevel,

                'probability' =>
                    $basinProbability,

                'discharge_status' =>
                    $dischargeStatus,

                'evacuation_status' =>
                    $evacuationStatus,

                'affected_farms' =>
                    $affectedCount,

                'high_risk_farms' =>
                    $highRiskCount,

                'medium_risk_farms' =>
                    $mediumRiskCount,

                'total_farms' =>
                    count($basinFarms),

                'last_updated' =>
                    now()->toIso8601String(),

                'discharge' => [

                    'peak_m3s' =>
                        round(
                            $basinPeakDischarge,
                            2
                        ),

                    'historical_mean_m3s' =>
                        $basinHistoricalMean,

                    'anomaly_percent' =>
                        $basinAnomalyPercent,
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | SORT DESCENDING
        |--------------------------------------------------------------------------
        */

        usort(

            $zones,

            fn($a, $b) =>

                $b['probability']
                <=>
                $a['probability']
        );

        return $zones;
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD DISCHARGE STATUS
    |--------------------------------------------------------------------------
    */

    private function buildDischargeStatus(

        float $peakDischarge,

        float $historicalMean,

        int $anomalyPercent,

    ): string {

        $peak =
            number_format(
                $peakDischarge,
                1
            );

        if ($anomalyPercent > 5) {

            return
                "peak {$peak} m³/s — {$anomalyPercent}% above historical average";
        }

        if ($anomalyPercent < -5) {

            $abs =
                abs(
                    $anomalyPercent
                );

            return
                "peak {$peak} m³/s — {$abs}% below historical average";
        }

        return
            "peak {$peak} m³/s — at historical average";
    }

    /*
    |--------------------------------------------------------------------------
    | EVACUATION STATUS
    |--------------------------------------------------------------------------
    */

    private function evacuationStatus(
        string $riskLevel
    ): string {

        return match ($riskLevel) {

            'high',
            'critical'

                => 'Recommended',

            'medium'

                => 'Monitor',

            default

                => 'Safe',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | RESOLVE BASIN
    |--------------------------------------------------------------------------
    */

    private function resolveBasin(
        float $lat,
        float $lng
    ): array {

        foreach (
            self::RIVER_BASINS
            as $basin
        ) {

            if (

                $lat >=
                    $basin['lat_min']

                &&

                $lat <=
                    $basin['lat_max']

                &&

                $lng >=
                    $basin['lng_min']

                &&

                $lng <=
                    $basin['lng_max']
            ) {

                return $basin;
            }
        }

        return [

            'name' =>
                'Other Region',

            'river' =>
                null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FLOOD THRESHOLD
    |--------------------------------------------------------------------------
    */

    private function getFloodThreshold(
        Farm $farm
    ): ?array {

        $cacheKey =
            "flood_threshold_{$farm->id}";

        return Cache::remember(

            $cacheKey,

            now()->addHours(
                self::THRESHOLD_CACHE_TTL_HOURS
            ),

            function () use ($farm) {

                $response =
                    Http::acceptJson()

                    ->timeout(30)

                    ->retry(2, 1000)

                    ->get(
                        self::FLOOD_API_URL,

                        [

                            'latitude' =>
                                $farm->latitude,

                            'longitude' =>
                                $farm->longitude,

                            'daily' =>
                                'river_discharge',

                            'start_date' =>
                                '1984-01-01',

                            'end_date' =>
                                Carbon::now()
                                    ->format('Y-m-d'),

                            'cell_selection' =>
                                'nearest',
                        ]
                    );

                if (
                    !$response->successful()
                ) {

                    return null;
                }

                $discharge =
                    $response->json(
                        'daily.river_discharge'
                    ) ?? [];

                if (
                    count($discharge) === 0
                ) {

                    return null;
                }

                return [

                    'p95' =>

                        $this->percentile(
                            $discharge,
                            95
                        ),

                    'p75' =>

                        $this->percentile(
                            $discharge,
                            75
                        ),

                    'mean' =>

                        $this->averageValue(
                            $discharge
                        ),
                ];
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORECAST
    |--------------------------------------------------------------------------
    */

    private function fetchForecast(
        float $lat,
        float $lng
    ): ?array {

        $response =
            Http::acceptJson()

            ->timeout(15)

            ->retry(2, 500)

            ->get(

                self::FLOOD_API_URL,

                [

                    'latitude' =>
                        $lat,

                    'longitude' =>
                        $lng,

                    'daily' =>
                        implode(
                            ',',
                            [
                                'river_discharge',
                                'river_discharge_max',
                            ]
                        ),

                    'forecast_days' =>
                        self::FORECAST_DAYS,

                    'cell_selection' =>
                        'nearest',
                ]
            );

        return
            $response->successful()

                ? $response->json()

                : null;
    }

    /*
    |--------------------------------------------------------------------------
    | CLASSIFY RISK
    |--------------------------------------------------------------------------
    */

    private function classifyRisk(
        float $ratio
    ): array {

        $score = match (true) {

            $ratio >= 1.00 =>

                min(
                    100,
                    70 + (
                        ($ratio - 1.00)
                        * 60
                    )
                ),

            $ratio >= 0.75 =>

                40 + (
                    (
                        $ratio - 0.75
                    ) / 0.25
                ) * 30,

            $ratio >= 0.50 =>

                15 + (
                    (
                        $ratio - 0.50
                    ) / 0.25
                ) * 25,

            default =>

                max(
                    5,
                    $ratio * 30
                ),
        };

        $level = match (true) {

            $score >= 70
                => 'high',

            $score >= 40
                => 'medium',

            default
                => 'low',
        };

        return [
            round($score, 2),
            $level,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function maxValue(
        array $values
    ): float {

        if (
            count($values) === 0
        ) {

            return 0.0;
        }

        return (float)
            max(
                array_map(
                    'floatval',
                    $values
                )
            );
    }

    private function averageValue(
        array $values
    ): float {

        if (
            count($values) === 0
        ) {

            return 0.0;
        }

        $normalized =
            array_map(
                'floatval',
                $values
            );

        return
            array_sum($normalized)
            /
            count($normalized);
    }

    private function percentile(
        array $values,
        int $percentile
    ): float {

        if (
            count($values) === 0
        ) {

            return 0.0;
        }

        $sorted =
            array_map(
                'floatval',
                $values
            );

        sort($sorted);

        $index =

            (int) ceil(
                (
                    $percentile
                    / 100
                )
                *
                count($sorted)
            ) - 1;

        return
            $sorted[
                max(0, $index)
            ];
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR CACHE
    |--------------------------------------------------------------------------
    */

    public function clearCache(
        Request $request
    ): JsonResponse {

        $userId =
            $request->user()->id;

        $cacheKey =
            "flood_zones_user_{$userId}";

        Cache::forget(
            $cacheKey
        );

        return response()->json([

            'message' =>

                'Flood zone cache cleared. Next request will fetch fresh data.',

            'cache_key' =>
                $cacheKey,

            'cleared_at' =>
                now()->toIso8601String(),
        ]);
    }
}