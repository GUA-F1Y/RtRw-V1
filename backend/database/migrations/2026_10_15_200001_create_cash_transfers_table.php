<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('rw_id');
            $table->uuid('rt_id')->nullable();
            $table->foreignUuid('source_cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignUuid('destination_cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->bigInteger('amount');
            $table->date('transfer_date')->default(DB::raw('CURRENT_DATE'));
            $table->text('description');
            $table->string('reference_number', 100)->nullable();
            $table->foreignUuid('evidence_file_id')->nullable()->constrained('files')->restrictOnDelete();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_reversal')->default(false);
            $table->uuid('original_transfer_id')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign(['rw_id', 'organization_id'], 'cash_transfers_rw_fk')
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['rt_id', 'rw_id'], 'cash_transfers_rt_fk')
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();

            $table->index(['source_cash_account_id', 'transfer_date'], 'cash_transfers_source_date_idx');
            $table->index(['destination_cash_account_id', 'transfer_date'], 'cash_transfers_dest_date_idx');
            $table->index(['recorded_by', 'created_at'], 'cash_transfers_recorded_by_idx');
            $table->index(['rw_id', 'transfer_date'], 'cash_transfers_rw_date_idx');
            $table->index(['rt_id', 'transfer_date'], 'cash_transfers_rt_date_idx');
        });

        // Add self-referencing foreign key after primary key index is committed
        Schema::table('cash_transfers', function (Blueprint $table) {
            $table->foreign('original_transfer_id')
                ->references('id')
                ->on('cash_transfers')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cash_transfers ADD CONSTRAINT cash_transfers_amount_check
            CHECK (amount > 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE cash_transfers ADD CONSTRAINT cash_transfers_distinct_accounts_check
            CHECK (source_cash_account_id <> destination_cash_account_id)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE cash_transfers ADD CONSTRAINT cash_transfers_reversal_check
            CHECK (
                (is_reversal = FALSE AND original_transfer_id IS NULL AND reversal_reason IS NULL)
                OR
                (is_reversal = TRUE AND original_transfer_id IS NOT NULL AND reversal_reason IS NOT NULL)
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE cash_transfers ADD CONSTRAINT cash_transfers_no_self_reversal
            CHECK (original_transfer_id IS NULL OR original_transfer_id <> id)
        SQL);

        // Ensure an original transfer can be reversed at most once
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX unique_cash_transfer_reversal
            ON cash_transfers (original_transfer_id)
            WHERE original_transfer_id IS NOT NULL
        SQL);

        // PostgreSQL trigger function enforcing append-only transfer ledger immutability
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION cash_transfers_prevent_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Cash transfers are immutable financial records and cannot be updated or deleted.'
                    USING ERRCODE = 'integrity_constraint_violation';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // PostgreSQL trigger attached to cash_transfers table
        DB::statement(<<<'SQL'
            CREATE TRIGGER cash_transfers_append_only
            BEFORE UPDATE OR DELETE ON cash_transfers
            FOR EACH ROW EXECUTE FUNCTION cash_transfers_prevent_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS cash_transfers_append_only ON cash_transfers');
        DB::statement('DROP FUNCTION IF EXISTS cash_transfers_prevent_mutation()');
        Schema::dropIfExists('cash_transfers');
    }
};
