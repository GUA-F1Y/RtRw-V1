<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residency_movements', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('resident_id')->constrained('residents')->restrictOnDelete();
            $table->string('movement_type', 30);
            $table->foreignUuid('from_organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->uuid('from_rw_id')->nullable();
            $table->uuid('from_rt_id')->nullable();
            $table->uuid('from_household_id')->nullable();
            $table->foreignUuid('to_organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->uuid('to_rw_id')->nullable();
            $table->uuid('to_rt_id')->nullable();
            $table->uuid('to_household_id')->nullable();
            $table->date('effective_date')->default(DB::raw('CURRENT_DATE'));
            $table->string('reference_letter_number', 100)->nullable();
            $table->foreignUuid('attachment_file_id')->nullable()->constrained('files')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            // Composite ancestry foreign keys (FROM side)
            $table->foreign(['from_rw_id', 'from_organization_id'], 'residency_movements_from_rw_fk')
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['from_rt_id', 'from_rw_id'], 'residency_movements_from_rt_fk')
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();
            $table->foreign(['from_household_id', 'from_rt_id'], 'residency_movements_from_hh_fk')
                ->references(['id', 'rt_id'])->on('households')->restrictOnDelete();

            // Composite ancestry foreign keys (TO side)
            $table->foreign(['to_rw_id', 'to_organization_id'], 'residency_movements_to_rw_fk')
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['to_rt_id', 'to_rw_id'], 'residency_movements_to_rt_fk')
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();
            $table->foreign(['to_household_id', 'to_rt_id'], 'residency_movements_to_hh_fk')
                ->references(['id', 'rt_id'])->on('households')->restrictOnDelete();

            $table->index(['resident_id', 'effective_date'], 'residency_movements_resident_date_idx');
            $table->index(['to_rt_id', 'effective_date'], 'residency_movements_to_rt_date_idx');
            $table->index(['from_rt_id', 'effective_date'], 'residency_movements_from_rt_date_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE residency_movements ADD CONSTRAINT residency_movements_type_check
            CHECK (movement_type IN ('MOVE_IN_EXTERNAL', 'MOVE_OUT_EXTERNAL', 'TRANSFER_INTERNAL', 'HOUSEHOLD_SPLIT'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE residency_movements ADD CONSTRAINT residency_movements_nullability_check CHECK (
                (
                    movement_type = 'MOVE_IN_EXTERNAL'
                    AND from_organization_id IS NULL AND from_rw_id IS NULL AND from_rt_id IS NULL AND from_household_id IS NULL
                    AND to_organization_id IS NOT NULL AND to_rw_id IS NOT NULL AND to_rt_id IS NOT NULL AND to_household_id IS NOT NULL
                )
                OR
                (
                    movement_type = 'MOVE_OUT_EXTERNAL'
                    AND from_organization_id IS NOT NULL AND from_rw_id IS NOT NULL AND from_rt_id IS NOT NULL AND from_household_id IS NOT NULL
                    AND to_organization_id IS NULL AND to_rw_id IS NULL AND to_rt_id IS NULL AND to_household_id IS NULL
                )
                OR
                (
                    movement_type = 'TRANSFER_INTERNAL'
                    AND from_organization_id IS NOT NULL AND from_rw_id IS NOT NULL AND from_rt_id IS NOT NULL AND from_household_id IS NOT NULL
                    AND to_organization_id IS NOT NULL AND to_rw_id IS NOT NULL AND to_rt_id IS NOT NULL AND to_household_id IS NOT NULL
                    AND from_organization_id = to_organization_id
                    AND from_rt_id <> to_rt_id
                    AND from_household_id <> to_household_id
                )
                OR
                (
                    movement_type = 'HOUSEHOLD_SPLIT'
                    AND from_organization_id IS NOT NULL AND from_rw_id IS NOT NULL AND from_rt_id IS NOT NULL AND from_household_id IS NOT NULL
                    AND to_organization_id IS NOT NULL AND to_rw_id IS NOT NULL AND to_rt_id IS NOT NULL AND to_household_id IS NOT NULL
                    AND from_organization_id = to_organization_id
                    AND from_rt_id = to_rt_id
                    AND from_household_id <> to_household_id
                )
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('residency_movements');
    }
};
