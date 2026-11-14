<?php

declare(strict_types=1);

namespace App\Domain\Resident\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\File\Models\Attachment;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Domain\Resident\Enums\ResidentBloodType;
use App\Domain\Resident\Enums\ResidentEducation;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Enums\ResidencyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Resident (Warga).
 *
 * Canonical representation of civil identity. NIK is permanently unique.
 * Head-of-household is NEVER a column on this model; it is solely derived from
 * active household membership.
 */
#[Fillable([
    'organization_id',
    'rw_id',
    'rt_id',
    'user_id',
    'current_household_id',
    'nik',
    'name',
    'gender',
    'birth_place',
    'birth_date',
    'religion',
    'marital_status',
    'blood_type',
    'occupation',
    'education',
    'phone',
    'residency_type',
    'status',
])]
class Resident extends Model implements ScopedResource
{
    use HasUuids;

    protected $table = 'residents';

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'gender' => ResidentGender::class,
            'marital_status' => ResidentMaritalStatus::class,
            'blood_type' => ResidentBloodType::class,
            'education' => ResidentEducation::class,
            'residency_type' => ResidencyType::class,
            'status' => ResidentStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Optional linked user authentication account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<Rw, $this>
     */
    public function rw(): BelongsTo
    {
        return $this->belongsTo(Rw::class, 'rw_id');
    }

    /**
     * @return BelongsTo<Rt, $this>
     */
    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class, 'rt_id');
    }

    /**
     * Current household assignment (scope-aligned via composite FK).
     *
     * @return BelongsTo<Household, $this>
     */
    public function currentHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'current_household_id');
    }

    /**
     * All household memberships (current and historical).
     *
     * @return HasMany<HouseholdMember, $this>
     */
    public function householdMemberships(): HasMany
    {
        return $this->hasMany(HouseholdMember::class, 'resident_id');
    }

    /**
     * Historical movement records.
     *
     * @return HasMany<ResidencyMovement, $this>
     */
    public function residencyMovements(): HasMany
    {
        return $this->hasMany(ResidencyMovement::class, 'resident_id');
    }

    /**
     * Historical status change records.
     *
     * @return HasMany<ResidentStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ResidentStatusHistory::class, 'resident_id');
    }

    /**
     * Polymorphic file attachments (KTP scans, civil certificates, etc.).
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ResidentStatus::Active);
    }

    /**
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeProspective(Builder $query): Builder
    {
        return $query->where('status', ResidentStatus::Prospective);
    }

    /**
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', ResidentStatus::Inactive);
    }

    /**
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeMoved(Builder $query): Builder
    {
        return $query->where('status', ResidentStatus::Moved);
    }

    /**
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeDeceased(Builder $query): Builder
    {
        return $query->where('status', ResidentStatus::Deceased);
    }

    /**
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeInRt(Builder $query, string $rtId): Builder
    {
        return $query->where('rt_id', $rtId);
    }

    /**
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeInRw(Builder $query, string $rwId): Builder
    {
        return $query->where('rw_id', $rwId);
    }

    public function scopeRef(): ScopeRef
    {
        return ScopeRef::rt($this->organization_id, $this->rw_id, $this->rt_id);
    }
}
