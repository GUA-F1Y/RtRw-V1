<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\File\Models\Attachment;
use App\Domain\File\Models\StoredFile;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * FinancialTransaction (Buku Kas RW / RT).
 *
 * Append-only immutable ledger record representing financial income, expense,
 * or compensating reversal entry.
 */
#[Fillable([
    'organization_id',
    'rw_id',
    'rt_id',
    'cash_account_id',
    'category_id',
    'type',
    'amount',
    'transaction_date',
    'description',
    'reference_number',
    'evidence_file_id',
    'recorded_by',
    'is_reversal',
    'original_transaction_id',
    'reversal_reason',
])]
class FinancialTransaction extends Model implements ScopedResource
{
    use HasUuids;

    public const UPDATED_AT = null; // Append-only immutable ledger: no updated_at column

    protected $table = 'financial_transactions';

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'integer',
            'is_reversal' => 'boolean',
            'transaction_date' => 'date:Y-m-d',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function account(): BelongsTo
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
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function evidenceFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'evidence_file_id');
    }

    /**
     * Extra evidence files attached after transaction was recorded.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Retrieve all evidence files associated with this transaction.
     *
     * @return Collection<int, StoredFile>
     */
    public function allEvidenceFiles(): Collection
    {
        $files = collect();

        if ($this->evidenceFile !== null) {
            $files->push($this->evidenceFile);
        }

        if ($this->relationLoaded('attachments')) {
            foreach ($this->attachments as $attachment) {
                if ($attachment->file !== null && ! $files->contains('id', $attachment->file->id)) {
                    $files->push($attachment->file);
                }
            }
        } else {
            $attachmentFiles = StoredFile::query()
                ->whereIn('id', $this->attachments()->pluck('file_id'))
                ->get();

            foreach ($attachmentFiles as $file) {
                if (! $files->contains('id', $file->id)) {
                    $files->push($file);
                }
            }
        }

        return $files;
    }

    /**
     * Reversal compensating entry if this transaction was reversed.
     *
     * @return HasOne<FinancialTransaction, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'original_transaction_id');
    }

    /**
     * Original transaction if this entry is a reversal.
     *
     * @return BelongsTo<FinancialTransaction, $this>
     */
    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_transaction_id');
    }

    public function isIncome(): bool
    {
        return $this->type === TransactionType::Income;
    }

    public function isExpense(): bool
    {
        return $this->type === TransactionType::Expense;
    }

    public function isReversed(): bool
    {
        return $this->relationLoaded('reversal')
            ? $this->reversal !== null
            : $this->reversal()->exists();
    }

    /**
     * @param  Builder<FinancialTransaction>  $query
     * @return Builder<FinancialTransaction>
     */
    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Income);
    }

    /**
     * @param  Builder<FinancialTransaction>  $query
     * @return Builder<FinancialTransaction>
     */
    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Expense);
    }

    /**
     * @param  Builder<FinancialTransaction>  $query
     * @return Builder<FinancialTransaction>
     */
    public function scopeNonReversals(Builder $query): Builder
    {
        return $query->where('is_reversal', false);
    }

    /**
     * @param  Builder<FinancialTransaction>  $query
     * @return Builder<FinancialTransaction>
     */
    public function scopeReversals(Builder $query): Builder
    {
        return $query->where('is_reversal', true);
    }

    public function scopeRef(): ScopeRef
    {
        if ($this->rt_id !== null) {
            return ScopeRef::rt($this->organization_id, $this->rw_id, $this->rt_id);
        }

        return ScopeRef::rw($this->organization_id, $this->rw_id);
    }
}
