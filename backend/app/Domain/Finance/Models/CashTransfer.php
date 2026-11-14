<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\File\Models\Attachment;
use App\Domain\File\Models\StoredFile;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * CashTransfer (Transfer Kas Internal RW / RT).
 *
 * Append-only immutable ledger record representing movements of funds
 * between cash accounts within the same organization, and compensating reversal transfers.
 */
#[Fillable([
    'organization_id',
    'rw_id',
    'rt_id',
    'source_cash_account_id',
    'destination_cash_account_id',
    'amount',
    'transfer_date',
    'description',
    'reference_number',
    'evidence_file_id',
    'recorded_by',
    'is_reversal',
    'original_transfer_id',
    'reversal_reason',
])]
class CashTransfer extends Model implements ScopedResource
{
    use HasUuids;

    public const UPDATED_AT = null; // Append-only immutable ledger: no updated_at column

    protected $table = 'cash_transfers';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_reversal' => 'boolean',
            'transfer_date' => 'date:Y-m-d',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'source_cash_account_id');
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'destination_cash_account_id');
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
     * Extra evidence files attached to this transfer.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Reversal compensating transfer if this transfer was reversed.
     *
     * @return HasOne<CashTransfer, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'original_transfer_id');
    }

    /**
     * Original transfer if this entry is a reversal.
     *
     * @return BelongsTo<CashTransfer, $this>
     */
    public function originalTransfer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_transfer_id');
    }

    public function isReversed(): bool
    {
        return $this->relationLoaded('reversal')
            ? $this->reversal !== null
            : $this->reversal()->exists();
    }

    public function scopeRef(): ScopeRef
    {
        return new ScopeRef(
            $this->organization_id,
            $this->rw_id,
            $this->rt_id,
        );
    }

    /**
     * Retrieve all evidence files associated with this transfer.
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
}
