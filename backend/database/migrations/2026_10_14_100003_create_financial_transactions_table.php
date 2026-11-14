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
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('rw_id');
            $table->uuid('rt_id')->nullable();
            $table->foreignUuid('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignUuid('category_id')->constrained('finance_categories')->restrictOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount');
            $table->date('transaction_date')->default(DB::raw('CURRENT_DATE'));
            $table->text('description');
            $table->string('reference_number', 100)->nullable();
            $table->foreignUuid('evidence_file_id')->nullable()->constrained('files')->restrictOnDelete();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_reversal')->default(false);
            $table->uuid('original_transaction_id')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign(['rw_id', 'organization_id'], 'financial_transactions_rw_fk')
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['rt_id', 'rw_id'], 'financial_transactions_rt_fk')
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();

            $table->index(['cash_account_id', 'transaction_date'], 'financial_transactions_account_date_idx');
            $table->index(['category_id', 'transaction_date'], 'financial_transactions_category_date_idx');
            $table->index(['recorded_by', 'created_at'], 'financial_transactions_recorded_by_idx');
            $table->index(['rw_id', 'transaction_date'], 'financial_transactions_rw_date_idx');
            $table->index(['rt_id', 'transaction_date'], 'financial_transactions_rt_date_idx');
        });

        // Add self-referencing foreign key after primary key index is committed
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreign('original_transaction_id')
                ->references('id')
                ->on('financial_transactions')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_amount_check
            CHECK (amount > 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_type_check
            CHECK (type IN ('INCOME', 'EXPENSE'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_reversal_check
            CHECK (
                (is_reversal = FALSE AND original_transaction_id IS NULL AND reversal_reason IS NULL)
                OR
                (is_reversal = TRUE AND original_transaction_id IS NOT NULL)
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_no_self_reversal
            CHECK (original_transaction_id IS NULL OR original_transaction_id <> id)
        SQL);

        // Ensure an original transaction can be reversed at most once
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX unique_financial_transaction_reversal
            ON financial_transactions (original_transaction_id)
            WHERE original_transaction_id IS NOT NULL
        SQL);

        // PostgreSQL trigger function enforcing append-only ledger immutability
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION financial_transactions_prevent_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Financial transactions are immutable append-only ledger entries and cannot be updated or deleted.'
                    USING ERRCODE = 'integrity_constraint_violation';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // PostgreSQL trigger attached to table
        DB::statement(<<<'SQL'
            CREATE TRIGGER financial_transactions_append_only
            BEFORE UPDATE OR DELETE ON financial_transactions
            FOR EACH ROW EXECUTE FUNCTION financial_transactions_prevent_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS financial_transactions_append_only ON financial_transactions');
        DB::statement('DROP FUNCTION IF EXISTS financial_transactions_prevent_mutation()');
        Schema::dropIfExists('financial_transactions');
    }
};
