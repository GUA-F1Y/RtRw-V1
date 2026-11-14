<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Enums\UnitStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rw>
 */
class RwFactory extends Factory
{
    protected $model = Rw::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->numberBetween(1, 99);

        return [
            'organization_id' => Organization::factory(),
            'number' => $number,
            'name' => sprintf('RW %02d', $number),
            'status' => UnitStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UnitStatus::Inactive,
        ]);
    }
}
