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
        | Crop Cycles Table
        |--------------------------------------------------------------------------
        */

        Schema::create('crop_cycles', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | Primary Key
            |--------------------------------------------------------------------------
            */

            $table->uuid('id')
                ->primary()
                ->default(
                    DB::raw('gen_random_uuid()')
                );

            /*
            |--------------------------------------------------------------------------
            | Farm Relationship
            |--------------------------------------------------------------------------
            */

            $table->foreignUuid('farm_id')
                ->constrained(
                    table: 'farms',
                    column: 'id'
                )
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional Farm Section
            |--------------------------------------------------------------------------
            */

            $table->foreignUuid('farm_section_id')
                ->nullable()
                ->constrained(
                    table: 'farm_sections',
                    column: 'id'
                )
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Creator Relationship
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by_user_id')
                ->constrained(
                    table: 'users',
                    column: 'id'
                )
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Crop Information
            |--------------------------------------------------------------------------
            */

            $table->text('crop_name');

            $table->text('season')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Dates
            |--------------------------------------------------------------------------
            */

            $table->date('planting_date');

            $table->date('expected_harvest_date')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Agricultural Metrics
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'expected_yield_kg_per_hectare',
                10,
                2
            )->nullable();

            $table->decimal(
                'expected_area_hectares',
                10,
                2
            )->nullable();

            $table->decimal(
                'actual_yield_kg',
                12,
                2
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Crop Lifecycle
            |--------------------------------------------------------------------------
            */

            $table->text('status')
                ->default('planned');

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text('notes')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestampTz('created_at')
                ->useCurrent();

            $table->timestampTz('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            /*
            |--------------------------------------------------------------------------
            | Useful Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('farm_id');

            $table->index('farm_section_id');

            $table->index('created_by_user_id');

            $table->index('status');

            $table->index('crop_name');

            $table->index('planting_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_cycles');
    }
};