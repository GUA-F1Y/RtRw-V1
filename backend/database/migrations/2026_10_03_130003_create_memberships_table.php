<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * USER -> MEMBERSHIP -> ORGANIZATION / RW / RT -> ROLE -> PERMISSIONS
 *
 * A membership grants one role to one user at exactly one scope:
 *   organization scope: rw_id NULL,     rt_id NULL
 *   rw scope:           rw_id NOT NULL, rt_id NULL
 *   rt scope:           rw_id NOT NULL, rt_id NOT NULL
 *
 * Composite foreign keys guarantee that the stored organization/rw/rt ids form a
 * real ancestry chain (an RT membership can never point at an RW from another
 * organization), and that the role can be granted at the membership's scope level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id');
            $table->string('scope_level', 20);
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('rw_id')->nullable();
            $table->uuid('rt_id')->nullable();
            $table->foreignUuid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            // Memberships are never deleted: revocation keeps the history.
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUuid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['role_id', 'scope_level'])
                ->references(['id', 'scope_level'])->on('roles')->restrictOnDelete();
            $table->foreign(['rw_id', 'organization_id'])
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['rt_id', 'rw_id'])
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();

            $table->index('user_id');
            $table->index('organization_id');
            $table->index('rw_id');
            $table->index('rt_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE memberships ADD CONSTRAINT memberships_scope_shape_check CHECK (
                (scope_level = 'organization' AND rw_id IS NULL AND rt_id IS NULL)
                OR (scope_level = 'rw' AND rw_id IS NOT NULL AND rt_id IS NULL)
                OR (scope_level = 'rt' AND rw_id IS NOT NULL AND rt_id IS NOT NULL)
            )
        SQL);

        // A user cannot hold the same role at the same scope twice while it is active.
        // NULLS NOT DISTINCT (PostgreSQL 15+) makes NULL rw_id/rt_id compare as equal.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX memberships_active_unique
            ON memberships (user_id, role_id, organization_id, rw_id, rt_id) NULLS NOT DISTINCT
            WHERE revoked_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
