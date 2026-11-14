<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('resident_id')->constrained('residents')->restrictOnDelete();
            $table->string('previous_status', 20);
            $table->string('new_status', 20);
            $table->text('reason')->nullable();
            $table->timestampTz('effective_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('reference_letter_number', 100)->nullable();
            $table->foreignUuid('attachment_file_id')->nullable()->constrained('files')->restrictOnDelete();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->index(['resident_id', 'effective_at'], 'resident_status_history_resident_effective_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE resident_status_history ADD CONSTRAINT resident_status_history_prev_check
            CHECK (previous_status IN ('PROSPECTIVE', 'ACTIVE', 'INACTIVE', 'MOVED', 'DECEASED'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE resident_status_history ADD CONSTRAINT resident_status_history_new_check
            CHECK (new_status IN ('PROSPECTIVE', 'ACTIVE', 'INACTIVE', 'MOVED', 'DECEASED'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_status_history');
    }
};
