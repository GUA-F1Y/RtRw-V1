<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CashAccount (Kas RW / Kas RT).
 *
 * Represents an operational cash holding unit at either RW level (rt_id IS NULL)
 * or RT level (rt_id IS NOT NULL).
 */
#[Fillable([
    'organization_id',
    'rw_id',
    'rt_id',
    'name',
    'account_number',
    'description',
    'status',
    'opening_balance',
    'closed_at',
    'closed_reason',
])]
class CashAccount extends Model implements ScopedResource
{
    use HasUuids;

    protected $table = 'cash_accounts';

    protected function casts(): array
    {
        return [
            'status' => CashAccountStatus::class,
            'opening_balance' => 'integer',
            'closed_at' => 'datetime',
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
     * @return HasMany<FinancialTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'cash_account_id');
    }

    public function isRtAccount(): bool
    {
        return $this->rt_id !== null;
    }

    public function isRwAccount(): bool
    {
        return $this->rt_id === null;
    }

    public function isActive(): bool
    {
        return $this->status === CashAccountStatus::Active;
    }

    public function isClosed(): bool
    {
        return $this->status === CashAccountStatus::Closed;
    }

    /**
     * @param  Builder<CashAccount>  $query
     * @return Builder<CashAccount>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CashAccountStatus::Active);
    }

    /**
     * @param  Builder<CashAccount>  $query
     * @return Builder<CashAccount>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', CashAccountStatus::Closed);
    }

    /**
     * @param  Builder<CashAccount>  $query
     * @return Builder<CashAccount>
     */
    public function scopeInRw(Builder $query, string $rwId): Builder
    {
        return $query->where('rw_id', $rwId);
    }

    /**
     * @param  Builder<CashAccount>  $query
     * @return Builder<CashAccount>
     */
    public function scopeInRt(Builder $query, string $rtId): Builder
    {
        return $query->where('rt_id', $rtId);
    }

    public function scopeRef(): ScopeRef
    {
        if ($this->rt_id !== null) {
            return ScopeRef::rt($this->organization_id, $this->rw_id, $this->rt_id);
        }

        return ScopeRef::rw($this->organization_id, $this->rw_id);
    }
}
