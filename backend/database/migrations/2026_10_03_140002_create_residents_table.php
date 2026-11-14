<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->nullable()->unique('residents_user_id_unique')->constrained('users')->nullOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('rw_id');
            $table->uuid('rt_id');
            $table->uuid('current_household_id')->nullable();
            $table->string('nik', 16)->unique('residents_nik_unique');
            $table->string('name', 150);
            $table->string('gender', 10);
            $table->string('birth_place', 100);
            $table->date('birth_date');
            $table->string('religion', 30);
            $table->string('marital_status', 20);
            $table->string('blood_type', 10)->default('UNKNOWN');
            $table->string('occupation', 100)->nullable();
            $table->string('education', 20)->default('NONE');
            $table->string('phone', 25)->nullable();
            $table->string('residency_type', 20)->default('PERMANENT');
            $table->string('status', 20)->default('PROSPECTIVE');
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            // Composite ancestry foreign keys ensuring RW and RT belong to the organization
            $table->foreign(['rw_id', 'organization_id'], 'residents_rw_fk')
                ->references(['id', 'organization_id'])->on('rws')->restrictOnDelete();
            $table->foreign(['rt_id', 'rw_id'], 'residents_rt_fk')
                ->references(['id', 'rw_id'])->on('rts')->restrictOnDelete();

            // Scope alignment: Resident and household must share identical RT
            // ON DELETE RESTRICT prevents silent clearing of resident household state
            $table->foreign(['current_household_id', 'rt_id'], 'residents_household_rt_fk')
                ->references(['id', 'rt_id'])->on('households')->restrictOnDelete();

            // Composite target for child references
            $table->unique(['id', 'rt_id'], 'residents_id_rt_unique');

            $table->index(['rt_id', 'status'], 'residents_rt_status_idx');
            $table->index('current_household_id', 'residents_current_household_idx');
            $table->index('name', 'residents_name_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE residents ADD CONSTRAINT residents_nik_format
            CHECK (nik ~ '^[0-9]{16}$')
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE residents ADD CONSTRAINT residents_gender_check
            CHECK (gender IN ('MALE', 'FEMALE'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE residents ADD CONSTRAINT residents_residency_type_check
            CHECK (residency_type IN ('PERMANENT', 'TEMPORARY', 'SEASONAL'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE residents ADD CONSTRAINT residents_status_check
            CHECK (status IN ('PROSPECTIVE', 'ACTIVE', 'INACTIVE', 'MOVED', 'DECEASED'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE residents ADD CONSTRAINT residents_marital_status_check
            CHECK (marital_status IN ('SINGLE', 'MARRIED', 'DIVORCED', 'WIDOWED'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE residents ADD CONSTRAINT residents_blood_type_check
            CHECK (blood_type IN ('A', 'B', 'AB', 'O', 'A_POS', 'A_NEG', 'B_POS', 'B_NEG', 'AB_POS', 'AB_NEG', 'O_POS', 'O_NEG', 'UNKNOWN'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE residents ADD CONSTRAINT residents_education_check
            CHECK (education IN ('NONE', 'SD', 'SMP', 'SMA', 'DIPLOMA', 'SARJANA_S1', 'MAGISTER_S2', 'DOKTOR_S3'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
