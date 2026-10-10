<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Str;

use Illuminate\Http\JsonResponse;

use App\Models\IncidentReportFile;

class FileUploadController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GENERATE PRESIGNED UPLOAD URL
    |--------------------------------------------------------------------------
    */

    public function generateUploadUrl(
        Request $request
    ): JsonResponse {

        $request->validate([

            'file_name' =>
                'required|string',

            'mime_type' =>
                'required|string',

            'size_bytes' =>
                'required|integer',
        ]);

        $extension =
            pathinfo(
                $request->file_name,
                PATHINFO_EXTENSION
            );

        $storedName =
            Str::uuid()
            . '.'
            . $extension;

        $path =
            'incident-reports/'
            . $storedName;

        // Fail fast when the S3 disk is misconfigured so the frontend
        // receives a useful API error instead of a blind 403 from S3.
        $accessKey =
            (string) config(
                'filesystems.disks.s3.key'
            );

        if (
            $accessKey === '' ||
            strlen($accessKey) !== 20
        ) {
            return response()->json([
                'message' =>
                    'S3 upload is misconfigured. Check AWS_ACCESS_KEY_ID on the backend.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | TEMPORARY UPLOAD URL
        |--------------------------------------------------------------------------
        */

        try {

            $uploadUrl =
                Storage::disk('s3')
                    ->temporaryUploadUrl(

                        $path,

                        now()->addMinutes(15),

                        [
                            // The AWS SDK expects `ContentType` here; using
                            // the HTTP header spelling leaves the presigned
                            // request contract inconsistent.
                            'ContentType' =>
                                $request->mime_type,
                        ]
                    );

        } catch (\Throwable $exception) {

            Log::error(
                'Failed to generate incident report upload URL',
                [
                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return response()->json([
                'message' =>
                    'Could not generate an upload URL for incident files.',
            ], 500);
        }

        return response()->json([

            'upload_url' =>

                $uploadUrl['url'],

            'headers' =>

                $uploadUrl['headers'],

            'path' =>

                $path,

            'stored_name' =>

                $storedName,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE FILE METADATA
    |--------------------------------------------------------------------------
    */

    public function storeMetadata(
        Request $request
    ): JsonResponse {

        // Some browsers/filesystem sources can send an empty MIME string
        // even when the object upload itself succeeds.
        $request->merge([
            'mime_type' =>
                trim(
                    (string) $request->input(
                        'mime_type',
                        ''
                    )
                ) !== ''
                    ? $request->input(
                        'mime_type'
                    )
                    : 'application/octet-stream',
        ]);

        $validated =
            $request->validate([

                'incident_report_id' =>
                    'required|string|exists:incident_reports,id',

                'original_name' =>
                    'required|string',

                'stored_name' =>
                    'required|string',

                'mime_type' =>
                    'required|string',

                'size_bytes' =>
                    'required|integer',

                'path' =>
                    'required|string',
            ]);

        $file =
            IncidentReportFile::create([

                ...$validated,

                'bucket' =>
                    env('AWS_BUCKET'),

                'disk' =>
                    's3',

                'visibility' =>
                    'private',

                'uploaded_at' =>
                    now(),
            ]);

        return response()->json([

            'message' =>
                'File metadata saved',

            'file' =>
                $file,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE DOWNLOAD URL
    |--------------------------------------------------------------------------
    */

    public function download(
        string $id
    ): JsonResponse {

        $file =
            IncidentReportFile::findOrFail($id);

        $url =
            Storage::disk('s3')
                ->temporaryUrl(

                    $file->path,

                    now()->addMinutes(10)
                );

        return response()->json([

            'download_url' =>
                $url,
        ]);
    }
}
