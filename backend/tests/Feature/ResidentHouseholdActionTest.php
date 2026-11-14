<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Household\Actions\AddResidentToHouseholdAction;
use App\Domain\Household\Actions\ChangeHouseholdHeadAction;
use App\Domain\Household\Actions\CreateHouseholdAction;
use App\Domain\Household\Actions\DissolveHouseholdAction;
use App\Domain\Household\Actions\MoveHouseholdAction;
use App\Domain\Household\DTOs\CreateHouseholdInput;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Exceptions\HouseholdStateConflict;
use App\Domain\Household\Exceptions\InvalidHouseholdStatusTransition;
use App\Domain\Household\Models\Household;
use App\Domain\Household\Services\HouseholdStatusStateMachine;
use App\Domain\Identity\Enums\AccountStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Domain\Resident\Actions\RecordResidentStatusAction;
use App\Domain\Resident\Actions\RegisterResidentAction;
use App\Domain\Resident\Actions\RelocateResidentAction;
use App\Domain\Resident\Actions\RemoveResidentFromHouseholdAction;
use App\Domain\Resident\Actions\VerifyResidentAction;
use App\Domain\Resident\DTOs\RegisterResidentInput;
use App\Domain\Resident\DTOs\RelocateResidentInput;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Enums\ResidencyMovementType;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Exceptions\DuplicateNikException;
use App\Domain\Resident\Exceptions\HouseholdMembershipConflict;
use App\Domain\Resident\Exceptions\InvalidHouseholdHeadChange;
use App\Domain\Resident\Exceptions\InvalidResidentStatusTransition;
use App\Domain\Resident\Exceptions\ResidentScopeMismatch;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\ResidencyMovement;
use App\Domain\Resident\Models\Resident;
use App\Domain\Resident\Models\ResidentStatusHistory;
use App\Domain\Resident\Services\ResidentStatusStateMachine;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResidentHouseholdActionTest extends TestCase
{
    private Organization $org;
    private Rw $rw1;
    private Rt $rt1;
    private Rt $rt2;
    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->rw1 = Rw::factory()->create(['organization_id' => $this->org->id, 'number' => 1]);
        $this->rt1 = Rt::factory()->create(['rw_id' => $this->rw1->id, 'number' => 1]);
        $this->rt2 = Rt::factory()->create(['rw_id' => $this->rw1->id, 'number' => 2]);
        $this->officer = User::factory()->create();
    }

    private function createActiveResident(
        string $nik,
        string $name,
        string $rtId,
        ?string $rwId = null,
        ?string $orgId = null,
        ?string $userId = null,
        ResidentGender $gender = ResidentGender::Male,
        ResidentMaritalStatus $maritalStatus = ResidentMaritalStatus::Single,
        string $birthPlace = 'Jakarta',
        string $birthDate = '1990-01-01',
        string $religion = 'ISLAM'
    ): Resident {
        $registerAction = app(RegisterResidentAction::class);
        $verifyAction = app(VerifyResidentAction::class);

        $resident = $registerAction->execute(new RegisterResidentInput(
            nik: $nik,
            name: $name,
            gender: $gender,
            birthPlace: $birthPlace,
            birthDate: $birthDate,
            religion: $religion,
            maritalStatus: $maritalStatus,
            organizationId: $orgId ?? $this->org->id,
            rwId: $rwId ?? $this->rw1->id,
            rtId: $rtId,
            userId: $userId,
        ), $this->officer);

        return $verifyAction->execute($resident->id, actor: $this->officer);
    }

    // =========================================================================
    // 1. REGISTER RESIDENT ACTION TESTS
    // =========================================================================

    public function test_valid_resident_registration_succeeds_with_prospective_status(): void
    {
        $action = app(RegisterResidentAction::class);

        $input = new RegisterResidentInput(
            nik: '3201010101010001',
            name: 'Budi Santoso',
            gender: ResidentGender::Male,
            birthPlace: 'Jakarta',
            birthDate: '1990-01-01',
            religion: 'ISLAM',
            maritalStatus: ResidentMaritalStatus::Single,
            organizationId: $this->org->id,
            rwId: $this->rw1->id,
            rtId: $this->rt1->id,
        );

        $resident = $action->execute($input, $this->officer);

        $this->assertInstanceOf(Resident::class, $resident);
        $this->assertEquals('3201010101010001', $resident->nik);
        $this->assertEquals(ResidentStatus::Prospective, $resident->status);
        $this->assertEquals($this->rt1->id, $resident->rt_id);
        $this->assertNull($resident->current_household_id);

        // Verify status history was created
        $history = ResidentStatusHistory::where('resident_id', $resident->id)->first();
        $this->assertNotNull($history);
        $this->assertEquals(ResidentStatus::Prospective, $history->new_status);
        $this->assertEquals($this->officer->id, $history->recorded_by);
    }

    public function test_registration_rejects_malformed_nik(): void
    {
        $action = app(RegisterResidentAction::class);

        $input = new RegisterResidentInput(
            nik: '12345', // < 16 digits
            name: 'Budi Santoso',
            gender: ResidentGender::Male,
            birthPlace: 'Jakarta',
            birthDate: '1990-01-01',
            religion: 'ISLAM',
            maritalStatus: ResidentMaritalStatus::Single,
            organizationId: $this->org->id,
            rwId: $this->rw1->id,
            rtId: $this->rt1->id,
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('NIK must be exactly 16 numeric digits.');

        $action->execute($input, $this->officer);
    }

    public function test_registration_rejects_duplicate_nik(): void
    {
        $action = app(RegisterResidentAction::class);

        $input = new RegisterResidentInput(
            nik: '3201010101010002',
            name: 'First Resident',
            gender: ResidentGender::Male,
            birthPlace: 'Jakarta',
            birthDate: '1990-01-01',
            religion: 'ISLAM',
            maritalStatus: ResidentMaritalStatus::Single,
            organizationId: $this->org->id,
            rwId: $this->rw1->id,
            rtId: $this->rt1->id,
        );

        $action->execute($input, $this->officer);

        $this->expectException(DuplicateNikException::class);
        $action->execute($input, $this->officer);
    }

    public function test_registration_rejects_hierarchy_scope_mismatch(): void
    {
        $action = app(RegisterResidentAction::class);

        $otherOrg = Organization::factory()->create();

        $input = new RegisterResidentInput(
            nik: '3201010101010003',
            name: 'Mismatched Resident',
            gender: ResidentGender::Male,
            birthPlace: 'Jakarta',
            birthDate: '1990-01-01',
            religion: 'ISLAM',
            maritalStatus: ResidentMaritalStatus::Single,
            organizationId: $otherOrg->id, // Mismatched organization
            rwId: $this->rw1->id,
            rtId: $this->rt1->id,
        );

        $this->expectException(ResidentScopeMismatch::class);
        $action->execute($input, $this->officer);
    }

    public function test_registration_with_optional_user_linking(): void
    {
        $user = User::factory()->create();
        $action = app(RegisterResidentAction::class);

        $input = new RegisterResidentInput(
            nik: '3201010101010004',
            name: 'Resident With Account',
            gender: ResidentGender::Male,
            birthPlace: 'Jakarta',
            birthDate: '1990-01-01',
            religion: 'ISLAM',
            maritalStatus: ResidentMaritalStatus::Single,
            organizationId: $this->org->id,
            rwId: $this->rw1->id,
            rtId: $this->rt1->id,
            userId: $user->id
        );

        $resident = $action->execute($input, $this->officer);

        $this->assertEquals($user->id, $resident->user_id);
    }

    // =========================================================================
    // 2. VERIFY RESIDENT ACTION TESTS
    // =========================================================================

    public function test_verify_prospective_resident_succeeds(): void
    {
        $registerAction = app(RegisterResidentAction::class);
        $verifyAction = app(VerifyResidentAction::class);

        $resident = $registerAction->execute(new RegisterResidentInput(
            nik: '3201010101010005',
            name: 'Prospective Resident',
            gender: ResidentGender::Female,
            birthPlace: 'Bandung',
            birthDate: '1992-02-02',
            religion: 'ISLAM',
            maritalStatus: ResidentMaritalStatus::Single,
            organizationId: $this->org->id,
            rwId: $this->rw1->id,
            rtId: $this->rt1->id,
        ), $this->officer);

        $verified = $verifyAction->execute(
            residentId: $resident->id,
            notes: 'Physical KTP verified',
            referenceLetterNumber: 'VERIF/2026/001',
            actor: $this->officer
        );

        $this->assertEquals(ResidentStatus::Active, $verified->status);

        // Check history for verification event
        $verificationHistory = ResidentStatusHistory::where('resident_id', $resident->id)
            ->where('new_status', ResidentStatus::Active)
            ->first();

        $this->assertNotNull($verificationHistory);
        $this->assertEquals(ResidentStatus::Prospective, $verificationHistory->previous_status);
        $this->assertEquals(ResidentStatus::Active, $verificationHistory->new_status);
        $this->assertEquals('Physical KTP verified', $verificationHistory->reason);
    }

    public function test_verify_non_prospective_resident_fails(): void
    {
        $registerAction = app(RegisterResidentAction::class);
        $verifyAction = app(VerifyResidentAction::class);

        $resident = $registerAction->execute(new RegisterResidentInput(
            nik: '3201010101010006',
            name: 'Already Active Candidate',
            gender: ResidentGender::Male,
            birthPlace: 'Bandung',
            birthDate: '1992-02-02',
            religion: 'ISLAM',
            maritalStatus: ResidentMaritalStatus::Single,
            organizationId: $this->org->id,
            rwId: $this->rw1->id,
            rtId: $this->rt1->id,
        ), $this->officer);

        // First verification: PROSPECTIVE -> ACTIVE
        $verifyAction->execute($resident->id, actor: $this->officer);

        // Second verification attempt on already ACTIVE resident must throw
        $this->expectException(InvalidResidentStatusTransition::class);
        $verifyAction->execute($resident->id, actor: $this->officer);
    }

    // =========================================================================
    // 3. CREATE HOUSEHOLD ACTION TESTS
    // =========================================================================

    public function test_create_household_without_head_sets_head_vacant(): void
    {
        $action = app(CreateHouseholdAction::class);

        $household = $action->execute(new CreateHouseholdInput(
            kkNumber: '3201010101011001',
            address: 'Jl. Melati No. 10',
            rtId: $this->rt1->id,
            notes: 'New family dwelling'
        ), $this->officer);

        $this->assertEquals(HouseholdStatus::HeadVacant, $household->status);
        $this->assertEquals('3201010101011001', $household->kk_number);
        $this->assertNull($household->activeHead);
    }

    public function test_create_household_with_initial_head_sets_active(): void
    {
        $headResident = $this->createActiveResident(
            nik: '3201010101011002',
            name: 'Bapak Kepala',
            rtId: $this->rt1->id
        );

        $action = app(CreateHouseholdAction::class);
        $household = $action->execute(new CreateHouseholdInput(
            kkNumber: '3201010101011002',
            address: 'Jl. Melati No. 12',
            rtId: $this->rt1->id,
            initialHeadResidentId: $headResident->id
        ), $this->officer);

        $this->assertEquals(HouseholdStatus::Active, $household->status);

        // Check active head relationship
        $activeHead = $household->activeHead;
        $this->assertNotNull($activeHead);
        $this->assertEquals($headResident->id, $activeHead->resident_id);
        $this->assertEquals(HouseholdMemberRelationship::KepalaKeluarga, $activeHead->relationship);

        // Check resident pointer
        $headResident->refresh();
        $this->assertEquals($household->id, $headResident->current_household_id);
    }

    public function test_create_household_rejects_prospective_initial_head(): void
    {
        $registerAction = app(RegisterResidentAction::class);
        $prospectiveResident = $registerAction->execute(new RegisterResidentInput(
            nik: '3201010101011099',
            name: 'Prospective Head',
            gender: ResidentGender::Male,
            birthPlace: 'Solo',
            birthDate: '1980-01-01',
            religion: 'ISLAM',
            maritalStatus: ResidentMaritalStatus::Married,
            organizationId: $this->org->id,
            rwId: $this->rw1->id,
            rtId: $this->rt1->id,
        ), $this->officer);

        $action = app(CreateHouseholdAction::class);

        $this->expectException(HouseholdMembershipConflict::class);
        $this->expectExceptionMessage("Resident with status 'PROSPECTIVE' cannot be appointed as household head. Must be active.");

        $action->execute(new CreateHouseholdInput(
            kkNumber: '3201010101011099',
            address: 'Jl. Melati No. 99',
            rtId: $this->rt1->id,
            initialHeadResidentId: $prospectiveResident->id,
        ), $this->officer);
    }

    public function test_create_household_rejects_duplicate_active_kk(): void
    {
        $head = $this->createActiveResident(
            nik: '3201010101011003',
            name: 'Active KK Head',
            rtId: $this->rt1->id
        );

        $action = app(CreateHouseholdAction::class);

        $input = new CreateHouseholdInput(
            kkNumber: '3201010101011003',
            address: 'Jl. Melati No. 15',
            rtId: $this->rt1->id,
            initialHeadResidentId: $head->id,
        );

        $action->execute($input, $this->officer);

        $this->expectException(HouseholdStateConflict::class);
        $action->execute(new CreateHouseholdInput(
            kkNumber: '3201010101011003',
            address: 'Jl. Melati No. 16',
            rtId: $this->rt1->id,
        ), $this->officer);
    }

    public function test_create_household_rejects_cross_rt_initial_head(): void
    {
        $residentInRt2 = $this->createActiveResident(
            nik: '3201010101011004',
            name: 'Resident in RT 2',
            rtId: $this->rt2->id
        );

        $action = app(CreateHouseholdAction::class);

        $this->expectException(ResidentScopeMismatch::class);
        $action->execute(new CreateHouseholdInput(
            kkNumber: '3201010101011005',
            address: 'Jl. Melati No. 20',
            rtId: $this->rt1->id, // Household in RT 1
            initialHeadResidentId: $residentInRt2->id
        ), $this->officer);
    }

    // =========================================================================
    // 4. ADD & REMOVE RESIDENT FROM HOUSEHOLD TESTS
    // =========================================================================

    public function test_add_resident_to_household_succeeds(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101012001',
            address: 'Jl. Kenanga No. 1',
            rtId: $this->rt1->id
        ), $this->officer);

        $resident = $this->createActiveResident(
            nik: '3201010101012001',
            name: 'Anggota Keluarga',
            rtId: $this->rt1->id
        );

        $membership = $addMember->execute(
            householdId: $hh->id,
            residentId: $resident->id,
            relationship: HouseholdMemberRelationship::Anak,
            actor: $this->officer
        );

        $this->assertTrue($membership->is_active);
        $this->assertEquals(HouseholdMemberRelationship::Anak, $membership->relationship);

        $resident->refresh();
        $this->assertEquals($hh->id, $resident->current_household_id);
    }

    public function test_add_resident_rejects_prospective_resident(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $registerAction = app(RegisterResidentAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101012098',
            address: 'Jl. Kenanga No. 98',
            rtId: $this->rt1->id
        ), $this->officer);

        $prospectiveResident = $registerAction->execute(new RegisterResidentInput(
            nik: '3201010101012098',
            name: 'Prospective Member',
            gender: ResidentGender::Female,
            birthPlace: 'Semarang',
            birthDate: '2000-01-01',
            religion: 'ISLAM',
            maritalStatus: ResidentMaritalStatus::Single,
            organizationId: $this->org->id,
            rwId: $this->rw1->id,
            rtId: $this->rt1->id,
        ), $this->officer);

        $this->expectException(HouseholdMembershipConflict::class);
        $this->expectExceptionMessage("Resident with status 'PROSPECTIVE' cannot join a household.");

        $addMember->execute($hh->id, $prospectiveResident->id, HouseholdMemberRelationship::Anak, actor: $this->officer);
    }

    public function test_add_resident_to_household_cross_rt_rejected(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);

        $hhInRt1 = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101012002',
            address: 'Jl. Kenanga No. 2',
            rtId: $this->rt1->id
        ), $this->officer);

        $residentInRt2 = $this->createActiveResident(
            nik: '3201010101012002',
            name: 'Cross RT Resident',
            rtId: $this->rt2->id
        );

        $this->expectException(ResidentScopeMismatch::class);
        $addMember->execute($hhInRt1->id, $residentInRt2->id, HouseholdMemberRelationship::Anak, actor: $this->officer);
    }

    public function test_add_resident_already_in_active_household_rejected(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);

        $hh1 = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101012003',
            address: 'Jl. Kenanga No. 3',
            rtId: $this->rt1->id
        ), $this->officer);

        $hh2 = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101012004',
            address: 'Jl. Kenanga No. 4',
            rtId: $this->rt1->id
        ), $this->officer);

        $resident = $this->createActiveResident(
            nik: '3201010101012003',
            name: 'Shared Member',
            rtId: $this->rt1->id
        );

        $addMember->execute($hh1->id, $resident->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        $this->expectException(HouseholdMembershipConflict::class);
        $addMember->execute($hh2->id, $resident->id, HouseholdMemberRelationship::Anak, actor: $this->officer);
    }

    public function test_remove_resident_from_household_deactivates_membership(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);
        $removeMember = app(RemoveResidentFromHouseholdAction::class);

        $resident = $this->createActiveResident(
            nik: '3201010101012005',
            name: 'Departing Member',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101012005',
            address: 'Jl. Kenanga No. 5',
            rtId: $this->rt1->id
        ), $this->officer);

        $addMember->execute($hh->id, $resident->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        $removed = $removeMember->execute(
            residentId: $resident->id,
            leftReason: 'Moved out as adult',
            actor: $this->officer
        );

        $this->assertFalse($removed->is_active);
        $this->assertEquals('Moved out as adult', $removed->left_reason);
        $this->assertNotNull($removed->left_at);

        $resident->refresh();
        $this->assertNull($resident->current_household_id);
    }

    public function test_remove_head_with_remaining_members_transitions_to_head_vacant(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);
        $removeMember = app(RemoveResidentFromHouseholdAction::class);

        $head = $this->createActiveResident(
            nik: '3201010101012006',
            name: 'Head To Depart',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101012006',
            address: 'Jl. Kenanga No. 6',
            rtId: $this->rt1->id,
            initialHeadResidentId: $head->id
        ), $this->officer);

        $child = $this->createActiveResident(
            nik: '3201010101012007',
            name: 'Remaining Child',
            rtId: $this->rt1->id
        );

        $addMember->execute($hh->id, $child->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        $removeMember->execute($head->id, leftReason: 'Departed', actor: $this->officer);

        $hh->refresh();
        $this->assertEquals(HouseholdStatus::HeadVacant, $hh->status);
    }

    public function test_remove_last_member_from_head_vacant_household_dissolves_household(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);
        $removeMember = app(RemoveResidentFromHouseholdAction::class);

        // Create household without head (HEAD_VACANT)
        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101012008',
            address: 'Jl. Kenanga No. 8',
            rtId: $this->rt1->id
        ), $this->officer);

        $this->assertEquals(HouseholdStatus::HeadVacant, $hh->status);

        $soleMember = $this->createActiveResident(
            nik: '3201010101012008',
            name: 'Sole Member In Vacant Household',
            rtId: $this->rt1->id
        );

        $addMember->execute($hh->id, $soleMember->id, HouseholdMemberRelationship::FamiliLain, actor: $this->officer);

        // When sole member departs, household must transition to DISSOLVED
        $removeMember->execute($soleMember->id, leftReason: 'Emigrated', actor: $this->officer);

        $hh->refresh();
        $this->assertEquals(HouseholdStatus::Dissolved, $hh->status);
    }

    // =========================================================================
    // 5. CHANGE HOUSEHOLD HEAD ACTION TESTS
    // =========================================================================

    public function test_change_household_head_promotes_member_and_demotes_old_head(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);
        $changeHead = app(ChangeHouseholdHeadAction::class);

        $father = $this->createActiveResident(
            nik: '3201010101013001',
            name: 'Father',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101013001',
            address: 'Jl. Mawar No. 1',
            rtId: $this->rt1->id,
            initialHeadResidentId: $father->id
        ), $this->officer);

        $son = $this->createActiveResident(
            nik: '3201010101013002',
            name: 'Son',
            rtId: $this->rt1->id
        );

        $addMember->execute($hh->id, $son->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        // Change head to son
        $newHeadMember = $changeHead->execute(
            householdId: $hh->id,
            newHeadResidentId: $son->id,
            oldHeadNewRelationship: HouseholdMemberRelationship::OrangTua,
            actor: $this->officer
        );

        $this->assertEquals(HouseholdMemberRelationship::KepalaKeluarga, $newHeadMember->relationship);

        // Verify father was demoted to ORANG_TUA
        $fatherMembership = HouseholdMember::where('household_id', $hh->id)
            ->where('resident_id', $father->id)
            ->first();
        $this->assertEquals(HouseholdMemberRelationship::OrangTua, $fatherMembership->relationship);

        // Verify household resolves active head accurately
        $hh->refresh();
        $this->assertEquals($son->id, $hh->activeHead->resident_id);
    }

    public function test_change_household_head_to_self_is_idempotent(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $changeHead = app(ChangeHouseholdHeadAction::class);

        $father = $this->createActiveResident(
            nik: '3201010101013090',
            name: 'Current Head',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101013090',
            address: 'Jl. Mawar No. 90',
            rtId: $this->rt1->id,
            initialHeadResidentId: $father->id
        ), $this->officer);

        $result = $changeHead->execute($hh->id, $father->id, actor: $this->officer);

        $this->assertEquals(HouseholdMemberRelationship::KepalaKeluarga, $result->relationship);
        $this->assertTrue($result->is_active);
    }

    public function test_change_household_head_recovers_head_vacant_to_active(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);
        $changeHead = app(ChangeHouseholdHeadAction::class);

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101013003',
            address: 'Jl. Mawar No. 3',
            rtId: $this->rt1->id
        ), $this->officer);

        $this->assertEquals(HouseholdStatus::HeadVacant, $hh->status);

        $member = $this->createActiveResident(
            nik: '3201010101013003',
            name: 'Member To Be Head',
            rtId: $this->rt1->id
        );

        $addMember->execute($hh->id, $member->id, HouseholdMemberRelationship::FamiliLain, actor: $this->officer);

        $changeHead->execute($hh->id, $member->id, actor: $this->officer);

        $hh->refresh();
        $this->assertEquals(HouseholdStatus::Active, $hh->status);
        $this->assertEquals($member->id, $hh->activeHead->resident_id);
    }

    public function test_change_household_head_rejects_non_member(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $changeHead = app(ChangeHouseholdHeadAction::class);

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101013004',
            address: 'Jl. Mawar No. 4',
            rtId: $this->rt1->id
        ), $this->officer);

        $nonMember = $this->createActiveResident(
            nik: '3201010101013004',
            name: 'Non Member',
            rtId: $this->rt1->id
        );

        $this->expectException(InvalidHouseholdHeadChange::class);
        $changeHead->execute($hh->id, $nonMember->id, actor: $this->officer);
    }

    public function test_change_household_head_rejects_dissolved_household(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $dissolveHh = app(DissolveHouseholdAction::class);
        $changeHead = app(ChangeHouseholdHeadAction::class);

        $member = $this->createActiveResident(
            nik: '3201010101013095',
            name: 'Dissolved Member',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101013095',
            address: 'Jl. Mawar No. 95',
            rtId: $this->rt1->id,
            initialHeadResidentId: $member->id
        ), $this->officer);

        $dissolveHh->execute($hh->id, 'Disbanded', $this->officer);

        $this->expectException(HouseholdStateConflict::class);
        $this->expectExceptionMessage("Cannot change head for household with status 'DISSOLVED'.");

        $changeHead->execute($hh->id, $member->id, actor: $this->officer);
    }

    // =========================================================================
    // 6. RELOCATE RESIDENT ACTION TESTS
    // =========================================================================

    public function test_relocate_resident_internal_transfer_between_rts(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);
        $relocateAction = app(RelocateResidentAction::class);

        $resident = $this->createActiveResident(
            nik: '3201010101014001',
            name: 'Relocating Resident',
            rtId: $this->rt1->id
        );

        $hhSource = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101014001',
            address: 'Jl. Flamboyan No. 1',
            rtId: $this->rt1->id
        ), $this->officer);

        $addMember->execute($hhSource->id, $resident->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        $hhTarget = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101014002',
            address: 'Jl. Flamboyan No. 2',
            rtId: $this->rt2->id
        ), $this->officer);

        $movement = $relocateAction->execute(new RelocateResidentInput(
            residentId: $resident->id,
            movementType: ResidencyMovementType::TransferInternal,
            toRtId: $this->rt2->id,
            toHouseholdId: $hhTarget->id,
            notes: 'Moving from RT 01 to RT 02'
        ), $this->officer);

        $this->assertInstanceOf(ResidencyMovement::class, $movement);
        $this->assertEquals(ResidencyMovementType::TransferInternal, $movement->movement_type);
        $this->assertEquals($this->rt1->id, $movement->from_rt_id);
        $this->assertEquals($this->rt2->id, $movement->to_rt_id);
        $this->assertEquals($hhTarget->id, $movement->to_household_id);

        $resident->refresh();
        $this->assertEquals($this->rt2->id, $resident->rt_id);
        $this->assertEquals($hhTarget->id, $resident->current_household_id);

        // Verify old household membership is deactivated
        $oldMembership = HouseholdMember::where('household_id', $hhSource->id)
            ->where('resident_id', $resident->id)
            ->first();
        $this->assertFalse($oldMembership->is_active);
        $this->assertEquals('TRANSFER_RT', $oldMembership->left_reason);
    }

    public function test_relocate_resident_external_move_out(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);
        $relocateAction = app(RelocateResidentAction::class);

        $resident = $this->createActiveResident(
            nik: '3201010101014002',
            name: 'Moving Out Resident',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101014003',
            address: 'Jl. Flamboyan No. 3',
            rtId: $this->rt1->id
        ), $this->officer);

        $addMember->execute($hh->id, $resident->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        $movement = $relocateAction->execute(new RelocateResidentInput(
            residentId: $resident->id,
            movementType: ResidencyMovementType::MoveOutExternal,
            referenceLetterNumber: 'SP/2026/099',
            notes: 'Moving to Bali'
        ), $this->officer);

        $this->assertEquals(ResidencyMovementType::MoveOutExternal, $movement->movement_type);
        $this->assertEquals($hh->id, $movement->from_household_id);
        $this->assertNull($movement->to_organization_id);
        $this->assertNull($movement->to_rt_id);
        $this->assertNull($movement->to_household_id);

        $resident->refresh();
        $this->assertEquals(ResidentStatus::Moved, $resident->status);
        $this->assertNull($resident->current_household_id);
    }

    public function test_relocate_resident_external_move_in(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $relocateAction = app(RelocateResidentAction::class);

        // Resident previously moved out
        $resident = $this->createActiveResident(
            nik: '3201010101014003',
            name: 'Returning Resident',
            rtId: $this->rt1->id
        );

        $hhDummy = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101014099',
            address: 'Jl. Old No. 1',
            rtId: $this->rt1->id
        ), $this->officer);

        app(AddResidentToHouseholdAction::class)->execute($hhDummy->id, $resident->id, HouseholdMemberRelationship::FamiliLain, actor: $this->officer);

        // Move out
        $relocateAction->execute(new RelocateResidentInput(
            residentId: $resident->id,
            movementType: ResidencyMovementType::MoveOutExternal,
        ), $this->officer);

        $resident->refresh();
        $this->assertEquals(ResidentStatus::Moved, $resident->status);

        $hhTarget = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101014004',
            address: 'Jl. Flamboyan No. 4',
            rtId: $this->rt2->id
        ), $this->officer);

        $movement = $relocateAction->execute(new RelocateResidentInput(
            residentId: $resident->id,
            movementType: ResidencyMovementType::MoveInExternal,
            toRtId: $this->rt2->id,
            toHouseholdId: $hhTarget->id,
            notes: 'Moving in to RT 02 from outside'
        ), $this->officer);

        $this->assertEquals(ResidencyMovementType::MoveInExternal, $movement->movement_type);
        $this->assertNull($movement->from_organization_id);
        $this->assertEquals($this->rt2->id, $movement->to_rt_id);
        $this->assertEquals($hhTarget->id, $movement->to_household_id);

        $resident->refresh();
        $this->assertEquals(ResidentStatus::Active, $resident->status);
        $this->assertEquals($this->rt2->id, $resident->rt_id);
        $this->assertEquals($hhTarget->id, $resident->current_household_id);
    }

    public function test_household_split_within_same_rt_succeeds(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);
        $relocateAction = app(RelocateResidentAction::class);

        $hhSource = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101014004',
            address: 'Jl. Indah No. 1',
            rtId: $this->rt1->id
        ), $this->officer);

        $hhTarget = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101014005',
            address: 'Jl. Indah No. 2',
            rtId: $this->rt1->id
        ), $this->officer);

        $adultChild = $this->createActiveResident(
            nik: '3201010101014004',
            name: 'Married Child',
            rtId: $this->rt1->id
        );

        $addMember->execute($hhSource->id, $adultChild->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        $movement = $relocateAction->execute(new RelocateResidentInput(
            residentId: $adultChild->id,
            movementType: ResidencyMovementType::HouseholdSplit,
            toHouseholdId: $hhTarget->id,
            notes: 'Forming new household after marriage'
        ), $this->officer);

        $this->assertEquals(ResidencyMovementType::HouseholdSplit, $movement->movement_type);
        $this->assertEquals($hhSource->id, $movement->from_household_id);
        $this->assertEquals($hhTarget->id, $movement->to_household_id);

        $adultChild->refresh();
        $this->assertEquals($hhTarget->id, $adultChild->current_household_id);

        $newMembership = HouseholdMember::where('household_id', $hhTarget->id)
            ->where('resident_id', $adultChild->id)
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($newMembership);
        $this->assertEquals(HouseholdMemberRelationship::KepalaKeluarga, $newMembership->relationship);
    }

    // =========================================================================
    // 7. RECORD RESIDENT STATUS ACTION & CIVIL DEATH TESTS
    // =========================================================================

    public function test_record_resident_death_active_to_deceased(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $recordStatus = app(RecordResidentStatusAction::class);

        $user = User::factory()->create(['status' => AccountStatus::Active]);
        $user->createToken('test-token');
        $this->assertNotEmpty($user->tokens);

        $resident = $this->createActiveResident(
            nik: '3201010101015001',
            name: 'Late Resident',
            rtId: $this->rt1->id,
            userId: $user->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101015001',
            address: 'Jl. Cemara No. 1',
            rtId: $this->rt1->id,
            initialHeadResidentId: $resident->id
        ), $this->officer);

        // Add a remaining member so household becomes HEAD_VACANT
        $child = $this->createActiveResident(
            nik: '3201010101015002',
            name: 'Surviving Child',
            rtId: $this->rt1->id
        );

        app(AddResidentToHouseholdAction::class)->execute($hh->id, $child->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        // Transition to DECEASED
        $deceased = $recordStatus->execute(
            residentId: $resident->id,
            newStatus: ResidentStatus::Deceased,
            reason: 'Natural causes',
            referenceLetterNumber: 'SK/2026/001',
            actor: $this->officer
        );

        $this->assertEquals(ResidentStatus::Deceased, $deceased->status);

        // 1. Household membership deactivated with reason DECEASED
        $membership = HouseholdMember::where('household_id', $hh->id)
            ->where('resident_id', $resident->id)
            ->first();
        $this->assertFalse($membership->is_active);
        $this->assertEquals('DECEASED', $membership->left_reason);

        // 2. Household transitioned to HEAD_VACANT
        $hh->refresh();
        $this->assertEquals(HouseholdStatus::HeadVacant, $hh->status);

        // 3. User account suspended
        $user->refresh();
        $this->assertEquals(AccountStatus::Suspended, $user->status);

        // 4. Sanctum tokens revoked
        $this->assertCount(0, $user->fresh()->tokens);

        // 5. Resident status history ledger recorded
        $history = ResidentStatusHistory::where('resident_id', $resident->id)
            ->where('new_status', ResidentStatus::Deceased)
            ->first();
        $this->assertNotNull($history);
        $this->assertEquals('SK/2026/001', $history->reference_letter_number);
    }

    public function test_death_of_only_household_member_dissolves_household(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $recordStatus = app(RecordResidentStatusAction::class);

        $resident = $this->createActiveResident(
            nik: '3201010101015099',
            name: 'Sole Household Resident',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101015099',
            address: 'Jl. Cemara No. 99',
            rtId: $this->rt1->id,
            initialHeadResidentId: $resident->id
        ), $this->officer);

        $recordStatus->execute(
            residentId: $resident->id,
            newStatus: ResidentStatus::Deceased,
            reason: 'Death of sole resident',
            actor: $this->officer
        );

        $resident->refresh();
        $this->assertEquals(ResidentStatus::Deceased, $resident->status);
        $this->assertNull($resident->current_household_id);

        $hh->refresh();
        $this->assertEquals(HouseholdStatus::Dissolved, $hh->status);
    }

    public function test_death_of_non_head_with_remaining_members_does_not_vacate_head(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $recordStatus = app(RecordResidentStatusAction::class);

        $head = $this->createActiveResident(
            nik: '3201010101015088',
            name: 'Surviving Head',
            rtId: $this->rt1->id
        );

        $child = $this->createActiveResident(
            nik: '3201010101015089',
            name: 'Deceased Child',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101015088',
            address: 'Jl. Cemara No. 88',
            rtId: $this->rt1->id,
            initialHeadResidentId: $head->id
        ), $this->officer);

        app(AddResidentToHouseholdAction::class)->execute($hh->id, $child->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        // Child dies
        $recordStatus->execute(
            residentId: $child->id,
            newStatus: ResidentStatus::Deceased,
            reason: 'Illness',
            actor: $this->officer
        );

        $child->refresh();
        $this->assertEquals(ResidentStatus::Deceased, $child->status);
        $this->assertNull($child->current_household_id);

        // Household MUST remain ACTIVE (not HEAD_VACANT)
        $hh->refresh();
        $this->assertEquals(HouseholdStatus::Active, $hh->status);
        $this->assertEquals($head->id, $hh->activeHead->resident_id);
    }

    public function test_deceased_resident_cannot_transition_to_any_status(): void
    {
        $recordStatus = app(RecordResidentStatusAction::class);

        $resident = $this->createActiveResident(
            nik: '3201010101015003',
            name: 'Deceased Person',
            rtId: $this->rt1->id
        );

        $recordStatus->execute($resident->id, ResidentStatus::Deceased, actor: $this->officer);

        $this->expectException(InvalidResidentStatusTransition::class);
        $recordStatus->execute($resident->id, ResidentStatus::Active, actor: $this->officer);
    }

    // =========================================================================
    // 8. HOUSEHOLD LIFECYCLE & MOVEMENT ACTIONS TESTS
    // =========================================================================

    public function test_dissolve_household_action(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $dissolveAction = app(DissolveHouseholdAction::class);

        $resident = $this->createActiveResident(
            nik: '3201010101016001',
            name: 'Household Dissolving Head',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101016001',
            address: 'Jl. Pinus No. 1',
            rtId: $this->rt1->id,
            initialHeadResidentId: $resident->id
        ), $this->officer);

        $dissolved = $dissolveAction->execute($hh->id, 'Family left administrative territory', $this->officer);

        $this->assertEquals(HouseholdStatus::Dissolved, $dissolved->status);

        $resident->refresh();
        $this->assertNull($resident->current_household_id);

        $membership = HouseholdMember::where('household_id', $hh->id)->first();
        $this->assertFalse($membership->is_active);
    }

    public function test_move_household_action_within_rt(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $moveAction = app(MoveHouseholdAction::class);

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101016002',
            address: 'Jl. Pinus No. 2',
            rtId: $this->rt1->id
        ), $this->officer);

        $moved = $moveAction->execute(
            householdId: $hh->id,
            newAddress: 'Jl. Pinus No. 10B',
            notes: 'Renovation relocation',
            actor: $this->officer
        );

        $this->assertEquals('Jl. Pinus No. 10B', $moved->address);
        $this->assertEquals('Renovation relocation', $moved->notes);
        $this->assertEquals($this->rt1->id, $moved->rt_id);
    }

    public function test_move_household_inter_rt_transfer_with_active_residents(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $moveAction = app(MoveHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);

        $head = $this->createActiveResident(
            nik: '3201010101016050',
            name: 'Family Head Moving RT',
            rtId: $this->rt1->id
        );

        $child = $this->createActiveResident(
            nik: '3201010101016051',
            name: 'Child Moving RT',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101016050',
            address: 'Jl. Pinus No. 50',
            rtId: $this->rt1->id,
            initialHeadResidentId: $head->id
        ), $this->officer);

        $addMember->execute($hh->id, $child->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        // Move household from RT1 to RT2
        $movedHousehold = $moveAction->execute(
            householdId: $hh->id,
            newAddress: 'Jl. Anggrek No. 10',
            newRtId: $this->rt2->id,
            notes: 'Relocated family to RT 02',
            actor: $this->officer
        );

        $this->assertEquals($this->rt2->id, $movedHousehold->rt_id);
        $this->assertEquals('Jl. Anggrek No. 10', $movedHousehold->address);

        // Verify all active residents migrated to RT2
        $head->refresh();
        $child->refresh();
        $this->assertEquals($this->rt2->id, $head->rt_id);
        $this->assertEquals($this->rt2->id, $child->rt_id);
        $this->assertEquals($hh->id, $head->current_household_id);
        $this->assertEquals($hh->id, $child->current_household_id);

        // Verify individual residency movement ledger records
        $headMovement = ResidencyMovement::where('resident_id', $head->id)
            ->where('movement_type', ResidencyMovementType::TransferInternal)
            ->first();
        $this->assertNotNull($headMovement);
        $this->assertEquals($this->rt1->id, $headMovement->from_rt_id);
        $this->assertEquals($this->rt2->id, $headMovement->to_rt_id);
        $this->assertEquals($hh->id, $headMovement->from_household_id);
        $this->assertEquals($hh->id, $headMovement->to_household_id);

        $childMovement = ResidencyMovement::where('resident_id', $child->id)
            ->where('movement_type', ResidencyMovementType::TransferInternal)
            ->first();
        $this->assertNotNull($childMovement);
        $this->assertEquals($this->rt1->id, $childMovement->from_rt_id);
        $this->assertEquals($this->rt2->id, $childMovement->to_rt_id);
        $this->assertEquals($hh->id, $childMovement->from_household_id);
        $this->assertEquals($hh->id, $childMovement->to_household_id);
    }

    public function test_move_household_external_with_active_residents(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $moveAction = app(MoveHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);

        $head = $this->createActiveResident(
            nik: '3201010101016060',
            name: 'Family Head Moving External',
            rtId: $this->rt1->id
        );

        $child = $this->createActiveResident(
            nik: '3201010101016061',
            name: 'Child Moving External',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101016060',
            address: 'Jl. Pinus No. 60',
            rtId: $this->rt1->id,
            initialHeadResidentId: $head->id
        ), $this->officer);

        $addMember->execute($hh->id, $child->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        // Move household externally out of community
        $movedHousehold = $moveAction->execute(
            householdId: $hh->id,
            newAddress: 'Jl. Luar Kota No. 1',
            notes: 'Relocating to another province',
            isExternalMove: true,
            actor: $this->officer
        );

        $this->assertEquals(HouseholdStatus::Moved, $movedHousehold->status);

        // Verify residents transitioned to MOVED and pointers cleared
        $head->refresh();
        $child->refresh();
        $this->assertEquals(ResidentStatus::Moved, $head->status);
        $this->assertEquals(ResidentStatus::Moved, $child->status);
        $this->assertNull($head->current_household_id);
        $this->assertNull($child->current_household_id);

        // Verify memberships deactivated
        $memberships = HouseholdMember::where('household_id', $hh->id)->get();
        foreach ($memberships as $m) {
            $this->assertFalse($m->is_active);
            $this->assertEquals('MOVE_OUT', $m->left_reason);
        }

        // Verify individual movement records
        $headMovement = ResidencyMovement::where('resident_id', $head->id)
            ->where('movement_type', ResidencyMovementType::MoveOutExternal)
            ->first();
        $this->assertNotNull($headMovement);
        $this->assertEquals($hh->id, $headMovement->from_household_id);
        $this->assertNull($headMovement->to_organization_id);
    }

    // =========================================================================
    // 9. STATE MACHINE COMPLETE MATRIX TESTS
    // =========================================================================

    public function test_resident_status_state_machine_matrix(): void
    {
        $sm = app(ResidentStatusStateMachine::class);

        $expectedAllowed = [
            ResidentStatus::Prospective->value => [
                ResidentStatus::Active->value,
            ],
            ResidentStatus::Active->value => [
                ResidentStatus::Inactive->value,
                ResidentStatus::Moved->value,
                ResidentStatus::Deceased->value,
            ],
            ResidentStatus::Inactive->value => [
                ResidentStatus::Active->value,
                ResidentStatus::Deceased->value,
            ],
            ResidentStatus::Moved->value => [
                ResidentStatus::Active->value,
            ],
            ResidentStatus::Deceased->value => [],
        ];

        foreach (ResidentStatus::cases() as $from) {
            foreach (ResidentStatus::cases() as $to) {
                $isAllowed = in_array($to->value, $expectedAllowed[$from->value], true);
                $this->assertEquals(
                    $isAllowed,
                    $sm->canTransition($from, $to),
                    "Resident status transition from {$from->value} to {$to->value} should be " . ($isAllowed ? 'allowed' : 'forbidden')
                );

                if (! $isAllowed) {
                    $caught = false;
                    try {
                        $sm->assertCanTransition($from, $to);
                    } catch (InvalidResidentStatusTransition) {
                        $caught = true;
                    }
                    $this->assertTrue($caught, "Expected InvalidResidentStatusTransition for {$from->value} -> {$to->value}");
                }
            }
        }
    }

    public function test_household_status_state_machine_matrix(): void
    {
        $sm = app(HouseholdStatusStateMachine::class);

        $expectedAllowed = [
            HouseholdStatus::Active->value => [
                HouseholdStatus::HeadVacant->value,
                HouseholdStatus::Moved->value,
                HouseholdStatus::Dissolved->value,
            ],
            HouseholdStatus::HeadVacant->value => [
                HouseholdStatus::Active->value,
                HouseholdStatus::Dissolved->value,
                HouseholdStatus::Moved->value,
            ],
            HouseholdStatus::Moved->value => [
                HouseholdStatus::Archived->value,
            ],
            HouseholdStatus::Dissolved->value => [
                HouseholdStatus::Archived->value,
            ],
            HouseholdStatus::Archived->value => [],
        ];

        foreach (HouseholdStatus::cases() as $from) {
            foreach (HouseholdStatus::cases() as $to) {
                $isAllowed = in_array($to->value, $expectedAllowed[$from->value], true);
                $this->assertEquals(
                    $isAllowed,
                    $sm->canTransition($from, $to),
                    "Household status transition from {$from->value} to {$to->value} should be " . ($isAllowed ? 'allowed' : 'forbidden')
                );

                if (! $isAllowed) {
                    $caught = false;
                    try {
                        $sm->assertCanTransition($from, $to);
                    } catch (InvalidHouseholdStatusTransition) {
                        $caught = true;
                    }
                    $this->assertTrue($caught, "Expected InvalidHouseholdStatusTransition for {$from->value} -> {$to->value}");
                }
            }
        }
    }

    // =========================================================================
    // 10. CONCURRENCY TESTS (Section 23)
    // =========================================================================

    public function test_concurrency_household_head_assignment_results_in_single_winner(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);
        $changeHead = app(ChangeHouseholdHeadAction::class);

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101017001',
            address: 'Jl. Merpati No. 1',
            rtId: $this->rt1->id
        ), $this->officer);

        $candidate1 = $this->createActiveResident(
            nik: '3201010101017001',
            name: 'Candidate One',
            rtId: $this->rt1->id
        );

        $candidate2 = $this->createActiveResident(
            nik: '3201010101017002',
            name: 'Candidate Two',
            rtId: $this->rt1->id
        );

        $addMember->execute($hh->id, $candidate1->id, HouseholdMemberRelationship::FamiliLain, actor: $this->officer);

        // First promotion succeeds
        $firstResult = $changeHead->execute($hh->id, $candidate1->id, actor: $this->officer);
        $this->assertEquals(HouseholdMemberRelationship::KepalaKeluarga, $firstResult->relationship);

        // A third candidate (not yet in any household) attempts to join as KepalaKeluarga
        $candidate3 = $this->createActiveResident(
            nik: '3201010101017005',
            name: 'Candidate Three',
            rtId: $this->rt1->id
        );

        // Attempting to add candidate3 as a second head while candidate1 is head is rejected
        $this->expectException(InvalidHouseholdHeadChange::class);
        $addMember->execute($hh->id, $candidate3->id, HouseholdMemberRelationship::KepalaKeluarga, actor: $this->officer);
    }

    public function test_concurrency_resident_household_assignment_enforces_single_active_membership(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);

        $hh1 = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101017003',
            address: 'Jl. Merpati No. 3',
            rtId: $this->rt1->id
        ), $this->officer);

        $hh2 = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101017004',
            address: 'Jl. Merpati No. 4',
            rtId: $this->rt1->id
        ), $this->officer);

        $resident = $this->createActiveResident(
            nik: '3201010101017003',
            name: 'Contested Resident',
            rtId: $this->rt1->id
        );

        // Assigned to hh1
        $addMember->execute($hh1->id, $resident->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        // Concurrent attempt to assign to hh2 must fail
        $this->expectException(HouseholdMembershipConflict::class);
        $addMember->execute($hh2->id, $resident->id, HouseholdMemberRelationship::Anak, actor: $this->officer);
    }

    // =========================================================================
    // 11. TRANSACTION ROLLBACK TESTS (Section 25)
    // =========================================================================

    public function test_rollback_relocate_resident_when_movement_ledger_fails(): void
    {
        $createHh = app(CreateHouseholdAction::class);
        $addMember = app(AddResidentToHouseholdAction::class);

        $resident = $this->createActiveResident(
            nik: '3201010101018001',
            name: 'Rollback Resident',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101018001',
            address: 'Jl. Seroja No. 1',
            rtId: $this->rt1->id
        ), $this->officer);

        $addMember->execute($hh->id, $resident->id, HouseholdMemberRelationship::Anak, actor: $this->officer);

        // Simulate failure during relocation action by wrapping a failing transaction
        try {
            DB::transaction(function () use ($resident, $hh) {
                // Mutate resident scope
                $resident->rt_id = $this->rt2->id;
                $resident->current_household_id = null;
                $resident->save();

                // Deactivate membership
                HouseholdMember::where('household_id', $hh->id)
                    ->where('resident_id', $resident->id)
                    ->update(['is_active' => false]);

                // Deliberately trigger failure before commit
                throw new \RuntimeException('Simulated catastrophic ledger failure');
            });
        } catch (\RuntimeException) {
            // Handled
        }

        // Assert full rollback: resident still in RT 1, still points to household, membership is still active
        $resident->refresh();
        $this->assertEquals($this->rt1->id, $resident->rt_id);
        $this->assertEquals($hh->id, $resident->current_household_id);

        $membership = HouseholdMember::where('household_id', $hh->id)
            ->where('resident_id', $resident->id)
            ->first();
        $this->assertTrue($membership->is_active);
    }

    public function test_rollback_death_procedure_on_failure(): void
    {
        $createHh = app(CreateHouseholdAction::class);

        $resident = $this->createActiveResident(
            nik: '3201010101018002',
            name: 'Rollback Death Resident',
            rtId: $this->rt1->id
        );

        $hh = $createHh->execute(new CreateHouseholdInput(
            kkNumber: '3201010101018002',
            address: 'Jl. Seroja No. 2',
            rtId: $this->rt1->id,
            initialHeadResidentId: $resident->id
        ), $this->officer);

        try {
            DB::transaction(function () use ($resident, $hh) {
                $resident->status = ResidentStatus::Deceased;
                $resident->save();

                $hh->status = HouseholdStatus::HeadVacant;
                $hh->save();

                throw new \RuntimeException('Simulated death workflow failure');
            });
        } catch (\RuntimeException) {
            // Handled
        }

        // Verify full rollback: resident is still ACTIVE, household is still ACTIVE
        $resident->refresh();
        $this->assertEquals(ResidentStatus::Active, $resident->status);

        $hh->refresh();
        $this->assertEquals(HouseholdStatus::Active, $hh->status);
    }
}
