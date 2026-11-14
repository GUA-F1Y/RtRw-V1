<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class FinanceCategoryBreakdownQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
    ) {}

    /**
     * Group financial transactions by category for a period and compute directional totals and percentages.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     categories: list<array{
     *         category_id: string,
     *         name: string,
     *         code: string|null,
     *         type: string,
     *         is_active: bool,
     *         posted_amount: int,
     *         reversed_amount: int,
     *         effective_amount: int,
     *         percentage: float,
     *         transaction_count: int
     *     }>,
     *     summary: array{
     *         total_effective_income: int,
     *         total_effective_expense: int
     *     }
     * }
     */
    public function execute(User $user, array $filters = []): array
    {
        $now = Carbon::now();
        $dateFrom = ! empty($filters['date_from'])
            ? (string) $filters['date_from']
            : $now->copy()->startOfMonth()->toDateString();
        $dateTo = ! empty($filters['date_to'])
            ? (string) $filters['date_to']
            : $now->copy()->endOfMonth()->toDateString();

        // 1. Resolve permitted cash accounts
        $accountQuery = CashAccount::query();
        $accountQuery = $this->scopedQuery->constrain(
            $accountQuery,
            $user,
            PermissionKey::FinanceAccountView,
            [
                'organization' => 'organization_id',
                'rw' => 'rw_id',
                'rt' => 'rt_id',
            ]
        );

        if (! empty($filters['cash_account_id'])) {
            $accountQuery->where('id', $filters['cash_account_id']);
        }

        if (! empty($filters['rw_id'])) {
            $accountQuery->where('rw_id', $filters['rw_id']);
        }

        if (array_key_exists('rt_id', $filters)) {
            if ($filters['rt_id'] === null || $filters['rt_id'] === 'null' || $filters['rt_id'] === '') {
                $accountQuery->whereNull('rt_id');
            } else {
                $accountQuery->where('rt_id', $filters['rt_id']);
            }
        }

        $accountIds = $accountQuery->pluck('id')->all();

        if (empty($accountIds)) {
            return [
                'categories' => [],
                'summary' => [
                    'total_effective_income' => 0,
                    'total_effective_expense' => 0,
                ],
            ];
        }

        // 2. Query transactions joined with categories
        $query = DB::table('financial_transactions')
            ->join('finance_categories', 'financial_transactions.category_id', '=', 'finance_categories.id')
            ->whereIn('financial_transactions.cash_account_id', $accountIds)
            ->where('financial_transactions.transaction_date', '>=', $dateFrom)
            ->where('financial_transactions.transaction_date', '<=', $dateTo)
            ->select(
                'finance_categories.id as category_id',
                'finance_categories.name as category_name',
                'finance_categories.code as category_code',
                'finance_categories.type as category_type',
                'finance_categories.is_active as category_is_active'
            )
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(CASE WHEN financial_transactions.is_reversal = FALSE THEN financial_transactions.amount ELSE 0 END), 0) AS posted_amount,
                COALESCE(SUM(CASE WHEN financial_transactions.is_reversal = TRUE THEN financial_transactions.amount ELSE 0 END), 0) AS reversed_amount,
                COUNT(*) AS transaction_count
            SQL)
            ->groupBy(
                'finance_categories.id',
                'finance_categories.name',
                'finance_categories.code',
                'finance_categories.type',
                'finance_categories.is_active'
            );

        if (! empty($filters['type'])) {
            $query->where('finance_categories.type', strtoupper((string) $filters['type']));
        }

        $rows = $query->get();

        // 3. First pass: compute totals per direction
        $totalEffectiveIncome = 0;
        $totalEffectiveExpense = 0;

        $items = [];
        foreach ($rows as $row) {
            $posted = (int) $row->posted_amount;
            $reversed = (int) $row->reversed_amount;
            $effective = $posted - $reversed;
            $type = strtoupper((string) $row->category_type);

            if ($type === 'INCOME') {
                $totalEffectiveIncome += $effective;
            } else {
                $totalEffectiveExpense += $effective;
            }

            $items[] = [
                'category_id' => (string) $row->category_id,
                'name' => (string) $row->category_name,
                'code' => $row->category_code !== null ? (string) $row->category_code : null,
                'type' => $type,
                'is_active' => (bool) $row->category_is_active,
                'posted_amount' => $posted,
                'reversed_amount' => $reversed,
                'effective_amount' => $effective,
                'transaction_count' => (int) $row->transaction_count,
            ];
        }

        // 4. Second pass: compute percentage relative to direction total
        $categories = [];
        foreach ($items as $item) {
            $directionTotal = $item['type'] === 'INCOME' ? $totalEffectiveIncome : $totalEffectiveExpense;
            $percentage = $directionTotal > 0
                ? round(($item['effective_amount'] / $directionTotal) * 100, 2)
                : 0.0;

            $categories[] = array_merge($item, [
                'percentage' => $percentage,
            ]);
        }

        // Sort by effective_amount descending
        usort($categories, fn ($a, $b) => $b['effective_amount'] <=> $a['effective_amount']);

        return [
            'categories' => $categories,
            'summary' => [
                'total_effective_income' => $totalEffectiveIncome,
                'total_effective_expense' => $totalEffectiveExpense,
            ],
        ];
    }
}
