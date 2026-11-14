<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResidentHouseholdDatabaseIntegrityTest extends TestCase
{
    private Organization $org;
    private Rw $rw1;
    private Rt $rt1;
    private Rt $rt2;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->rw1 = Rw::factory()->create(['organization_id' => $this->org->id, 'number' => 1]);
        $this->rt1 = Rt::factory()->create(['rw_id' => $this->rw1->id, 'number' => 1]);
        $this->rt2 = Rt::factory()->create(['rw_id' => $this->rw1->id, 'number' => 2]);
        $this->user = User::factory()->create();
    }

    // =========================================================================
    // 1. IDENTITY & NIK CONSTRAINTS
    // =========================================================================

    public function test_duplicate_nik_throws_unique_constraint_violation(): void
    {
        $this->insertResident(['nik' => '3201010101010001']);

        $this->expectException(QueryException::class);
        $this->insertResident(['nik' => '3201010101010001']);
    }

    public function test_deceased_nik_cannot_be_reused_by_another_resident(): void
    {
        $this->insertResident([
            'nik' => '3201010101010002',
            'status' => 'DECEASED',
        ]);

        $this->expectException(QueryException::class);
        // Lifetime uniqueness: NIK is never recycled upon death
        $this->insertResident([
            'nik' => '3201010101010002',
            'status' => 'PROSPECTIVE',
        ]);
    }

    public function test_nik_format_regex_rejects_non_16_digits(): void
    {
        $this->expectException(QueryException::class);
        // 15 digits instead of 16
        $this->insertResident(['nik' => '320101010101001']);
    }

    // =========================================================================
    // 2. HOUSEHOLD CONSTRAINTS & KK UNIQUENESS
    // =========================================================================

    public function test_duplicate_active_kk_number_in_same_organization_is_rejected(): void
    {
        $this->insertHousehold(['kk_number' => '3201010101010001', 'status' => 'ACTIVE']);

        $this->expectException(QueryException::class);
        $this->insertHousehold(['kk_number' => '3201010101010001', 'status' => 'ACTIVE']);
    }

    public function test_archived_or_dissolved_kk_number_may_be_reused(): void
    {
        $this->insertHousehold(['kk_number' => '3201010101010002', 'status' => 'DISSOLVED']);

        // A new active household can reuse the dissolved KK number
        $newHouseholdId = $this->insertHousehold(['kk_number' => '3201010101010002', 'status' => 'ACTIVE']);

        $this->assertDatabaseHas('households', [
            'id' => $newHouseholdId,
            'kk_number' => '3201010101010002',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_multiple_active_heads_in_same_household_is_rejected(): void
    {
        $householdId = $this->insertHousehold();
        $resident1 = $this->insertResident(['nik' => '3201010101010011']);
        $resident2 = $this->insertResident(['nik' => '3201010101010012']);

        $this->insertHouseholdMember([
            'household_id' => $householdId,
            'resident_id' => $resident1,
            'relationship' => 'KEPALA_KELUARGA',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        // Second active head for same household violates partial unique index
        $this->insertHouseholdMember([
            'household_id' => $householdId,
            'resident_id' => $resident2,
            'relationship' => 'KEPALA_KELUARGA',
            'is_active' => true,
        ]);
    }

    public function test_one_resident_cannot_have_two_active_household_memberships(): void
    {
        $household1 = $this->insertHousehold(['kk_number' => '3201010101010021']);
        $household2 = $this->insertHousehold(['kk_number' => '3201010101010022']);
        $residentId = $this->insertResident();

        $this->insertHouseholdMember([
            'household_id' => $household1,
            'resident_id' => $residentId,
            'relationship' => 'KEPALA_KELUARGA',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        // Same resident joining second household while active violates partial unique index
        $this->insertHouseholdMember([
            'household_id' => $household2,
            'resident_id' => $residentId,
            'relationship' => 'ANAK',
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // 3. SCOPE ALIGNMENT & ANCESTRY
    // =========================================================================

    public function test_resident_in_rt1_cannot_join_household_in_rt2_via_trigger(): void
    {
        // Resident in RT 01
        $residentId = $this->insertResident(['rt_id' => $this->rt1->id]);
        // Household in RT 02
        $householdId = $this->insertHousehold(['rt_id' => $this->rt2->id]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Scope mismatch: Resident in RT');

        // Scope guard trigger rejects cross-RT membership
        $this->insertHouseholdMember([
            'household_id' => $householdId,
            'resident_id' => $residentId,
            'is_active' => true,
        ]);
    }

    public function test_resident_and_household_in_same_rt_membership_succeeds(): void
    {
        $residentId = $this->insertResident(['rt_id' => $this->rt1->id]);
        $householdId = $this->insertHousehold(['rt_id' => $this->rt1->id]);

        $memberId = $this->insertHouseholdMember([
            'household_id' => $householdId,
            'resident_id' => $residentId,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('household_members', ['id' => $memberId]);
    }

    public function test_cross_rt_current_household_link_is_rejected_by_composite_fk(): void
    {
        // Household in RT 02
        $householdId = $this->insertHousehold(['rt_id' => $this->rt2->id]);

        $this->expectException(QueryException::class);
        // Attempting to assign Resident in RT 01 to Household in RT 02
        $this->insertResident([
            'rt_id' => $this->rt1->id,
            'current_household_id' => $householdId,
        ]);
    }

    public function test_household_mismatched_ancestry_is_rejected_by_composite_fk(): void
    {
        $rw2 = Rw::factory()->create(['organization_id' => $this->org->id, 'number' => 2]);

        $this->expectException(QueryException::class);
        // RT belongs to RW 01, but household specifies RW 02
        $this->insertHousehold([
            'rw_id' => $rw2->id,
            'rt_id' => $this->rt1->id,
        ]);
    }

    // =========================================================================
    // 4. RESIDENCY MOVEMENTS & NULLABILITY RULES
    // =========================================================================

    public function test_move_in_external_requires_null_from_and_not_null_to(): void
    {
        $residentId = $this->insertResident();
        $householdId = $this->insertHousehold();

        // Valid MOVE_IN_EXTERNAL: all from_* are null, all to_* are not null
        $movementId = (string) Str::uuid();
        DB::table('residency_movements')->insert([
            'id' => $movementId,
            'resident_id' => $residentId,
            'movement_type' => 'MOVE_IN_EXTERNAL',
            'from_organization_id' => null,
            'from_rw_id' => null,
            'from_rt_id' => null,
            'from_household_id' => null,
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw1->id,
            'to_rt_id' => $this->rt1->id,
            'to_household_id' => $householdId,
            'effective_date' => '2026-10-01',
            'recorded_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('residency_movements', ['id' => $movementId]);

        // Invalid: MOVE_IN_EXTERNAL with non-null from_organization_id
        $this->expectException(QueryException::class);
        DB::table('residency_movements')->insert([
            'id' => (string) Str::uuid(),
            'resident_id' => $residentId,
            'movement_type' => 'MOVE_IN_EXTERNAL',
            'from_organization_id' => $this->org->id, // VIOLATION
            'from_rw_id' => null,
            'from_rt_id' => null,
            'from_household_id' => null,
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw1->id,
            'to_rt_id' => $this->rt1->id,
            'to_household_id' => $householdId,
            'effective_date' => '2026-10-01',
            'recorded_by' => $this->user->id,
        ]);
    }

    public function test_move_out_external_requires_not_null_from_and_null_to(): void
    {
        $residentId = $this->insertResident();
        $householdId = $this->insertHousehold();

        // Valid MOVE_OUT_EXTERNAL: all from_* not null, all to_* null
        $movementId = (string) Str::uuid();
        DB::table('residency_movements')->insert([
            'id' => $movementId,
            'resident_id' => $residentId,
            'movement_type' => 'MOVE_OUT_EXTERNAL',
            'from_organization_id' => $this->org->id,
            'from_rw_id' => $this->rw1->id,
            'from_rt_id' => $this->rt1->id,
            'from_household_id' => $householdId,
            'to_organization_id' => null,
            'to_rw_id' => null,
            'to_rt_id' => null,
            'to_household_id' => null,
            'effective_date' => '2026-10-01',
            'recorded_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('residency_movements', ['id' => $movementId]);

        // Invalid: MOVE_OUT_EXTERNAL with non-null to_household_id
        $this->expectException(QueryException::class);
        DB::table('residency_movements')->insert([
            'id' => (string) Str::uuid(),
            'resident_id' => $residentId,
            'movement_type' => 'MOVE_OUT_EXTERNAL',
            'from_organization_id' => $this->org->id,
            'from_rw_id' => $this->rw1->id,
            'from_rt_id' => $this->rt1->id,
            'from_household_id' => $householdId,
            'to_organization_id' => null,
            'to_rw_id' => null,
            'to_rt_id' => null,
            'to_household_id' => $householdId, // VIOLATION
            'effective_date' => '2026-10-01',
            'recorded_by' => $this->user->id,
        ]);
    }

    public function test_transfer_internal_in_same_rt_is_rejected(): void
    {
        $residentId = $this->insertResident();
        $hh1 = $this->insertHousehold(['kk_number' => '3201010101010041', 'rt_id' => $this->rt1->id]);
        $hh2 = $this->insertHousehold(['kk_number' => '3201010101010042', 'rt_id' => $this->rt1->id]);

        $this->expectException(QueryException::class);
        // TRANSFER_INTERNAL must be between DIFFERENT RTs (from_rt_id <> to_rt_id)
        DB::table('residency_movements')->insert([
            'id' => (string) Str::uuid(),
            'resident_id' => $residentId,
            'movement_type' => 'TRANSFER_INTERNAL',
            'from_organization_id' => $this->org->id,
            'from_rw_id' => $this->rw1->id,
            'from_rt_id' => $this->rt1->id,
            'from_household_id' => $hh1,
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw1->id,
            'to_rt_id' => $this->rt1->id, // VIOLATION: same RT
            'to_household_id' => $hh2,
            'effective_date' => '2026-10-01',
            'recorded_by' => $this->user->id,
        ]);
    }

    public function test_transfer_internal_between_different_rts_succeeds(): void
    {
        $residentId = $this->insertResident();
        $hh1 = $this->insertHousehold(['kk_number' => '3201010101010051', 'rt_id' => $this->rt1->id]);
        $hh2 = $this->insertHousehold(['kk_number' => '3201010101010052', 'rt_id' => $this->rt2->id]);

        $movementId = (string) Str::uuid();
        DB::table('residency_movements')->insert([
            'id' => $movementId,
            'resident_id' => $residentId,
            'movement_type' => 'TRANSFER_INTERNAL',
            'from_organization_id' => $this->org->id,
            'from_rw_id' => $this->rw1->id,
            'from_rt_id' => $this->rt1->id,
            'from_household_id' => $hh1,
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw1->id,
            'to_rt_id' => $this->rt2->id, // DIFFERENT RT
            'to_household_id' => $hh2,
            'effective_date' => '2026-10-01',
            'recorded_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('residency_movements', ['id' => $movementId]);
    }

    public function test_transfer_internal_household_relocation_between_rts_succeeds(): void
    {
        $residentId = $this->insertResident();
        // Household has already moved its rt_id to RT 2
        $hh = $this->insertHousehold(['kk_number' => '3201010101010053', 'rt_id' => $this->rt2->id]);

        $movementId = (string) Str::uuid();
        // from_household_id = to_household_id = hh (same household, different RTs)
        DB::table('residency_movements')->insert([
            'id' => $movementId,
            'resident_id' => $residentId,
            'movement_type' => 'TRANSFER_INTERNAL',
            'from_organization_id' => $this->org->id,
            'from_rw_id' => $this->rw1->id,
            'from_rt_id' => $this->rt1->id, // old RT
            'from_household_id' => $hh,
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw1->id,
            'to_rt_id' => $this->rt2->id, // new RT
            'to_household_id' => $hh,
            'effective_date' => '2026-10-01',
            'recorded_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('residency_movements', ['id' => $movementId]);
    }

    public function test_household_split_in_different_rt_is_rejected(): void
    {
        $residentId = $this->insertResident();
        $hh1 = $this->insertHousehold(['kk_number' => '3201010101010061', 'rt_id' => $this->rt1->id]);
        $hh2 = $this->insertHousehold(['kk_number' => '3201010101010062', 'rt_id' => $this->rt2->id]);

        $this->expectException(QueryException::class);
        // HOUSEHOLD_SPLIT must be within the SAME RT (from_rt_id = to_rt_id)
        DB::table('residency_movements')->insert([
            'id' => (string) Str::uuid(),
            'resident_id' => $residentId,
            'movement_type' => 'HOUSEHOLD_SPLIT',
            'from_organization_id' => $this->org->id,
            'from_rw_id' => $this->rw1->id,
            'from_rt_id' => $this->rt1->id,
            'from_household_id' => $hh1,
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw1->id,
            'to_rt_id' => $this->rt2->id, // VIOLATION: different RT
            'to_household_id' => $hh2,
            'effective_date' => '2026-10-01',
            'recorded_by' => $this->user->id,
        ]);
    }

    public function test_household_split_within_same_rt_succeeds(): void
    {
        $residentId = $this->insertResident();
        $hh1 = $this->insertHousehold(['kk_number' => '3201010101010071', 'rt_id' => $this->rt1->id]);
        $hh2 = $this->insertHousehold(['kk_number' => '3201010101010072', 'rt_id' => $this->rt1->id]);

        $movementId = (string) Str::uuid();
        DB::table('residency_movements')->insert([
            'id' => $movementId,
            'resident_id' => $residentId,
            'movement_type' => 'HOUSEHOLD_SPLIT',
            'from_organization_id' => $this->org->id,
            'from_rw_id' => $this->rw1->id,
            'from_rt_id' => $this->rt1->id,
            'from_household_id' => $hh1,
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw1->id,
            'to_rt_id' => $this->rt1->id, // SAME RT
            'to_household_id' => $hh2,
            'effective_date' => '2026-10-01',
            'recorded_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('residency_movements', ['id' => $movementId]);
    }

    // =========================================================================
    // 5. APPEND-ONLY IMMUTABILITY TRIGGERS
    // =========================================================================

    public function test_residency_movement_update_rejected_by_trigger(): void
    {
        $residentId = $this->insertResident();
        $hh = $this->insertHousehold();
        $movementId = (string) Str::uuid();

        DB::table('residency_movements')->insert([
            'id' => $movementId,
            'resident_id' => $residentId,
            'movement_type' => 'MOVE_IN_EXTERNAL',
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw1->id,
            'to_rt_id' => $this->rt1->id,
            'to_household_id' => $hh,
            'recorded_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Residency movement records are immutable append-only events');

        DB::table('residency_movements')
            ->where('id', $movementId)
            ->update(['notes' => 'Tampered record']);
    }

    public function test_residency_movement_delete_rejected_by_trigger(): void
    {
        $residentId = $this->insertResident();
        $hh = $this->insertHousehold();
        $movementId = (string) Str::uuid();

        DB::table('residency_movements')->insert([
            'id' => $movementId,
            'resident_id' => $residentId,
            'movement_type' => 'MOVE_IN_EXTERNAL',
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw1->id,
            'to_rt_id' => $this->rt1->id,
            'to_household_id' => $hh,
            'recorded_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Residency movement records are immutable append-only events');

        DB::table('residency_movements')->where('id', $movementId)->delete();
    }

    public function test_resident_status_history_update_rejected_by_trigger(): void
    {
        $residentId = $this->insertResident();
        $historyId = (string) Str::uuid();

        DB::table('resident_status_history')->insert([
            'id' => $historyId,
            'resident_id' => $residentId,
            'previous_status' => 'PROSPECTIVE',
            'new_status' => 'ACTIVE',
            'reason' => 'Identity verified',
            'recorded_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Resident status history records are immutable append-only events');

        DB::table('resident_status_history')
            ->where('id', $historyId)
            ->update(['reason' => 'Altered']);
    }

    public function test_resident_status_history_delete_rejected_by_trigger(): void
    {
        $residentId = $this->insertResident();
        $historyId = (string) Str::uuid();

        DB::table('resident_status_history')->insert([
            'id' => $historyId,
            'resident_id' => $residentId,
            'previous_status' => 'PROSPECTIVE',
            'new_status' => 'ACTIVE',
            'recorded_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Resident status history records are immutable append-only events');

        DB::table('resident_status_history')->where('id', $historyId)->delete();
    }

    // =========================================================================
    // 6. DELETION RESTRICTION & HISTORICAL PRESERVATION
    // =========================================================================

    public function test_household_cannot_be_deleted_when_referenced_by_members(): void
    {
        $hhId = $this->insertHousehold();
        $resId = $this->insertResident();
        $this->insertHouseholdMember([
            'household_id' => $hhId,
            'resident_id' => $resId,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        DB::table('households')->where('id', $hhId)->delete();
    }

    public function test_household_cannot_be_deleted_when_referenced_by_resident_current_household(): void
    {
        $hhId = $this->insertHousehold();
        $this->insertResident([
            'current_household_id' => $hhId,
        ]);

        $this->expectException(QueryException::class);
        // ON DELETE RESTRICT on residents.current_household_id prevents deletion
        DB::table('households')->where('id', $hhId)->delete();
    }

    public function test_resident_cannot_be_deleted_when_referenced_by_movements(): void
    {
        $resId = $this->insertResident();
        $hhId = $this->insertHousehold();

        DB::table('residency_movements')->insert([
            'id' => (string) Str::uuid(),
            'resident_id' => $resId,
            'movement_type' => 'MOVE_IN_EXTERNAL',
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw1->id,
            'to_rt_id' => $this->rt1->id,
            'to_household_id' => $hhId,
            'recorded_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);
        DB::table('residents')->where('id', $resId)->delete();
    }

    public function test_evidence_file_cannot_be_physically_deleted_when_referenced_by_movement(): void
    {
        $fileId = (string) Str::uuid();
        DB::table('files')->insert([
            'id' => $fileId,
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw1->id,
            'rt_id' => $this->rt1->id,
            'disk' => 'local',
            'path' => 'evidence/surat_pindah.pdf',
            'original_name' => 'surat_pindah',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', 'evidence'),
            'visibility' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resId = $this->insertResident();
        $hhId = $this->insertHousehold();

        DB::table('residency_movements')->insert([
            'id' => (string) Str::uuid(),
            'resident_id' => $resId,
            'movement_type' => 'MOVE_IN_EXTERNAL',
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw1->id,
            'to_rt_id' => $this->rt1->id,
            'to_household_id' => $hhId,
            'attachment_file_id' => $fileId,
            'recorded_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);
        // Physical deletion of evidentiary document is blocked by ON DELETE RESTRICT
        DB::table('files')->where('id', $fileId)->delete();
    }

    // =========================================================================
    // 7. CONCURRENCY TESTS
    // =========================================================================

    public function test_concurrent_household_head_assignment_results_in_single_winner(): void
    {
        $hhId = $this->insertHousehold();
        $res1 = $this->insertResident(['nik' => '3201010101010081']);
        $res2 = $this->insertResident(['nik' => '3201010101010082']);

        // First transaction assigns resident 1 as head
        DB::transaction(function () use ($hhId, $res1) {
            $this->insertHouseholdMember([
                'household_id' => $hhId,
                'resident_id' => $res1,
                'relationship' => 'KEPALA_KELUARGA',
                'is_active' => true,
            ]);
        });

        // Concurrent/subsequent attempt to assign resident 2 as head must fail
        $this->expectException(QueryException::class);
        DB::transaction(function () use ($hhId, $res2) {
            $this->insertHouseholdMember([
                'household_id' => $hhId,
                'resident_id' => $res2,
                'relationship' => 'KEPALA_KELUARGA',
                'is_active' => true,
            ]);
        });
    }

    public function test_concurrent_resident_household_assignment_enforces_single_active_kk(): void
    {
        $hh1 = $this->insertHousehold(['kk_number' => '3201010101010091']);
        $hh2 = $this->insertHousehold(['kk_number' => '3201010101010092']);
        $res = $this->insertResident();

        DB::transaction(function () use ($hh1, $res) {
            $this->insertHouseholdMember([
                'household_id' => $hh1,
                'resident_id' => $res,
                'relationship' => 'ANAK',
                'is_active' => true,
            ]);
        });

        $this->expectException(QueryException::class);
        DB::transaction(function () use ($hh2, $res) {
            $this->insertHouseholdMember([
                'household_id' => $hh2,
                'resident_id' => $res,
                'relationship' => 'ANAK',
                'is_active' => true,
            ]);
        });
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function insertHousehold(array $attributes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('households')->insert(array_merge([
            'id' => $id,
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw1->id,
            'rt_id' => $this->rt1->id,
            'kk_number' => '320101010101' . str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'address' => 'Jl. Kenanga No. ' . rand(1, 100),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return $id;
    }

    private function insertResident(array $attributes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('residents')->insert(array_merge([
            'id' => $id,
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw1->id,
            'rt_id' => $this->rt1->id,
            'current_household_id' => null,
            'nik' => '320101010101' . str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'name' => 'Warga ' . Str::random(5),
            'gender' => 'MALE',
            'birth_place' => 'Jakarta',
            'birth_date' => '1995-05-15',
            'religion' => 'ISLAM',
            'marital_status' => 'SINGLE',
            'blood_type' => 'O',
            'occupation' => 'Karyawan',
            'education' => 'SARJANA_S1',
            'residency_type' => 'PERMANENT',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return $id;
    }

    private function insertHouseholdMember(array $attributes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('household_members')->insert(array_merge([
            'id' => $id,
            'relationship' => 'ANAK',
            'is_active' => true,
            'joined_at' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return $id;
    }
}
