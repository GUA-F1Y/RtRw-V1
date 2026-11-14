<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Enums\PrivacyTier;
use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Authorization\Enums\ScopeLevel;
use App\Domain\Authorization\Models\Permission;
use App\Domain\Authorization\Models\Role;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Enums\AccountStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Domain\Resident\Actions\RegisterResidentAction;
use App\Domain\Resident\DTOs\RegisterResidentInput;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Enums\ResidencyMovementType;
use App\Domain\Resident\Enums\ResidencyType;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Exceptions\ResidentScopeMismatch;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\ResidencyMovement;
use App\Domain\Resident\Models\Resident;
use App\Domain\Resident\Models\ResidentStatusHistory;
use App\Http\Resources\HouseholdMemberResource;
use App\Http\Resources\HouseholdResource;
use App\Http\Resources\ResidencyMovementResource;
use App\Http\Resources\ResidentResource;
use App\Http\Resources\ResidentStatusHistoryResource;
use App\Policies\HouseholdPolicy;
use App\Policies\ResidentPolicy;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResourceSerializationTest extends TestCase
{
    private const NIK = '3201011205900005';
    private const KK = '3201011205900001';
    private const PHONE = '+6281234567890';

    private Organization $org;
    private Rw $rw;
    private Rt $rt;
    private User $ketuaRt;
    private User $bendaharaRt;
    private User $siblingKetuaRt;
    private User $regularUser;
    private User $linkedUser;
    private Resident $resident;
    private Household $household;
    private ResidentPolicy $residentPolicy;
    private HouseholdPolicy $householdPolicy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create(['name' => 'Org Resource Test']);
        $this->rw = Rw::factory()->create(['organization_id' => $this->org->id, 'number' => 1]);
        $this->rt = Rt::factory()->create(['rw_id' => $this->rw->id, 'number' => 1]);
        $siblingRt = Rt::factory()->create(['rw_id' => $this->rw->id, 'number' => 2]);

        $this->ketuaRt = $this->createScopedUser(RoleKey::KetuaRt, $this->org, $this->rw, $this->rt);
        $this->bendaharaRt = $this->createScopedUser(RoleKey::BendaharaRt, $this->org, $this->rw, $this->rt);
        $this->siblingKetuaRt = $this->createScopedUser(RoleKey::KetuaRt, $this->org, $this->rw, $siblingRt);
        $this->regularUser = User::factory()->create(['name' => 'Regular User']);
        $this->linkedUser = User::factory()->create(['name' => 'Linked Resident User']);

        $this->household = Household::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'kk_number' => self::KK,
            'address' => 'Jl. Kenanga No. 10',
            'status' => HouseholdStatus::Active,
            'notes' => 'Catatan internal RT',
        ]);

        $this->resident = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'current_household_id' => $this->household->id,
            'user_id' => $this->linkedUser->id,
            'nik' => self::NIK,
            'name' => 'Ahmad Dahlan',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '1990-05-12',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Married,
            'phone' => self::PHONE,
            'residency_type' => ResidencyType::Permanent,
            'status' => ResidentStatus::Active,
        ]);

        $this->residentPolicy = app(ResidentPolicy::class);
        $this->householdPolicy = app(HouseholdPolicy::class);
    }

    /**
     * Fully serialize a resource (including nested resources) the way the HTTP layer would.
     *
     * @return array<string, mixed>
     */
    private function render(object $resource): array
    {
        return json_decode(json_encode($resource, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Create a user whose single RT membership carries exactly the given permissions.
     *
     * @param  list<PermissionKey>  $permissions
     */
    private function createUserWithPermissions(array $permissions): User
    {
        /** @var Role $role */
        $role = Role::factory()->create([
            'key' => 'custom_'.Str::lower(Str::random(10)),
            'name' => 'Custom test role',
            'scope_level' => ScopeLevel::Rt,
            'description' => 'Test-only role',
        ]);

        $role->permissions()->attach(
            Permission::query()
                ->whereIn('key', array_map(static fn (PermissionKey $p): string => $p->value, $permissions))
                ->pluck('id')
                ->all()
        );

        /** @var User $user */
        $user = User::factory()->create(['status' => AccountStatus::Active]);

        Membership::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_level' => ScopeLevel::Rt,
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'granted_by' => null,
            'revoked_at' => null,
        ]);

        return $user;
    }

    private function makeStatusHistory(): ResidentStatusHistory
    {
        return ResidentStatusHistory::query()->create([
            'resident_id' => $this->resident->id,
            'previous_status' => ResidentStatus::Prospective,
            'new_status' => ResidentStatus::Active,
            'reason' => 'Verifikasi berkas kependudukan disetujui',
            'effective_at' => '2026-01-15 10:00:00',
            'reference_letter_number' => 'VERIF/001',
            'recorded_by' => $this->ketuaRt->id,
        ]);
    }

    private function makeMovement(): ResidencyMovement
    {
        $rt2 = Rt::factory()->create(['rw_id' => $this->rw->id, 'number' => 3]);
        $hh2 = Household::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $rt2->id,
            'kk_number' => '3201011205900099',
            'address' => 'Jl. Anggrek No. 2',
            'status' => HouseholdStatus::Active,
        ]);

        return ResidencyMovement::query()->create([
            'resident_id' => $this->resident->id,
            'movement_type' => ResidencyMovementType::TransferInternal,
            'from_organization_id' => $this->org->id,
            'from_rw_id' => $this->rw->id,
            'from_rt_id' => $this->rt->id,
            'from_household_id' => $this->household->id,
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw->id,
            'to_rt_id' => $rt2->id,
            'to_household_id' => $hh2->id,
            'effective_date' => '2026-02-01',
            'reference_letter_number' => 'SK/2026/01',
            'notes' => 'Pindah rumah antar RT',
            'recorded_by' => $this->ketuaRt->id,
        ]);
    }

    // =========================================================================
    // RESIDENT — three tiers
    // =========================================================================

    public function test_resident_directory_representation_has_only_community_safe_fields(): void
    {
        $json = $this->render(ResidentResource::at($this->resident, PrivacyTier::Directory));

        $this->assertSame('directory', $json['representation']);
        $this->assertSame('Ahmad Dahlan', $json['name']);
        $this->assertSame('MALE', $json['gender']);
        $this->assertSame('ACTIVE', $json['status']);
        $this->assertIsInt($json['age']);
        $this->assertSame($this->rt->id, $json['rt_id']);

        foreach ([
            'nik', 'nik_masked', 'phone', 'phone_masked', 'birth_date', 'birth_place', 'religion',
            'blood_type', 'user_id', 'current_household_id', 'marital_status', 'occupation', 'education',
            'residency_movements', 'status_history', 'household_memberships', 'current_household',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $json, "Directory tier must not expose [{$forbidden}]");
        }

        $raw = json_encode($json, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::NIK, $raw);
        $this->assertStringNotContainsString(self::PHONE, $raw);
        $this->assertStringNotContainsString('1990-05-12', $raw);
    }

    public function test_resident_default_representation_is_the_most_restrictive_tier(): void
    {
        $single = $this->render(new ResidentResource($this->resident));
        $collection = $this->render(ResidentResource::collection(collect([$this->resident])));

        $this->assertSame('directory', $single['representation']);
        $this->assertArrayNotHasKey('nik', $single);

        // Laravel's stock ::collection() builds `new static($item)`, so it must also default to Directory.
        $this->assertSame('directory', $collection[0]['representation']);
        $this->assertArrayNotHasKey('nik', $collection[0]);
        $this->assertArrayNotHasKey('phone', $collection[0]);
    }

    public function test_resident_normal_representation_masks_nik_and_phone_and_hides_sensitive_demographics(): void
    {
        $json = $this->render(ResidentResource::at($this->resident, PrivacyTier::Normal));

        $this->assertSame('normal', $json['representation']);
        $this->assertSame('320101******0005', $json['nik']);
        $this->assertTrue($json['nik_masked']);
        $this->assertSame('+628*******890', $json['phone']);
        $this->assertTrue($json['phone_masked']);
        $this->assertSame('1990-05-12', $json['birth_date']);
        $this->assertSame('Jakarta', $json['birth_place']);
        $this->assertSame('MARRIED', $json['marital_status']);
        $this->assertSame($this->household->id, $json['current_household_id']);

        foreach (['religion', 'blood_type', 'user_id'] as $sensitiveOnly) {
            $this->assertArrayNotHasKey($sensitiveOnly, $json, "Normal tier must not expose [{$sensitiveOnly}]");
        }

        $raw = json_encode($json, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::NIK, $raw);
        $this->assertStringNotContainsString(self::PHONE, $raw);
    }

    public function test_resident_sensitive_representation_exposes_full_identifiers(): void
    {
        $json = $this->render(ResidentResource::at($this->resident, PrivacyTier::Sensitive));

        $this->assertSame('sensitive', $json['representation']);
        $this->assertSame(self::NIK, $json['nik']);
        $this->assertFalse($json['nik_masked']);
        $this->assertSame(self::PHONE, $json['phone']);
        $this->assertFalse($json['phone_masked']);
        $this->assertSame('ISLAM', $json['religion']);
        $this->assertSame($this->linkedUser->id, $json['user_id']);
        $this->assertArrayHasKey('blood_type', $json);
    }

    public function test_resident_ledgers_follow_resident_tier(): void
    {
        $history = $this->makeStatusHistory();
        $movement = $this->makeMovement();

        $resident = Resident::query()->findOrFail($this->resident->id);
        $resident->setRelation('statusHistory', collect([$history]));
        $resident->setRelation('residencyMovements', collect([$movement]));

        // Directory: ledgers are never rendered, even when eager-loaded.
        $directory = $this->render(ResidentResource::at($resident, PrivacyTier::Directory));
        $this->assertArrayNotHasKey('status_history', $directory);
        $this->assertArrayNotHasKey('residency_movements', $directory);

        // Normal: safe ledger shape only.
        $normal = $this->render(ResidentResource::at($resident, PrivacyTier::Normal));
        $this->assertSame('ACTIVE', $normal['status_history'][0]['new_status']);
        $this->assertArrayNotHasKey('reason', $normal['status_history'][0]);
        $this->assertArrayNotHasKey('reference_letter_number', $normal['residency_movements'][0]);
        $this->assertArrayNotHasKey('notes', $normal['residency_movements'][0]);

        // Sensitive: restricted metadata appears.
        $sensitive = $this->render(ResidentResource::at($resident, PrivacyTier::Sensitive));
        $this->assertSame('Verifikasi berkas kependudukan disetujui', $sensitive['status_history'][0]['reason']);
        $this->assertSame('SK/2026/01', $sensitive['residency_movements'][0]['reference_letter_number']);
    }

    public function test_policy_maps_authority_to_resident_tier(): void
    {
        $this->assertSame(PrivacyTier::Sensitive, $this->residentPolicy->representationTier($this->ketuaRt, $this->resident));
        $this->assertSame(PrivacyTier::Normal, $this->residentPolicy->representationTier($this->bendaharaRt, $this->resident));

        // Other RT / no membership: no representation at all.
        $this->assertNull($this->residentPolicy->representationTier($this->siblingKetuaRt, $this->resident));
        $this->assertNull($this->residentPolicy->representationTier($this->regularUser, $this->resident));

        // Directory-only grant -> Directory tier.
        $directoryOnly = $this->createUserWithPermissions([PermissionKey::ResidentDirectoryView]);
        $this->assertSame(PrivacyTier::Directory, $this->residentPolicy->representationTier($directoryOnly, $this->resident));

        // A sensitive-only grant without resident.view must NOT yield a sensitive representation.
        $sensitiveOnly = $this->createUserWithPermissions([PermissionKey::ResidentViewSensitive]);
        $this->assertNull($this->residentPolicy->representationTier($sensitiveOnly, $this->resident));
    }

    public function test_end_to_end_policy_tier_resource_flow_for_resident(): void
    {
        $officer = $this->render(ResidentResource::at(
            $this->resident,
            $this->residentPolicy->representationTier($this->ketuaRt, $this->resident)
        ));
        $billing = $this->render(ResidentResource::at(
            $this->resident,
            $this->residentPolicy->representationTier($this->bendaharaRt, $this->resident)
        ));

        $this->assertSame(self::NIK, $officer['nik']);
        $this->assertSame('320101******0005', $billing['nik']);
    }

    // =========================================================================
    // HOUSEHOLD — safe / normal / sensitive
    // =========================================================================

    public function test_household_directory_safe_representation_hides_kk_address_and_notes(): void
    {
        $json = $this->render(HouseholdResource::at($this->household, PrivacyTier::Directory));

        $this->assertSame('directory', $json['representation']);
        $this->assertSame($this->household->id, $json['id']);
        $this->assertSame('ACTIVE', $json['status']);
        $this->assertSame($this->rt->id, $json['rt_id']);
        $this->assertArrayHasKey('active_members_count', $json);

        foreach (['kk_number', 'kk_number_masked', 'address', 'notes', 'active_head', 'members', 'active_members'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $json, "Safe household tier must not expose [{$forbidden}]");
        }
        $this->assertStringNotContainsString(self::KK, json_encode($json, JSON_THROW_ON_ERROR));
    }

    public function test_household_default_representation_is_the_safe_tier(): void
    {
        $json = $this->render(new HouseholdResource($this->household));

        $this->assertSame('directory', $json['representation']);
        $this->assertArrayNotHasKey('kk_number', $json);
    }

    public function test_household_normal_representation_masks_kk(): void
    {
        $json = $this->render(HouseholdResource::at($this->household, PrivacyTier::Normal));

        $this->assertSame('normal', $json['representation']);
        $this->assertSame('320101******0001', $json['kk_number']);
        $this->assertTrue($json['kk_number_masked']);
        $this->assertSame('Jl. Kenanga No. 10', $json['address']);
        $this->assertSame('ACTIVE', $json['status']);
        $this->assertStringNotContainsString(self::KK, json_encode($json, JSON_THROW_ON_ERROR));
    }

    public function test_household_sensitive_representation_exposes_full_kk(): void
    {
        $json = $this->render(HouseholdResource::at($this->household, PrivacyTier::Sensitive));

        $this->assertSame('sensitive', $json['representation']);
        $this->assertSame(self::KK, $json['kk_number']);
        $this->assertFalse($json['kk_number_masked']);
    }

    public function test_household_never_dumps_members_or_residents_by_default(): void
    {
        HouseholdMember::query()->create([
            'household_id' => $this->household->id,
            'resident_id' => $this->resident->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
            'is_active' => true,
            'joined_at' => '2026-01-01',
        ]);

        $fresh = Household::query()->findOrFail($this->household->id);
        $json = $this->render(HouseholdResource::at($fresh, PrivacyTier::Sensitive));

        foreach (['members', 'active_members', 'active_head', 'residents'] as $key) {
            $this->assertArrayNotHasKey($key, $json);
        }
    }

    public function test_household_with_explicit_includes_exposes_only_resident_summaries(): void
    {
        HouseholdMember::query()->create([
            'household_id' => $this->household->id,
            'resident_id' => $this->resident->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
            'is_active' => true,
            'joined_at' => '2026-01-01',
        ]);

        $household = Household::query()->findOrFail($this->household->id);
        $household->load(['activeMembers.resident', 'activeHead.resident']);

        $json = $this->render(HouseholdResource::at($household, PrivacyTier::Normal));

        $this->assertSame(1, $json['active_members_count']);
        $this->assertSame(['id', 'name'], array_keys($json['active_members'][0]['resident']));
        $this->assertSame('Ahmad Dahlan', $json['active_head']['resident']['name']);

        $raw = json_encode($json, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::NIK, $raw);
        $this->assertStringNotContainsString(self::PHONE, $raw);
        $this->assertStringNotContainsString(self::KK, $raw);
    }

    public function test_policy_maps_authority_to_household_tier_and_kk_visibility(): void
    {
        // household.view only -> masked
        $viewOnly = $this->householdPolicy->representationTier($this->bendaharaRt, $this->household);
        $this->assertSame(PrivacyTier::Normal, $viewOnly);
        $masked = $this->render(HouseholdResource::at($this->household, $viewOnly));
        $this->assertSame('320101******0001', $masked['kk_number']);

        // household.view_sensitive -> full
        $full = $this->householdPolicy->representationTier($this->ketuaRt, $this->household);
        $this->assertSame(PrivacyTier::Sensitive, $full);
        $this->assertSame(self::KK, $this->render(HouseholdResource::at($this->household, $full))['kk_number']);

        // no access -> denied (no representation at all)
        $this->assertNull($this->householdPolicy->representationTier($this->siblingKetuaRt, $this->household));
        $this->assertNull($this->householdPolicy->representationTier($this->regularUser, $this->household));
    }

    // =========================================================================
    // HOUSEHOLD MEMBER — resident summary only
    // =========================================================================

    public function test_household_member_resource_carries_resident_summary_only(): void
    {
        $member = HouseholdMember::query()->create([
            'household_id' => $this->household->id,
            'resident_id' => $this->resident->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
            'is_active' => true,
            'joined_at' => '2026-01-01',
        ]);
        $member->load(['resident', 'household']);

        $json = $this->render(new HouseholdMemberResource($member));

        $this->assertSame($this->household->id, $json['household_id']);
        $this->assertSame($this->resident->id, $json['resident_id']);
        $this->assertSame('KEPALA_KELUARGA', $json['relationship']);
        $this->assertTrue($json['is_active']);
        $this->assertSame('2026-01-01', $json['joined_at']);
        $this->assertNull($json['left_at']);
        $this->assertNull($json['left_reason']);

        // Summary is exactly {id, name}; no nested household even though it was loaded.
        $this->assertSame(['id' => $this->resident->id, 'name' => 'Ahmad Dahlan'], $json['resident']);
        $this->assertArrayNotHasKey('household', $json);

        $raw = json_encode($json, JSON_THROW_ON_ERROR);
        foreach ([self::NIK, self::PHONE, '1990-05-12', 'ISLAM', self::KK] as $leak) {
            $this->assertStringNotContainsString($leak, $raw);
        }
    }

    // =========================================================================
    // LEDGERS — read-only, restricted metadata
    // =========================================================================

    public function test_residency_movement_safe_representation_hides_restricted_metadata(): void
    {
        $movement = $this->makeMovement();

        foreach ([new ResidencyMovementResource($movement), ResidencyMovementResource::at($movement, PrivacyTier::Normal)] as $resource) {
            $json = $this->render($resource);

            $this->assertSame($this->resident->id, $json['resident_id']);
            $this->assertSame('TRANSFER_INTERNAL', $json['movement_type']);
            $this->assertSame('2026-02-01', $json['effective_date']);
            $this->assertSame($this->rt->id, $json['from_scope']['rt_id']);
            $this->assertNotNull($json['to_scope']['rt_id']);

            foreach (['reference_letter_number', 'notes', 'attachment_file_id', 'attachment_file', 'recorded_by', 'resident'] as $restricted) {
                $this->assertArrayNotHasKey($restricted, $json, "Safe movement shape must not expose [{$restricted}]");
            }
        }
    }

    public function test_residency_movement_sensitive_representation_adds_restricted_metadata(): void
    {
        $json = $this->render(ResidencyMovementResource::at($this->makeMovement(), PrivacyTier::Sensitive));

        $this->assertSame('SK/2026/01', $json['reference_letter_number']);
        $this->assertSame('Pindah rumah antar RT', $json['notes']);
        $this->assertArrayHasKey('attachment_file_id', $json);
        $this->assertSame($this->ketuaRt->id, $json['recorded_by']['id']);
        $this->assertArrayNotHasKey('resident', $json);
    }

    public function test_residency_movement_household_context_is_safe_shape_without_kk(): void
    {
        $movement = (new ResidencyMovement)->forceFill([
            'resident_id' => $this->resident->id,
            'movement_type' => ResidencyMovementType::TransferInternal,
            'from_household_id' => $this->household->id,
        ]);
        $movement->setRelation('fromHousehold', $this->household);

        $json = $this->render(ResidencyMovementResource::at($movement, PrivacyTier::Sensitive));

        $this->assertSame('directory', $json['from_household']['representation']);
        $this->assertArrayNotHasKey('kk_number', $json['from_household']);
        $this->assertArrayNotHasKey('address', $json['from_household']);
        $this->assertStringNotContainsString(self::KK, json_encode($json, JSON_THROW_ON_ERROR));
    }

    public function test_resident_status_history_safe_representation_hides_restricted_metadata(): void
    {
        $history = $this->makeStatusHistory();

        foreach ([new ResidentStatusHistoryResource($history), ResidentStatusHistoryResource::at($history, PrivacyTier::Normal)] as $resource) {
            $json = $this->render($resource);

            $this->assertSame($this->resident->id, $json['resident_id']);
            $this->assertSame('PROSPECTIVE', $json['previous_status']);
            $this->assertSame('ACTIVE', $json['new_status']);
            $this->assertArrayHasKey('effective_at', $json);

            foreach (['reason', 'reference_letter_number', 'attachment_file_id', 'attachment_file', 'recorded_by', 'resident'] as $restricted) {
                $this->assertArrayNotHasKey($restricted, $json, "Safe history shape must not expose [{$restricted}]");
            }
        }
    }

    public function test_resident_status_history_sensitive_representation_adds_restricted_metadata(): void
    {
        $json = $this->render(ResidentStatusHistoryResource::at($this->makeStatusHistory(), PrivacyTier::Sensitive));

        $this->assertSame('Verifikasi berkas kependudukan disetujui', $json['reason']);
        $this->assertSame('VERIF/001', $json['reference_letter_number']);
        $this->assertSame($this->ketuaRt->id, $json['recorded_by']['id']);
        $this->assertArrayNotHasKey('resident', $json);
    }

    // =========================================================================
    // SECURITY — privacy switch, Gate, N+1, mutation trust boundary
    // =========================================================================

    public function test_client_request_parameters_cannot_escalate_the_representation_tier(): void
    {
        $request = Request::create(
            '/api/v1/residents/'.$this->resident->id.'?sensitive=true&tier=sensitive&representation=sensitive&fields=*&include=*',
            'GET',
            ['sensitive' => true, 'tier' => 'sensitive']
        );
        // The caller is even a fully privileged officer: the request still cannot pick the tier.
        $request->setUserResolver(fn () => $this->ketuaRt);
        $request->headers->set('X-Sensitive', '1');
        $this->app->instance('request', $request);

        $default = $this->render(new ResidentResource($this->resident));
        $this->assertSame('directory', $default['representation']);
        $this->assertArrayNotHasKey('nik', $default);

        $normal = $this->render(ResidentResource::at($this->resident, PrivacyTier::Normal));
        $this->assertSame('320101******0005', $normal['nik']);
        $this->assertTrue($normal['nik_masked']);

        $household = $this->render(HouseholdResource::at($this->household, PrivacyTier::Normal));
        $this->assertSame('320101******0001', $household['kk_number']);

        // toArray() receives the same hostile request directly and still honours the constructed tier.
        $direct = (new ResidentResource($this->resident))->toArray($request);
        $this->assertArrayNotHasKey('nik', $direct);
    }

    public function test_no_public_sensitive_override_exists_on_resources(): void
    {
        foreach ([ResidentResource::class, HouseholdResource::class] as $class) {
            $this->assertFalse(method_exists($class, 'withSensitive'), "{$class} must not expose withSensitive()");
            $this->assertFalse(method_exists($class, 'canViewSensitive'), "{$class} must not decide sensitivity itself");
        }
    }

    public function test_resources_never_consult_authorization_while_serializing(): void
    {
        $gateChecks = 0;
        Gate::before(function () use (&$gateChecks) {
            $gateChecks++;

            return null;
        });
        $this->actingAs($this->ketuaRt);

        $history = $this->makeStatusHistory();
        $movement = $this->makeMovement();
        $resident = Resident::query()->findOrFail($this->resident->id);
        $resident->setRelation('statusHistory', collect([$history]));
        $resident->setRelation('residencyMovements', collect([$movement]));

        $this->render(ResidentResource::at($resident, PrivacyTier::Sensitive));
        $this->render(HouseholdResource::at($this->household, PrivacyTier::Sensitive));
        $this->render(ResidentResource::collectionAt([$resident, $resident], PrivacyTier::Normal));
        $this->render(ResidencyMovementResource::at($movement, PrivacyTier::Sensitive));
        $this->render(ResidentStatusHistoryResource::at($history, PrivacyTier::Sensitive));

        $this->assertSame(0, $gateChecks, 'Resources must not call Gate/policies; the caller picks the tier.');
    }

    public function test_serializing_a_collection_issues_no_queries(): void
    {
        $rt2 = Rt::factory()->create(['rw_id' => $this->rw->id, 'number' => 9]);
        $second = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $rt2->id,
            'nik' => '3201011205900006',
            'name' => 'Siti Aminah',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Bandung',
            'birth_date' => '1992-03-04',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'residency_type' => ResidencyType::Permanent,
            'status' => ResidentStatus::Active,
        ]);

        $residents = Resident::query()->with(['rt', 'rw'])->whereIn('id', [$this->resident->id, $second->id])->get();
        $households = Household::query()->withCount('activeMembers')->get();

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach ([PrivacyTier::Directory, PrivacyTier::Normal, PrivacyTier::Sensitive] as $tier) {
            $this->render(ResidentResource::collectionAt($residents, $tier));
            $this->render(HouseholdResource::collectionAt($households, $tier));
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries, 'Serialization of preloaded collections must be query-free (no N+1).');
    }

    public function test_register_contract_rejects_user_link_scope_and_status_fields(): void
    {
        $operation = MutationContract::RESIDENT_REGISTER;
        $accepted = MutationContract::accepted($operation);

        $this->assertContains('rt_id', $accepted);
        $this->assertContains('nik', $accepted);
        $this->assertContains('name', $accepted);
        $this->assertNotContains('full_name', $accepted);

        foreach (['user_id', 'organization_id', 'rw_id', 'status', 'current_household_id'] as $refused) {
            $this->assertNotContains($refused, $accepted);
            $this->assertArrayHasKey($refused, MutationContract::rejected($operation));
        }

        $this->assertEqualsCanonicalizing(
            ['user_id', 'organization_id', 'rw_id', 'status'],
            MutationContract::unexpectedFields($operation, [
                'nik' => self::NIK,
                'name' => 'X',
                'rt_id' => 'rt',
                'user_id' => 'u',
                'organization_id' => 'o',
                'rw_id' => 'r',
                'status' => 'ACTIVE',
            ])
        );
    }

    public function test_no_operation_accepts_state_scope_or_source_fields(): void
    {
        $forbiddenEverywhere = [
            'status', 'new_status', 'is_active', 'is_head', 'user_id', 'organization_id', 'rw_id',
            'from_organization_id', 'from_rw_id', 'from_rt_id', 'from_household_id', 'recorded_by',
            'current_household_id', 'id',
        ];

        foreach (MutationContract::operations() as $operation) {
            $accepted = MutationContract::accepted($operation);
            $this->assertSame([], array_values(array_intersect($accepted, $forbiddenEverywhere)), "[{$operation}] accepts a forbidden field");
            $this->assertSame([], array_values(array_intersect($accepted, array_keys(MutationContract::rejected($operation)))), "[{$operation}] accepts a field it also rejects");
        }
    }

    public function test_patch_contracts_cannot_perform_lifecycle_or_relocation(): void
    {
        $resident = MutationContract::accepted(MutationContract::RESIDENT_UPDATE);
        foreach (['status', 'nik', 'current_household_id', 'organization_id', 'rw_id', 'rt_id', 'relationship', 'user_id'] as $field) {
            $this->assertNotContains($field, $resident);
            $this->assertArrayHasKey($field, MutationContract::rejected(MutationContract::RESIDENT_UPDATE));
        }

        $household = MutationContract::accepted(MutationContract::HOUSEHOLD_UPDATE);
        $this->assertSame(['address', 'notes'], $household);
        foreach (['rt_id', 'status', 'is_external_move', 'kk_number'] as $field) {
            $this->assertArrayHasKey($field, MutationContract::rejected(MutationContract::HOUSEHOLD_UPDATE));
        }
    }

    public function test_relocation_contract_takes_target_intent_only_and_defers_move_in_external(): void
    {
        $operation = MutationContract::RESIDENT_RELOCATE;

        foreach (['from_organization_id', 'from_rw_id', 'from_rt_id', 'from_household_id'] as $source) {
            $this->assertNotContains($source, MutationContract::accepted($operation));
            $this->assertArrayHasKey($source, MutationContract::rejected($operation));
        }

        $types = MutationContract::allowedValues($operation, 'movement_type');
        $this->assertNotNull($types);
        $this->assertNotContains('MOVE_IN_EXTERNAL', $types);
        $this->assertEqualsCanonicalizing(['TRANSFER_INTERNAL', 'MOVE_OUT_EXTERNAL', 'HOUSEHOLD_SPLIT'], $types);
        $this->assertSame(['MOVE_IN_EXTERNAL'], MutationContract::deferredMovementTypes());
    }

    public function test_status_contract_is_intent_based_and_cannot_set_moved_or_arbitrary_state(): void
    {
        $intents = MutationContract::allowedValues(MutationContract::RESIDENT_STATUS, 'intent');

        $this->assertSame(['DEACTIVATE', 'REACTIVATE', 'MARK_DECEASED'], $intents);
        $this->assertNotContains('MOVED', $intents);
        $this->assertNotContains('status', MutationContract::accepted(MutationContract::RESIDENT_STATUS));
        $this->assertNotContains('status', MutationContract::accepted(MutationContract::RESIDENT_VERIFY));
    }

    public function test_idempotency_policy_marks_creating_and_relocating_commands_as_required(): void
    {
        foreach ([
            MutationContract::RESIDENT_REGISTER,
            MutationContract::RESIDENT_RELOCATE,
            MutationContract::HOUSEHOLD_CREATE,
            MutationContract::HOUSEHOLD_ADD_MEMBER,
            MutationContract::HOUSEHOLD_MOVE,
        ] as $operation) {
            $this->assertTrue(MutationContract::requiresIdempotencyKey($operation), $operation);
        }

        $this->assertFalse(MutationContract::requiresIdempotencyKey(MutationContract::RESIDENT_VERIFY));
    }

    public function test_domain_does_not_trust_client_scope_or_status_on_registration(): void
    {
        $otherOrg = Organization::factory()->create(['name' => 'Other Org']);
        $action = app(RegisterResidentAction::class);

        // organization_id that does not match the RT's authoritative hierarchy is refused.
        try {
            $action->execute(new RegisterResidentInput(
                nik: '3201011205900010',
                name: 'Scope Forger',
                gender: ResidentGender::Male,
                birthPlace: 'Jakarta',
                birthDate: '1991-01-01',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Single,
                organizationId: $otherOrg->id,
                rwId: $this->rw->id,
                rtId: $this->rt->id,
            ), $this->ketuaRt);
            $this->fail('Mismatched organization/RW/RT hierarchy must be rejected.');
        } catch (ResidentScopeMismatch $e) {
            $this->assertSame('resident_scope_mismatch', $e->errorCode);
        }

        // Even a hostile initial status is ignored: registration is always PROSPECTIVE.
        $resident = $action->execute(new RegisterResidentInput(
            nik: '3201011205900011',
            name: 'Status Forger',
            gender: ResidentGender::Female,
            birthPlace: 'Jakarta',
            birthDate: '1991-01-01',
            religion: 'ISLAM',
            maritalStatus: ResidentMaritalStatus::Single,
            organizationId: $this->org->id,
            rwId: $this->rw->id,
            rtId: $this->rt->id,
            status: ResidentStatus::Active,
        ), $this->ketuaRt);

        $this->assertSame(ResidentStatus::Prospective, $resident->status);
    }
}
