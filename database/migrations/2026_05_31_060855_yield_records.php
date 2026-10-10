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
        | Yield Records Table
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'yield_records',

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

                $table->foreignId('recorded_by_user_id')
                    ->constrained(
                        table: 'users',
                        column: 'id'
                    )
                    ->cascadeOnDelete();

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
                | Optional Farm Section Relationship
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
                | Optional Crop Cycle Relationship
                |--------------------------------------------------------------------------
                */

                $table->foreignUuid('crop_cycle_id')
                    ->nullable()
                    ->constrained(
                        table: 'crop_cycles',
                        column: 'id'
                    )
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Crop Information
                |--------------------------------------------------------------------------
                */

                $table->text('crop_name');

                /*
                |--------------------------------------------------------------------------
                | Harvest Date
                |--------------------------------------------------------------------------
                */

                $table->date('harvest_date');

                /*
                |--------------------------------------------------------------------------
                | Yield Metrics
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'quantity_kg',
                    12,
                    2
                );

                $table->decimal(
                    'loss_kg',
                    12,
                    2
                )->default(0.00);

                /*
                |--------------------------------------------------------------------------
                | Derived: stored for quick querying, avoid recalculating at runtime
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'net_yield_kg',
                    12,
                    2
                )->storedAs(
                    'quantity_kg - loss_kg'
                );

                $table->decimal(
                    'efficiency_percentage',
                    5,
                    2
                )->storedAs(
                    'CASE WHEN quantity_kg > 0
                        THEN ROUND(((quantity_kg - loss_kg) / quantity_kg) * 100, 2)
                        ELSE 0
                    END'
                );

                /*
                |--------------------------------------------------------------------------
                | Revenue
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'revenue',
                    15,
                    2
                )->nullable();

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
                | Performance Indexes
                |--------------------------------------------------------------------------
                */

                $table->index('recorded_by_user_id');
                $table->index('farm_id');
                $table->index('farm_section_id');
                $table->index('crop_cycle_id');
                $table->index('crop_name');
                $table->index('harvest_date');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('yield_records');
    }
};