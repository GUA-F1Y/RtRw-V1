<?php

declare(strict_types=1);

namespace App\Domain\Resident\Models;

use App\Domain\File\Models\StoredFile;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Domain\Resident\Enums\ResidencyMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ResidencyMovement.
 *
 * Immutable chronological ledger of domestic and geographical movements.
 * Protected by database triggers against updates and deletions.
 */
#[Fillable([
    'resident_id',
    'movement_type',
    'from_organization_id',
    'from_rw_id',
    'from_rt_id',
    'from_household_id',
    'to_organization_id',
    'to_rw_id',
    'to_rt_id',
    'to_household_id',
    'effective_date',
    'reference_letter_number',
    'attachment_file_id',
    'notes',
    'recorded_by',
])]
class ResidencyMovement extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'residency_movements';

    protected function casts(): array
    {
        return [
            'movement_type' => ResidencyMovementType::class,
            'effective_date' => 'date:Y-m-d',
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
     * @return BelongsTo<Organization, $this>
     */
    public function fromOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'from_organization_id');
    }

    /**
     * @return BelongsTo<Rw, $this>
     */
    public function fromRw(): BelongsTo
    {
        return $this->belongsTo(Rw::class, 'from_rw_id');
    }

    /**
     * @return BelongsTo<Rt, $this>
     */
    public function fromRt(): BelongsTo
    {
        return $this->belongsTo(Rt::class, 'from_rt_id');
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function fromHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'from_household_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function toOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'to_organization_id');
    }

    /**
     * @return BelongsTo<Rw, $this>
     */
    public function toRw(): BelongsTo
    {
        return $this->belongsTo(Rw::class, 'to_rw_id');
    }

    /**
     * @return BelongsTo<Rt, $this>
     */
    public function toRt(): BelongsTo
    {
        return $this->belongsTo(Rt::class, 'to_rt_id');
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function toHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'to_household_id');
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
     * @param  Builder<ResidencyMovement>  $query
     * @return Builder<ResidencyMovement>
     */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('effective_date', 'asc')->orderBy('created_at', 'asc');
    }

    /**
     * @param  Builder<ResidencyMovement>  $query
     * @return Builder<ResidencyMovement>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderBy('effective_date', 'desc')->orderBy('created_at', 'desc');
    }
}
