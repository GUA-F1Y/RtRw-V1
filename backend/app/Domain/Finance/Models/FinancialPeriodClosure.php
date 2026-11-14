<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FinancialPeriodClosure (Tutup Buku Kas Bulanan).
 *
 * Immutable historical closure snapshot of cash account balances,
 * in-period movements, and optional manual physical cash reconciliation.
 */
#[Fillable([
    'organization_id',
    'rw_id',
    'rt_id',
    'cash_account_id',
    'period_year',
    'period_month',
    'period_start',
    'period_end',
    'opening_balance',
    'closing_balance',
    'total_income',
    'total_expense',
    'posted_income',
    'reversed_income',
    'posted_expense',
    'reversed_expense',
    'net_movement',
    'transfers_in',
    'transfers_out',
    'net_transfer_movement',
    'transfer_count',
    'transaction_count',
    'reversal_count',
    'counted_cash_balance',
    'reconciliation_difference',
    'reconciliation_notes',
    'closed_by',
    'closed_at',
])]
class FinancialPeriodClosure extends Model implements ScopedResource
{
    use HasUuids;

    public const UPDATED_AT = null; // Immutable snapshot record: no updated_at column

    protected $table = 'financial_period_closures';

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'opening_balance' => 'integer',
            'closing_balance' => 'integer',
            'total_income' => 'integer',
            'total_expense' => 'integer',
            'posted_income' => 'integer',
            'reversed_income' => 'integer',
            'posted_expense' => 'integer',
            'reversed_expense' => 'integer',
            'net_movement' => 'integer',
            'transfers_in' => 'integer',
            'transfers_out' => 'integer',
            'net_transfer_movement' => 'integer',
            'transfer_count' => 'integer',
            'transaction_count' => 'integer',
            'reversal_count' => 'integer',
            'counted_cash_balance' => 'integer',
            'reconciliation_difference' => 'integer',
            'closed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
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
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Checks if physical cash was recorded for this closure.
     */
    public function hasPhysicalCount(): bool
    {
        return $this->counted_cash_balance !== null;
    }

    /**
     * Checks if there was a nonzero difference between counted cash and ledger closing balance.
     */
    public function hasDiscrepancy(): bool
    {
        return $this->reconciliation_difference !== null && $this->reconciliation_difference !== 0;
    }

    public function scopeRef(): ScopeRef
    {
        if ($this->rt_id !== null) {
            return ScopeRef::rt($this->organization_id, $this->rw_id, $this->rt_id);
        }

        return ScopeRef::rw($this->organization_id, $this->rw_id);
    }
}
