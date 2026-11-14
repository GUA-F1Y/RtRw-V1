<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Finance\Models\ExpenditureRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExpenditureRequest
 */
class ExpenditureRequestResource extends JsonResource
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
            'scope_type' => $this->rt_id !== null ? 'RT' : 'RW',
            'budget_line_id' => $this->budget_line_id,
            'cash_account_id' => $this->cash_account_id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'description' => $this->description,
            'proposed_amount' => (int) $this->proposed_amount,
            'approved_amount' => $this->approved_amount !== null ? (int) $this->approved_amount : null,
            'actual_amount' => $this->actual_amount !== null ? (int) $this->actual_amount : null,
            'planned_date' => $this->planned_date->toDateString(),
            'reference_number' => $this->reference_number,
            'evidence_file_id' => $this->evidence_file_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'requested_by' => $this->requested_by,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'decision_at' => $this->decision_at?->toIso8601String(),
            'decision_reason' => $this->decision_reason,
            'financial_transaction_id' => $this->financial_transaction_id,
            'posted_by' => $this->posted_by,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'budget_line' => $this->whenLoaded('budgetLine', fn () => [
                'id' => $this->budgetLine->id,
                'planned_amount' => (int) $this->budgetLine->planned_amount,
                'category_id' => $this->budgetLine->category_id,
                'category' => $this->budgetLine->relationLoaded('category') && $this->budgetLine->category ? [
                    'id' => $this->budgetLine->category->id,
                    'name' => $this->budgetLine->category->name,
                    'code' => $this->budgetLine->category->code,
                ] : null,
                'plan' => $this->budgetLine->relationLoaded('budgetPlan') && $this->budgetLine->budgetPlan ? [
                    'id' => $this->budgetLine->budgetPlan->id,
                    'year' => $this->budgetLine->budgetPlan->year,
                    'month' => $this->budgetLine->budgetPlan->month,
                    'version' => $this->budgetLine->budgetPlan->version,
                    'status' => $this->budgetLine->budgetPlan->status->value,
                ] : null,
            ]),
            'cash_account' => $this->whenLoaded('cashAccount', fn () => [
                'id' => $this->cashAccount->id,
                'name' => $this->cashAccount->name,
                'account_number' => $this->cashAccount->account_number,
                'status' => $this->cashAccount->status->value,
                'current_balance' => (int) $this->cashAccount->current_balance,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'code' => $this->category->code,
                'type' => $this->category->type->value,
            ]),
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
                'email' => $this->requester->email,
            ]),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
                'email' => $this->approver->email,
            ] : null),
            'poster' => $this->whenLoaded('poster', fn () => $this->poster ? [
                'id' => $this->poster->id,
                'name' => $this->poster->name,
                'email' => $this->poster->email,
            ] : null),
            'financial_transaction' => $this->whenLoaded('financialTransaction', fn () => $this->financialTransaction ? [
                'id' => $this->financialTransaction->id,
                'amount' => (int) $this->financialTransaction->amount,
                'transaction_date' => $this->financialTransaction->transaction_date->toDateString(),
                'reference_number' => $this->financialTransaction->reference_number,
                'description' => $this->financialTransaction->description,
            ] : null),
            'evidence_file' => $this->whenLoaded('evidenceFile', fn () => $this->evidenceFile ? [
                'id' => $this->evidenceFile->id,
                'original_name' => $this->evidenceFile->original_name,
                'mime_type' => $this->evidenceFile->mime_type,
                'size_bytes' => $this->evidenceFile->size_bytes,
            ] : null),
            'rw' => $this->whenLoaded('rw', fn () => $this->rw ? [
                'id' => $this->rw->id,
                'rw_number' => $this->rw->number,
                'number' => $this->rw->number,
                'name' => $this->rw->name,
            ] : null),
            'rt' => $this->whenLoaded('rt', fn () => $this->rt ? [
                'id' => $this->rt->id,
                'rt_number' => $this->rt->number,
                'number' => $this->rt->number,
                'name' => $this->rt->name,
            ] : null),
        ];
    }
}
