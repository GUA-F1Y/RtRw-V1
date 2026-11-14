<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\File\Models\StoredFile;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Enums\AccountStatus;
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
use Illuminate\Support\Str;
use Tests\TestCase;

class ResidentHouseholdMutationApiTest extends TestCase
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

    private User $ketuaRtA1_1;
    private User $ketuaRtA1_2;
    private User $ketuaRwA1;
    private User $ketuaRtB1_1;
    private User $regularUser;
    private User $suspendedUser;

    private Resident $residentA1_1;
    private Resident $prospectiveA1_1;
    private Resident $residentA1_2;
    private Resident $residentOrgB;

    private Household $householdA1_1;
    private Household $householdA1_2;
    private Household $householdOrgB;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Structure
        $this->orgA = Organization::factory()->create(['name' => 'Organisasi A']);
        $this->orgB = Organization::factory()->create(['name' => 'Organisasi B']);

        $this->rwA1 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 1]);
        $this->rwA2 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 2]);
        $this->rwB1 = Rw::factory()->create(['organization_id' => $this->orgB->id, 'number' => 1]);

        $this->rtA1_1 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 1]);
        $this->rtA1_2 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 2]);
        $this->rtA2_1 = Rt::factory()->create(['rw_id' => $this->rwA2->id, 'number' => 1]);
        $this->rtB1_1 = Rt::factory()->create(['rw_id' => $this->rwB1->id, 'number' => 1]);

        // 2. Actors
        $this->ketuaRtA1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $this->ketuaRtA1_2 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_2);
        $this->ketuaRwA1 = $this->createScopedUser(RoleKey::KetuaRw, $this->orgA, $this->rwA1);
        $this->ketuaRtB1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgB, $this->rwB1, $this->rtB1_1);
        $this->regularUser = User::factory()->create(['status' => AccountStatus::Active]);
        $this->suspendedUser = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1, AccountStatus::Suspended);

        // 3. Households
        $this->householdA1_1 = Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'kk_number' => '3201011205900001',
            'address' => 'Jl. Mawar No. 1',
            'status' => HouseholdStatus::Active,
        ]);

        $this->householdA1_2 = Household::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_2->id,
            'kk_number' => '3201011205900002',
            'address' => 'Jl. Melati No. 2',
            'status' => HouseholdStatus::Active,
        ]);

        $this->householdOrgB = Household::query()->create([
            'organization_id' => $this->orgB->id,
            'rw_id' => $this->rwB1->id,
            'rt_id' => $this->rtB1_1->id,
            'kk_number' => '3202011205900001',
            'address' => 'Jl. Diponegoro No. 3',
            'status' => HouseholdStatus::Active,
        ]);

        // 4. Residents
        $this->residentA1_1 = Resident::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'current_household_id' => $this->householdA1_1->id,
            'nik' => '3201011205900001',
            'name' => 'Budi Santoso',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Bogor',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Married,
            'status' => ResidentStatus::Active,
        ]);

        HouseholdMember::query()->create([
            'household_id' => $this->householdA1_1->id,
            'resident_id' => $this->residentA1_1->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
            'is_active' => true,
            'joined_at' => '2020-01-01',
        ]);

        $this->prospectiveA1_1 = Resident::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'nik' => '3201011205900002',
            'name' => 'Calon Warga',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Jakarta',
            'birth_date' => '1995-05-05',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Prospective,
        ]);

        $this->residentA1_2 = Resident::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_2->id,
            'current_household_id' => $this->householdA1_2->id,
            'nik' => '3201011205900003',
            'name' => 'Warga RT 2',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Depok',
            'birth_date' => '1988-08-08',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Active,
        ]);

        HouseholdMember::query()->create([
            'household_id' => $this->householdA1_2->id,
            'resident_id' => $this->residentA1_2->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
            'is_active' => true,
            'joined_at' => '2020-01-01',
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
            'birth_date' => '1985-02-02',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Married,
            'status' => ResidentStatus::Active,
        ]);

        HouseholdMember::query()->create([
            'household_id' => $this->householdOrgB->id,
            'resident_id' => $this->residentOrgB->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
            'is_active' => true,
            'joined_at' => '2020-01-01',
        ]);
    }

    protected function assertValidationErrorField($response, string $field): void
    {
        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
        $errors = $response->json('error.errors', []);
        $this->assertArrayHasKey($field, $errors, "Expected validation error for field [{$field}], but got: " . json_encode($errors));
    }

    // =========================================================================
    // 1. REGISTRATION TESTS (POST /api/v1/residents)
    // =========================================================================

    public function test_resident_registration_success(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'nik' => '3201011205909999',
            'name' => 'Warga Baru Terdaftar',
            'gender' => 'MALE',
            'birth_place' => 'Bandung',
            'birth_date' => '1998-10-10',
            'religion' => 'ISLAM',
            'marital_status' => 'SINGLE',
            'rt_id' => $this->rtA1_1->id,
            'occupation' => 'Software Engineer',
            'education' => 'SARJANA_S1',
            'phone' => '+6281233445566',
        ];

        $response = $this->postJson('/api/v1/residents', $payload, [
            'Idempotency-Key' => 'register-key-1',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Warga Baru Terdaftar')
            ->assertJsonPath('data.status', 'PROSPECTIVE');

        $this->assertDatabaseHas('residents', [
            'nik' => '3201011205909999',
            'status' => ResidentStatus::Prospective->value,
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
        ]);
    }

    public function test_resident_registration_missing_idempotency_key_fails(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'nik' => '3201011205909998',
            'name' => 'Test Missing Idempotency',
            'gender' => 'MALE',
            'birth_place' => 'Bandung',
            'birth_date' => '1998-10-10',
            'religion' => 'ISLAM',
            'marital_status' => 'SINGLE',
            'rt_id' => $this->rtA1_1->id,
        ];

        $response = $this->postJson('/api/v1/residents', $payload);
        $response->assertStatus(400)->assertJsonPath('error.code', 'idempotency_key_required');
    }

    public function test_resident_registration_idempotent_replay(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'nik' => '3201011205909997',
            'name' => 'Replay Resident',
            'gender' => 'FEMALE',
            'birth_place' => 'Bogor',
            'birth_date' => '1999-01-01',
            'religion' => 'ISLAM',
            'marital_status' => 'SINGLE',
            'rt_id' => $this->rtA1_1->id,
        ];

        $first = $this->postJson('/api/v1/residents', $payload, ['Idempotency-Key' => 'replay-key-res']);
        $first->assertStatus(201);

        $second = $this->postJson('/api/v1/residents', $payload, ['Idempotency-Key' => 'replay-key-res']);
        $second->assertStatus(201)->assertHeader('Idempotent-Replayed', 'true');

        // Same key, different payload -> 422 idempotency_key_reused
        $differentPayload = array_merge($payload, ['name' => 'Changed Name']);
        $conflict = $this->postJson('/api/v1/residents', $differentPayload, ['Idempotency-Key' => 'replay-key-res']);
        $conflict->assertStatus(422)->assertJsonPath('error.code', 'idempotency_key_reused');
    }

    public function test_resident_registration_overposting_forbidden_fields_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $forbiddenPayloads = [
            'status' => 'ACTIVE',
            'organization_id' => (string) Str::uuid(),
            'rw_id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'current_household_id' => (string) Str::uuid(),
            'id' => (string) Str::uuid(),
        ];

        foreach ($forbiddenPayloads as $forbiddenKey => $forbiddenValue) {
            $payload = [
                'nik' => '3201011205908888',
                'name' => 'Overpost Test',
                'gender' => 'MALE',
                'birth_place' => 'Bogor',
                'birth_date' => '1990-01-01',
                'religion' => 'ISLAM',
                'marital_status' => 'SINGLE',
                'rt_id' => $this->rtA1_1->id,
                $forbiddenKey => $forbiddenValue,
            ];

            $response = $this->postJson('/api/v1/residents', $payload, [
                'Idempotency-Key' => 'overpost-' . $forbiddenKey,
            ]);

            $this->assertValidationErrorField($response, $forbiddenKey);
        }
    }

    public function test_resident_registration_duplicate_nik_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'nik' => $this->residentA1_1->nik, // existing NIK
            'name' => 'Duplicate NIK Person',
            'gender' => 'MALE',
            'birth_place' => 'Bogor',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => 'SINGLE',
            'rt_id' => $this->rtA1_1->id,
        ];

        $response = $this->postJson('/api/v1/residents', $payload, [
            'Idempotency-Key' => 'duplicate-nik-test',
        ]);

        $response->assertStatus(409)->assertJsonPath('error.code', 'duplicate_nik');
    }

    public function test_resident_registration_invalid_nik_syntax_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'nik' => '12345', // Not 16 digits
            'name' => 'Bad NIK Person',
            'gender' => 'MALE',
            'birth_place' => 'Bogor',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => 'SINGLE',
            'rt_id' => $this->rtA1_1->id,
        ];

        $response = $this->postJson('/api/v1/residents', $payload, [
            'Idempotency-Key' => 'bad-nik-test',
        ]);

        $this->assertValidationErrorField($response, 'nik');
    }

    // =========================================================================
    // 2. RESIDENT PROFILE UPDATE (PATCH /api/v1/residents/{resident})
    // =========================================================================

    public function test_resident_profile_update_success(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'occupation' => 'Senior Architect',
            'education' => 'SARJANA_S1',
            'phone' => '+6281112223334',
            'marital_status' => 'MARRIED',
        ];

        $response = $this->patchJson("/api/v1/residents/{$this->residentA1_1->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.occupation', 'Senior Architect')
            ->assertJsonPath('data.phone', '+6281112223334');

        $this->assertDatabaseHas('residents', [
            'id' => $this->residentA1_1->id,
            'occupation' => 'Senior Architect',
            'phone' => '+6281112223334',
        ]);
    }

    public function test_resident_profile_update_forbidden_fields_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $forbiddenFields = [
            'nik' => '3201019999999999',
            'name' => 'Hacked Name',
            'status' => 'INACTIVE',
            'rt_id' => $this->rtA1_2->id,
            'rw_id' => $this->rwA2->id,
            'organization_id' => $this->orgB->id,
            'current_household_id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'relationship' => 'KEPALA_KELUARGA',
        ];

        foreach ($forbiddenFields as $field => $val) {
            $response = $this->patchJson("/api/v1/residents/{$this->residentA1_1->id}", [
                $field => $val,
            ]);

            $this->assertValidationErrorField($response, $field);
        }
    }

    // =========================================================================
    // 3. RESIDENT VERIFICATION (POST /api/v1/residents/{resident}/verify)
    // =========================================================================

    public function test_resident_verify_prospective_to_active_success(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->postJson("/api/v1/residents/{$this->prospectiveA1_1->id}/verify", [
            'notes' => 'Dokumen fisik KTP & KK sesuai',
            'reference_letter_number' => 'SK/2026/001',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertDatabaseHas('residents', [
            'id' => $this->prospectiveA1_1->id,
            'status' => ResidentStatus::Active->value,
        ]);
    }

    public function test_resident_verify_already_active_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->postJson("/api/v1/residents/{$this->residentA1_1->id}/verify", [
            'notes' => 'Coba verifikasi resident yang sudah ACTIVE',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_resident_status_transition');
    }

    public function test_resident_verify_status_override_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->postJson("/api/v1/residents/{$this->prospectiveA1_1->id}/verify", [
            'status' => 'ACTIVE',
        ]);

        $this->assertValidationErrorField($response, 'status');
    }

    // =========================================================================
    // 4. RESIDENT STATUS COMMAND (POST /api/v1/residents/{resident}/status)
    // =========================================================================

    public function test_resident_status_deactivate_and_reactivate(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        // Deactivate
        $deactResponse = $this->postJson("/api/v1/residents/{$this->residentA1_1->id}/status", [
            'intent' => 'DEACTIVATE',
            'reason' => 'Tugas belajar ke luar negeri sementara',
        ]);

        $deactResponse->assertStatus(200)->assertJsonPath('data.status', 'INACTIVE');
        $this->assertDatabaseHas('residents', ['id' => $this->residentA1_1->id, 'status' => ResidentStatus::Inactive->value]);

        // Reactivate
        $reactResponse = $this->postJson("/api/v1/residents/{$this->residentA1_1->id}/status", [
            'intent' => 'REACTIVATE',
            'reason' => 'Kembali dari tugas belajar',
        ]);

        $reactResponse->assertStatus(200)->assertJsonPath('data.status', 'ACTIVE');
        $this->assertDatabaseHas('residents', ['id' => $this->residentA1_1->id, 'status' => ResidentStatus::Active->value]);
    }

    public function test_resident_status_mark_deceased_executes_civil_death(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->postJson("/api/v1/residents/{$this->residentA1_1->id}/status", [
            'intent' => 'MARK_DECEASED',
            'reason' => 'Surat kematian No. 474.3/01/2026',
            'reference_letter_number' => 'SK/MATI/001',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'DECEASED');

        $this->assertDatabaseHas('residents', [
            'id' => $this->residentA1_1->id,
            'status' => ResidentStatus::Deceased->value,
            'current_household_id' => null,
        ]);

        // Household membership deactivated with left_reason DECEASED
        $this->assertDatabaseHas('household_members', [
            'household_id' => $this->householdA1_1->id,
            'resident_id' => $this->residentA1_1->id,
            'is_active' => false,
            'left_reason' => 'DECEASED',
        ]);
    }

    public function test_resident_status_invalid_intent_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->postJson("/api/v1/residents/{$this->residentA1_1->id}/status", [
            'intent' => 'INVALID_INTENT',
            'reason' => 'Random intent',
        ]);

        $this->assertValidationErrorField($response, 'intent');
    }

    public function test_resident_status_client_supplied_status_field_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->postJson("/api/v1/residents/{$this->residentA1_1->id}/status", [
            'intent' => 'DEACTIVATE',
            'status' => 'INACTIVE',
        ]);

        $this->assertValidationErrorField($response, 'status');
    }

    // =========================================================================
    // 5. RESIDENT RELOCATION (POST /api/v1/residents/{resident}/relocate)
    // =========================================================================

    public function test_resident_relocation_transfer_internal(): void
    {
        // Ketua RW has scope over both RT 1 and RT 2
        $this->actingAs($this->ketuaRwA1);

        $response = $this->postJson("/api/v1/residents/{$this->residentA1_1->id}/relocate", [
            'movement_type' => 'TRANSFER_INTERNAL',
            'target_rt_id' => $this->rtA1_2->id,
            'target_household_id' => $this->householdA1_2->id,
            'notes' => 'Pindah domisili ke RT 2',
        ], ['Idempotency-Key' => 'relocate-key-1']);

        $response->assertStatus(200)
            ->assertJsonPath('data.movement_type', 'TRANSFER_INTERNAL')
            ->assertJsonPath('data.to_scope.rt_id', $this->rtA1_2->id);

        $this->assertDatabaseHas('residents', [
            'id' => $this->residentA1_1->id,
            'rt_id' => $this->rtA1_2->id,
            'current_household_id' => $this->householdA1_2->id,
        ]);

        $this->assertDatabaseHas('residency_movements', [
            'resident_id' => $this->residentA1_1->id,
            'movement_type' => ResidencyMovementType::TransferInternal->value,
            'from_rt_id' => $this->rtA1_1->id,
            'to_rt_id' => $this->rtA1_2->id,
        ]);
    }

    public function test_resident_relocation_move_in_external_is_not_exposed(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->postJson("/api/v1/residents/{$this->residentA1_1->id}/relocate", [
            'movement_type' => 'MOVE_IN_EXTERNAL',
            'target_rt_id' => $this->rtA1_1->id,
        ], ['Idempotency-Key' => 'movein-key']);

        $this->assertValidationErrorField($response, 'movement_type');
    }

    public function test_resident_relocation_source_from_fields_rejected(): void
    {
        $this->actingAs($this->ketuaRwA1);

        $response = $this->postJson("/api/v1/residents/{$this->residentA1_1->id}/relocate", [
            'movement_type' => 'TRANSFER_INTERNAL',
            'target_rt_id' => $this->rtA1_2->id,
            'from_rt_id' => $this->rtA1_1->id,
            'recorded_by' => (string) Str::uuid(),
        ], ['Idempotency-Key' => 'overpost-relocate']);

        $this->assertValidationErrorField($response, 'from_rt_id');
        $this->assertValidationErrorField($response, 'recorded_by');
    }

    public function test_resident_relocation_dual_scope_authorization_enforced(): void
    {
        // Ketua RT 1 has authority over RT 1, but NOT RT 2!
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->postJson("/api/v1/residents/{$this->residentA1_1->id}/relocate", [
            'movement_type' => 'TRANSFER_INTERNAL',
            'target_rt_id' => $this->rtA1_2->id,
        ], ['Idempotency-Key' => 'dual-scope-denied']);

        // Should be forbidden because actor lacks ResidentRelocate on target RT scope!
        $response->assertStatus(403);
    }

    // =========================================================================
    // 6. RESIDENT REMOVE FROM HOUSEHOLD (POST /api/v1/residents/{resident}/remove-from-household)
    // =========================================================================

    public function test_resident_remove_from_household_endpoint(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->postJson("/api/v1/residents/{$this->residentA1_1->id}/remove-from-household", [
            'left_reason' => 'Pindah ke kos',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('residents', [
            'id' => $this->residentA1_1->id,
            'current_household_id' => null,
        ]);

        $this->assertDatabaseHas('household_members', [
            'resident_id' => $this->residentA1_1->id,
            'household_id' => $this->householdA1_1->id,
            'is_active' => false,
            'left_reason' => 'Pindah ke kos',
        ]);
    }

    // =========================================================================
    // 7. HOUSEHOLD CREATE (POST /api/v1/households)
    // =========================================================================

    public function test_household_create_success(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'kk_number' => '3201011205908888',
            'address' => 'Jl. Baru No. 12',
            'rt_id' => $this->rtA1_1->id,
            'notes' => 'Keluarga baru',
        ];

        $response = $this->postJson('/api/v1/households', $payload, [
            'Idempotency-Key' => 'hh-create-1',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.address', 'Jl. Baru No. 12')
            ->assertJsonPath('data.status', 'HEAD_VACANT');

        $this->assertDatabaseHas('households', [
            'kk_number' => '3201011205908888',
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
        ]);
    }

    public function test_household_create_with_active_initial_head_success(): void
    {
        $this->actingAs($this->ketuaRtA1_2);

        // Remove residentA1_2 from existing household first
        $this->residentA1_2->update(['current_household_id' => null]);
        HouseholdMember::where('resident_id', $this->residentA1_2->id)->update(['is_active' => false]);

        $payload = [
            'kk_number' => '3201011205907777',
            'address' => 'Jl. Pinus No. 5',
            'rt_id' => $this->rtA1_2->id,
            'initial_head_resident_id' => $this->residentA1_2->id,
        ];

        $response = $this->postJson('/api/v1/households', $payload, [
            'Idempotency-Key' => 'hh-create-with-head',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_household_create_prospective_head_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'kk_number' => '3201011205906666',
            'address' => 'Jl. Anggrek No. 3',
            'rt_id' => $this->rtA1_1->id,
            'initial_head_resident_id' => $this->prospectiveA1_1->id, // Prospective
        ];

        $response = $this->postJson('/api/v1/households', $payload, [
            'Idempotency-Key' => 'hh-create-prosp-head',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'household_membership_conflict');
    }

    public function test_household_create_forbidden_fields_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'kk_number' => '3201011205905555',
            'address' => 'Jl. Flamboyan No. 7',
            'rt_id' => $this->rtA1_1->id,
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'status' => 'ACTIVE',
        ];

        $response = $this->postJson('/api/v1/households', $payload, [
            'Idempotency-Key' => 'hh-overpost',
        ]);

        $this->assertValidationErrorField($response, 'organization_id');
        $this->assertValidationErrorField($response, 'rw_id');
        $this->assertValidationErrorField($response, 'status');
    }

    // =========================================================================
    // 8. HOUSEHOLD UPDATE (PATCH /api/v1/households/{household})
    // =========================================================================

    public function test_household_update_success(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'address' => 'Jl. Mawar No. 1A (Renovasi)',
            'notes' => 'Catatan diperbarui',
        ];

        $response = $this->patchJson("/api/v1/households/{$this->householdA1_1->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.address', 'Jl. Mawar No. 1A (Renovasi)');

        $this->assertDatabaseHas('households', [
            'id' => $this->householdA1_1->id,
            'address' => 'Jl. Mawar No. 1A (Renovasi)',
        ]);
    }

    public function test_household_update_forbidden_fields_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $forbidden = [
            'kk_number' => '3201019999999999',
            'status' => 'DISSOLVED',
            'rt_id' => $this->rtA1_2->id,
            'rw_id' => $this->rwA2->id,
            'organization_id' => $this->orgB->id,
            'is_external_move' => true,
        ];

        foreach ($forbidden as $field => $val) {
            $response = $this->patchJson("/api/v1/households/{$this->householdA1_1->id}", [
                $field => $val,
            ]);

            $this->assertValidationErrorField($response, $field);
        }
    }

    // =========================================================================
    // 9. HOUSEHOLD MEMBERS (POST /households/{household}/members & DELETE /households/{household}/members/{resident})
    // =========================================================================

    public function test_household_add_member_success(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        // Verify prospectiveA1_1 first
        $this->prospectiveA1_1->status = ResidentStatus::Active;
        $this->prospectiveA1_1->save();

        $payload = [
            'resident_id' => $this->prospectiveA1_1->id,
            'relationship' => 'ANAK',
            'joined_at' => '2026-01-01',
        ];

        $response = $this->postJson("/api/v1/households/{$this->householdA1_1->id}/members", $payload, [
            'Idempotency-Key' => 'add-member-1',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('household_members', [
            'household_id' => $this->householdA1_1->id,
            'resident_id' => $this->prospectiveA1_1->id,
            'relationship' => HouseholdMemberRelationship::Anak->value,
            'is_active' => true,
        ]);
    }

    public function test_household_add_member_prospective_resident_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        // prospectiveA1_1 is still Prospective
        $payload = [
            'resident_id' => $this->prospectiveA1_1->id,
            'relationship' => 'ANAK',
        ];

        $response = $this->postJson("/api/v1/households/{$this->householdA1_1->id}/members", $payload, [
            'Idempotency-Key' => 'add-prosp-member',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'household_membership_conflict');
    }

    public function test_household_remove_member_via_delete_route(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->deleteJson("/api/v1/households/{$this->householdA1_1->id}/members/{$this->residentA1_1->id}", [
            'left_reason' => 'Keluar dari KK',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('household_members', [
            'household_id' => $this->householdA1_1->id,
            'resident_id' => $this->residentA1_1->id,
            'is_active' => false,
        ]);
    }

    public function test_household_remove_member_not_belonging_to_household_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        // residentA1_2 belongs to householdA1_2, NOT householdA1_1!
        $response = $this->deleteJson("/api/v1/households/{$this->householdA1_1->id}/members/{$this->residentA1_2->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'resident_scope_mismatch');
    }

    // =========================================================================
    // 10. CHANGE HOUSEHOLD HEAD (POST /api/v1/households/{household}/change-head)
    // =========================================================================

    public function test_household_change_head_success(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        // Add second active resident to householdA1_1
        $memberResident = Resident::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'current_household_id' => $this->householdA1_1->id,
            'nik' => '3201011205904444',
            'name' => 'Calon Kepala Baru',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Bogor',
            'birth_date' => '1992-02-02',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Married,
            'status' => ResidentStatus::Active,
        ]);

        HouseholdMember::query()->create([
            'household_id' => $this->householdA1_1->id,
            'resident_id' => $memberResident->id,
            'relationship' => HouseholdMemberRelationship::Istri,
            'is_active' => true,
            'joined_at' => '2020-01-01',
        ]);

        $payload = [
            'new_head_resident_id' => $memberResident->id,
            'old_head_new_relationship' => 'SUAMI',
        ];

        $response = $this->postJson("/api/v1/households/{$this->householdA1_1->id}/change-head", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('household_members', [
            'household_id' => $this->householdA1_1->id,
            'resident_id' => $memberResident->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga->value,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('household_members', [
            'household_id' => $this->householdA1_1->id,
            'resident_id' => $this->residentA1_1->id,
            'relationship' => HouseholdMemberRelationship::Suami->value,
            'is_active' => true,
        ]);
    }

    public function test_household_change_head_non_member_candidate_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'new_head_resident_id' => $this->residentA1_2->id, // Member of RT 2 household!
        ];

        $response = $this->postJson("/api/v1/households/{$this->householdA1_1->id}/change-head", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_household_head_change');
    }

    // =========================================================================
    // 11. HOUSEHOLD MOVE (POST /api/v1/households/{household}/move)
    // =========================================================================

    public function test_household_move_success(): void
    {
        // Ketua RW has scope over both RT 1 and RT 2
        $this->actingAs($this->ketuaRwA1);

        $payload = [
            'move_type' => 'INTER_RT',
            'target_rt_id' => $this->rtA1_2->id,
            'new_address' => 'Jl. RT2 Pindahan No. 50',
            'notes' => 'Pindah RT satu RW',
        ];

        $response = $this->postJson("/api/v1/households/{$this->householdA1_1->id}/move", $payload, [
            'Idempotency-Key' => 'hh-move-key-1',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.address', 'Jl. RT2 Pindahan No. 50');

        $this->assertDatabaseHas('households', [
            'id' => $this->householdA1_1->id,
            'rt_id' => $this->rtA1_2->id,
            'address' => 'Jl. RT2 Pindahan No. 50',
        ]);

        $this->assertDatabaseHas('residents', [
            'id' => $this->residentA1_1->id,
            'rt_id' => $this->rtA1_2->id,
            'current_household_id' => $this->householdA1_1->id,
        ]);
    }

    public function test_household_move_external_is_deferred_and_rejected(): void
    {
        $this->actingAs($this->ketuaRwA1);

        $payload = [
            'move_type' => 'EXTERNAL',
            'target_rt_id' => $this->rtA1_2->id,
            'new_address' => 'Jl. Luar',
        ];

        $response = $this->postJson("/api/v1/households/{$this->householdA1_1->id}/move", $payload, [
            'Idempotency-Key' => 'hh-move-external',
        ]);

        $this->assertValidationErrorField($response, 'move_type');
    }

    // =========================================================================
    // 12. HOUSEHOLD DISSOLVE (POST /api/v1/households/{household}/dissolve)
    // =========================================================================

    public function test_household_dissolve_success(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->postJson("/api/v1/households/{$this->householdA1_1->id}/dissolve", [
            'reason' => 'Keluarga bubar secara administratif',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'DISSOLVED');

        $this->assertDatabaseHas('households', [
            'id' => $this->householdA1_1->id,
            'status' => HouseholdStatus::Dissolved->value,
        ]);

        $this->assertDatabaseHas('residents', [
            'id' => $this->residentA1_1->id,
            'current_household_id' => null,
        ]);
    }

    public function test_household_dissolve_already_dissolved_rejected(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $this->householdA1_1->status = HouseholdStatus::Dissolved;
        $this->householdA1_1->save();

        $response = $this->postJson("/api/v1/households/{$this->householdA1_1->id}/dissolve", [
            'reason' => 'Coba bubarkan lagi',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'household_state_conflict');
    }

    // =========================================================================
    // 13. IDOR / CROSS-SCOPE MUTATION SECURITY (Sections 27 & 43)
    // =========================================================================

    public function test_idor_cross_rt_mutations_denied(): void
    {
        // Ketua RT 1 tries to mutate RT 2 resident & household
        $this->actingAs($this->ketuaRtA1_1);

        // Update RT 2 resident
        $res = $this->patchJson("/api/v1/residents/{$this->residentA1_2->id}", ['phone' => '+628999999999']);
        $res->assertStatus(403);

        // Verify RT 2 resident
        $res = $this->postJson("/api/v1/residents/{$this->residentA1_2->id}/verify");
        $res->assertStatus(403);

        // Change status RT 2 resident
        $res = $this->postJson("/api/v1/residents/{$this->residentA1_2->id}/status", ['intent' => 'DEACTIVATE']);
        $res->assertStatus(403);

        // Update RT 2 household
        $res = $this->patchJson("/api/v1/households/{$this->householdA1_2->id}", ['address' => 'Jl. Hacked']);
        $res->assertStatus(403);

        // Dissolve RT 2 household
        $res = $this->postJson("/api/v1/households/{$this->householdA1_2->id}/dissolve");
        $res->assertStatus(403);
    }

    public function test_idor_cross_org_mutations_denied(): void
    {
        // Ketua RT in Org A tries to mutate Org B resident & household
        $this->actingAs($this->ketuaRtA1_1);

        // Update Org B resident
        $res = $this->patchJson("/api/v1/residents/{$this->residentOrgB->id}", ['phone' => '+628999999999']);
        $res->assertStatus(403);

        // Update Org B household
        $res = $this->patchJson("/api/v1/households/{$this->householdOrgB->id}", ['address' => 'Jl. Hacked Org B']);
        $res->assertStatus(403);

        // Register resident in Org B RT
        $res = $this->postJson('/api/v1/residents', [
            'nik' => '3202011205903333',
            'name' => 'Warga Org B Baru',
            'gender' => 'MALE',
            'birth_place' => 'Surabaya',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => 'SINGLE',
            'rt_id' => $this->rtB1_1->id,
        ], ['Idempotency-Key' => 'cross-org-reg']);
        $res->assertStatus(403);
    }

    public function test_suspended_account_cannot_mutate(): void
    {
        $this->actingAs($this->suspendedUser);

        $res = $this->patchJson("/api/v1/residents/{$this->residentA1_1->id}", ['phone' => '+628999999999']);
        $res->assertStatus(403)->assertJsonPath('error.code', 'account_inactive');
    }

    public function test_unauthenticated_request_denied(): void
    {
        $res = $this->postJson('/api/v1/residents', [
            'nik' => '3201011205901111',
            'name' => 'No Auth Person',
            'gender' => 'MALE',
            'birth_place' => 'Bogor',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => 'SINGLE',
            'rt_id' => $this->rtA1_1->id,
        ], ['Idempotency-Key' => 'no-auth-reg']);

        $res->assertStatus(401);
    }

    // =========================================================================
    // 14. EXPLICIT ACTOR, ATTACHMENT, IDEMPOTENCY & ATOMICITY
    // =========================================================================

    public function test_mutations_record_authenticated_actor_and_never_fallback_user(): void
    {
        // Ensure there is another user that is "first"
        $firstUser = User::query()->orderBy('id')->first();
        $this->assertNotNull($firstUser);

        // Authenticated actor is ketuaRtA1_1 (which is different from first user or explicitly distinct)
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'nik' => '3201011205906655',
            'name' => 'Warga Explicit Actor',
            'gender' => 'MALE',
            'birth_place' => 'Bogor',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => 'SINGLE',
            'rt_id' => $this->rtA1_1->id,
        ];

        $res = $this->postJson('/api/v1/residents', $payload, ['Idempotency-Key' => 'explicit-actor-reg']);
        $res->assertStatus(201);

        $residentId = $res->json('data.id');

        // Verify status history recorded_by is exactly ketuaRtA1_1
        $this->assertDatabaseHas('resident_status_history', [
            'resident_id' => $residentId,
            'recorded_by' => $this->ketuaRtA1_1->id,
        ]);

        // Verify audit log causer_id is exactly ketuaRtA1_1
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $residentId,
            'causer_id' => $this->ketuaRtA1_1->id,
            'event' => 'resident.registered',
        ]);
    }

    public function test_attachment_access_validation_protects_evidence(): void
    {
        // Create file in Org B owned by Org B user
        $foreignFile = StoredFile::query()->create([
            'owner_id' => $this->ketuaRtB1_1->id,
            'organization_id' => $this->orgB->id,
            'rw_id' => $this->rwB1->id,
            'rt_id' => $this->rtB1_1->id,
            'disk' => 'local',
            'path' => 'files/foreign.pdf',
            'original_name' => 'foreign.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', 'content'),
            'visibility' => 'private',
            'category' => 'civil_registry',
        ]);

        // Ketua RT A1_1 tries to attach foreign file
        $this->actingAs($this->ketuaRtA1_1);

        $response = $this->postJson("/api/v1/residents/{$this->prospectiveA1_1->id}/verify", [
            'notes' => 'Attempt foreign file attach',
            'attachment_file_id' => $foreignFile->id,
        ]);

        $this->assertValidationErrorField($response, 'attachment_file_id');

        // Create file owned by Ketua RT A1_1 in RT A1_1
        $validFile = StoredFile::query()->create([
            'owner_id' => $this->ketuaRtA1_1->id,
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'disk' => 'local',
            'path' => 'files/valid.pdf',
            'original_name' => 'valid.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', 'valid-content'),
            'visibility' => 'private',
            'category' => 'civil_registry',
        ]);

        $validResponse = $this->postJson("/api/v1/residents/{$this->prospectiveA1_1->id}/verify", [
            'notes' => 'Valid file attached',
            'attachment_file_id' => $validFile->id,
        ]);

        $validResponse->assertStatus(200);
    }

    public function test_idempotency_request_in_progress_returns_409(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $payload = [
            'nik' => '3201011205901234',
            'name' => 'In Progress Test',
            'gender' => 'MALE',
            'birth_place' => 'Bogor',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => 'SINGLE',
            'rt_id' => $this->rtA1_1->id,
        ];

        $sortedInput = $payload;
        ksort($sortedInput);
        $requestHash = hash('sha256', json_encode([
            'POST',
            'api/v1/residents',
            [],
            $sortedInput,
            [],
        ], JSON_THROW_ON_ERROR));

        // Pre-insert an in-progress idempotency key (completed_at is null)
        \Illuminate\Support\Facades\DB::table('idempotency_keys')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->ketuaRtA1_1->id,
            'key' => 'in-progress-key',
            'request_method' => 'POST',
            'request_path' => 'api/v1/residents',
            'request_hash' => $requestHash,
            'response_status' => null,
            'response_body' => null,
            'completed_at' => null,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/residents', $payload, [
            'Idempotency-Key' => 'in-progress-key',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'idempotency_request_in_progress');
    }

    public function test_domain_failure_leaves_no_partial_state(): void
    {
        $this->actingAs($this->ketuaRtA1_1);

        $initialResidentCount = Resident::count();
        $initialHistoryCount = \App\Domain\Resident\Models\ResidentStatusHistory::count();

        // Attempt registration with duplicate NIK
        $this->postJson('/api/v1/residents', [
            'nik' => $this->residentA1_1->nik,
            'name' => 'Failed Person',
            'gender' => 'MALE',
            'birth_place' => 'Bogor',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => 'SINGLE',
            'rt_id' => $this->rtA1_1->id,
        ], ['Idempotency-Key' => 'failed-reg-atomicity']);

        $this->assertSame($initialResidentCount, Resident::count());
        $this->assertSame($initialHistoryCount, \App\Domain\Resident\Models\ResidentStatusHistory::count());
    }
}

