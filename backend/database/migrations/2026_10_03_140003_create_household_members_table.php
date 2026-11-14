<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_members', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('household_id')->constrained('households')->restrictOnDelete();
            $table->foreignUuid('resident_id')->constrained('residents')->restrictOnDelete();
            $table->string('relationship', 30);
            $table->boolean('is_active')->default(true);
            $table->date('joined_at')->default(DB::raw('CURRENT_DATE'));
            $table->date('left_at')->nullable();
            $table->string('left_reason', 100)->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->index(['household_id', 'is_active'], 'household_members_household_active_idx');
            $table->index(['resident_id', 'is_active'], 'household_members_resident_active_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE household_members ADD CONSTRAINT household_members_relationship_check
            CHECK (relationship IN (
                'KEPALA_KELUARGA', 'SUAMI', 'ISTRI', 'ANAK',
                'MENANTU', 'CUCU', 'ORANG_TUA', 'MERTUA',
                'FAMILI_LAIN', 'PEMBANTU', 'LAINNYA'
            ))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE household_members ADD CONSTRAINT household_members_dates_check
            CHECK (left_at IS NULL OR left_at >= joined_at)
        SQL);

        // Exactly one active Head of Household per Household
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX household_single_active_head_unique
            ON household_members (household_id)
            WHERE relationship = 'KEPALA_KELUARGA' AND is_active = TRUE
        SQL);

        // A resident can belong to at most one active household at any time
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX resident_single_active_household_unique
            ON household_members (resident_id)
            WHERE is_active = TRUE
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('household_members');
    }
};
