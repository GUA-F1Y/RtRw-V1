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
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('rw_id');
            $table->uuid('rt_id')->nullable();
            $table->string('name', 100);
            $table->string('account_number', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->bigInteger('opening_balance')->default(0);
            $table->timestampTz('closed_at')->nullable();
            $table->text('closed_reason')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            // Composite ancestry foreign keys ensuring RW and RT belong to the organization
            $table->foreign(['rw_id', 'organization_id'], 'cash_accounts_rw_fk')
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['rt_id', 'rw_id'], 'cash_accounts_rt_fk')
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();

            $table->index(['rw_id', 'status'], 'cash_accounts_rw_status_idx');
            $table->index(['rt_id', 'status'], 'cash_accounts_rt_status_idx');
            $table->index(['organization_id', 'name'], 'cash_accounts_org_name_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cash_accounts ADD CONSTRAINT cash_accounts_status_check
            CHECK (status IN ('ACTIVE', 'CLOSED'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE cash_accounts ADD CONSTRAINT cash_accounts_opening_balance_check
            CHECK (opening_balance >= 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_accounts');
    }
};
