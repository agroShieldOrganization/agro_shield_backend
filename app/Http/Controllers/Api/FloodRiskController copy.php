<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\Http;

class TomorrowIoTestController extends Controller
{
    public function test(): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | DUMMY COORDINATES
        |--------------------------------------------------------------------------
        |
        | Abuja, Nigeria
        |
        */

        $latitude = 9.0765;

        $longitude = 7.3986;

        /*
        |--------------------------------------------------------------------------
        | CALL TOMORROW.IO
        |--------------------------------------------------------------------------
        */

        $response = Http::get(

            'https://api.tomorrow.io/v4/weather/realtime',

            [

                'location' =>
                    $latitude . ',' . $longitude,

                'apikey' =>
                    env('TOMORROW_IO_API_KEY'),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | RETURN RAW RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' =>
                $response->successful(),

            'status' =>
                $response->status(),

            'data' =>
                $response->json(),
        ]);
    }
}