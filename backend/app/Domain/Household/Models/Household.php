<?php

declare(strict_types=1);

namespace App\Domain\Household\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\File\Models\Attachment;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\Resident;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Household (Kartu Keluarga - KK).
 *
 * Represents a registered domestic unit within an RT. Head of household is NEVER
 * stored as a column here; it is always derived from the active HouseholdMember
 * relationship where relationship = KEPALA_KELUARGA.
 */
#[Fillable([
    'organization_id',
    'rw_id',
    'rt_id',
    'kk_number',
    'address',
    'status',
    'notes',
])]
class Household extends Model implements ScopedResource
{
    use HasUuids;

    protected $table = 'households';

    protected function casts(): array
    {
        return [
            'status' => HouseholdStatus::class,
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
     * All household members (active and historical).
     *
     * @return HasMany<HouseholdMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(HouseholdMember::class, 'household_id');
    }

    /**
     * Currently active household members.
     *
     * @return HasMany<HouseholdMember, $this>
     */
    public function activeMembers(): HasMany
    {
        return $this->hasMany(HouseholdMember::class, 'household_id')
            ->where('is_active', true);
    }

    /**
     * The single active head of household (canonical source of truth).
     *
     * @return HasOne<HouseholdMember, $this>
     */
    public function activeHead(): HasOne
    {
        return $this->hasOne(HouseholdMember::class, 'household_id')
            ->where('relationship', HouseholdMemberRelationship::KepalaKeluarga)
            ->where('is_active', true);
    }

    /**
     * Residents whose current_household_id points directly to this household.
     *
     * @return HasMany<Resident, $this>
     */
    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class, 'current_household_id');
    }

    /**
     * Polymorphic file attachments (KK document scans, etc.).
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @param  Builder<Household>  $query
     * @return Builder<Household>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', HouseholdStatus::Active);
    }

    /**
     * @param  Builder<Household>  $query
     * @return Builder<Household>
     */
    public function scopeHeadVacant(Builder $query): Builder
    {
        return $query->where('status', HouseholdStatus::HeadVacant);
    }

    /**
     * @param  Builder<Household>  $query
     * @return Builder<Household>
     */
    public function scopeMoved(Builder $query): Builder
    {
        return $query->where('status', HouseholdStatus::Moved);
    }

    /**
     * @param  Builder<Household>  $query
     * @return Builder<Household>
     */
    public function scopeDissolved(Builder $query): Builder
    {
        return $query->where('status', HouseholdStatus::Dissolved);
    }

    /**
     * @param  Builder<Household>  $query
     * @return Builder<Household>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', HouseholdStatus::Archived);
    }

    /**
     * @param  Builder<Household>  $query
     * @return Builder<Household>
     */
    public function scopeInRt(Builder $query, string $rtId): Builder
    {
        return $query->where('rt_id', $rtId);
    }

    public function scopeRef(): ScopeRef
    {
        return ScopeRef::rt($this->organization_id, $this->rw_id, $this->rt_id);
    }
}
