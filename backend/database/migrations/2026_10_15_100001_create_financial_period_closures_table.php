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
        Schema::create('financial_period_closures', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('rw_id');
            $table->uuid('rt_id')->nullable();
            $table->foreignUuid('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->smallInteger('period_year');
            $table->smallInteger('period_month');
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('opening_balance');
            $table->bigInteger('closing_balance');
            $table->bigInteger('total_income')->default(0);
            $table->bigInteger('total_expense')->default(0);
            $table->bigInteger('posted_income')->default(0);
            $table->bigInteger('reversed_income')->default(0);
            $table->bigInteger('posted_expense')->default(0);
            $table->bigInteger('reversed_expense')->default(0);
            $table->bigInteger('net_movement')->default(0);
            $table->integer('transaction_count')->default(0);
            $table->integer('reversal_count')->default(0);
            $table->bigInteger('counted_cash_balance')->nullable();
            $table->bigInteger('reconciliation_difference')->nullable();
            $table->text('reconciliation_notes')->nullable();
            $table->foreignUuid('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('closed_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign(['rw_id', 'organization_id'], 'financial_period_closures_rw_fk')
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['rt_id', 'rw_id'], 'financial_period_closures_rt_fk')
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();

            $table->unique(['cash_account_id', 'period_year', 'period_month'], 'financial_period_closures_account_month_unique');
            $table->index(['cash_account_id', 'period_start', 'period_end'], 'financial_period_closures_account_dates_idx');
            $table->index(['organization_id', 'period_year', 'period_month'], 'financial_period_closures_org_period_idx');
            $table->index(['rw_id', 'period_year', 'period_month'], 'financial_period_closures_rw_period_idx');
            $table->index(['rt_id', 'period_year', 'period_month'], 'financial_period_closures_rt_period_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE financial_period_closures ADD CONSTRAINT financial_period_closures_month_range
            CHECK (period_month >= 1 AND period_month <= 12)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE financial_period_closures ADD CONSTRAINT financial_period_closures_year_range
            CHECK (period_year >= 2000 AND period_year <= 2100)
        SQL);

        // PostgreSQL trigger function enforcing closure snapshot immutability
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION financial_period_closures_prevent_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Financial period closures are immutable audit records and cannot be updated or deleted.'
                    USING ERRCODE = 'integrity_constraint_violation';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // PostgreSQL trigger attached to table
        DB::statement(<<<'SQL'
            CREATE TRIGGER financial_period_closures_append_only
            BEFORE UPDATE OR DELETE ON financial_period_closures
            FOR EACH ROW EXECUTE FUNCTION financial_period_closures_prevent_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS financial_period_closures_append_only ON financial_period_closures');
        DB::statement('DROP FUNCTION IF EXISTS financial_period_closures_prevent_mutation()');
        Schema::dropIfExists('financial_period_closures');
    }
};
