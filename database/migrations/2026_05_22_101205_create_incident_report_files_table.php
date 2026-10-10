<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'incident_report_files',

            function (Blueprint $table) {

                $table->uuid('id')
                    ->primary()
                    ->default(
                        DB::raw(
                            'gen_random_uuid()'
                        )
                    );

                /*
                |--------------------------------------------------------------------------
                | INCIDENT RELATIONSHIP
                |--------------------------------------------------------------------------
                */

                $table->foreignUuid(
                        'incident_report_id'
                    )
                    ->constrained(
                        table: 'incident_reports',
                        column: 'id'
                    )
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | FILE METADATA
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'original_name'
                );

                $table->text(
                    'stored_name'
                );

                $table->text(
                    'mime_type'
                );

                $table->bigInteger(
                    'size_bytes'
                );

                /*
                |--------------------------------------------------------------------------
                | AWS S3
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'disk'
                )->default('s3');

                $table->text(
                    'path'
                );

                $table->text(
                    'bucket'
                );

                /*
                |--------------------------------------------------------------------------
                | OPTIONAL
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'visibility'
                )->default('private');

                $table->timestampTz(
                    'uploaded_at'
                )->useCurrent();

                $table->timestampTz(
                    'created_at'
                )->useCurrent();

                $table->timestampTz(
                    'updated_at'
                )
                ->useCurrent()
                ->useCurrentOnUpdate();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'incident_report_files'
        );
    }
};