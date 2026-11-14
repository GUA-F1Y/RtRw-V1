<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Authorization\Enums\ScopeLevel;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role_id' => Role::factory(),
            'scope_level' => ScopeLevel::Organization,
            'organization_id' => Organization::factory(),
            'rw_id' => null,
            'rt_id' => null,
            'granted_by' => null,
            'revoked_at' => null,
        ];
    }

    public function forRw(Rw $rw, ?Role $role = null): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_level' => ScopeLevel::Rw,
            'organization_id' => $rw->organization_id,
            'rw_id' => $rw->id,
            'rt_id' => null,
            'role_id' => $role?->id ?? $attributes['role_id'],
        ]);
    }

    public function forRt(Rt $rt, ?Role $role = null): static
    {
        $rw = $rt->relationLoaded('rw') ? $rt->rw : $rt->rw()->first();

        return $this->state(fn (array $attributes) => [
            'scope_level' => ScopeLevel::Rt,
            'organization_id' => $rw->organization_id,
            'rw_id' => $rt->rw_id,
            'rt_id' => $rt->id,
            'role_id' => $role?->id ?? $attributes['role_id'],
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
