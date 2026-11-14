<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organization hierarchy: organization -> rw -> rt.
 *
 * Separate tables (instead of a generic adjacency tree) make every parent link a
 * real foreign key and let child tables (memberships, files, ...) enforce a
 * consistent ancestry through composite foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('rws', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            // One RW number per organization.
            $table->unique(['organization_id', 'number']);
            // Target of composite foreign keys that must stay inside one organization.
            $table->unique(['id', 'organization_id']);
        });

        Schema::create('rts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rw_id')->constrained('rws')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            // One RT number per RW.
            $table->unique(['rw_id', 'number']);
            // Target of composite foreign keys that must stay inside one RW.
            $table->unique(['id', 'rw_id']);
        });

        DB::statement('ALTER TABLE rws ADD CONSTRAINT rws_number_positive CHECK (number > 0)');
        DB::statement('ALTER TABLE rts ADD CONSTRAINT rts_number_positive CHECK (number > 0)');
        DB::statement("ALTER TABLE rws ADD CONSTRAINT rws_status_check CHECK (status IN ('active', 'inactive'))");
        DB::statement("ALTER TABLE rts ADD CONSTRAINT rts_status_check CHECK (status IN ('active', 'inactive'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('rts');
        Schema::dropIfExists('rws');
        Schema::dropIfExists('organizations');
    }
};
