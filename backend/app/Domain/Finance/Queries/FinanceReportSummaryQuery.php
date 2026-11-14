<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class FinanceReportSummaryQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
    ) {}

    /**
     * Calculate comprehensive financial report summary for a given period.
     *
     * Semantics:
     * - Opening balance is calculated as baseline cash account opening balance
     *   PLUS all net transaction and transfer effects strictly before date_from.
     * - Effective income and expense strictly inside the period [date_from, date_to]
     *   account for operating transactions:
     *     effective_income = posted_income - reversed_income
     *     effective_expense = posted_expense - reversed_expense
     * - Transfers in and out represent internal non-operational funds movements.
     *     net_transfer_movement = transfers_in - transfers_out
     *     net_movement = effective_income - effective_expense + net_transfer_movement
     * - Closing balance strictly equals opening_balance + net_movement.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     period: array{date_from: string, date_to: string},
     *     opening_balance: int,
     *     posted_income: int,
     *     reversed_income: int,
     *     effective_income: int,
     *     posted_expense: int,
     *     reversed_expense: int,
     *     effective_expense: int,
     *     transfers_in: int,
     *     transfers_out: int,
     *     net_transfer_movement: int,
     *     net_movement: int,
     *     closing_balance: int,
     *     total_transactions_count: int,
     *     reversed_transactions_count: int,
     *     effective_transactions_count: int,
     *     total_transfers_count: int,
     *     cash_account_count: int
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

        $accounts = $accountQuery->get(['id', 'opening_balance']);
        $cashAccountCount = $accounts->count();

        if ($cashAccountCount === 0) {
            return [
                'period' => ['date_from' => $dateFrom, 'date_to' => $dateTo],
                'opening_balance' => 0,
                'posted_income' => 0,
                'reversed_income' => 0,
                'effective_income' => 0,
                'posted_expense' => 0,
                'reversed_expense' => 0,
                'effective_expense' => 0,
                'transfers_in' => 0,
                'transfers_out' => 0,
                'net_transfer_movement' => 0,
                'net_movement' => 0,
                'closing_balance' => 0,
                'total_transactions_count' => 0,
                'reversed_transactions_count' => 0,
                'effective_transactions_count' => 0,
                'total_transfers_count' => 0,
                'cash_account_count' => 0,
            ];
        }

        $accountIds = $accounts->pluck('id')->all();
        $baselineOpeningBalance = (int) $accounts->sum('opening_balance');

        // 2. Pre-period net cash movement (transaction_date < date_from)
        $prePeriod = DB::table('financial_transactions')
            ->whereIn('cash_account_id', $accountIds)
            ->where('transaction_date', '<', $dateFrom)
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS posted_income,
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS posted_expense,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS reversed_income,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS reversed_expense
            SQL)
            ->first();

        $prePostedIncome = (int) ($prePeriod->posted_income ?? 0);
        $prePostedExpense = (int) ($prePeriod->posted_expense ?? 0);
        $preReversedIncome = (int) ($prePeriod->reversed_income ?? 0);
        $preReversedExpense = (int) ($prePeriod->reversed_expense ?? 0);
        $preNetOperating = ($prePostedIncome - $preReversedIncome) - ($prePostedExpense - $preReversedExpense);

        // Pre-period transfers
        $preTransfersIn = (int) DB::table('cash_transfers')
            ->whereIn('destination_cash_account_id', $accountIds)
            ->where('transfer_date', '<', $dateFrom)
            ->sum('amount');

        $preTransfersOut = (int) DB::table('cash_transfers')
            ->whereIn('source_cash_account_id', $accountIds)
            ->where('transfer_date', '<', $dateFrom)
            ->sum('amount');

        $preNetTransfers = $preTransfersIn - $preTransfersOut;
        $openingBalance = $baselineOpeningBalance + $preNetOperating + $preNetTransfers;

        // 3. In-period cash movements (date_from <= transaction_date <= date_to)
        $inPeriod = DB::table('financial_transactions')
            ->whereIn('cash_account_id', $accountIds)
            ->where('transaction_date', '>=', $dateFrom)
            ->where('transaction_date', '<=', $dateTo)
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS posted_income,
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS posted_expense,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS reversed_income,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS reversed_expense,
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE THEN 1 ELSE 0 END), 0) AS reversed_count,
                COALESCE(SUM(CASE WHEN is_reversal = FALSE THEN 1 ELSE 0 END), 0) AS effective_count
            SQL)
            ->first();

        $postedIncome = (int) ($inPeriod->posted_income ?? 0);
        $postedExpense = (int) ($inPeriod->posted_expense ?? 0);
        $reversedIncome = (int) ($inPeriod->reversed_income ?? 0);
        $reversedExpense = (int) ($inPeriod->reversed_expense ?? 0);

        $effectiveIncome = $postedIncome - $reversedIncome;
        $effectiveExpense = $postedExpense - $reversedExpense;

        // In-period transfers
        $inTransfersInAgg = DB::table('cash_transfers')
            ->whereIn('destination_cash_account_id', $accountIds)
            ->where('transfer_date', '>=', $dateFrom)
            ->where('transfer_date', '<=', $dateTo)
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_amount, COUNT(*) AS count')
            ->first();

        $inTransfersOutAgg = DB::table('cash_transfers')
            ->whereIn('source_cash_account_id', $accountIds)
            ->where('transfer_date', '>=', $dateFrom)
            ->where('transfer_date', '<=', $dateTo)
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_amount, COUNT(*) AS count')
            ->first();

        $transfersIn = (int) ($inTransfersInAgg->total_amount ?? 0);
        $transfersOut = (int) ($inTransfersOutAgg->total_amount ?? 0);
        $netTransferMovement = $transfersIn - $transfersOut;
        $totalTransfersCount = (int) (($inTransfersInAgg->count ?? 0) + ($inTransfersOutAgg->count ?? 0));

        $netMovement = $effectiveIncome - $effectiveExpense + $netTransferMovement;
        $closingBalance = $openingBalance + $netMovement;

        $totalCount = (int) ($inPeriod->total_count ?? 0);
        $reversedCount = (int) ($inPeriod->reversed_count ?? 0);
        $effectiveCount = (int) ($inPeriod->effective_count ?? 0);

        return [
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'opening_balance' => $openingBalance,
            'posted_income' => $postedIncome,
            'reversed_income' => $reversedIncome,
            'effective_income' => $effectiveIncome,
            'posted_expense' => $postedExpense,
            'reversed_expense' => $reversedExpense,
            'effective_expense' => $effectiveExpense,
            'transfers_in' => $transfersIn,
            'transfers_out' => $transfersOut,
            'net_transfer_movement' => $netTransferMovement,
            'net_movement' => $netMovement,
            'closing_balance' => $closingBalance,
            'total_transactions_count' => $totalCount,
            'reversed_transactions_count' => $reversedCount,
            'effective_transactions_count' => $effectiveCount,
            'total_transfers_count' => $totalTransfersCount,
            'cash_account_count' => $cashAccountCount,
        ];
    }
}
