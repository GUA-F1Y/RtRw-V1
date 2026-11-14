<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Finance\Enums\BudgetPlanStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BudgetPlan.
 *
 * Represents a monthly budget plan scoped to an RW or an RT.
 */
#[Fillable([
    'organization_id',
    'rw_id',
    'rt_id',
    'year',
    'month',
    'version',
    'status',
    'notes',
    'created_by',
    'published_by',
    'published_at',
])]
class BudgetPlan extends Model implements ScopedResource
{
    use HasUuids;

    protected $table = 'budget_plans';

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'version' => 'integer',
            'status' => BudgetPlanStatus::class,
            'published_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'budget_plan_id');
    }

    public function isDraft(): bool
    {
        return $this->status === BudgetPlanStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === BudgetPlanStatus::Published;
    }

    public function isSuperseded(): bool
    {
        return $this->status === BudgetPlanStatus::Superseded;
    }

    public function isRtScope(): bool
    {
        return $this->rt_id !== null;
    }

    public function isRwScope(): bool
    {
        return $this->rt_id === null;
    }

    public function scopeRef(): ScopeRef
    {
        if ($this->rt_id !== null) {
            return ScopeRef::rt($this->organization_id, $this->rw_id, $this->rt_id);
        }

        return ScopeRef::rw($this->organization_id, $this->rw_id);
    }
}
