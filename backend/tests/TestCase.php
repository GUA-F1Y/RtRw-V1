<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Enums\AccountStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Create a user and assign an active membership with the specified RoleKey at the appropriate scope.
     */
    protected function createScopedUser(
        RoleKey $roleKey,
        Organization $organization,
        ?Rw $rw = null,
        ?Rt $rt = null,
        AccountStatus $status = AccountStatus::Active
    ): User {
        /** @var User $user */
        $user = User::factory()->create([
            'status' => $status,
        ]);

        /** @var Role $role */
        $role = Role::query()->where('key', $roleKey->value)->firstOrFail();

        $membershipData = [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_level' => $roleKey->scopeLevel(),
            'organization_id' => $organization->id,
            'rw_id' => $rw?->id,
            'rt_id' => $rt?->id,
            'granted_by' => null,
            'revoked_at' => null,
        ];

        Membership::query()->create($membershipData);

        return $user;
    }

    /**
     * Authenticate a user using Sanctum for API tests.
     */
    protected function actAsScopedUser(
        RoleKey $roleKey,
        Organization $organization,
        ?Rw $rw = null,
        ?Rt $rt = null
    ): User {
        $user = $this->createScopedUser($roleKey, $organization, $rw, $rt);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }
}
