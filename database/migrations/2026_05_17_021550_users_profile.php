<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL extensions
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "citext"');

        Schema::create('user_profiles', function (Blueprint $table) {

            // UUID Primary Key
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('gen_random_uuid()'));

            // One-to-One Relationship
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // Additional Profile Data
            $table->text('organization_name')
                ->nullable();

            $table->text('phone')
                ->nullable();

            $table->text('role')
                ->default('farmer');

            $table->text('status')
                ->default('active');

            // Timestamps
            $table->timestampTz('created_at')
                ->useCurrent();

            $table->timestampTz('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();
        });

        // Optional: Convert users.email to CITEXT
        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE CITEXT');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};