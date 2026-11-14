<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Finance\Models\FinancialPeriodClosure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialPeriodClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FinancialPeriodClosure $closure */
        $closure = $this->resource;

        return [
            'id' => $closure->id,
            'organization_id' => $closure->organization_id,
            'rw_id' => $closure->rw_id,
            'rt_id' => $closure->rt_id,
            'cash_account_id' => $closure->cash_account_id,
            'account' => $closure->relationLoaded('account') && $closure->account !== null
                ? new CashAccountResource($closure->account)
                : null,
            'period_year' => (int) $closure->period_year,
            'period_month' => (int) $closure->period_month,
            'period_start' => $closure->period_start->toDateString(),
            'period_end' => $closure->period_end->toDateString(),
            'opening_balance' => (int) $closure->opening_balance,
            'closing_balance' => (int) $closure->closing_balance,
            'total_income' => (int) $closure->total_income,
            'total_expense' => (int) $closure->total_expense,
            'posted_income' => (int) $closure->posted_income,
            'reversed_income' => (int) $closure->reversed_income,
            'posted_expense' => (int) $closure->posted_expense,
            'reversed_expense' => (int) $closure->reversed_expense,
            'net_movement' => (int) $closure->net_movement,
            'transaction_count' => (int) $closure->transaction_count,
            'reversal_count' => (int) $closure->reversal_count,
            'counted_cash_balance' => $closure->counted_cash_balance !== null ? (int) $closure->counted_cash_balance : null,
            'reconciliation_difference' => $closure->reconciliation_difference !== null ? (int) $closure->reconciliation_difference : null,
            'reconciliation_notes' => $closure->reconciliation_notes,
            'is_reconciled' => $closure->hasPhysicalCount(),
            'has_discrepancy' => $closure->hasDiscrepancy(),
            'closed_by' => $closure->relationLoaded('closedBy') && $closure->closedBy !== null
                ? [
                    'id' => $closure->closedBy->id,
                    'name' => $closure->closedBy->name,
                    'email' => $closure->closedBy->email,
                ]
                : ['id' => $closure->closed_by],
            'closed_at' => $closure->closed_at->toIso8601String(),
            'created_at' => $closure->created_at->toIso8601String(),
        ];
    }
}
