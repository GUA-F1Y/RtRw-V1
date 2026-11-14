<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Identity\Enums\AccountStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rw;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_unauthenticated_request_to_protected_route_is_rejected_with_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonStructure([
                'error' => ['code', 'message', 'request_id'],
            ]);
    }

    public function test_login_with_valid_credentials_returns_sanctum_token(): void
    {
        $organization = Organization::factory()->create();
        $rw = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);

        $user = $this->createScopedUser(
            RoleKey::KetuaRw,
            $organization,
            $rw,
            null,
            AccountStatus::Active
        );
        $user->forceFill(['password' => Hash::make('Secret123!')])->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
            'device_name' => 'test-client',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'status',
                        'memberships',
                    ],
                ],
                'meta' => ['request_id'],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword'),
            'status' => AccountStatus::Active,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_suspended_account_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secret123!'),
            'status' => AccountStatus::Suspended,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'account_inactive');
    }

    public function test_authenticated_user_can_retrieve_me_profile_with_active_memberships(): void
    {
        $organization = Organization::factory()->create();
        $rw = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 5]);

        $user = $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonCount(1, 'data.memberships')
            ->assertJsonPath('data.memberships.0.role.key', RoleKey::KetuaRw->value)
            ->assertJsonPath('data.memberships.0.rw_id', $rw->id);
    }

    public function test_logout_revokes_current_access_token(): void
    {
        $user = User::factory()->create(['status' => AccountStatus::Active]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Logged out successfully.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
