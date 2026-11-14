<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * File metadata and attachments. The binary content lives on a filesystem disk
 * (local in development, S3-compatible in production); the database only stores
 * metadata. Files carry their organizational scope so authorization can be
 * decided without knowing which future module attached them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('rw_id')->nullable();
            $table->uuid('rt_id')->nullable();
            $table->string('disk', 40);
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('extension', 16);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->string('visibility', 10)->default('private');
            $table->string('category', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['rw_id', 'organization_id'])
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['rt_id', 'rw_id'])
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();

            $table->unique(['disk', 'path']);
            $table->index('owner_id');
            $table->index(['organization_id', 'rw_id', 'rt_id']);
            $table->index('checksum_sha256');
        });

        DB::statement("ALTER TABLE files ADD CONSTRAINT files_visibility_check CHECK (visibility IN ('private', 'public'))");
        DB::statement('ALTER TABLE files ADD CONSTRAINT files_size_check CHECK (size_bytes >= 0)');
        DB::statement('ALTER TABLE files ADD CONSTRAINT files_scope_shape_check CHECK (rt_id IS NULL OR rw_id IS NOT NULL)');

        // Polymorphic on purpose: any future entity (resident, letter, invoice...) can
        // own attachments without a dedicated pivot table per module.
        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('file_id')->constrained('files')->restrictOnDelete();
            $table->uuidMorphs('attachable');
            $table->string('collection', 64)->default('default');
            $table->timestamps();

            $table->unique(['file_id', 'attachable_type', 'attachable_id', 'collection'], 'attachments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('files');
    }
};
