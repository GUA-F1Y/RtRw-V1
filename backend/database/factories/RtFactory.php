<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Enums\UnitStatus;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rt>
 */
class RtFactory extends Factory
{
    protected $model = Rt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->numberBetween(1, 99);

        return [
            'rw_id' => Rw::factory(),
            'number' => $number,
            'name' => sprintf('RT %02d', $number),
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
