<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\Enums\ScopeLevel;
use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Identity\Models\User;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grants one role to one user at exactly one scope. Revocation is a soft state
 * (`revoked_at`) so authorization history is never lost.
 *
 * All fillable columns are server-determined (see AssignMembership); request input
 * is never passed straight to the model.
 */
#[Fillable(['user_id', 'role_id', 'scope_level', 'organization_id', 'rw_id', 'rt_id', 'granted_by', 'revoked_at', 'revoked_by'])]
#[UseFactory(MembershipFactory::class)]
class Membership extends Model implements ScopedResource
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'scope_level' => ScopeLevel::class,
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Membership>  $query
     * @return Builder<Membership>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function scopeRef(): ScopeRef
    {
        return new ScopeRef($this->organization_id, $this->rw_id, $this->rt_id);
    }
}
