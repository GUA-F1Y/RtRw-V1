<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationScopeTest extends TestCase
{
    public function test_rw_user_can_view_own_rw_and_nested_rts(): void
    {
        $organization = Organization::factory()->create();
        $rw5 = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 5]);
        $rt1 = Rt::factory()->create(['rw_id' => $rw5->id, 'number' => 1]);
        $rt2 = Rt::factory()->create(['rw_id' => $rw5->id, 'number' => 2]);

        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw5);

        // Can view own RW
        $response = $this->getJson("/api/v1/rws/{$rw5->id}");
        $response->assertStatus(200)->assertJsonPath('data.id', $rw5->id);

        // Can view RTs nested inside own RW
        $rtsResponse = $this->getJson("/api/v1/rws/{$rw5->id}/rts");
        $rtsResponse->assertStatus(200);
        $this->assertCount(2, $rtsResponse->json('data'));

        // Can view individual RT inside own RW
        $rtResponse = $this->getJson("/api/v1/rts/{$rt1->id}");
        $rtResponse->assertStatus(200)->assertJsonPath('data.id', $rt1->id);
    }

    public function test_rw_user_cannot_view_or_access_another_rw(): void
    {
        $organization = Organization::factory()->create();
        $rw5 = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 5]);
        $rw6 = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 6]);

        // Authenticate as Ketua RW 05
        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw5);

        // Attempting to access RW 06 is forbidden
        $response = $this->getJson("/api/v1/rws/{$rw6->id}");
        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        // Attempting to list RTs in RW 06 is forbidden
        $rtsResponse = $this->getJson("/api/v1/rws/{$rw6->id}/rts");
        $rtsResponse->assertStatus(403);
    }

    public function test_rt_user_cannot_access_another_rt_even_in_same_rw(): void
    {
        $organization = Organization::factory()->create();
        $rw = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);
        $rt1 = Rt::factory()->create(['rw_id' => $rw->id, 'number' => 1]);
        $rt2 = Rt::factory()->create(['rw_id' => $rw->id, 'number' => 2]);

        // Authenticate as Ketua RT 01
        $this->actAsScopedUser(RoleKey::KetuaRt, $organization, $rw, $rt1);

        // Viewing own RT succeeds
        $this->getJson("/api/v1/rts/{$rt1->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $rt1->id);

        // Viewing another RT is rejected with 403 forbidden
        $this->getJson("/api/v1/rts/{$rt2->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_rt_user_cannot_create_rt_in_rw(): void
    {
        $organization = Organization::factory()->create();
        $rw = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);
        $rt1 = Rt::factory()->create(['rw_id' => $rw->id, 'number' => 1]);

        // Ketua RT 01 does NOT have rt.manage at the RW scope
        $this->actAsScopedUser(RoleKey::KetuaRt, $organization, $rw, $rt1);

        $response = $this->postJson("/api/v1/rws/{$rw->id}/rts", [
            'number' => 9,
            'name' => 'RT 09',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_frontend_supplied_scope_cannot_bypass_authorization_when_assigning_membership(): void
    {
        $organization = Organization::factory()->create();
        $rw5 = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 5]);
        $rw6 = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 6]);

        // Authenticate as Ketua RW 05
        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw5);

        $targetUser = User::factory()->create();
        $ketuaRwRole = Role::query()->where('key', RoleKey::KetuaRw->value)->firstOrFail();

        // Attempt to assign membership to RW 06 (outside of caller's authority)
        $response = $this->postJson('/api/v1/memberships', [
            'user_id' => $targetUser->id,
            'role_id' => $ketuaRwRole->id,
            'scope_type' => 'rw',
            'scope_id' => $rw6->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_assigning_role_with_mismatched_scope_level_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $rw5 = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 5]);

        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw5);

        $targetUser = User::factory()->create();
        // Ketua RT role has scope_level = 'rt', cannot be assigned at 'rw' level
        $ketuaRtRole = Role::query()->where('key', RoleKey::KetuaRt->value)->firstOrFail();

        $response = $this->postJson('/api/v1/memberships', [
            'user_id' => $targetUser->id,
            'role_id' => $ketuaRtRole->id,
            'scope_type' => 'rw',
            'scope_id' => $rw5->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'business_rule_violation');
    }

    public function test_rw_officer_can_view_own_organization_but_not_another_organization(): void
    {
        $org1 = Organization::factory()->create(['name' => 'Org 1']);
        $org2 = Organization::factory()->create(['name' => 'Org 2']);
        $rw = Rw::factory()->create(['organization_id' => $org1->id, 'number' => 1]);

        $this->actAsScopedUser(RoleKey::KetuaRw, $org1, $rw);

        // Can view own organization
        $this->getJson("/api/v1/organizations/{$org1->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $org1->id);

        // Cannot view another organization
        $this->getJson("/api/v1/organizations/{$org2->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_rt_officer_can_view_own_parent_rw_and_organization_but_not_other_rws(): void
    {
        $org = Organization::factory()->create();
        $rw1 = Rw::factory()->create(['organization_id' => $org->id, 'number' => 1]);
        $rw2 = Rw::factory()->create(['organization_id' => $org->id, 'number' => 2]);
        $rt = Rt::factory()->create(['rw_id' => $rw1->id, 'number' => 1]);

        $this->actAsScopedUser(RoleKey::KetuaRt, $org, $rw1, $rt);

        // Can view own parent RW
        $this->getJson("/api/v1/rws/{$rw1->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $rw1->id);

        // Can view own organization
        $this->getJson("/api/v1/organizations/{$org->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $org->id);

        // Cannot view another RW in same organization
        $this->getJson("/api/v1/rws/{$rw2->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_organization_isolation_user_in_org_a_cannot_access_org_b(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $rwA = Rw::factory()->create(['organization_id' => $orgA->id, 'number' => 1]);
        $rwB = Rw::factory()->create(['organization_id' => $orgB->id, 'number' => 1]);
        $rtB = Rt::factory()->create(['rw_id' => $rwB->id, 'number' => 1]);

        $this->actAsScopedUser(RoleKey::KetuaRw, $orgA, $rwA);

        $this->getJson("/api/v1/organizations/{$orgB->id}")
            ->assertStatus(403);
        $this->getJson("/api/v1/rws/{$rwB->id}")
            ->assertStatus(403);
        $this->getJson("/api/v1/rts/{$rtB->id}")
            ->assertStatus(403);
    }

    public function test_revoked_membership_immediately_stops_granting_access(): void
    {
        $org = Organization::factory()->create();
        $rw = Rw::factory()->create(['organization_id' => $org->id, 'number' => 1]);

        $user = $this->actAsScopedUser(RoleKey::KetuaRw, $org, $rw);

        // Access works
        $this->getJson("/api/v1/rws/{$rw->id}")->assertStatus(200);

        // Revoke the membership
        $membership = Membership::query()->where('user_id', $user->id)->firstOrFail();
        $membership->update(['revoked_at' => now()]);

        // Next request is immediately denied
        $this->getJson("/api/v1/rws/{$rw->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_user_with_multiple_memberships_combines_permissions_without_scope_bleeding(): void
    {
        $org = Organization::factory()->create();
        $rw5 = Rw::factory()->create(['organization_id' => $org->id, 'number' => 5]);
        $rw6 = Rw::factory()->create(['organization_id' => $org->id, 'number' => 6]);
        $rt1 = Rt::factory()->create(['rw_id' => $rw5->id, 'number' => 1]);
        $rt2 = Rt::factory()->create(['rw_id' => $rw5->id, 'number' => 2]);

        // User is Ketua RT 01 (under RW 5) AND Sekretaris RW 06
        $user = $this->createScopedUser(RoleKey::KetuaRt, $org, $rw5, $rt1);

        $roleSekretaris = Role::query()->where('key', RoleKey::SekretarisRw->value)->firstOrFail();
        Membership::query()->create([
            'user_id' => $user->id,
            'role_id' => $roleSekretaris->id,
            'scope_level' => RoleKey::SekretarisRw->scopeLevel(),
            'organization_id' => $org->id,
            'rw_id' => $rw6->id,
            'rt_id' => null,
            'granted_by' => null,
            'revoked_at' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        // Can view own RT 01
        $this->getJson("/api/v1/rts/{$rt1->id}")->assertStatus(200);

        // Cannot view RT 02 in same RW (Ketua RT 01 scope does not extend to RT 02)
        $this->getJson("/api/v1/rts/{$rt2->id}")->assertStatus(403);

        // Can view RW 06 (granted by Sekretaris RW 06)
        $this->getJson("/api/v1/rws/{$rw6->id}")->assertStatus(200);

        // Can view parent RW 05 (granted because user is RT officer under RW 05)
        $this->getJson("/api/v1/rws/{$rw5->id}")->assertStatus(200);

        // But cannot create RT in RW 05 (requires RtManage at RW scope, neither role has this)
        $this->postJson("/api/v1/rws/{$rw5->id}/rts", [
            'number' => 99,
            'name' => 'RT 99',
        ])->assertStatus(403);

        // Nor create RT in RW 06 (Sekretaris RW does not have RtManage permission)
        $this->postJson("/api/v1/rws/{$rw6->id}/rts", [
            'number' => 99,
            'name' => 'RT 99',
        ])->assertStatus(403);
    }
}
