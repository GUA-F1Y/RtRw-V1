<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Authorization\Enums\ScopeLevel;
use App\Domain\Authorization\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roleKey = fake()->randomElement(RoleKey::cases());

        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->jobTitle(),
            'scope_level' => fake()->randomElement(ScopeLevel::cases()),
            'description' => fake()->sentence(),
        ];
    }

    public function forRoleKey(RoleKey $key): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key->value,
            'name' => $key->label(),
            'scope_level' => $key->scopeLevel(),
            'description' => $key->label(),
        ]);
    }
}
