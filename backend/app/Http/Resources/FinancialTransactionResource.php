<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Finance\Models\FinancialTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialTransaction
 */
class FinancialTransactionResource extends JsonResource
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
            'cash_account_id' => $this->cash_account_id,
            'category_id' => $this->category_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'amount' => (int) $this->amount,
            'transaction_date' => $this->transaction_date->toDateString(),
            'description' => $this->description,
            'reference_number' => $this->reference_number,
            'evidence_file_id' => $this->evidence_file_id,
            'recorded_by' => $this->recorded_by,
            'is_reversal' => (bool) $this->is_reversal,
            'original_transaction_id' => $this->original_transaction_id,
            'reversal_reason' => $this->reversal_reason,
            'created_at' => $this->created_at->toIso8601String(),
            'is_reversed' => $this->isReversed(),
            'reversal' => $this->whenLoaded('reversal', fn () => $this->reversal ? [
                'id' => $this->reversal->id,
                'amount' => (int) $this->reversal->amount,
                'transaction_date' => $this->reversal->transaction_date->toDateString(),
                'reversal_reason' => $this->reversal->reversal_reason,
                'created_at' => $this->reversal->created_at->toIso8601String(),
            ] : null),
            'original_transaction' => $this->whenLoaded('originalTransaction', fn () => $this->originalTransaction ? [
                'id' => $this->originalTransaction->id,
                'type' => $this->originalTransaction->type->value,
                'amount' => (int) $this->originalTransaction->amount,
                'transaction_date' => $this->originalTransaction->transaction_date->toDateString(),
                'description' => $this->originalTransaction->description,
            ] : null),
            'account' => $this->whenLoaded('account', fn () => [
                'id' => $this->account->id,
                'name' => $this->account->name,
                'status' => $this->account->status->value,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'type' => $this->category->type->value,
            ]),
            'recorder' => $this->whenLoaded('recorder', fn () => [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
                'email' => $this->recorder->email,
            ]),
            'evidence_file' => $this->whenLoaded('evidenceFile', fn () => $this->evidenceFile ? [
                'id' => $this->evidenceFile->id,
                'original_name' => $this->evidenceFile->original_name,
                'extension' => $this->evidenceFile->extension,
                'mime_type' => $this->evidenceFile->mime_type,
                'size_bytes' => $this->evidenceFile->size_bytes,
            ] : null),
            'evidence_files' => $this->when(
                $this->relationLoaded('evidenceFile') || $this->relationLoaded('attachments'),
                fn () => $this->allEvidenceFiles()->map(fn (\App\Domain\File\Models\StoredFile $f) => [
                    'id' => $f->id,
                    'original_name' => $f->original_name,
                    'extension' => $f->extension,
                    'mime_type' => $f->mime_type,
                    'size_bytes' => $f->size_bytes,
                ])->values()->all()
            ),
        ];
    }
}
