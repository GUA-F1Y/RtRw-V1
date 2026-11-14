<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Resident\Models\HouseholdMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Household membership representation with a privacy-safe resident SUMMARY only.
 *
 * The resident summary is `{ id, name }` and nothing else: no NIK, phone, birth data,
 * religion, blood type, documents or nested resources. It is rendered only when the
 * caller eager-loaded `resident`. There is no `household` back-reference (no recursion).
 *
 * @mixin HouseholdMember
 */
class HouseholdMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'resident_id' => $this->resident_id,
            'relationship' => $this->relationship?->value,
            'is_active' => (bool) $this->is_active,
            'joined_at' => $this->joined_at?->format('Y-m-d'),
            'left_at' => $this->left_at?->format('Y-m-d'),
            'left_reason' => $this->left_reason,
        ];

        if ($this->relationLoaded('resident') && $this->resident !== null) {
            $data['resident'] = [
                'id' => $this->resident->id,
                'name' => $this->resident->name,
            ];
        }

        return $data;
    }
}
