<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Authorization\Enums\PrivacyTier;
use App\Domain\Resident\Models\ResidencyMovement;
use App\Http\Resources\Concerns\HasPrivacyTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * READ-ONLY representation of the residency movement ledger. There is no mutation
 * contract for movement records; corrections are compensating domain operations.
 *
 * Safe shape (any tier except Sensitive): id, resident_id, movement_type, effective_date,
 * from/to scope summaries, created_at.
 *
 * Sensitive shape (caller holds resident.view_sensitive on the resident): additionally
 * reference_letter_number, notes, attachment_file_id (+ attachment_file when loaded) and
 * recorded_by. No nested resident is ever embedded (avoids recursion / over-exposure).
 *
 * @mixin ResidencyMovement
 */
class ResidencyMovementResource extends JsonResource
{
    use HasPrivacyTier;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'representation' => $this->tier->value,
            'resident_id' => $this->resident_id,
            'movement_type' => $this->movement_type?->value,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'from_scope' => [
                'organization_id' => $this->from_organization_id,
                'rw_id' => $this->from_rw_id,
                'rt_id' => $this->from_rt_id,
                'household_id' => $this->from_household_id,
                'rw_number' => $this->relationLoaded('fromRw') ? $this->fromRw?->number : null,
                'rt_number' => $this->relationLoaded('fromRt') ? $this->fromRt?->number : null,
            ],
            'to_scope' => [
                'organization_id' => $this->to_organization_id,
                'rw_id' => $this->to_rw_id,
                'rt_id' => $this->to_rt_id,
                'household_id' => $this->to_household_id,
                'rw_number' => $this->relationLoaded('toRw') ? $this->toRw?->number : null,
                'rt_number' => $this->relationLoaded('toRt') ? $this->toRt?->number : null,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];

        // Household context is always rendered at its safe (Directory) shape: no KK, no address.
        if ($this->relationLoaded('fromHousehold') && $this->fromHousehold !== null) {
            $data['from_household'] = HouseholdResource::at($this->fromHousehold, PrivacyTier::Directory);
        }
        if ($this->relationLoaded('toHousehold') && $this->toHousehold !== null) {
            $data['to_household'] = HouseholdResource::at($this->toHousehold, PrivacyTier::Directory);
        }

        if ($this->tier !== PrivacyTier::Sensitive) {
            return $data;
        }

        // ---- Restricted historical metadata ---------------------------------------------
        $data['reference_letter_number'] = $this->reference_letter_number;
        $data['attachment_file_id'] = $this->attachment_file_id;
        $data['notes'] = $this->notes;
        $data['recorded_by'] = [
            'id' => $this->recorded_by,
            'name' => $this->relationLoaded('recordedBy') ? $this->recordedBy?->name : null,
        ];

        if ($this->relationLoaded('attachmentFile') && $this->attachmentFile !== null) {
            $data['attachment_file'] = new StoredFileResource($this->attachmentFile);
        }

        return $data;
    }
}
