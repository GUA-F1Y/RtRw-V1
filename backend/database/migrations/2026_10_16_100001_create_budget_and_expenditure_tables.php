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
        // 1. Budget Plans Table
        Schema::create('budget_plans', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('rw_id');
            $table->uuid('rt_id')->nullable();
            $table->smallInteger('year');
            $table->smallInteger('month');
            $table->integer('version')->default(1);
            $table->string('status', 20)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['rw_id', 'organization_id'], 'budget_plans_rw_fk')
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['rt_id', 'rw_id'], 'budget_plans_rt_fk')
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();

            $table->index(['organization_id', 'rw_id', 'year', 'month'], 'budget_plans_scope_period_idx');
            $table->index(['created_by', 'created_at'], 'budget_plans_created_by_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE budget_plans ADD CONSTRAINT budget_plans_month_check
            CHECK (month >= 1 AND month <= 12)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE budget_plans ADD CONSTRAINT budget_plans_year_check
            CHECK (year >= 2000 AND year <= 2100)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE budget_plans ADD CONSTRAINT budget_plans_version_check
            CHECK (version >= 1)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE budget_plans ADD CONSTRAINT budget_plans_status_check
            CHECK (status IN ('DRAFT', 'PUBLISHED', 'SUPERSEDED'))
        SQL);

        // Version uniqueness per scope, year, month, and version
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX unique_budget_plan_version
            ON budget_plans (organization_id, rw_id, COALESCE(rt_id, '00000000-0000-0000-0000-000000000000'::uuid), year, month, version)
        SQL);

        // Exactly one PUBLISHED budget plan per scope, year, and month
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX unique_active_published_budget
            ON budget_plans (organization_id, rw_id, COALESCE(rt_id, '00000000-0000-0000-0000-000000000000'::uuid), year, month)
            WHERE status = 'PUBLISHED'
        SQL);

        // 2. Budget Lines Table
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('budget_plan_id')->constrained('budget_plans')->restrictOnDelete();
            $table->foreignUuid('category_id')->constrained('finance_categories')->restrictOnDelete();
            $table->bigInteger('planned_amount');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['budget_plan_id', 'category_id'], 'unique_budget_line_category');
            $table->index(['category_id'], 'budget_lines_category_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE budget_lines ADD CONSTRAINT budget_lines_planned_amount_check
            CHECK (planned_amount >= 0 AND planned_amount <= 100000000000000)
        SQL);

        // Trigger to prevent updating or deleting lines of published/superseded plans
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION budget_lines_prevent_modification_on_published()
            RETURNS trigger AS $$
            DECLARE
                plan_status text;
            BEGIN
                SELECT status INTO plan_status FROM budget_plans WHERE id = OLD.budget_plan_id;
                IF plan_status IN ('PUBLISHED', 'SUPERSEDED') THEN
                    RAISE EXCEPTION 'Budget lines of published or superseded budget plans are immutable.'
                        USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER budget_lines_immutable_on_published
            BEFORE UPDATE OR DELETE ON budget_lines
            FOR EACH ROW EXECUTE FUNCTION budget_lines_prevent_modification_on_published();
        SQL);

        // 3. Expenditure Requests Table
        Schema::create('expenditure_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('rw_id');
            $table->uuid('rt_id')->nullable();
            $table->foreignUuid('budget_line_id')->constrained('budget_lines')->restrictOnDelete();
            $table->foreignUuid('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignUuid('category_id')->constrained('finance_categories')->restrictOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->bigInteger('proposed_amount');
            $table->bigInteger('approved_amount')->nullable();
            $table->bigInteger('actual_amount')->nullable();
            $table->date('planned_date');
            $table->string('reference_number', 100)->nullable();
            $table->foreignUuid('evidence_file_id')->nullable()->constrained('files')->restrictOnDelete();
            $table->string('status', 20)->default('DRAFT');
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('decision_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignUuid('financial_transaction_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['rw_id', 'organization_id'], 'expenditure_requests_rw_fk')
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['rt_id', 'rw_id'], 'expenditure_requests_rt_fk')
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();

            $table->index(['budget_line_id', 'status'], 'expenditure_requests_line_status_idx');
            $table->index(['cash_account_id', 'status'], 'expenditure_requests_account_status_idx');
            $table->index(['organization_id', 'rw_id', 'status'], 'expenditure_requests_scope_status_idx');
            $table->index(['requested_by', 'created_at'], 'expenditure_requests_requester_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE expenditure_requests ADD CONSTRAINT expenditure_requests_proposed_amount_check
            CHECK (proposed_amount > 0 AND proposed_amount <= 100000000000000)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE expenditure_requests ADD CONSTRAINT expenditure_requests_approved_amount_check
            CHECK (approved_amount IS NULL OR (approved_amount > 0 AND approved_amount <= 100000000000000))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE expenditure_requests ADD CONSTRAINT expenditure_requests_actual_amount_check
            CHECK (actual_amount IS NULL OR (actual_amount > 0 AND actual_amount <= approved_amount))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE expenditure_requests ADD CONSTRAINT expenditure_requests_status_check
            CHECK (status IN ('DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED', 'POSTED'))
        SQL);

        // One financial transaction can be linked to at most one expenditure request
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX unique_expenditure_transaction
            ON expenditure_requests (financial_transaction_id)
            WHERE financial_transaction_id IS NOT NULL
        SQL);

        // PostgreSQL trigger function preventing hard deletion of requests with decision history
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION expenditure_requests_prevent_delete_decision()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.status IN ('APPROVED', 'REJECTED', 'POSTED') THEN
                    RAISE EXCEPTION 'Expenditure requests with financial decision history cannot be deleted.'
                        USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER expenditure_requests_prevent_deletion
            BEFORE DELETE ON expenditure_requests
            FOR EACH ROW EXECUTE FUNCTION expenditure_requests_prevent_delete_decision();
        SQL);

        // PostgreSQL trigger function preventing mutation of POSTED requests
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION expenditure_requests_prevent_mutation_on_posted()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.status = 'POSTED' THEN
                    RAISE EXCEPTION 'Posted expenditure requests are immutable and cannot be updated.'
                        USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER expenditure_requests_immutable_on_posted
            BEFORE UPDATE ON expenditure_requests
            FOR EACH ROW EXECUTE FUNCTION expenditure_requests_prevent_mutation_on_posted();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS expenditure_requests_immutable_on_posted ON expenditure_requests');
        DB::statement('DROP FUNCTION IF EXISTS expenditure_requests_prevent_mutation_on_posted()');
        DB::statement('DROP TRIGGER IF EXISTS expenditure_requests_prevent_deletion ON expenditure_requests');
        DB::statement('DROP FUNCTION IF EXISTS expenditure_requests_prevent_delete_decision()');
        Schema::dropIfExists('expenditure_requests');

        DB::statement('DROP TRIGGER IF EXISTS budget_lines_immutable_on_published ON budget_lines');
        DB::statement('DROP FUNCTION IF EXISTS budget_lines_prevent_modification_on_published()');
        Schema::dropIfExists('budget_lines');

        Schema::dropIfExists('budget_plans');
    }
};
