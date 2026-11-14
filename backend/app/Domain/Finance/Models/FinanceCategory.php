<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FinanceCategory.
 *
 * Categorization for financial entries (e.g. Iuran Warga, Kebersihan, Keamanan, Perbaikan).
 * Tied to direction type (INCOME or EXPENSE).
 */
#[Fillable([
    'organization_id',
    'rw_id',
    'rt_id',
    'name',
    'code',
    'type',
    'description',
    'is_active',
])]
class FinanceCategory extends Model implements ScopedResource
{
    use HasUuids;

    protected $table = 'finance_categories';

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'is_active' => 'boolean',
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
     * @return HasMany<FinancialTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'category_id');
    }

    /**
     * @param  Builder<FinanceCategory>  $query
     * @return Builder<FinanceCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<FinanceCategory>  $query
     * @return Builder<FinanceCategory>
     */
    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Income);
    }

    /**
     * @param  Builder<FinanceCategory>  $query
     * @return Builder<FinanceCategory>
     */
    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Expense);
    }

    public function scopeRef(): ScopeRef
    {
        if ($this->rt_id !== null && $this->rw_id !== null) {
            return ScopeRef::rt($this->organization_id, $this->rw_id, $this->rt_id);
        }

        if ($this->rw_id !== null) {
            return ScopeRef::rw($this->organization_id, $this->rw_id);
        }

        return ScopeRef::organization($this->organization_id);
    }
}
