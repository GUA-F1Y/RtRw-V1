<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('rw_id');
            $table->uuid('rt_id');
            $table->string('kk_number', 16);
            $table->text('address');
            $table->string('status', 20)->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            // Composite ancestry foreign keys ensuring RW and RT belong to the organization
            $table->foreign(['rw_id', 'organization_id'], 'households_rw_fk')
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['rt_id', 'rw_id'], 'households_rt_fk')
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();

            // Composite target for resident scope alignment
            $table->unique(['id', 'rt_id'], 'households_id_rt_unique');

            $table->index(['rt_id', 'status'], 'households_rt_status_idx');
            $table->index(['organization_id', 'kk_number'], 'households_org_kk_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE households ADD CONSTRAINT households_status_check
            CHECK (status IN ('ACTIVE', 'HEAD_VACANT', 'MOVED', 'DISSOLVED', 'ARCHIVED'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE households ADD CONSTRAINT households_kk_number_format
            CHECK (kk_number ~ '^[0-9]{16}$')
        SQL);

        // Active KK is unique within an organization
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX households_active_kk_unique
            ON households (organization_id, kk_number)
            WHERE status = 'ACTIVE'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
