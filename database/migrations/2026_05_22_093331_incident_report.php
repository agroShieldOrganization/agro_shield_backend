<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | PostgreSQL Extensions
        |--------------------------------------------------------------------------
        */

        DB::statement(
            'CREATE EXTENSION IF NOT EXISTS "pgcrypto"'
        );

        /*
        |--------------------------------------------------------------------------
        | Incident Reports Table
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'incident_reports',

            function (Blueprint $table) {

                /*
                |--------------------------------------------------------------------------
                | Primary Key
                |--------------------------------------------------------------------------
                */

                $table->uuid('id')
                    ->primary()
                    ->default(
                        DB::raw(
                            'gen_random_uuid()'
                        )
                    );

                /*
                |--------------------------------------------------------------------------
                | Reporter Relationship
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                        'reporter_user_id'
                    )
                    ->nullable()
                    ->constrained(
                        table: 'users',
                        column: 'id'
                    )
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Incident Information
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'incident_type'
                );

                /*
                |--------------------------------------------------------------------------
                | Allowed:
                | flooding
                | pest
                | disease
                | drought
                | fire
                | erosion
                | other
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'severity'
                );

                /*
                |--------------------------------------------------------------------------
                | Allowed:
                | low
                | medium
                | high
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'title'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Location
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'location_name'
                );

                $table->decimal(
                    'latitude',
                    9,
                    6
                )->nullable();

                $table->decimal(
                    'longitude',
                    9,
                    6
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Description
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'description'
                );

                /*
                |--------------------------------------------------------------------------
                | Impact Metrics
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'affected_area_hectares',
                    10,
                    2
                )->nullable();

                $table->text(
                    'contact_phone'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Moderation / Verification
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'verification_status'
                )->default(
                    'pending'
                );

                /*
                |--------------------------------------------------------------------------
                | Allowed:
                | pending
                | under_review
                | verified
                | rejected
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'public_visibility'
                )->default(
                    'community'
                );

                /*
                |--------------------------------------------------------------------------
                | Allowed:
                | private
                | community
                | admin_only
                |--------------------------------------------------------------------------
                */

                /*
                |--------------------------------------------------------------------------
                | Event Dates
                |--------------------------------------------------------------------------
                */

                $table->timestampTz(
                    'occurred_at'
                )->nullable();

                $table->timestampTz(
                    'submitted_at'
                )->useCurrent();

                /*
                |--------------------------------------------------------------------------
                | Timestamps
                |--------------------------------------------------------------------------
                */

                $table->timestampTz(
                    'created_at'
                )->useCurrent();

                $table->timestampTz(
                    'updated_at'
                )
                ->useCurrent()
                ->useCurrentOnUpdate();

                /*
                |--------------------------------------------------------------------------
                | PERFORMANCE INDEXES
                |--------------------------------------------------------------------------
                */

                $table->index(
                    'incident_type'
                );

                $table->index(
                    'severity'
                );

                $table->index(
                    'verification_status'
                );

                $table->index(
                    'public_visibility'
                );

                $table->index(
                    'submitted_at'
                );

                $table->index([
                    'latitude',
                    'longitude',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'incident_reports'
        );
    }
};