<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\File\Models\Attachment;
use App\Domain\File\Models\StoredFile;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Enums\ResidencyMovementType;
use App\Domain\Resident\Enums\ResidentBloodType;
use App\Domain\Resident\Enums\ResidentEducation;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Enums\ResidencyType;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\ResidencyMovement;
use App\Domain\Resident\Models\Resident;
use App\Domain\Resident\Models\ResidentStatusHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResidentHouseholdModelTest extends TestCase
{
    private Organization $org;
    private Rw $rw;
    private Rt $rt;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->rw = Rw::factory()->create(['organization_id' => $this->org->id, 'number' => 1]);
        $this->rt = Rt::factory()->create(['rw_id' => $this->rw->id, 'number' => 1]);
        $this->user = User::factory()->create();
    }

    // =========================================================================
    // 1. RESIDENT MODEL TESTS
    // =========================================================================

    public function test_resident_belongs_to_organization_rw_rt(): void
    {
        $resident = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010101',
            'name' => 'Ahmad Dahlan',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Yogyakarta',
            'birth_date' => '1985-08-01',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Married,
            'blood_type' => ResidentBloodType::O,
            'education' => ResidentEducation::SarjanaS1,
            'residency_type' => ResidencyType::Permanent,
            'status' => ResidentStatus::Active,
        ]);

        $this->assertTrue($resident->organization->is($this->org));
        $this->assertTrue($resident->rw->is($this->rw));
        $this->assertTrue($resident->rt->is($this->rt));
    }

    public function test_resident_user_relationship_is_optional(): void
    {
        $residentWithoutUser = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'user_id' => null,
            'nik' => '3201010101010102',
            'name' => 'Siti Aminah',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Bandung',
            'birth_date' => '1992-03-10',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'blood_type' => ResidentBloodType::A,
            'education' => ResidentEducation::Sma,
            'residency_type' => ResidencyType::Permanent,
            'status' => ResidentStatus::Active,
        ]);

        $this->assertNull($residentWithoutUser->user);

        // Associate user
        $residentWithoutUser->user()->associate($this->user);
        $residentWithoutUser->save();

        $this->assertTrue($residentWithoutUser->fresh()->user->is($this->user));
    }

    public function test_resident_current_household_relationship(): void
    {
        $household = Household::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'kk_number' => '3201010101010103',
            'address' => 'Jl. Merpati No. 10',
            'status' => HouseholdStatus::Active,
        ]);

        $resident = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'current_household_id' => $household->id,
            'nik' => '3201010101010104',
            'name' => 'Bambang Sudarmono',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Surabaya',
            'birth_date' => '1978-11-20',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Married,
            'blood_type' => ResidentBloodType::B,
            'education' => ResidentEducation::MagisterS2,
            'residency_type' => ResidencyType::Permanent,
            'status' => ResidentStatus::Active,
        ]);

        $this->assertTrue($resident->currentHousehold->is($household));
    }

    public function test_resident_enum_and_date_casts(): void
    {
        $resident = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010105',
            'name' => 'Dewi Sartika',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Semarang',
            'birth_date' => '1995-12-05',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'blood_type' => ResidentBloodType::AbPos,
            'education' => ResidentEducation::Diploma,
            'residency_type' => ResidencyType::Temporary,
            'status' => ResidentStatus::Prospective,
        ]);

        $fresh = $resident->fresh();

        $this->assertInstanceOf(ResidentGender::class, $fresh->gender);
        $this->assertSame(ResidentGender::Female, $fresh->gender);

        $this->assertInstanceOf(ResidentMaritalStatus::class, $fresh->marital_status);
        $this->assertSame(ResidentMaritalStatus::Single, $fresh->marital_status);

        $this->assertInstanceOf(ResidentBloodType::class, $fresh->blood_type);
        $this->assertSame(ResidentBloodType::AbPos, $fresh->blood_type);

        $this->assertInstanceOf(ResidentEducation::class, $fresh->education);
        $this->assertSame(ResidentEducation::Diploma, $fresh->education);

        $this->assertInstanceOf(ResidencyType::class, $fresh->residency_type);
        $this->assertSame(ResidencyType::Temporary, $fresh->residency_type);

        $this->assertInstanceOf(ResidentStatus::class, $fresh->status);
        $this->assertSame(ResidentStatus::Prospective, $fresh->status);

        $this->assertInstanceOf(CarbonInterface::class, $fresh->birth_date);
        $this->assertSame('1995-12-05', $fresh->birth_date->format('Y-m-d'));
    }

    public function test_resident_attachments_relationship(): void
    {
        $resident = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010106',
            'name' => 'Attachment Subject',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Active,
        ]);

        $file = StoredFile::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'disk' => 'local',
            'path' => 'ktp/ktp_scan.pdf',
            'original_name' => 'ktp_scan',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'checksum_sha256' => hash('sha256', 'ktp_scan_test'),
            'visibility' => 'private',
        ]);

        $attachment = Attachment::query()->create([
            'file_id' => $file->id,
            'attachable_type' => 'resident',
            'attachable_id' => $resident->id,
            'collection' => 'ktp',
        ]);

        $this->assertCount(1, $resident->attachments);
        $this->assertTrue($resident->attachments->first()->is($attachment));
    }

    public function test_resident_scopes(): void
    {
        $activeResident = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010107',
            'name' => 'Active Scope Resident',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Active,
        ]);

        $movedResident = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010108',
            'name' => 'Moved Scope Resident',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Jakarta',
            'birth_date' => '1992-01-01',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Moved,
        ]);

        $this->assertTrue(Resident::query()->active()->pluck('id')->contains($activeResident->id));
        $this->assertFalse(Resident::query()->active()->pluck('id')->contains($movedResident->id));
        $this->assertTrue(Resident::query()->moved()->pluck('id')->contains($movedResident->id));
    }

    // =========================================================================
    // 2. HOUSEHOLD MODEL TESTS
    // =========================================================================

    public function test_household_belongs_to_organization_rw_rt(): void
    {
        $household = Household::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'kk_number' => '3201010101010201',
            'address' => 'Jl. Anggrek No. 5',
            'status' => HouseholdStatus::Active,
        ]);

        $this->assertTrue($household->organization->is($this->org));
        $this->assertTrue($household->rw->is($this->rw));
        $this->assertTrue($household->rt->is($this->rt));
    }

    public function test_household_members_and_active_members_relationships(): void
    {
        $household = Household::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'kk_number' => '3201010101010202',
            'address' => 'Jl. Cempaka No. 12',
            'status' => HouseholdStatus::Active,
        ]);

        $res1 = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010203',
            'name' => 'Active Member Res',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '1990-01-01',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Active,
        ]);

        $res2 = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010204',
            'name' => 'Departed Member Res',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Jakarta',
            'birth_date' => '1993-01-01',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Moved,
        ]);

        $activeMember = HouseholdMember::query()->create([
            'household_id' => $household->id,
            'resident_id' => $res1->id,
            'relationship' => HouseholdMemberRelationship::Anak,
            'is_active' => true,
        ]);

        $historicalMember = HouseholdMember::query()->create([
            'household_id' => $household->id,
            'resident_id' => $res2->id,
            'relationship' => HouseholdMemberRelationship::Anak,
            'is_active' => false,
            'joined_at' => '2020-01-01',
            'left_at' => '2026-09-01',
            'left_reason' => 'Menikah',
        ]);

        $this->assertCount(2, $household->members);
        $this->assertCount(1, $household->activeMembers);
        $this->assertTrue($household->activeMembers->first()->is($activeMember));
    }

    public function test_household_active_head_resolves_only_active_kepala_keluarga(): void
    {
        $household = Household::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'kk_number' => '3201010101010205',
            'address' => 'Jl. Flamboyan No. 1',
            'status' => HouseholdStatus::Active,
        ]);

        $headRes = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010206',
            'name' => 'Kepala Keluarga Res',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '1970-01-01',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Married,
            'status' => ResidentStatus::Active,
        ]);

        $childRes = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010207',
            'name' => 'Anak Res',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Jakarta',
            'birth_date' => '2005-01-01',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Active,
        ]);

        $activeHead = HouseholdMember::query()->create([
            'household_id' => $household->id,
            'resident_id' => $headRes->id,
            'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
            'is_active' => true,
        ]);

        HouseholdMember::query()->create([
            'household_id' => $household->id,
            'resident_id' => $childRes->id,
            'relationship' => HouseholdMemberRelationship::Anak,
            'is_active' => true,
        ]);

        $this->assertNotNull($household->activeHead);
        $this->assertTrue($household->activeHead->is($activeHead));
        $this->assertTrue($household->activeHead->resident->is($headRes));
    }

    public function test_household_residents_relationship(): void
    {
        $household = Household::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'kk_number' => '3201010101010208',
            'address' => 'Jl. Dahlia No. 4',
            'status' => HouseholdStatus::Active,
        ]);

        $resident = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'current_household_id' => $household->id,
            'nik' => '3201010101010209',
            'name' => 'Resident Linked To Household',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '1994-01-01',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Active,
        ]);

        $this->assertCount(1, $household->residents);
        $this->assertTrue($household->residents->first()->is($resident));
    }

    // =========================================================================
    // 3. HOUSEHOLD MEMBER MODEL TESTS
    // =========================================================================

    public function test_household_member_relationships_and_casts(): void
    {
        $household = Household::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'kk_number' => '3201010101010301',
            'address' => 'Jl. Melati No. 2',
            'status' => HouseholdStatus::Active,
        ]);

        $resident = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010302',
            'name' => 'Member Relation Test Res',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Jakarta',
            'birth_date' => '1998-04-12',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Active,
        ]);

        $member = HouseholdMember::query()->create([
            'household_id' => $household->id,
            'resident_id' => $resident->id,
            'relationship' => HouseholdMemberRelationship::Istri,
            'is_active' => true,
            'joined_at' => '2026-01-15',
        ]);

        $this->assertTrue($member->household->is($household));
        $this->assertTrue($member->resident->is($resident));
        $this->assertInstanceOf(HouseholdMemberRelationship::class, $member->relationship);
        $this->assertSame(HouseholdMemberRelationship::Istri, $member->relationship);
        $this->assertTrue($member->is_active);
    }

    // =========================================================================
    // 4. RESIDENCY MOVEMENT MODEL TESTS
    // =========================================================================

    public function test_residency_movement_relationships_and_casts(): void
    {
        $household = Household::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'kk_number' => '3201010101010401',
            'address' => 'Jl. Anyelir No. 8',
            'status' => HouseholdStatus::Active,
        ]);

        $resident = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010402',
            'name' => 'Movement Test Resident',
            'gender' => ResidentGender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '1991-07-21',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Active,
        ]);

        $movement = ResidencyMovement::query()->create([
            'resident_id' => $resident->id,
            'movement_type' => ResidencyMovementType::MoveInExternal,
            'from_organization_id' => null,
            'from_rw_id' => null,
            'from_rt_id' => null,
            'from_household_id' => null,
            'to_organization_id' => $this->org->id,
            'to_rw_id' => $this->rw->id,
            'to_rt_id' => $this->rt->id,
            'to_household_id' => $household->id,
            'effective_date' => '2026-10-01',
            'reference_letter_number' => 'SKP/2026/001',
            'notes' => 'Pindahan dari luar wilayah',
            'recorded_by' => $this->user->id,
        ]);

        $this->assertTrue($movement->resident->is($resident));
        $this->assertTrue($movement->toOrganization->is($this->org));
        $this->assertTrue($movement->toRw->is($this->rw));
        $this->assertTrue($movement->toRt->is($this->rt));
        $this->assertTrue($movement->toHousehold->is($household));
        $this->assertTrue($movement->recordedBy->is($this->user));
        $this->assertSame(ResidencyMovementType::MoveInExternal, $movement->movement_type);

        // Verify resident's hasMany relationship
        $this->assertCount(1, $resident->residencyMovements);
        $this->assertTrue($resident->residencyMovements->first()->is($movement));
    }

    // =========================================================================
    // 5. RESIDENT STATUS HISTORY MODEL TESTS
    // =========================================================================

    public function test_resident_status_history_relationships_casts_and_scopes(): void
    {
        $resident = Resident::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'nik' => '3201010101010501',
            'name' => 'Status History Test Resident',
            'gender' => ResidentGender::Female,
            'birth_place' => 'Jakarta',
            'birth_date' => '1996-09-18',
            'religion' => 'ISLAM',
            'marital_status' => ResidentMaritalStatus::Single,
            'status' => ResidentStatus::Active,
        ]);

        $history1 = ResidentStatusHistory::query()->create([
            'resident_id' => $resident->id,
            'previous_status' => ResidentStatus::Prospective,
            'new_status' => ResidentStatus::Active,
            'reason' => 'Identitas terverifikasi',
            'effective_at' => '2026-01-01 08:00:00',
            'recorded_by' => $this->user->id,
        ]);

        $history2 = ResidentStatusHistory::query()->create([
            'resident_id' => $resident->id,
            'previous_status' => ResidentStatus::Active,
            'new_status' => ResidentStatus::Inactive,
            'reason' => 'Menjadi non-aktif',
            'effective_at' => '2026-06-01 08:00:00',
            'recorded_by' => $this->user->id,
        ]);

        $this->assertTrue($history1->resident->is($resident));
        $this->assertTrue($history1->recordedBy->is($this->user));
        $this->assertSame(ResidentStatus::Prospective, $history1->previous_status);
        $this->assertSame(ResidentStatus::Active, $history1->new_status);

        // Verify resident hasMany statusHistory
        $this->assertCount(2, $resident->statusHistory);

        // Verify chronological scope
        $chronological = ResidentStatusHistory::query()->where('resident_id', $resident->id)->chronological()->get();
        $this->assertTrue($chronological->first()->is($history1));
        $this->assertTrue($chronological->last()->is($history2));
    }
}
