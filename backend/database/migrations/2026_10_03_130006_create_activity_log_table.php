<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail on top of spatie/laravel-activitylog's table, adapted for this project:
 *  - UUID subject/causer (all domain keys are UUIDs)
 *  - organizational scope columns so audit visibility can be scoped
 *  - append-only: a trigger rejects UPDATE and DELETE.
 *
 * Scope columns intentionally have no foreign keys: audit history must never block,
 * or be cascaded by, changes to the organizational structure.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('activitylog.table_name', 'activity_log');

        Schema::create($table, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableUuidMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableUuidMorphs('causer', 'causer');
            $table->jsonb('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->uuid('organization_id')->nullable();
            $table->uuid('rw_id')->nullable();
            $table->uuid('rt_id')->nullable();
            $table->timestamps();

            $table->index('batch_uuid');
            $table->index(['organization_id', 'rw_id', 'rt_id']);
            $table->index('created_at');
        });

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION {$table}_prevent_mutation() RETURNS trigger AS \$\$
            BEGIN
                RAISE EXCEPTION 'The audit log is append-only.' USING ERRCODE = 'integrity_constraint_violation';
            END;
            \$\$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<SQL
            CREATE TRIGGER {$table}_append_only
            BEFORE UPDATE OR DELETE ON {$table}
            FOR EACH ROW EXECUTE FUNCTION {$table}_prevent_mutation();
        SQL);
    }

    public function down(): void
    {
        $table = config('activitylog.table_name', 'activity_log');

        DB::unprepared("DROP TRIGGER IF EXISTS {$table}_append_only ON {$table}");
        Schema::dropIfExists($table);
        DB::unprepared("DROP FUNCTION IF EXISTS {$table}_prevent_mutation()");
    }
};
