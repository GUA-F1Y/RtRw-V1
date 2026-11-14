<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Authorization\Enums\PrivacyTier;
use App\Domain\Household\Models\Household;
use App\Http\Resources\Concerns\HasPrivacyTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialization boundary for a Household (KK). Three explicit tiers (see PrivacyTier):
 *
 *  - Directory (safe): id, status, unit ids, member count. No KK number, no address, no notes.
 *  - Normal          : adds address, notes, head summary, relations; KK number MASKED.
 *  - Sensitive       : KK number in full.
 *
 * The tier is chosen by the authorized caller (HouseholdPolicy::representationTier()).
 * This class performs no authorization, issues no queries, and ignores the request.
 * Members are never dumped by default; they appear only when the caller eager-loaded them.
 *
 * @mixin Household
 */
class HouseholdResource extends JsonResource
{
    use HasPrivacyTier;

    /**
     * Masks a 16-digit Kartu Keluarga (KK) number:
     * Prefix: 6 digits (regional code: province, regency, district)
     * Masked: 6 digits
     * Suffix: 4 digits (unique sequence)
     * Example: 320101******0001
     */
    public static function maskKk(?string $kk): ?string
    {
        if ($kk === null || strlen($kk) < 10) {
            return $kk !== null ? str_repeat('*', strlen($kk)) : null;
        }

        $prefix = substr($kk, 0, 6);
        $suffix = substr($kk, -4);
        $maskedLength = max(0, strlen($kk) - 10);

        return $prefix . str_repeat('*', $maskedLength) . $suffix;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tier = $this->tier;

        // ---- Directory / safe (always present) ------------------------------------------
        $data = [
            'id' => $this->id,
            'representation' => $tier->value,
            'status' => $this->status?->value,
            'organization_id' => $this->organization_id,
            'rw_id' => $this->rw_id,
            'rt_id' => $this->rt_id,
            'active_members_count' => $this->activeMembersCount(),
        ];

        if (! $tier->atLeast(PrivacyTier::Normal)) {
            return $data;
        }

        // ---- Normal (household.view): KK masked -----------------------------------------
        $data['kk_number'] = self::maskKk($this->kk_number);
        $data['kk_number_masked'] = true;
        $data['address'] = $this->address;
        $data['notes'] = $this->notes;
        $data['created_at'] = $this->created_at?->toISOString();
        $data['updated_at'] = $this->updated_at?->toISOString();

        // Explicit, non-recursive relations (only when the caller eager-loaded them).
        if ($this->relationLoaded('activeHead') && $this->activeHead !== null) {
            $data['active_head'] = new HouseholdMemberResource($this->activeHead);
        }
        if ($this->relationLoaded('members')) {
            $data['members'] = HouseholdMemberResource::collection($this->members);
        }
        if ($this->relationLoaded('activeMembers')) {
            $data['active_members'] = HouseholdMemberResource::collection($this->activeMembers);
        }
        if ($this->relationLoaded('organization') && $this->organization !== null) {
            $data['organization'] = new OrganizationResource($this->organization);
        }
        if ($this->relationLoaded('rw') && $this->rw !== null) {
            $data['rw'] = new RwResource($this->rw);
        }
        if ($this->relationLoaded('rt') && $this->rt !== null) {
            $data['rt'] = new RtResource($this->rt);
        }

        if ($tier !== PrivacyTier::Sensitive) {
            return $data;
        }

        // ---- Sensitive (household.view + household.view_sensitive) ----------------------
        $data['kk_number'] = $this->kk_number;
        $data['kk_number_masked'] = false;

        return $data;
    }

    /**
     * Uses a withCount() attribute or an eager-loaded relation; never triggers a query.
     */
    private function activeMembersCount(): ?int
    {
        $attributes = $this->resource->getAttributes();
        if (array_key_exists('active_members_count', $attributes)) {
            return (int) $attributes['active_members_count'];
        }

        return $this->relationLoaded('activeMembers') ? $this->activeMembers->count() : null;
    }
}
