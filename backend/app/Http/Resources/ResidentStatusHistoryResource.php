<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Authorization\Enums\PrivacyTier;
use App\Domain\Resident\Models\ResidentStatusHistory;
use App\Http\Resources\Concerns\HasPrivacyTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * READ-ONLY representation of the resident status ledger. There is no mutation contract
 * for history records; a correction is a new compensating status operation.
 *
 * Safe shape (any tier except Sensitive): id, resident_id, previous_status, new_status,
 * effective_at, created_at.
 *
 * Sensitive shape (caller holds resident.view_sensitive on the resident): additionally
 * reason, reference_letter_number, attachment_file_id (+ attachment_file when loaded) and
 * recorded_by. No nested resident is ever embedded.
 *
 * @mixin ResidentStatusHistory
 */
class ResidentStatusHistoryResource extends JsonResource
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
            'previous_status' => $this->previous_status?->value,
            'new_status' => $this->new_status?->value,
            'effective_at' => $this->effective_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];

        if ($this->tier !== PrivacyTier::Sensitive) {
            return $data;
        }

        // ---- Restricted historical metadata ---------------------------------------------
        $data['reason'] = $this->reason;
        $data['reference_letter_number'] = $this->reference_letter_number;
        $data['attachment_file_id'] = $this->attachment_file_id;
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
