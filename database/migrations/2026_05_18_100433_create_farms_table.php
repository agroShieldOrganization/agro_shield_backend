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
        | Farms Table
        |--------------------------------------------------------------------------
        */

        Schema::create('farms', function (Blueprint $table) {

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
            | Owner Relationship
            |--------------------------------------------------------------------------
            */

            $table->foreignId('owner_user_id')
                ->constrained(
                    table: 'users',
                    column: 'id'
                )
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Farm Information
            |--------------------------------------------------------------------------
            */

            $table->text('name');

            $table->text('location_name');

            $table->text('country')
                ->default('Nigeria');

            $table->text('state_region')
                ->nullable();

            $table->text('address_text')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Coordinates
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'latitude',
                9,
                6
            );

            $table->decimal(
                'longitude',
                9,
                6
            );

            /*
            |--------------------------------------------------------------------------
            | Farm Metadata
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'size_hectares',
                10,
                2
            )->nullable();

            $table->text('primary_crop_name')
                ->nullable();

            $table->decimal(
                'health_score',
                5,
                2
            )->nullable();

            $table->text('status')
                ->default('active');

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

            $table->index('owner_user_id');

            $table->index([
                'latitude',
                'longitude',
            ]);

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};