<?php

declare(strict_types=1);

namespace App\Domain\Resident\Models;

use App\Domain\Household\Models\Household;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HouseholdMember.
 *
 * Canonical representation of a resident's membership in a household.
 * Historical records are never deleted; members who leave have is_active = FALSE.
 */
#[Fillable([
    'household_id',
    'resident_id',
    'relationship',
    'is_active',
    'joined_at',
    'left_at',
    'left_reason',
])]
class HouseholdMember extends Model
{
    use HasUuids;

    protected $table = 'household_members';

    protected function casts(): array
    {
        return [
            'relationship' => HouseholdMemberRelationship::class,
            'is_active' => 'boolean',
            'joined_at' => 'date:Y-m-d',
            'left_at' => 'date:Y-m-d',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'resident_id');
    }

    /**
     * @param  Builder<HouseholdMember>  $query
     * @return Builder<HouseholdMember>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<HouseholdMember>  $query
     * @return Builder<HouseholdMember>
     */
    public function scopeHistorical(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * @param  Builder<HouseholdMember>  $query
     * @return Builder<HouseholdMember>
     */
    public function scopeHead(Builder $query): Builder
    {
        return $query->where('relationship', HouseholdMemberRelationship::KepalaKeluarga);
    }
}
