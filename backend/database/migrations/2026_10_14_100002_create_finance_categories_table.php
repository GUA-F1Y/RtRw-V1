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
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('rw_id')->nullable();
            $table->uuid('rt_id')->nullable();
            $table->string('name', 100);
            $table->string('code', 30)->nullable();
            $table->string('type', 20);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign(['rw_id', 'organization_id'], 'finance_categories_rw_fk')
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();

            $table->index(['organization_id', 'type', 'is_active'], 'finance_categories_org_type_idx');
            $table->index(['rw_id', 'type'], 'finance_categories_rw_type_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE finance_categories ADD CONSTRAINT finance_categories_type_check
            CHECK (type IN ('INCOME', 'EXPENSE'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_categories');
    }
};
