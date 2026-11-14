<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 1. Relax the check constraint on residency_movements to allow TRANSFER_INTERNAL
     *    when an entire household relocates to another RT (where from_household_id = to_household_id).
     * 2. Replace the composite foreign key residency_movements_from_hh_fk with a direct FK on
     *    from_household_id -> households(id), because during an inter-RT household relocation,
     *    the household's rt_id in the households table is updated to the target RT, meaning
     *    (from_household_id, from_rt_id) cannot point to the same row in households.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE residency_movements DROP CONSTRAINT IF EXISTS residency_movements_from_hh_fk');

        DB::statement('ALTER TABLE residency_movements ADD CONSTRAINT residency_movements_from_hh_fk FOREIGN KEY (from_household_id) REFERENCES households(id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE residency_movements DROP CONSTRAINT IF EXISTS residency_movements_nullability_check');

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
        DB::statement('ALTER TABLE residency_movements DROP CONSTRAINT IF EXISTS residency_movements_nullability_check');

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

        DB::statement('ALTER TABLE residency_movements DROP CONSTRAINT IF EXISTS residency_movements_from_hh_fk');

        DB::statement('ALTER TABLE residency_movements ADD CONSTRAINT residency_movements_from_hh_fk FOREIGN KEY (from_household_id, from_rt_id) REFERENCES households(id, rt_id) ON DELETE RESTRICT');
    }
};
