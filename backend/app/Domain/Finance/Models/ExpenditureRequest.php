<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\File\Models\StoredFile;
use App\Domain\Finance\Enums\ExpenditureRequestStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ExpenditureRequest.
 *
 * Represents an expenditure proposal and approval workflow separate from the immutable ledger.
 */
#[Fillable([
    'organization_id',
    'rw_id',
    'rt_id',
    'budget_line_id',
    'cash_account_id',
    'category_id',
    'title',
    'description',
    'proposed_amount',
    'approved_amount',
    'actual_amount',
    'planned_date',
    'reference_number',
    'evidence_file_id',
    'status',
    'requested_by',
    'submitted_at',
    'approved_by',
    'decision_at',
    'decision_reason',
    'financial_transaction_id',
    'posted_by',
    'posted_at',
])]
class ExpenditureRequest extends Model implements ScopedResource
{
    use HasUuids;

    protected $table = 'expenditure_requests';

    protected function casts(): array
    {
        return [
            'proposed_amount' => 'integer',
            'approved_amount' => 'integer',
            'actual_amount' => 'integer',
            'planned_date' => 'date',
            'status' => ExpenditureRequestStatus::class,
            'submitted_at' => 'datetime',
            'decision_at' => 'datetime',
            'posted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Rw, $this>
     */
    public function rw(): BelongsTo
    {
        return $this->belongsTo(Rw::class);
    }

    /**
     * @return BelongsTo<Rt, $this>
     */
    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    /**
     * @return BelongsTo<BudgetLine, $this>
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'budget_line_id');
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    /**
     * @return BelongsTo<FinanceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * @return BelongsTo<FinancialTransaction, $this>
     */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function evidenceFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'evidence_file_id');
    }

    public function isDraft(): bool
    {
        return $this->status === ExpenditureRequestStatus::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === ExpenditureRequestStatus::Submitted;
    }

    public function isApproved(): bool
    {
        return $this->status === ExpenditureRequestStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === ExpenditureRequestStatus::Rejected;
    }

    public function isCancelled(): bool
    {
        return $this->status === ExpenditureRequestStatus::Cancelled;
    }

    public function isPosted(): bool
    {
        return $this->status === ExpenditureRequestStatus::Posted;
    }

    public function scopeRef(): ScopeRef
    {
        if ($this->rt_id !== null) {
            return ScopeRef::rt($this->organization_id, $this->rw_id, $this->rt_id);
        }

        return ScopeRef::rw($this->organization_id, $this->rw_id);
    }
}
