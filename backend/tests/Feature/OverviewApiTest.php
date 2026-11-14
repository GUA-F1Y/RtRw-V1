<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Enums\AccountStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Enums\ResidentBloodType;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Enums\ResidencyType;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\Resident;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OverviewApiTest extends TestCase
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

    private User $orgAdminA;
    private User $ketuaRwA1;
    private User $ketuaRtA1_1;
    private User $ketuaRtA1_2;
    private User $ketuaRtB1_1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::factory()->create(['name' => 'Organisasi Sukses']);
        $this->orgB = Organization::factory()->create(['name' => 'Organisasi Maju']);

        $this->rwA1 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 1, 'name' => 'RW 01']);
        $this->rwA2 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 2, 'name' => 'RW 02']);
        $this->rwB1 = Rw::factory()->create(['organization_id' => $this->orgB->id, 'number' => 1, 'name' => 'RW 01 B']);

        $this->rtA1_1 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 1, 'name' => 'RT 01']);
        $this->rtA1_2 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 2, 'name' => 'RT 02']);
        $this->rtA2_1 = Rt::factory()->create(['rw_id' => $this->rwA2->id, 'number' => 1, 'name' => 'RT 01 RW 02']);
        $this->rtB1_1 = Rt::factory()->create(['rw_id' => $this->rwB1->id, 'number' => 1, 'name' => 'RT 01 Org B']);

        $this->orgAdminA = $this->createScopedUser(RoleKey::OrganizationAdmin, $this->orgA);
        $this->ketuaRwA1 = $this->createScopedUser(RoleKey::KetuaRw, $this->orgA, $this->rwA1);
        $this->ketuaRtA1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $this->ketuaRtA1_2 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_2);
        $this->ketuaRtB1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgB, $this->rwB1, $this->rtB1_1);
    }

    // =========================================================================
    // 1. AUTHENTICATION & ACCESS CONTROL
    // =========================================================================

    public function test_unauthenticated_request_is_denied(): void
    {
        $response = $this->getJson('/api/v1/overview');
        $response->assertStatus(401);
    }

    public function test_inactive_account_is_denied(): void
    {
        $suspendedUser = $this->createScopedUser(
            RoleKey::KetuaRt,
            $this->orgA,
            $this->rwA1,
            $this->rtA1_1,
            AccountStatus::Suspended
        );

        $this->actingAs($suspendedUser);

        $response = $this->getJson('/api/v1/overview');
        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'account_inactive');
    }

    public function test_user_without_active_memberships_is_denied(): void
    {
        $userWithoutMembership = User::factory()->create(['status' => AccountStatus::Active]);
        $this->actingAs($userWithoutMembership);

        $response = $this->getJson('/api/v1/overview');
        $response->assertStatus(403);
    }

    // =========================================================================
    // 2. SCOPE DERIVATION & SCOPE ISOLATION
    // =========================================================================

    public function test_rt_scoped_actor_sees_only_authorized_rt(): void
    {
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111101', ResidentStatus::Active);
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_2, '1111111111111102', ResidentStatus::Active);

        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->getJson('/api/v1/overview');
        $response->assertStatus(200);

        $response->assertJsonPath('data.scope.level', 'RT');
        $response->assertJsonPath('data.scope.id', $this->rtA1_1->id);
        $response->assertJsonPath('data.population.total', 1);
        $response->assertJsonPath('data.population.active', 1);

        $rtBreakdown = $response->json('data.rt_breakdown');
        $this->assertCount(1, $rtBreakdown);
        $this->assertSame($this->rtA1_1->id, $rtBreakdown[0]['rt_id']);
        $this->assertSame('01', $rtBreakdown[0]['rt_code']);
        $this->assertSame(1, $rtBreakdown[0]['resident_count']);
    }

    public function test_rw_scoped_actor_sees_authorized_rw_and_all_child_rts(): void
    {
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111101', ResidentStatus::Active);
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_2, '1111111111111102', ResidentStatus::Active);
        // Foreign RW resident
        $this->createResidentFixture($this->orgA, $this->rwA2, $this->rtA2_1, '1111111111111103', ResidentStatus::Active);

        $this->actingAs($this->ketuaRwA1);

        $response = $this->getJson('/api/v1/overview');
        $response->assertStatus(200);

        $response->assertJsonPath('data.scope.level', 'RW');
        $response->assertJsonPath('data.scope.id', $this->rwA1->id);
        $response->assertJsonPath('data.population.total', 2);
        $response->assertJsonPath('data.population.active', 2);

        $rtBreakdown = $response->json('data.rt_breakdown');
        $this->assertCount(2, $rtBreakdown);
        $this->assertSame($this->rtA1_1->id, $rtBreakdown[0]['rt_id']);
        $this->assertSame('01', $rtBreakdown[0]['rt_code']);
        $this->assertSame(1, $rtBreakdown[0]['resident_count']);
        $this->assertSame($this->rtA1_2->id, $rtBreakdown[1]['rt_id']);
        $this->assertSame('02', $rtBreakdown[1]['rt_code']);
        $this->assertSame(1, $rtBreakdown[1]['resident_count']);
    }

    public function test_rw_a_cannot_see_rw_b_data(): void
    {
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111101', ResidentStatus::Active);
        $this->createResidentFixture($this->orgA, $this->rwA2, $this->rtA2_1, '1111111111111102', ResidentStatus::Active);

        $this->actingAs($this->ketuaRwA1);

        $response = $this->getJson('/api/v1/overview');
        $response->assertStatus(200);
        $response->assertJsonPath('data.population.total', 1);

        $rtIds = array_column($response->json('data.rt_breakdown'), 'rt_id');
        $this->assertNotContains($this->rtA2_1->id, $rtIds);
    }

    public function test_organization_scoped_actor_aggregates_entire_organization_and_cannot_see_org_b(): void
    {
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111101', ResidentStatus::Active);
        $this->createResidentFixture($this->orgA, $this->rwA2, $this->rtA2_1, '1111111111111102', ResidentStatus::Active);
        // Foreign Org B
        $this->createResidentFixture($this->orgB, $this->rwB1, $this->rtB1_1, '2222222222222201', ResidentStatus::Active);

        $this->actingAs($this->orgAdminA);

        $response = $this->getJson('/api/v1/overview');
        $response->assertStatus(200);

        $response->assertJsonPath('data.scope.level', 'ORGANIZATION');
        $response->assertJsonPath('data.scope.id', $this->orgA->id);
        $response->assertJsonPath('data.population.total', 2);

        $rtIds = array_column($response->json('data.rt_breakdown'), 'rt_id');
        $this->assertNotContains($this->rtB1_1->id, $rtIds);
    }

    // =========================================================================
    // 3. QUERY MANIPULATION PREVENTION
    // =========================================================================

    public function test_query_parameter_manipulation_is_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response1 = $this->getJson("/api/v1/overview?rt_id={$this->rtA1_2->id}");
        $response1->assertStatus(422);

        $response2 = $this->getJson("/api/v1/overview?rw_id={$this->rwA2->id}");
        $response2->assertStatus(422);

        $response3 = $this->getJson("/api/v1/overview?organization_id={$this->orgB->id}");
        $response3->assertStatus(422);
    }

    // =========================================================================
    // 4. DATA CORRECTNESS & METRIC DEFINITIONS (Sections 9, 10, 40, 41)
    // =========================================================================

    public function test_population_metric_definitions_across_lifecycle_statuses(): void
    {
        // 2 Active
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111101', ResidentStatus::Active);
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111102', ResidentStatus::Active);
        // 1 Prospective
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111103', ResidentStatus::Prospective);
        // 1 Inactive
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111104', ResidentStatus::Inactive);
        // 1 Moved
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111105', ResidentStatus::Moved);
        // 1 Deceased (Must not be counted in active, but included in total)
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111106', ResidentStatus::Deceased);

        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->getJson('/api/v1/overview');
        $response->assertStatus(200);

        $pop = $response->json('data.population');
        $this->assertSame(6, $pop['total'], 'Total population must count all lifecycle records within scope.');
        $this->assertSame(2, $pop['active'], 'Active population must count only status=ACTIVE.');
        $this->assertSame(1, $pop['prospective'], 'Prospective must count only status=PROSPECTIVE.');
        $this->assertSame(1, $pop['inactive'], 'Inactive must count only status=INACTIVE.');
        $this->assertSame(1, $pop['moved'], 'Moved must count only status=MOVED.');
        $this->assertSame(1, $pop['deceased'], 'Deceased must count only status=DECEASED.');
    }

    public function test_household_metric_definitions_including_head_assigned_and_vacant(): void
    {
        // 1. Active household with active KEPALA_KELUARGA
        $hh1 = Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'kk_number' => '3201011205900001',
            'address' => 'Jl. Merpati No. 1',
            'status' => HouseholdStatus::Active,
        ]);
        $resHead = $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111101', ResidentStatus::Active);
        HouseholdMember::query()->create([
            'household_id' => $hh1->id,
            'resident_id' => $resHead->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
            'is_active' => true,
        ]);

        // 2. Active household without head
        $hh2 = Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'kk_number' => '3201011205900002',
            'address' => 'Jl. Merpati No. 2',
            'status' => HouseholdStatus::Active,
        ]);

        // 3. Head vacant household
        Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'kk_number' => '3201011205900003',
            'address' => 'Jl. Merpati No. 3',
            'status' => HouseholdStatus::HeadVacant,
        ]);

        // 4. Moved household
        Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'kk_number' => '3201011205900004',
            'address' => 'Jl. Merpati No. 4',
            'status' => HouseholdStatus::Moved,
        ]);

        // 5. Dissolved household with historical head (is_active = false)
        $hhDissolved = Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'kk_number' => '3201011205900005',
            'address' => 'Jl. Merpati No. 5',
            'status' => HouseholdStatus::Dissolved,
        ]);
        $resOldHead = $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111102', ResidentStatus::Active);
        HouseholdMember::query()->create([
            'household_id' => $hhDissolved->id,
            'resident_id' => $resOldHead->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
            'is_active' => false, // Historical
        ]);

        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->getJson('/api/v1/overview');
        $response->assertStatus(200);

        $hh = $response->json('data.households');
        $this->assertSame(2, $hh['active'], 'Active households: hh1 + hh2.');
        $this->assertSame(1, $hh['head_assigned'], 'Only active households with an active KEPALA_KELUARGA count toward head_assigned.');
        $this->assertSame(1, $hh['head_vacant'], 'Head vacant households count.');
        $this->assertSame(1, $hh['moved'], 'Moved households count.');
        $this->assertSame(1, $hh['dissolved'], 'Dissolved households count.');
    }

    // =========================================================================
    // 5. PRIVACY & SENSITIVE ATTRIBUTE PROTECTION (Section 37)
    // =========================================================================

    public function test_privacy_strict_absence_of_sensitive_data(): void
    {
        $res = $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '3201011205900001', ResidentStatus::Active);
        $hh = Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'kk_number' => '3201011205900001',
            'address' => 'Jl. Rahasia No. 99',
            'status' => HouseholdStatus::Active,
        ]);
        HouseholdMember::query()->create([
            'household_id' => $hh->id,
            'resident_id' => $res->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
            'is_active' => true,
        ]);

        $this->actingAs($this->ketuaRwA1);

        $response = $this->getJson('/api/v1/overview');
        $response->assertStatus(200);

        $rawBody = $response->getContent();
        $this->assertStringNotContainsString('3201011205900001', $rawBody, 'Full NIK/KK must never leak in overview.');
        $this->assertStringNotContainsString('Jl. Rahasia', $rawBody, 'Address must never leak in overview.');
        $this->assertStringNotContainsString($res->name, $rawBody, 'Resident name must never leak in overview.');
        $this->assertStringNotContainsString('+62812', $rawBody, 'Phone must never leak in overview.');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('residents', $data);
        $this->assertArrayNotHasKey('households_list', $data);
    }

    // =========================================================================
    // 6. PERFORMANCE & N+1 AGGREGATION PREVENTION (Section 17, 18)
    // =========================================================================

    public function test_performance_query_count_remains_constant_with_more_rts(): void
    {
        // Setup initial 2 RTs with data
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_1, '1111111111111101', ResidentStatus::Active);
        $this->createResidentFixture($this->orgA, $this->rwA1, $this->rtA1_2, '1111111111111102', ResidentStatus::Active);

        $this->actingAs($this->ketuaRwA1);

        // Run once to warm up auth cache
        $this->getJson('/api/v1/overview');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response1 = $this->getJson('/api/v1/overview');
        $response1->assertStatus(200);

        $initialQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Add 3 more RTs to RW A1 (total 5 RTs)
        for ($i = 3; $i <= 5; $i++) {
            $rt = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => $i, 'name' => "RT 0{$i}"]);
            $this->createResidentFixture($this->orgA, $this->rwA1, $rt, "111111111111110{$i}", ResidentStatus::Active);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response2 = $this->getJson('/api/v1/overview');
        $response2->assertStatus(200);

        $subsequentQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 5 RTs breakdown is verified
        $this->assertCount(5, $response2->json('data.rt_breakdown'));

        // Query count MUST remain equal: adding 3 RTs should NOT add queries!
        $this->assertSame(
            $initialQueryCount,
            $subsequentQueryCount,
            "Query count must remain fixed (O(1)) regardless of RT count. Baseline: {$initialQueryCount}, with 3 more RTs: {$subsequentQueryCount}."
        );

        // Document measured behavior: Auth bootstrapping + membership check + 4 aggregate queries
        $this->assertLessThanOrEqual(
            15,
            $subsequentQueryCount,
            "Overview query count must be tightly bounded. Queries executed: {$subsequentQueryCount}."
        );
    }

    // =========================================================================
    // HELPER FIXTURE BUILDER
    // =========================================================================

    private function createResidentFixture(
        Organization $org,
        Rw $rw,
        Rt $rt,
        string $nik,
        ResidentStatus $status
    ): Resident {
        return Resident::query()->create([
            'organization_id' => $org->id,
            'rw_id' => $rw->id,
            'rt_id' => $rt->id,
            'nik' => $nik,
            'name' => 'Warga ' . substr($nik, -4),
            'gender' => ResidentGender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'blood_type' => ResidentBloodType::O,
            'phone' => '+62812' . substr($nik, -8),
            'residency_type' => ResidencyType::Permanent,
            'status' => $status,
        ]);
    }
}
