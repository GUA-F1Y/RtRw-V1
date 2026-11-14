<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Authorization\Enums\PrivacyTier;
use App\Domain\Resident\Models\Resident;
use App\Http\Resources\Concerns\HasPrivacyTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialization boundary for a Resident. Three explicit tiers (see PrivacyTier):
 *
 *  - Directory : community-safe fields only. No NIK, no phone, no birth date, no religion,
 *                no blood type, no household link, no ledgers, no user link.
 *  - Normal    : profile detail. NIK and phone are present but MASKED.
 *  - Sensitive : full NIK, full phone, religion, blood type, linked user id.
 *
 * The tier is chosen by the authorized caller (ResidentPolicy::representationTier()).
 * This class performs no authorization, issues no queries, and ignores the request.
 *
 * @mixin Resident
 */
class ResidentResource extends JsonResource
{
    use HasPrivacyTier;

    /**
     * Masks a 16-digit Indonesian NIK:
     * Prefix: 6 digits (regional code: province, regency, district)
     * Masked: 6 digits (birth date code)
     * Suffix: 4 digits (unique sequence)
     * Example: 320101******0005
     */
    public static function maskNik(?string $nik): ?string
    {
        if ($nik === null || strlen($nik) < 10) {
            return $nik !== null ? str_repeat('*', strlen($nik)) : null;
        }

        $prefix = substr($nik, 0, 6);
        $suffix = substr($nik, -4);
        $maskedLength = max(0, strlen($nik) - 10);

        return $prefix . str_repeat('*', $maskedLength) . $suffix;
    }

    /**
     * Masks telephone number preserving international/area prefix and last 3 digits.
     * Example: +62812****7890
     */
    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) < 7) {
            return $phone !== null ? str_repeat('*', strlen($phone)) : null;
        }

        $prefix = substr($phone, 0, 4);
        $suffix = substr($phone, -3);
        $maskedLength = max(0, strlen($phone) - 7);

        return $prefix . str_repeat('*', $maskedLength) . $suffix;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tier = $this->tier;

        // ---- Directory (always present) -------------------------------------------------
        $data = [
            'id' => $this->id,
            'representation' => $tier->value,
            'name' => $this->name,
            'gender' => $this->gender?->value,
            'age' => $this->birth_date?->age,
            'residency_type' => $this->residency_type?->value,
            'status' => $this->status?->value,
            'organization_id' => $this->organization_id,
            'rw_id' => $this->rw_id,
            'rt_id' => $this->rt_id,
        ];

        if ($this->relationLoaded('organization') && $this->organization !== null) {
            $data['organization'] = new OrganizationResource($this->organization);
        }
        if ($this->relationLoaded('rw') && $this->rw !== null) {
            $data['rw'] = new RwResource($this->rw);
        }
        if ($this->relationLoaded('rt') && $this->rt !== null) {
            $data['rt'] = new RtResource($this->rt);
        }

        if (! $tier->atLeast(PrivacyTier::Normal)) {
            return $data;
        }

        // ---- Normal (resident.view): identifiers masked ---------------------------------
        $data['birth_place'] = $this->birth_place;
        $data['birth_date'] = $this->birth_date?->format('Y-m-d');
        $data['marital_status'] = $this->marital_status?->value;
        $data['occupation'] = $this->occupation;
        $data['education'] = $this->education?->value;
        $data['current_household_id'] = $this->current_household_id;
        $data['nik'] = self::maskNik($this->nik);
        $data['nik_masked'] = true;
        $data['phone'] = self::maskPhone($this->phone);
        $data['phone_masked'] = $this->phone !== null;
        $data['created_at'] = $this->created_at?->toISOString();
        $data['updated_at'] = $this->updated_at?->toISOString();

        // Nested resources are explicit, non-recursive and never exceed what this tier allows:
        // the household is always rendered at its safe (Directory) shape, memberships are
        // summaries, and ledgers follow the resident tier (restricted metadata only when Sensitive).
        if ($this->relationLoaded('currentHousehold') && $this->currentHousehold !== null) {
            $data['current_household'] = HouseholdResource::at($this->currentHousehold, PrivacyTier::Directory);
        }
        if ($this->relationLoaded('householdMemberships')) {
            $data['household_memberships'] = HouseholdMemberResource::collection($this->householdMemberships);
        }

        $ledgerTier = $tier === PrivacyTier::Sensitive ? PrivacyTier::Sensitive : PrivacyTier::Normal;
        if ($this->relationLoaded('residencyMovements')) {
            $data['residency_movements'] = ResidencyMovementResource::collectionAt($this->residencyMovements, $ledgerTier);
        }
        if ($this->relationLoaded('statusHistory')) {
            $data['status_history'] = ResidentStatusHistoryResource::collectionAt($this->statusHistory, $ledgerTier);
        }

        if ($tier !== PrivacyTier::Sensitive) {
            return $data;
        }

        // ---- Sensitive (resident.view + resident.view_sensitive) ------------------------
        $data['nik'] = $this->nik;
        $data['nik_masked'] = false;
        $data['phone'] = $this->phone;
        $data['phone_masked'] = false;
        $data['religion'] = $this->religion;
        $data['blood_type'] = $this->blood_type?->value;
        $data['user_id'] = $this->user_id;

        return $data;
    }
}
