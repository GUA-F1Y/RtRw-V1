<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Finance\Models\CashTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashTransfer
 */
class CashTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'rw_id' => $this->rw_id,
            'rt_id' => $this->rt_id,
            'source_cash_account_id' => $this->source_cash_account_id,
            'destination_cash_account_id' => $this->destination_cash_account_id,
            'amount' => (int) $this->amount,
            'transfer_date' => $this->transfer_date->toDateString(),
            'description' => $this->description,
            'reference_number' => $this->reference_number,
            'evidence_file_id' => $this->evidence_file_id,
            'recorded_by' => $this->recorded_by,
            'is_reversal' => (bool) $this->is_reversal,
            'original_transfer_id' => $this->original_transfer_id,
            'reversal_reason' => $this->reversal_reason,
            'created_at' => $this->created_at->toIso8601String(),
            'is_reversed' => $this->isReversed(),
            'source_account' => $this->whenLoaded('sourceAccount', fn () => [
                'id' => $this->sourceAccount->id,
                'name' => $this->sourceAccount->name,
                'account_number' => $this->sourceAccount->account_number,
                'scope_type' => $this->sourceAccount->rt_id !== null ? 'RT' : 'RW',
                'rt_id' => $this->sourceAccount->rt_id,
                'rt_number' => $this->sourceAccount->rt?->number !== null
                    ? str_pad((string) $this->sourceAccount->rt->number, 3, '0', STR_PAD_LEFT)
                    : null,
                'status' => $this->sourceAccount->status->value,
            ]),
            'destination_account' => $this->whenLoaded('destinationAccount', fn () => [
                'id' => $this->destinationAccount->id,
                'name' => $this->destinationAccount->name,
                'account_number' => $this->destinationAccount->account_number,
                'scope_type' => $this->destinationAccount->rt_id !== null ? 'RT' : 'RW',
                'rt_id' => $this->destinationAccount->rt_id,
                'rt_number' => $this->destinationAccount->rt?->number !== null
                    ? str_pad((string) $this->destinationAccount->rt->number, 3, '0', STR_PAD_LEFT)
                    : null,
                'status' => $this->destinationAccount->status->value,
            ]),
            'recorder' => $this->whenLoaded('recorder', fn () => [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
                'email' => $this->recorder->email,
            ]),
            'reversal' => $this->whenLoaded('reversal', fn () => $this->reversal ? [
                'id' => $this->reversal->id,
                'amount' => (int) $this->reversal->amount,
                'transfer_date' => $this->reversal->transfer_date->toDateString(),
                'reversal_reason' => $this->reversal->reversal_reason,
                'created_at' => $this->reversal->created_at->toIso8601String(),
            ] : null),
            'original_transfer' => $this->whenLoaded('originalTransfer', fn () => $this->originalTransfer ? [
                'id' => $this->originalTransfer->id,
                'amount' => (int) $this->originalTransfer->amount,
                'transfer_date' => $this->originalTransfer->transfer_date->toDateString(),
                'description' => $this->originalTransfer->description,
            ] : null),
            'evidence_file' => $this->whenLoaded('evidenceFile', fn () => $this->evidenceFile ? [
                'id' => $this->evidenceFile->id,
                'original_name' => $this->evidenceFile->original_name,
                'extension' => $this->evidenceFile->extension,
                'mime_type' => $this->evidenceFile->mime_type,
                'size_bytes' => $this->evidenceFile->size_bytes,
            ] : null),
        ];
    }
}
