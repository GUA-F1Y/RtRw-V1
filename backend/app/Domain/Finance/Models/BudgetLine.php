<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BudgetLine.
 *
 * Represents an individual expense category allocation within a budget plan.
 */
#[Fillable([
    'budget_plan_id',
    'category_id',
    'planned_amount',
    'notes',
])]
class BudgetLine extends Model implements ScopedResource
{
    use HasUuids;

    protected $table = 'budget_lines';

    protected function casts(): array
    {
        return [
            'planned_amount' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BudgetPlan, $this>
     */
    public function budgetPlan(): BelongsTo
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    /**
     * @return BelongsTo<FinanceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    /**
     * @return HasMany<ExpenditureRequest, $this>
     */
    public function expenditureRequests(): HasMany
    {
        return $this->hasMany(ExpenditureRequest::class, 'budget_line_id');
    }

    public function scopeRef(): ScopeRef
    {
        return $this->budgetPlan->scopeRef();
    }
}
