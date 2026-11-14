<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Overview\DTOs\OverviewSnapshot;
use App\Domain\Overview\DTOs\RtOverviewMetricDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OverviewSnapshot
 */
class OverviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Pure serialization-only layer. Zero aggregate queries or policy checks are executed here.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OverviewSnapshot $snapshot */
        $snapshot = $this->resource;

        return [
            'scope' => [
                'level' => $snapshot->scope->level,
                'id' => $snapshot->scope->id,
                'name' => $snapshot->scope->name,
            ],
            'population' => [
                'total' => $snapshot->population->total,
                'active' => $snapshot->population->active,
                'prospective' => $snapshot->population->prospective,
                'inactive' => $snapshot->population->inactive,
                'moved' => $snapshot->population->moved,
                'deceased' => $snapshot->population->deceased,
            ],
            'households' => [
                'active' => $snapshot->households->active,
                'head_assigned' => $snapshot->households->headAssigned,
                'head_vacant' => $snapshot->households->headVacant,
                'moved' => $snapshot->households->moved,
                'dissolved' => $snapshot->households->dissolved,
            ],
            'rt_breakdown' => array_map(
                static fn (RtOverviewMetricDto $rt): array => [
                    'rt_id' => $rt->rtId,
                    'rt_code' => $rt->rtCode,
                    'resident_count' => $rt->residentCount,
                    'household_count' => $rt->householdCount,
                    'head_vacant_household_count' => $rt->headVacantHouseholdCount,
                ],
                $snapshot->rtBreakdown
            ),
        ];
    }
}
