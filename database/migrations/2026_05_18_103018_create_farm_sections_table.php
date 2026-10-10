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
        | Farm Sections Table
        |--------------------------------------------------------------------------
        */

        Schema::create('farm_sections', function (Blueprint $table) {

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
            | Section Information
            |--------------------------------------------------------------------------
            */

            $table->text('name');

            $table->decimal(
                'area_hectares',
                10,
                2
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optional Coordinates
            |--------------------------------------------------------------------------
            */

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
            | Soil Information
            |--------------------------------------------------------------------------
            */

            $table->text('soil_type')
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

            $table->index([
                'latitude',
                'longitude',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_sections');
    }
};