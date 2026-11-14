<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Trigger function and trigger for household_members scope enforcement
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION household_members_enforce_scope_match()
            RETURNS trigger AS $$
            DECLARE
                v_household_rt UUID;
                v_resident_rt  UUID;
            BEGIN
                IF NEW.is_active = TRUE THEN
                    SELECT rt_id INTO v_household_rt
                    FROM households
                    WHERE id = NEW.household_id;

                    -- Row-level share lock serializes against concurrent resident RT mutation
                    SELECT rt_id INTO v_resident_rt
                    FROM residents
                    WHERE id = NEW.resident_id
                    FOR SHARE;

                    IF v_household_rt IS NULL THEN
                        RAISE EXCEPTION 'Target household does not exist.'
                            USING ERRCODE = 'foreign_key_violation';
                    END IF;

                    IF v_resident_rt IS NULL THEN
                        RAISE EXCEPTION 'Target resident does not exist.'
                            USING ERRCODE = 'foreign_key_violation';
                    END IF;

                    IF v_household_rt <> v_resident_rt THEN
                        RAISE EXCEPTION 'Scope mismatch: Resident in RT (%) cannot join Household in RT (%).',
                            v_resident_rt, v_household_rt
                            USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER household_members_scope_guard
            BEFORE INSERT OR UPDATE OF household_id, resident_id, is_active ON household_members
            FOR EACH ROW EXECUTE FUNCTION household_members_enforce_scope_match();
        SQL);

        // 2. Trigger function and trigger for residency_movements append-only immutability
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION residency_movements_prevent_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Residency movement records are immutable append-only events and cannot be updated or deleted.'
                    USING ERRCODE = 'integrity_constraint_violation';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER residency_movements_append_only
            BEFORE UPDATE OR DELETE ON residency_movements
            FOR EACH ROW EXECUTE FUNCTION residency_movements_prevent_mutation();
        SQL);

        // 3. Trigger function and trigger for resident_status_history append-only immutability
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION resident_status_history_prevent_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Resident status history records are immutable append-only events and cannot be updated or deleted.'
                    USING ERRCODE = 'integrity_constraint_violation';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER resident_status_history_append_only
            BEFORE UPDATE OR DELETE ON resident_status_history
            FOR EACH ROW EXECUTE FUNCTION resident_status_history_prevent_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS household_members_scope_guard ON household_members');
        DB::statement('DROP FUNCTION IF EXISTS household_members_enforce_scope_match()');

        DB::statement('DROP TRIGGER IF EXISTS residency_movements_append_only ON residency_movements');
        DB::statement('DROP FUNCTION IF EXISTS residency_movements_prevent_mutation()');

        DB::statement('DROP TRIGGER IF EXISTS resident_status_history_append_only ON resident_status_history');
        DB::statement('DROP FUNCTION IF EXISTS resident_status_history_prevent_mutation()');
    }
};
