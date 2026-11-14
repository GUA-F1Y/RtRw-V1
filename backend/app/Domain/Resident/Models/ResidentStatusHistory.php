<?php

declare(strict_types=1);

namespace App\Domain\Resident\Models;

use App\Domain\File\Models\StoredFile;
use App\Domain\Identity\Models\User;
use App\Domain\Resident\Enums\ResidentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ResidentStatusHistory.
 *
 * Immutable chronological ledger of civil status transitions.
 * Protected by database triggers against updates and deletions.
 */
#[Fillable([
    'resident_id',
    'previous_status',
    'new_status',
    'reason',
    'effective_at',
    'reference_letter_number',
    'attachment_file_id',
    'recorded_by',
])]
class ResidentStatusHistory extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'resident_status_history';

    protected function casts(): array
    {
        return [
            'previous_status' => ResidentStatus::class,
            'new_status' => ResidentStatus::class,
            'effective_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'resident_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function attachmentFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'attachment_file_id');
    }

    /**
     * @param  Builder<ResidentStatusHistory>  $query
     * @return Builder<ResidentStatusHistory>
     */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('effective_at', 'asc')->orderBy('created_at', 'asc');
    }

    /**
     * @param  Builder<ResidentStatusHistory>  $query
     * @return Builder<ResidentStatusHistory>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderBy('effective_at', 'desc')->orderBy('created_at', 'desc');
    }

    /**
     * @param  Builder<ResidentStatusHistory>  $query
     * @return Builder<ResidentStatusHistory>
     */
    public function scopeByNewStatus(Builder $query, ResidentStatus $status): Builder
    {
        return $query->where('new_status', $status);
    }
}
