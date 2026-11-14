<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Authorization\Models\Permission;
use App\Domain\Authorization\Models\Role;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Enums\ResidencyMovementType;
use App\Domain\Resident\Enums\ResidencyType;
use App\Domain\Resident\Enums\ResidentBloodType;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\ResidencyMovement;
use App\Domain\Resident\Models\Resident;
use App\Domain\Resident\Models\ResidentStatusHistory;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResidentHouseholdReadApiTest extends TestCase
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

    private Resident $resident1;
    private Resident $resident2;
    private Resident $residentRt2;
    private Resident $residentOrgB;

    private Household $household1;
    private Household $householdVacant;
    private Household $householdRt2;
    private Household $householdOrgB;

    private User $orgAdminA;
    private User $ketuaRwA1;
    private User $sekretarisRwA1;
    private User $bendaharaRwA1;
    private User $ketuaRtA1_1;
    private User $sekretarisRtA1_1;
    private User $bendaharaRtA1_1;
    private User $ketuaRtA1_2;
    private User $ketuaRtB1_1;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Organizations and Units
        $this->orgA = Organization::factory()->create(['name' => 'Organisasi A']);
        $this->orgB = Organization::factory()->create(['name' => 'Organisasi B']);

        $this->rwA1 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 1]);
        $this->rwA2 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 2]);
        $this->rwB1 = Rw::factory()->create(['organization_id' => $this->orgB->id, 'number' => 1]);

        $this->rtA1_1 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 1]);
        $this->rtA1_2 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 2]);
        $this->rtA2_1 = Rt::factory()->create(['rw_id' => $this->rwA2->id, 'number' => 1]);
        $this->rtB1_1 = Rt::factory()->create(['rw_id' => $this->rwB1->id, 'number' => 1]);

        // 2. Setup Households
        $this->household1 = Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'kk_number' => '3201011205900001',
            'address' => 'Jl. Kenanga No. 10',
            'status' => HouseholdStatus::Active,
            'notes' => 'Keluarga Bapak Budi',
        ]);

        $this->householdVacant = Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'kk_number' => '3201011205900002',
            'address' => 'Jl. Melati No. 20',
            'status' => HouseholdStatus::HeadVacant,
            'notes' => 'Kepala keluarga wafat',
        ]);

        $this->householdRt2 = Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_2->id,
            'kk_number' => '3201011205900003',
            'address' => 'Jl. Mawar No. 30',
            'status' => HouseholdStatus::Active,
        ]);

        $this->householdOrgB = Household::query()->create([
            'organization_id' => $this->orgB->id,
            'rw_id' => $this->rwB1->id,
            'rt_id' => $this->rtB1_1->id,
            'kk_number' => '3202011205900001',
            'address' => 'Jl. Sudirman No. 99',
            'status' => HouseholdStatus::Active,
        ]);

        // 3. Setup Residents
        $this->resident1 = Resident::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'current_household_id' => $this->household1->id,
            'nik' => '3201011205900001',
            'name' => 'Budi Santoso',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Bogor',
            'birth_date' => '1990-05-12',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Married,
            'blood_type' => ResidentBloodType::O,
            'occupation' => 'PNS',
            'phone' => '+6281234567890',
            'residency_type' => ResidencyType::Permanent,
            'status' => ResidentStatus::Active,
        ]);

        $this->resident2 = Resident::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'current_household_id' => $this->household1->id,
            'nik' => '3201011205900002',
            'name' => 'Siti Rahma',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Bandung',
            'birth_date' => '1992-08-20',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Married,
            'blood_type' => ResidentBloodType::A,
            'occupation' => 'Wiraswasta',
            'phone' => '+6281298765432',
            'residency_type' => ResidencyType::Permanent,
            'status' => ResidentStatus::Active,
        ]);

        $this->residentRt2 = Resident::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_2->id,
            'current_household_id' => $this->householdRt2->id,
            'nik' => '3201011205900003',
            'name' => 'Agus Pratama',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '1985-01-10',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'phone' => '+6281311122233',
            'residency_type' => ResidencyType::Permanent,
            'status' => ResidentStatus::Active,
        ]);

        $this->residentOrgB = Resident::query()->create([
            'organization_id' => $this->orgB->id,
            'rw_id' => $this->rwB1->id,
            'rt_id' => $this->rtB1_1->id,
            'current_household_id' => $this->householdOrgB->id,
            'nik' => '3202011205900001',
            'name' => 'Warga Org B',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Surabaya',
            'birth_date' => '1995-03-15',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'phone' => '+6281599887766',
            'residency_type' => ResidencyType::Permanent,
            'status' => ResidentStatus::Active,
        ]);

        // 4. Setup Household Members (Head & Member)
        HouseholdMember::query()->create([
            'household_id' => $this->household1->id,
            'resident_id' => $this->resident1->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
            'is_active' => true,
            'joined_at' => '2020-01-01',
        ]);

        HouseholdMember::query()->create([
            'household_id' => $this->household1->id,
            'resident_id' => $this->resident2->id,
            'relationship' => HouseholdMemberRelationship::Istri,
            'is_active' => true,
            'joined_at' => '2020-01-01',
        ]);

        // 5. Setup Actors
        $this->orgAdminA = $this->createScopedUser(RoleKey::OrganizationAdmin, $this->orgA);
        $this->ketuaRwA1 = $this->createScopedUser(RoleKey::KetuaRw, $this->orgA, $this->rwA1);
        $this->sekretarisRwA1 = $this->createScopedUser(RoleKey::SekretarisRw, $this->orgA, $this->rwA1);
        $this->bendaharaRwA1 = $this->createScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);

        $this->ketuaRtA1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $this->sekretarisRtA1_1 = $this->createScopedUser(RoleKey::SekretarisRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $this->bendaharaRtA1_1 = $this->createScopedUser(RoleKey::BendaharaRt, $this->orgA, $this->rwA1, $this->rtA1_1);

        $this->ketuaRtA1_2 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_2);
        $this->ketuaRtB1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgB, $this->rwB1, $this->rtB1_1);
    }

    // =========================================================================
    // 1. PERMISSION SEED VERIFICATION (Section 34)
    // =========================================================================

    public function test_household_view_sensitive_is_properly_seeded_across_roles(): void
    {
        $perm = Permission::query()->where('key', PermissionKey::HouseholdViewSensitive->value)->first();
        $this->assertNotNull($perm, 'Permission household.view_sensitive must exist in DB.');

        $rolesWithSensitive = [
            RoleKey::OrganizationAdmin,
            RoleKey::KetuaRw,
            RoleKey::SekretarisRw,
            RoleKey::KetuaRt,
            RoleKey::SekretarisRt,
        ];

        foreach ($rolesWithSensitive as $roleKey) {
            /** @var Role $role */
            $role = Role::query()->where('key', $roleKey->value)->firstOrFail();
            $this->assertTrue(
                $role->permissions()->where('key', PermissionKey::HouseholdViewSensitive->value)->exists(),
                "Role {$roleKey->value} must have household.view_sensitive permission."
            );
        }

        $rolesWithoutSensitive = [
            RoleKey::BendaharaRw,
            RoleKey::BendaharaRt,
        ];

        foreach ($rolesWithoutSensitive as $roleKey) {
            /** @var Role $role */
            $role = Role::query()->where('key', $roleKey->value)->firstOrFail();
            $this->assertFalse(
                $role->permissions()->where('key', PermissionKey::HouseholdViewSensitive->value)->exists(),
                "Role {$roleKey->value} must NOT have household.view_sensitive permission."
            );
        }
    }

    // =========================================================================
    // 2. RESIDENTS READ API (Section 6, 7, 8, 9, 10, 11, 12)
    // =========================================================================

    public function test_resident_list_scoped_to_authorized_rt(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->getJson('/api/v1/residents');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'status', 'representation', 'rt_id', 'rw_id', 'organization_id'],
                ],
                'meta' => [
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page', 'has_more'],
                    'request_id',
                ],
            ]);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($this->resident1->id, $ids);
        $this->assertContains($this->resident2->id, $ids);
        $this->assertNotContains($this->residentRt2->id, $ids, 'RT 1 actor must not see RT 2 resident.');
        $this->assertNotContains($this->residentOrgB->id, $ids, 'Actor must not see Org B resident.');
    }

    public function test_resident_list_scoped_to_authorized_rw(): void
    {
        $this->actingAs($this->ketuaRwA1);

        $response = $this->getJson('/api/v1/residents');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');

        // Ketua RW 1 sees both RT 1 and RT 2 residents
        $this->assertContains($this->resident1->id, $ids);
        $this->assertContains($this->resident2->id, $ids);
        $this->assertContains($this->residentRt2->id, $ids);
        $this->assertNotContains($this->residentOrgB->id, $ids);
    }

    public function test_resident_detail_same_rt_allowed_and_different_rt_denied(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        // Same RT -> ALLOW
        $allowed = $this->getJson("/api/v1/residents/{$this->resident1->id}");
        $allowed->assertStatus(200)->assertJsonPath('data.id', $this->resident1->id);

        // Different RT in same RW -> DENIED (403)
        $deniedRt = $this->getJson("/api/v1/residents/{$this->residentRt2->id}");
        $deniedRt->assertStatus(403)->assertJsonPath('error.code', 'forbidden');

        // Different Org -> DENIED (403)
        $deniedOrg = $this->getJson("/api/v1/residents/{$this->residentOrgB->id}");
        $deniedOrg->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
    }

    public function test_resident_detail_rw_actor_can_access_nested_rts_but_denied_other_rw(): void
    {
        $this->actingAs($this->ketuaRwA1);

        // Resident in RT 1 -> ALLOW
        $res1 = $this->getJson("/api/v1/residents/{$this->resident1->id}");
        $res1->assertStatus(200);

        // Resident in RT 2 -> ALLOW
        $res2 = $this->getJson("/api/v1/residents/{$this->residentRt2->id}");
        $res2->assertStatus(200);

        // Resident in Org B -> DENIED (403)
        $resB = $this->getJson("/api/v1/residents/{$this->residentOrgB->id}");
        $resB->assertStatus(403);
    }

    public function test_resident_privacy_tiers_enforcement(): void
    {
        // 1. Sensitive Actor (Ketua RT has resident.view and resident.view_sensitive)
        $this->actingAs($this->ketuaRtA1_1);
        $sensitiveRes = $this->getJson("/api/v1/residents/{$this->resident1->id}");
        $sensitiveRes->assertStatus(200)
            ->assertJsonPath('data.representation', 'sensitive')
            ->assertJsonPath('data.nik', '3201011205900001')
            ->assertJsonPath('data.nik_masked', false)
            ->assertJsonPath('data.phone', '+6281234567890')
            ->assertJsonPath('data.phone_masked', false)
            ->assertJsonPath('data.religion', 'ISLAM')
            ->assertJsonPath('data.blood_type', 'O');

        // 2. Normal Actor (Bendahara RT has resident.view but NOT resident.view_sensitive)
        $this->actingAs($this->bendaharaRtA1_1);
        $normalRes = $this->getJson("/api/v1/residents/{$this->resident1->id}");
        $normalRes->assertStatus(200)
            ->assertJsonPath('data.representation', 'normal')
            ->assertJsonPath('data.nik_masked', true)
            ->assertJsonPath('data.phone_masked', true);
        $this->assertNotEquals('3201011205900001', $normalRes->json('data.nik'));
        $this->assertNotEquals('+6281234567890', $normalRes->json('data.phone'));
        $this->assertArrayNotHasKey('religion', $normalRes->json('data'));
        $this->assertArrayNotHasKey('blood_type', $normalRes->json('data'));
    }

    public function test_resident_filters_search_and_sorting(): void
    {
        $this->actingAs($this->ketuaRwA1);

        // Filter by gender
        $femaleRes = $this->getJson('/api/v1/residents?gender=FEMALE');
        $femaleRes->assertStatus(200);
        $names = collect($femaleRes->json('data'))->pluck('name');
        $this->assertContains('Siti Rahma', $names);
        $this->assertNotContains('Budi Santoso', $names);

        // Filter by status
        $statusRes = $this->getJson('/api/v1/residents?status=ACTIVE');
        $statusRes->assertStatus(200);
        $this->assertCount(3, $statusRes->json('data'));

        // Search (?q=)
        $searchRes = $this->getJson('/api/v1/residents?q=Budi');
        $searchRes->assertStatus(200);
        $searchNames = collect($searchRes->json('data'))->pluck('name');
        $this->assertContains('Budi Santoso', $searchNames);
        $this->assertNotContains('Siti Rahma', $searchNames);

        // Search too short (fails validation with 422)
        $shortSearch = $this->getJson('/api/v1/residents?q=ab');
        $shortSearch->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

        // Sort ascending by name
        $sortAsc = $this->getJson('/api/v1/residents?sort=name');
        $sortAsc->assertStatus(200);
        $first = $sortAsc->json('data.0.name');
        $this->assertEquals('Agus Pratama', $first);

        // Sort descending by created_at
        $sortDesc = $this->getJson('/api/v1/residents?sort=-created_at');
        $sortDesc->assertStatus(200);
    }

    public function test_resident_includes_eager_loading(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        // Allowed includes: organization, rw, rt, household, memberships
        $res = $this->getJson("/api/v1/residents/{$this->resident1->id}?include=organization,rw,rt,household,memberships");
        $res->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'organization' => ['id', 'name'],
                    'rw' => ['id', 'number'],
                    'rt' => ['id', 'number'],
                    'current_household' => ['id', 'representation', 'active_members_count'],
                    'household_memberships' => [
                        '*' => ['id', 'relationship', 'is_active'],
                    ],
                ],
            ]);

        // Unsupported include -> 422 validation error
        $badInclude = $this->getJson("/api/v1/residents/{$this->resident1->id}?include=password,secret_relation");
        $badInclude->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    // =========================================================================
    // 3. HOUSEHOLDS READ API (Section 14, 15, 16, 17, 18, 19, 20)
    // =========================================================================

    public function test_household_list_scoped_to_authorized_rt(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->getJson('/api/v1/households');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'representation', 'status', 'active_members_count', 'rt_id', 'rw_id', 'organization_id'],
                ],
                'meta' => [
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page', 'has_more'],
                    'request_id',
                ],
            ]);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($this->household1->id, $ids);
        $this->assertContains($this->householdVacant->id, $ids);
        $this->assertNotContains($this->householdRt2->id, $ids, 'RT 1 actor must not see RT 2 household.');
        $this->assertNotContains($this->householdOrgB->id, $ids);
    }

    public function test_household_detail_same_rt_allowed_and_different_rt_denied(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        // Same RT -> ALLOW
        $allowed = $this->getJson("/api/v1/households/{$this->household1->id}");
        $allowed->assertStatus(200)->assertJsonPath('data.id', $this->household1->id);

        // Different RT -> DENIED (403)
        $deniedRt = $this->getJson("/api/v1/households/{$this->householdRt2->id}");
        $deniedRt->assertStatus(403)->assertJsonPath('error.code', 'forbidden');

        // Different Org -> DENIED (403)
        $deniedOrg = $this->getJson("/api/v1/households/{$this->householdOrgB->id}");
        $deniedOrg->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
    }

    public function test_household_kk_number_privacy_tiers(): void
    {
        // 1. Sensitive Actor (Ketua RT has household.view_sensitive) -> Full KK
        $this->actingAs($this->ketuaRtA1_1);
        $sensitiveRes = $this->getJson("/api/v1/households/{$this->household1->id}");
        $sensitiveRes->assertStatus(200)
            ->assertJsonPath('data.representation', 'sensitive')
            ->assertJsonPath('data.kk_number', '3201011205900001')
            ->assertJsonPath('data.kk_number_masked', false);

        // 2. Normal Actor (Bendahara RT has household.view but NOT household.view_sensitive) -> Masked KK
        $this->actingAs($this->bendaharaRtA1_1);
        $normalRes = $this->getJson("/api/v1/households/{$this->household1->id}");
        $normalRes->assertStatus(200)
            ->assertJsonPath('data.representation', 'normal')
            ->assertJsonPath('data.kk_number_masked', true);
        $this->assertNotEquals('3201011205900001', $normalRes->json('data.kk_number'));
        $this->assertEquals('320101******0001', $normalRes->json('data.kk_number'));
    }

    public function test_household_filters_head_availability_and_search(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        // head_availability: present
        $presentRes = $this->getJson('/api/v1/households?head_availability=present');
        $presentRes->assertStatus(200);
        $presentIds = collect($presentRes->json('data'))->pluck('id');
        $this->assertContains($this->household1->id, $presentIds);
        $this->assertNotContains($this->householdVacant->id, $presentIds);

        // head_availability: vacant
        $vacantRes = $this->getJson('/api/v1/households?head_availability=vacant');
        $vacantRes->assertStatus(200);
        $vacantIds = collect($vacantRes->json('data'))->pluck('id');
        $this->assertContains($this->householdVacant->id, $vacantIds);
        $this->assertNotContains($this->household1->id, $vacantIds);

        // Search (?q=) by address
        $searchRes = $this->getJson('/api/v1/households?q=Kenanga');
        $searchRes->assertStatus(200);
        $searchIds = collect($searchRes->json('data'))->pluck('id');
        $this->assertContains($this->household1->id, $searchIds);
        $this->assertNotContains($this->householdVacant->id, $searchIds);
    }

    public function test_household_includes_and_member_summary_privacy(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $res = $this->getJson("/api/v1/households/{$this->household1->id}?include=head,head.resident,members,members.resident");
        $res->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'active_head' => [
                        'id',
                        'relationship',
                        'is_active',
                        'resident' => ['id', 'name'],
                    ],
                    'active_members' => [
                        '*' => [
                            'id',
                            'relationship',
                            'is_active',
                            'resident' => ['id', 'name'],
                        ],
                    ],
                ],
            ]);

        // Verify member summary NEVER exposes NIK, phone, user_id, religion, blood_type
        $headResident = $res->json('data.active_head.resident');
        $this->assertEquals(['id', 'name'], array_keys($headResident));
        $this->assertEquals('Budi Santoso', $headResident['name']);
        $this->assertArrayNotHasKey('nik', $headResident);
        $this->assertArrayNotHasKey('phone', $headResident);
        $this->assertArrayNotHasKey('user_id', $headResident);
    }

    // =========================================================================
    // 4. SECURITY ATTACK VECTORS & INVARIANT DEFENSE (Section 32)
    // =========================================================================

    public function test_security_client_controlled_tier_parameters_are_ignored(): void
    {
        // Bendahara tries to elevate privacy tier via query parameters
        $this->actingAs($this->bendaharaRtA1_1);

        $response = $this->getJson("/api/v1/residents/{$this->resident1->id}?tier=sensitive&sensitive=true&privacy=sensitive");
        $response->assertStatus(200)
            ->assertJsonPath('data.representation', 'normal')
            ->assertJsonPath('data.nik_masked', true);

        $hhResponse = $this->getJson("/api/v1/households/{$this->household1->id}?tier=sensitive&sensitive=true");
        $hhResponse->assertStatus(200)
            ->assertJsonPath('data.representation', 'normal')
            ->assertJsonPath('data.kk_number_masked', true);
        $this->assertEquals('320101******0001', $hhResponse->json('data.kk_number'));
    }

    public function test_security_sensitive_filters_are_strictly_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        // Attempting to filter by nik -> 422
        $resNik = $this->getJson('/api/v1/residents?nik=3201011205900001');
        $resNik->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

        // Attempting to filter by phone -> 422
        $resPhone = $this->getJson('/api/v1/residents?phone=081234567890');
        $resPhone->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

        // Attempting to filter by kk_number -> 422
        $hhKk = $this->getJson('/api/v1/households?kk_number=3201011205900001');
        $hhKk->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_security_arbitrary_sort_is_blocked(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $badSort = $this->getJson('/api/v1/residents?sort=nik');
        $badSort->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

        $sqlInjectionSort = $this->getJson('/api/v1/residents?sort=name;DROP TABLE residents;');
        $sqlInjectionSort->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_security_arbitrary_include_is_blocked(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $badInclude = $this->getJson('/api/v1/households?include=users,secrets');
        $badInclude->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    // =========================================================================
    // 5. QUERY COUNT & N+1 DEFENSE (Section 33)
    // =========================================================================

    public function test_query_count_resident_list_has_no_per_item_queries(): void
    {
        $this->actingAs($this->ketuaRwA1);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/residents?per_page=15');
        $response->assertStatus(200);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // One-time auth bootstrapping (memberships, roles, permissions) + 1 count query + 1 select query = 7 queries total.
        // Critically: ZERO per-item Gate or relationship queries during serialization!
        $this->assertLessThanOrEqual(8, $queryCount, "Resident list must not produce N+1 queries. Queries executed: {$queryCount}");
    }

    public function test_query_count_household_list_with_members_count_has_no_n_plus_one(): void
    {
        $this->actingAs($this->ketuaRwA1);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/households?per_page=15');
        $response->assertStatus(200);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // One-time auth bootstrapping + Count query + select query with subquery count = 7 queries total.
        // Serialization uses withCount direct attribute, triggering ZERO additional SQL queries!
        $this->assertLessThanOrEqual(8, $queryCount, "Household list must not produce N+1 queries. Queries executed: {$queryCount}");
    }
}
