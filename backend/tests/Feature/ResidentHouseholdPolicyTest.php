<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Models\Resident;
use App\Policies\HouseholdPolicy;
use App\Policies\ResidentPolicy;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ResidentHouseholdPolicyTest extends TestCase
{
    private Organization $orgA;
    private Organization $orgB;

    private Rw $rwA1;
    private Rw $rwA2;
    private Rw $rwB1;

    private Rt $rtA1_1;
    private Rt $rtA1_2;
    private Rt $rtA2_1;
    private Rt $rtB1_1;

    private Resident $residentA1_1;
    private Household $householdA1_1;

    private User $ketuaRtA1_1;
    private User $sekretarisRtA1_1;
    private User $bendaharaRtA1_1;

    private User $ketuaRtA1_2;
    private User $ketuaRwA1;
    private User $ketuaRwA2;
    private User $orgAdminA;
    private User $ketuaRtB1_1;
    private User $regularUser;
    private User $linkedResidentUser;

    private ResidentPolicy $residentPolicy;
    private HouseholdPolicy $householdPolicy;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Hierarchy: Organization A
        $this->orgA = Organization::factory()->create(['name' => 'Org A']);
        $this->rwA1 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 1]);
        $this->rwA2 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 2]);

        $this->rtA1_1 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 1]);
        $this->rtA1_2 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 2]);
        $this->rtA2_1 = Rt::factory()->create(['rw_id' => $this->rwA2->id, 'number' => 1]);

        // 2. Hierarchy: Organization B
        $this->orgB = Organization::factory()->create(['name' => 'Org B']);
        $this->rwB1 = Rw::factory()->create(['organization_id' => $this->orgB->id, 'number' => 1]);
        $this->rtB1_1 = Rt::factory()->create(['rw_id' => $this->rwB1->id, 'number' => 1]);

        // 3. Resources in RT A1_1
        $this->linkedResidentUser = User::factory()->create();

        $this->residentA1_1 = Resident::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'nik' => '3201010101990001',
            'name' => 'Warga RT 01',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '1990-05-15',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Active,
            'user_id' => $this->linkedResidentUser->id,
        ]);

        $this->householdA1_1 = Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'kk_number' => '3201010101990001',
            'address' => 'Jl. Mawar No. 1',
            'status' => HouseholdStatus::Active,
        ]);

        // 4. Actor Roles
        $this->ketuaRtA1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $this->sekretarisRtA1_1 = $this->createScopedUser(RoleKey::SekretarisRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $this->bendaharaRtA1_1 = $this->createScopedUser(RoleKey::BendaharaRt, $this->orgA, $this->rwA1, $this->rtA1_1);

        $this->ketuaRtA1_2 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_2);
        $this->ketuaRwA1 = $this->createScopedUser(RoleKey::KetuaRw, $this->orgA, $this->rwA1);
        $this->ketuaRwA2 = $this->createScopedUser(RoleKey::KetuaRw, $this->orgA, $this->rwA2);
        $this->orgAdminA = $this->createScopedUser(RoleKey::OrganizationAdmin, $this->orgA);

        $this->ketuaRtB1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgB, $this->rwB1, $this->rtB1_1);
        $this->regularUser = User::factory()->create();

        // 5. Policy instances
        $this->residentPolicy = app(ResidentPolicy::class);
        $this->householdPolicy = app(HouseholdPolicy::class);
    }

    // =========================================================================
    // RESIDENT VIEW & PRIVACY BOUNDARIES
    // =========================================================================

    public function test_resident_view_scope_boundaries(): void
    {
        // Same RT officer: authorized
        $this->assertTrue($this->residentPolicy->view($this->ketuaRtA1_1, $this->residentA1_1));
        $this->assertTrue($this->residentPolicy->view($this->sekretarisRtA1_1, $this->residentA1_1));
        $this->assertTrue($this->residentPolicy->view($this->bendaharaRtA1_1, $this->residentA1_1));

        // Parent RW officer: authorized
        $this->assertTrue($this->residentPolicy->view($this->ketuaRwA1, $this->residentA1_1));

        // Organization admin: authorized
        $this->assertTrue($this->residentPolicy->view($this->orgAdminA, $this->residentA1_1));

        // Sibling RT in same RW: forbidden
        $this->assertFalse($this->residentPolicy->view($this->ketuaRtA1_2, $this->residentA1_1));

        // Sibling RW in same organization: forbidden
        $this->assertFalse($this->residentPolicy->view($this->ketuaRwA2, $this->residentA1_1));

        // Different organization: forbidden
        $this->assertFalse($this->residentPolicy->view($this->ketuaRtB1_1, $this->residentA1_1));

        // Regular user: forbidden
        $this->assertFalse($this->residentPolicy->view($this->regularUser, $this->residentA1_1));
    }

    public function test_resident_sensitive_view_distinction(): void
    {
        // RT Ketua and Sekretaris have viewSensitive
        $this->assertTrue($this->residentPolicy->viewSensitive($this->ketuaRtA1_1, $this->residentA1_1));
        $this->assertTrue($this->residentPolicy->viewSensitive($this->sekretarisRtA1_1, $this->residentA1_1));

        // Parent RW Ketua has viewSensitive
        $this->assertTrue($this->residentPolicy->viewSensitive($this->ketuaRwA1, $this->residentA1_1));

        // CRITICAL PRIVACY: Bendahara has resident.view for dues, but NEVER resident.view_sensitive
        $this->assertTrue($this->residentPolicy->view($this->bendaharaRtA1_1, $this->residentA1_1));
        $this->assertFalse($this->residentPolicy->viewSensitive($this->bendaharaRtA1_1, $this->residentA1_1));

        // Sibling RT cannot view sensitive
        $this->assertFalse($this->residentPolicy->viewSensitive($this->ketuaRtA1_2, $this->residentA1_1));

        // Regular user cannot view sensitive
        $this->assertFalse($this->residentPolicy->viewSensitive($this->regularUser, $this->residentA1_1));
    }

    public function test_resident_directory_view_scope_enforcement(): void
    {
        $rtScope = $this->residentA1_1->scopeRef();

        // Same RT officer can view directory
        $this->assertTrue($this->residentPolicy->viewDirectory($this->ketuaRtA1_1, $rtScope));

        // Parent RW officer can view directory of child RT
        $this->assertTrue($this->residentPolicy->viewDirectory($this->ketuaRwA1, $rtScope));

        // Sibling RT officer cannot view directory of RT A1_1
        $this->assertFalse($this->residentPolicy->viewDirectory($this->ketuaRtA1_2, $rtScope));

        // Sibling RW officer cannot view directory of RT A1_1
        $this->assertFalse($this->residentPolicy->viewDirectory($this->ketuaRwA2, $rtScope));

        // Null target safely returns false
        $this->assertFalse($this->residentPolicy->viewDirectory($this->ketuaRtA1_1, null));
    }

    // =========================================================================
    // RESIDENT ADMINISTRATIVE ACTIONS
    // =========================================================================

    public function test_resident_register_scope_enforcement(): void
    {
        $targetScope = ScopeRef::rt($this->orgA->id, $this->rwA1->id, $this->rtA1_1->id);

        // Same RT officer can register
        $this->assertTrue($this->residentPolicy->register($this->ketuaRtA1_1, $targetScope));
        $this->assertTrue($this->residentPolicy->register($this->sekretarisRtA1_1, $targetScope));

        // Parent RW officer can register in child RT
        $this->assertTrue($this->residentPolicy->register($this->ketuaRwA1, $targetScope));

        // Sibling RT officer cannot register into RT A1_1
        $this->assertFalse($this->residentPolicy->register($this->ketuaRtA1_2, $targetScope));

        // Bendahara does NOT have register permission
        $this->assertFalse($this->residentPolicy->register($this->bendaharaRtA1_1, $targetScope));

        // Regular user cannot register
        $this->assertFalse($this->residentPolicy->register($this->regularUser, $targetScope));

        // Null target safely returns false
        $this->assertFalse($this->residentPolicy->register($this->ketuaRtA1_1, null));
    }

    public function test_resident_verify_scope_enforcement(): void
    {
        // Same RT officer can verify
        $this->assertTrue($this->residentPolicy->verify($this->ketuaRtA1_1, $this->residentA1_1));

        // Parent RW officer can verify
        $this->assertTrue($this->residentPolicy->verify($this->ketuaRwA1, $this->residentA1_1));

        // Sibling RT officer cannot verify
        $this->assertFalse($this->residentPolicy->verify($this->ketuaRtA1_2, $this->residentA1_1));

        // Bendahara cannot verify
        $this->assertFalse($this->residentPolicy->verify($this->bendaharaRtA1_1, $this->residentA1_1));

        // Different org cannot verify
        $this->assertFalse($this->residentPolicy->verify($this->ketuaRtB1_1, $this->residentA1_1));
    }

    public function test_resident_update_scope_enforcement(): void
    {
        // Same RT officer can update
        $this->assertTrue($this->residentPolicy->update($this->ketuaRtA1_1, $this->residentA1_1));

        // Parent RW officer can update
        $this->assertTrue($this->residentPolicy->update($this->ketuaRwA1, $this->residentA1_1));

        // Sibling RT officer cannot update
        $this->assertFalse($this->residentPolicy->update($this->ketuaRtA1_2, $this->residentA1_1));

        // Bendahara cannot update
        $this->assertFalse($this->residentPolicy->update($this->bendaharaRtA1_1, $this->residentA1_1));
    }

    public function test_resident_remove_from_household_and_change_status(): void
    {
        // removeFromHousehold
        $this->assertTrue($this->residentPolicy->removeFromHousehold($this->ketuaRtA1_1, $this->residentA1_1));
        $this->assertFalse($this->residentPolicy->removeFromHousehold($this->ketuaRtA1_2, $this->residentA1_1));
        $this->assertFalse($this->residentPolicy->removeFromHousehold($this->bendaharaRtA1_1, $this->residentA1_1));

        // changeStatus
        $this->assertTrue($this->residentPolicy->changeStatus($this->ketuaRtA1_1, $this->residentA1_1));
        $this->assertFalse($this->residentPolicy->changeStatus($this->ketuaRtA1_2, $this->residentA1_1));
        $this->assertFalse($this->residentPolicy->changeStatus($this->bendaharaRtA1_1, $this->residentA1_1));
    }

    public function test_resident_relocation_dual_scope_enforcement(): void
    {
        $siblingRtScope = ScopeRef::rt($this->orgA->id, $this->rwA1->id, $this->rtA1_2->id);
        $siblingRwRtScope = ScopeRef::rt($this->orgA->id, $this->rwA2->id, $this->rtA2_1->id);

        // 1. RT Officer attempting relocation:
        // Relocating within same RT (or no target specified): allowed
        $this->assertTrue($this->residentPolicy->relocate($this->ketuaRtA1_1, $this->residentA1_1));
        $this->assertTrue($this->residentPolicy->relocate($this->ketuaRtA1_1, $this->residentA1_1, $this->residentA1_1->scopeRef()));

        // Relocating from RT A1_1 to sibling RT A1_2:
        // RT A1_1 officer has authority on source, but NO authority on target RT A1_2 -> MUST BE DENIED!
        $this->assertFalse($this->residentPolicy->relocate($this->ketuaRtA1_1, $this->residentA1_1, $siblingRtScope));

        // 2. RW Officer attempting relocation:
        // Relocating between two child RTs (RT A1_1 -> RT A1_2):
        // RW officer covers both child RTs -> ALLOWED!
        $this->assertTrue($this->residentPolicy->relocate($this->ketuaRwA1, $this->residentA1_1, $siblingRtScope));

        // Relocating to a different RW (RT A1_1 -> RT A2_1):
        // RW A1 officer does NOT cover RW A2 / RT A2_1 -> MUST BE DENIED!
        $this->assertFalse($this->residentPolicy->relocate($this->ketuaRwA1, $this->residentA1_1, $siblingRwRtScope));
    }

    // =========================================================================
    // HOUSEHOLD ACTIONS & SCOPE BOUNDARIES
    // =========================================================================

    public function test_household_view_scope_boundaries(): void
    {
        // Same RT officer can view
        $this->assertTrue($this->householdPolicy->view($this->ketuaRtA1_1, $this->householdA1_1));
        $this->assertTrue($this->householdPolicy->view($this->bendaharaRtA1_1, $this->householdA1_1));

        // Parent RW officer can view
        $this->assertTrue($this->householdPolicy->view($this->ketuaRwA1, $this->householdA1_1));

        // Sibling RT officer cannot view
        $this->assertFalse($this->householdPolicy->view($this->ketuaRtA1_2, $this->householdA1_1));

        // Sibling RW officer cannot view
        $this->assertFalse($this->householdPolicy->view($this->ketuaRwA2, $this->householdA1_1));

        // Different org officer cannot view
        $this->assertFalse($this->householdPolicy->view($this->ketuaRtB1_1, $this->householdA1_1));

        // Regular user cannot view
        $this->assertFalse($this->householdPolicy->view($this->regularUser, $this->householdA1_1));
    }

    public function test_household_sensitive_view_distinction(): void
    {
        // RT Ketua and Sekretaris have viewSensitive (can see full unmasked KK number)
        $this->assertTrue($this->householdPolicy->viewSensitive($this->ketuaRtA1_1, $this->householdA1_1));
        $this->assertTrue($this->householdPolicy->viewSensitive($this->sekretarisRtA1_1, $this->householdA1_1));

        // Parent RW Ketua has viewSensitive
        $this->assertTrue($this->householdPolicy->viewSensitive($this->ketuaRwA1, $this->householdA1_1));

        // OrgAdmin has viewSensitive
        $this->assertTrue($this->householdPolicy->viewSensitive($this->orgAdminA, $this->householdA1_1));

        // CRITICAL PRIVACY: Bendahara has household.view for dues/management, but NEVER household.view_sensitive
        $this->assertTrue($this->householdPolicy->view($this->bendaharaRtA1_1, $this->householdA1_1));
        $this->assertFalse($this->householdPolicy->viewSensitive($this->bendaharaRtA1_1, $this->householdA1_1));

        // Sibling RT cannot view sensitive
        $this->assertFalse($this->householdPolicy->viewSensitive($this->ketuaRtA1_2, $this->householdA1_1));

        // Sibling RW cannot view sensitive
        $this->assertFalse($this->householdPolicy->viewSensitive($this->ketuaRwA2, $this->householdA1_1));

        // Different org cannot view sensitive
        $this->assertFalse($this->householdPolicy->viewSensitive($this->ketuaRtB1_1, $this->householdA1_1));

        // Regular user cannot view sensitive
        $this->assertFalse($this->householdPolicy->viewSensitive($this->regularUser, $this->householdA1_1));
    }

    public function test_household_create_scope_enforcement(): void
    {
        $targetRtScope = ScopeRef::rt($this->orgA->id, $this->rwA1->id, $this->rtA1_1->id);

        // Same RT officer can create household
        $this->assertTrue($this->householdPolicy->create($this->ketuaRtA1_1, $targetRtScope));

        // Parent RW officer can create household in child RT
        $this->assertTrue($this->householdPolicy->create($this->ketuaRwA1, $targetRtScope));

        // Sibling RT officer cannot create household in RT A1_1
        $this->assertFalse($this->householdPolicy->create($this->ketuaRtA1_2, $targetRtScope));

        // Bendahara cannot create household
        $this->assertFalse($this->householdPolicy->create($this->bendaharaRtA1_1, $targetRtScope));

        // Regular user cannot create household
        $this->assertFalse($this->householdPolicy->create($this->regularUser, $targetRtScope));

        // Null target safely returns false
        $this->assertFalse($this->householdPolicy->create($this->ketuaRtA1_1, null));
    }

    public function test_household_update_change_head_and_dissolve(): void
    {
        // update
        $this->assertTrue($this->householdPolicy->update($this->ketuaRtA1_1, $this->householdA1_1));
        $this->assertFalse($this->householdPolicy->update($this->ketuaRtA1_2, $this->householdA1_1));
        $this->assertFalse($this->householdPolicy->update($this->bendaharaRtA1_1, $this->householdA1_1));

        // changeHead
        $this->assertTrue($this->householdPolicy->changeHead($this->ketuaRtA1_1, $this->householdA1_1));
        $this->assertTrue($this->householdPolicy->changeHead($this->ketuaRwA1, $this->householdA1_1));
        $this->assertFalse($this->householdPolicy->changeHead($this->ketuaRtA1_2, $this->householdA1_1));
        $this->assertFalse($this->householdPolicy->changeHead($this->bendaharaRtA1_1, $this->householdA1_1));

        // dissolve
        $this->assertTrue($this->householdPolicy->dissolve($this->ketuaRtA1_1, $this->householdA1_1));
        $this->assertTrue($this->householdPolicy->dissolve($this->ketuaRwA1, $this->householdA1_1));
        $this->assertFalse($this->householdPolicy->dissolve($this->ketuaRtA1_2, $this->householdA1_1));
        $this->assertFalse($this->householdPolicy->dissolve($this->bendaharaRtA1_1, $this->householdA1_1));
    }

    public function test_household_member_addition_and_removal_scope_enforcement(): void
    {
        // Resident in same RT
        $this->assertTrue($this->householdPolicy->addMember($this->ketuaRtA1_1, $this->householdA1_1, $this->residentA1_1));
        $this->assertTrue($this->householdPolicy->removeMember($this->ketuaRtA1_1, $this->householdA1_1, $this->residentA1_1));

        // Resident in sibling RT A1_2
        $residentInSiblingRt = Resident::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_2->id,
            'nik' => '3201010101990002',
            'name' => 'Warga RT 02',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Bandung',
            'birth_date' => '1995-03-20',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Active,
        ]);

        // RT A1_1 officer attempting to add resident from sibling RT:
        // Actor does NOT hold authority over sibling RT resident -> MUST BE DENIED!
        $this->assertFalse($this->householdPolicy->addMember($this->ketuaRtA1_1, $this->householdA1_1, $residentInSiblingRt));
        $this->assertFalse($this->householdPolicy->removeMember($this->ketuaRtA1_1, $this->householdA1_1, $residentInSiblingRt));

        // RW Officer covers both RTs -> ALLOWED!
        $this->assertTrue($this->householdPolicy->addMember($this->ketuaRwA1, $this->householdA1_1, $residentInSiblingRt));
        $this->assertTrue($this->householdPolicy->removeMember($this->ketuaRwA1, $this->householdA1_1, $residentInSiblingRt));
    }

    public function test_household_move_dual_scope_enforcement(): void
    {
        $siblingRtScope = ScopeRef::rt($this->orgA->id, $this->rwA1->id, $this->rtA1_2->id);
        $siblingRwRtScope = ScopeRef::rt($this->orgA->id, $this->rwA2->id, $this->rtA2_1->id);

        // Moving without target scope or within same RT: allowed for RT officer
        $this->assertTrue($this->householdPolicy->move($this->ketuaRtA1_1, $this->householdA1_1));
        $this->assertTrue($this->householdPolicy->move($this->ketuaRtA1_1, $this->householdA1_1, $this->householdA1_1->scopeRef()));

        // RT officer moving household to sibling RT:
        // Actor lacks authority on target RT -> MUST BE DENIED!
        $this->assertFalse($this->householdPolicy->move($this->ketuaRtA1_1, $this->householdA1_1, $siblingRtScope));

        // RW officer moving household between child RTs:
        // RW officer covers both child RTs -> ALLOWED!
        $this->assertTrue($this->householdPolicy->move($this->ketuaRwA1, $this->householdA1_1, $siblingRtScope));

        // RW officer moving household across RWs:
        // RW officer lacks authority on target RW -> MUST BE DENIED!
        $this->assertFalse($this->householdPolicy->move($this->ketuaRwA1, $this->householdA1_1, $siblingRwRtScope));
    }

    // =========================================================================
    // RESIDENT LINKAGE / OWNERSHIP VS SCOPE
    // =========================================================================

    public function test_resident_linked_user_does_not_gain_administrative_privileges(): void
    {
        // The linked user is NOT granted administrative capabilities simply by being linked to resident
        $this->assertFalse(Gate::forUser($this->linkedResidentUser)->allows('verify', $this->residentA1_1));
        $this->assertFalse(Gate::forUser($this->linkedResidentUser)->allows('update', $this->residentA1_1));
        $this->assertFalse(Gate::forUser($this->linkedResidentUser)->allows('relocate', $this->residentA1_1));
        $this->assertFalse(Gate::forUser($this->linkedResidentUser)->allows('changeStatus', $this->residentA1_1));
        $this->assertFalse(Gate::forUser($this->linkedResidentUser)->allows('changeHead', $this->householdA1_1));
        $this->assertFalse(Gate::forUser($this->linkedResidentUser)->allows('dissolve', $this->householdA1_1));
    }

    // =========================================================================
    // GATE FACADE INTEGRATION
    // =========================================================================

    public function test_laravel_gate_facade_resolves_policies_correctly(): void
    {
        // Gate::allows on Resident
        $this->assertTrue(Gate::forUser($this->ketuaRtA1_1)->allows('view', $this->residentA1_1));
        $this->assertTrue(Gate::forUser($this->ketuaRtA1_1)->allows('viewSensitive', $this->residentA1_1));
        $this->assertFalse(Gate::forUser($this->ketuaRtA1_2)->allows('view', $this->residentA1_1));

        // Gate::allows on Household
        $this->assertTrue(Gate::forUser($this->ketuaRtA1_1)->allows('view', $this->householdA1_1));
        $this->assertTrue(Gate::forUser($this->ketuaRtA1_1)->allows('viewSensitive', $this->householdA1_1));
        $this->assertFalse(Gate::forUser($this->bendaharaRtA1_1)->allows('viewSensitive', $this->householdA1_1));
        $this->assertFalse(Gate::forUser($this->ketuaRtA1_2)->allows('view', $this->householdA1_1));
    }
}
