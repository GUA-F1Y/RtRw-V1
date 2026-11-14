<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and permissions are system reference data (see RolePermissionSeeder).
 *
 * A role can only be granted at one scope level (organization, rw or rt). This
 * level is enforced again at the database level by memberships through a
 * composite foreign key on (role_id, scope_level).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('scope_level', 20);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['id', 'scope_level']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
            $table->index('permission_id');
        });

        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_scope_level_check CHECK (scope_level IN ('organization', 'rw', 'rt'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
