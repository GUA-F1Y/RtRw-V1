<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinancialPeriodClosure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PreviewPeriodClosureQuery
{
    /**
     * Preview prospective closure snapshot for a cash account in a specific calendar month.
     *
     * @return array{
     *     account_id: string,
     *     account_name: string,
     *     period_year: int,
     *     period_month: int,
     *     period_start: string,
     *     period_end: string,
     *     opening_balance: int,
     *     closing_balance: int,
     *     posted_income: int,
     *     reversed_income: int,
     *     effective_income: int,
     *     posted_expense: int,
     *     reversed_expense: int,
     *     effective_expense: int,
     *     net_movement: int,
     *     total_transactions_count: int,
     *     reversed_transactions_count: int,
     *     effective_transactions_count: int,
     *     is_already_closed: bool,
     *     is_completed_month: bool,
     *     is_eligible_for_closure: bool,
     *     existing_closure_id: ?string
     * }
     */
    public function execute(CashAccount $account, int $year, int $month): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException("Invalid year {$year}. Must be between 2000 and 2100.");
        }

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Invalid month {$month}. Must be between 1 and 12.");
        }

        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $periodEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $currentMonthStart = Carbon::now()->startOfMonth();
        $isCompletedMonth = Carbon::parse($periodEnd)->lessThan($currentMonthStart);

        /** @var FinancialPeriodClosure|null $existingClosure */
        $existingClosure = FinancialPeriodClosure::query()
            ->where('cash_account_id', $account->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        $isAlreadyClosed = $existingClosure !== null;
        $isEligible = $isCompletedMonth && ! $isAlreadyClosed;

        // 1. Pre-period cash movement (transaction_date < period_start)
        $prePeriod = DB::table('financial_transactions')
            ->where('cash_account_id', $account->id)
            ->where('transaction_date', '<', $periodStart)
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

        $preTransfersIn = (int) DB::table('cash_transfers')
            ->where('destination_cash_account_id', $account->id)
            ->where('transfer_date', '<', $periodStart)
            ->sum('amount');

        $preTransfersOut = (int) DB::table('cash_transfers')
            ->where('source_cash_account_id', $account->id)
            ->where('transfer_date', '<', $periodStart)
            ->sum('amount');

        $preNetMovement = ($prePostedIncome - $preReversedIncome) - ($prePostedExpense - $preReversedExpense) + ($preTransfersIn - $preTransfersOut);
        $openingBalance = (int) $account->opening_balance + $preNetMovement;

        // 2. In-period movements (period_start <= transaction_date <= period_end)
        $inPeriod = DB::table('financial_transactions')
            ->where('cash_account_id', $account->id)
            ->where('transaction_date', '>=', $periodStart)
            ->where('transaction_date', '<=', $periodEnd)
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

        $inTransfersIn = (int) DB::table('cash_transfers')
            ->where('destination_cash_account_id', $account->id)
            ->where('transfer_date', '>=', $periodStart)
            ->where('transfer_date', '<=', $periodEnd)
            ->sum('amount');

        $inTransfersOut = (int) DB::table('cash_transfers')
            ->where('source_cash_account_id', $account->id)
            ->where('transfer_date', '>=', $periodStart)
            ->where('transfer_date', '<=', $periodEnd)
            ->sum('amount');

        $netTransferMovement = $inTransfersIn - $inTransfersOut;

        $inTransfersCount = (int) DB::table('cash_transfers')
            ->where(function ($q) use ($account) {
                $q->where('source_cash_account_id', $account->id)
                    ->orWhere('destination_cash_account_id', $account->id);
            })
            ->where('transfer_date', '>=', $periodStart)
            ->where('transfer_date', '<=', $periodEnd)
            ->count();

        $netMovement = $effectiveIncome - $effectiveExpense + $netTransferMovement;
        $closingBalance = $openingBalance + $netMovement;

        $totalCount = (int) ($inPeriod->total_count ?? 0);
        $reversedCount = (int) ($inPeriod->reversed_count ?? 0);
        $effectiveCount = (int) ($inPeriod->effective_count ?? 0);

        return [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'period_year' => $year,
            'period_month' => $month,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'total_income' => $effectiveIncome,
            'total_expense' => $effectiveExpense,
            'posted_income' => $postedIncome,
            'reversed_income' => $reversedIncome,
            'effective_income' => $effectiveIncome,
            'posted_expense' => $postedExpense,
            'reversed_expense' => $reversedExpense,
            'effective_expense' => $effectiveExpense,
            'transfers_in' => $inTransfersIn,
            'transfers_out' => $inTransfersOut,
            'net_transfer_movement' => $netTransferMovement,
            'transfer_count' => $inTransfersCount,
            'net_movement' => $netMovement,
            'total_transactions_count' => $totalCount,
            'reversed_transactions_count' => $reversedCount,
            'effective_transactions_count' => $effectiveCount,
            'is_already_closed' => $isAlreadyClosed,
            'is_completed_month' => $isCompletedMonth,
            'is_eligible_for_closure' => $isEligible,
            'existing_closure_id' => $existingClosure?->id,
        ];
    }
}
