<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UsersProfileSettingsController;
use App\Http\Controllers\Api\FarmController;
use App\Http\Controllers\Api\CropCycleController;
use App\Http\Controllers\Api\FloodRiskController;
use App\Http\Controllers\Api\TomorrowIoTestController;
use App\Http\Controllers\Api\FloodzoneController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\IncidentReportController;
use App\Http\Controllers\Api\FarmAnalyticsController;
use App\Http\Controllers\Api\FarmSectionController;
use App\Http\Controllers\Api\Climate\RainfallController;
use App\Http\Controllers\Api\Climate\SingleDateForecastController;
use App\Http\Controllers\Api\Climate\SoilAnalyticsController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);



// Protected Routes
Route::middleware('auth:sanctum')->group(function () {

    Route::put(
        '/user/profile',
        [UsersProfileSettingsController::class, 'update']
    );

    Route::post(
        '/user/request-email-change',
        [UsersProfileSettingsController::class, 'requestEmailChange']
    );

    Route::post(
        '/user/verify-email-change',
        [UsersProfileSettingsController::class, 'verifyEmailChange']
    );

    Route::post(
    '/user/resend-email-change-otp',
    [UsersProfileSettingsController::class, 'resendEmailChangeOtp']
    );


             /*
                |--------------------------------------------------------------------------
                | GIS MAP DATA
                |--------------------------------------------------------------------------
             */

                Route::get(
                    '/farms/map-data',
                    [FarmController::class, 'mapData']
                );

                // Getting the flood RIsk data                

                Route::get(
                        '/climate/flood-risk',
                        [FloodRiskController::class, 'index']
                    );


                  /*
            |--------------------------------------------------------------------------
                                | Flood Zones
                                Response shape:
                        | {
                        |   "flood_zones": [
                        |     {
                        |       "location":          "Benue River Basin",
                        |       "river":             "Benue",
                        |       "risk_level":        "high",
                        |       "probability":       85,
                        |       "discharge_status":  "peak 847.0 m³/s — 62% above historical average",
                        |       "evacuation_status": "Recommended",
                        |       "affected_farms":    48,
                        |       "high_risk_farms":   12,
                        |       "medium_risk_farms": 36,
                        |       "total_farms":       60,
                        |       "last_updated":      "2025-05-21T10:00:00+00:00",
                        |       "discharge": {
                        |         "peak_m3s":            847.0,
                        |         "historical_mean_m3s": 523.0,
                        |         "anomaly_percent":     62
                        |       }
                        |     }
                        |   ],
                        |   "meta": {
                        |     "total_zones":   5,
                        |     "critical_zones": 1,
                        |     "high_zones":    2,
                        |     "generated_at":  "2025-05-21T10:00:00+00:00"
                        |   }
                        | }
                        |
                        | Caching: results are cached 2 hours per user.
                        | First request per user may be slow (fetches 40 years of threshold
                        | data per farm). Subsequent requests within 2 hours are instant.
                        |
                |--------------------------------------------------------------------------
            */

                Route::get('/flood-zones', [FloodZoneController::class, 'index']);

                                                /*
                    |--------------------------------------------------------------------------
                    | DELETE /api/flood-zones/cache
                    |--------------------------------------------------------------------------
                    |
                    | Force-clears the 2-hour zone cache for the authenticated user.
                    | Useful when:
                    |   - A farm's coordinates are corrected and you want fresh data now.
                    |   - A new farm is added and you don't want to wait 2 hours.
                    |   - You're debugging and need a fresh API response.
                    |
                    | Does NOT clear the 7-day flood threshold cache (that is intentionally
                    | long-lived). To clear threshold caches, use the Artisan command:
                    |   php artisan cache:clear
                    | or selectively:
                    |   Cache::forget("flood_threshold_{$farmId}");
                    |
                    */
                
                    Route::delete('/flood-zones/cache', [FloodZoneController::class, 'clearCache']);
                        


                /*
            |--------------------------------------------------------------------------
            | Farms
            |--------------------------------------------------------------------------
            */

            
                

                Route::get(
                    '/farms',
                    [FarmController::class, 'index']
                );

                Route::post(
                    '/farms',
                    [FarmController::class, 'store']
                );

                Route::put(
                    '/farms/{farm}',
                    [FarmController::class, 'update']
                );

                Route::delete(
                    '/farms/{farm}',
                    [FarmController::class, 'destroy']
                );

    /*
    |--------------------------------------------------------------------------
    | Farm Sections
    |--------------------------------------------------------------------------
    */

                    Route::post(
                        '/farms/{farm}/sections',
                        [FarmSectionController::class, 'store']
                    );

                    Route::put(
                        '/farm-sections/{section}',
                        [FarmSectionController::class, 'update']
                    );

                    Route::delete(
                        '/farm-sections/{section}',
                        [FarmSectionController::class, 'mapData']
                    );

                    Route::get('/farms/sections', [FarmSectionController::class, 'getSectionsForFarm']);

                     /*
    |--------------------------------------------------------------------------
    | Crop Cycles
    |--------------------------------------------------------------------------
    */

        Route::get(
            '/crop-cycles',
            [CropCycleController::class, 'index']
        );

        Route::get(
            '/crop-cycles/farm',
            [CropCycleController::class, 'getCropCyclesForFarm']
        );

        Route::post(
            '/crop-cycles',
            [CropCycleController::class, 'store']
        );

        Route::put(
            '/crop-cycles/{cropCycle}',
            [CropCycleController::class, 'update']
        );

        Route::delete(
            '/crop-cycles/{cropCycle}',
            [CropCycleController::class, 'destroy']
        );


        /* 
        |--------------------------------------------------------------------------
        |Incident Reports and File Upload Controller
        |--------------------------------------------------------------------------
        */

            
                    /*
                    |--------------------------------------------------------------------------
                    | INCIDENT REPORTS
                    |--------------------------------------------------------------------------
                    */

                    Route::post(
                        '/incident-reports',
                        [IncidentReportController::class, 'store']
                    );

                    Route::get(
                        '/incident-reports',
                        [IncidentReportController::class, 'index']
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | FILE UPLOADS
                    |--------------------------------------------------------------------------
                    */

                    Route::post(
                        '/files/upload-url',
                        [FileUploadController::class, 'generateUploadUrl']
                    );

                    Route::post(
                        '/files/store-metadata',
                        [FileUploadController::class, 'storeMetadata']
                    );

                    Route::get(
                        '/files/{id}/download',
                        [FileUploadController::class, 'download']
                    );

                    // Rainfall Route
                    Route::get(
                      '/climate/rainfall/{farm}',
                      [RainfallController::class, 'show']
                    );

                    // Single date specific weather snapshot
                    Route::get('/climate/farm-date-snapshot', 
                    [SingleDateForecastController::class, 'getSnapshot']);


                    // Soil Analytics Route for the soil layers data from Open-Meteo
                    Route::get('/climate/soil-analytics/{farm}',
                    [SoilAnalyticsController::class, 'soilanalytics']);


                    
                        /*
                        |--------------------------------------------------------------------------
                        | Yield Records
                        | Summary route MUST be above the resource routes to avoid
                        | being caught by the {yieldRecord} wildcard — same pattern
                        | as your /farms/map-data fix.
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            '/yield-records/summary',
                            [FarmAnalyticsController::class, 'summary']
                        );

                        Route::apiResource(
                            'yield-records',
                            FarmAnalyticsController::class
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | Farm Sections by Farm (used by the AddYieldRecord modal dropdown)
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            '/farms/sections',
                            [FarmSectionController::class, 'getSectionsForFarm']
                        );


        });

        

   
    


    


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::get(
    '/tomorrow-test',
    [TomorrowIoTestController::class, 'test']
);