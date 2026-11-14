<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\CashTransfer;
use App\Domain\Finance\Models\FinancialTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AccountBalanceService
{
    /**
     * Calculate comprehensive financial balance breakdown for a cash account,
     * incorporating operating transactions and internal cash transfers.
     *
     * Balance formula:
     *   net_income = posted_income - reversed_income
     *   net_expense = posted_expense - reversed_expense
     *   net_transfers = transfers_in - transfers_out
     *   current_balance = opening_balance + net_income - net_expense + net_transfers
     *
     * @return array{
     *     opening_balance: int,
     *     posted_income: int,
     *     posted_expense: int,
     *     reversed_income: int,
     *     reversed_expense: int,
     *     net_income: int,
     *     net_expense: int,
     *     transfers_in: int,
     *     transfers_out: int,
     *     net_transfers: int,
     *     current_balance: int,
     *     total_transactions_count: int,
     *     reversed_transactions_count: int,
     *     total_transfers_count: int
     * }
     */
    public function calculate(CashAccount $account): array
    {
        $aggregates = FinancialTransaction::query()
            ->where('cash_account_id', $account->id)
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS posted_income,
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS posted_expense,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS reversed_income,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS reversed_expense,
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE THEN 1 ELSE 0 END), 0) AS reversed_count
            SQL)
            ->first();

        // Transfers in: transfers where this account is the destination
        $transfersInAgg = CashTransfer::query()
            ->where('destination_cash_account_id', $account->id)
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_amount, COUNT(*) AS total_count')
            ->first();

        // Transfers out: transfers where this account is the source
        $transfersOutAgg = CashTransfer::query()
            ->where('source_cash_account_id', $account->id)
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_amount, COUNT(*) AS total_count')
            ->first();

        $openingBalance = (int) $account->opening_balance;
        $postedIncome = (int) ($aggregates->posted_income ?? 0);
        $postedExpense = (int) ($aggregates->posted_expense ?? 0);
        $reversedIncome = (int) ($aggregates->reversed_income ?? 0);
        $reversedExpense = (int) ($aggregates->reversed_expense ?? 0);
        $totalCount = (int) ($aggregates->total_count ?? 0);
        $reversedCount = (int) ($aggregates->reversed_count ?? 0);

        $transfersIn = (int) ($transfersInAgg->total_amount ?? 0);
        $transfersOut = (int) ($transfersOutAgg->total_amount ?? 0);
        $netTransfers = $transfersIn - $transfersOut;
        $totalTransfersCount = (int) (($transfersInAgg->total_count ?? 0) + ($transfersOutAgg->total_count ?? 0));

        $netIncome = $postedIncome - $reversedIncome;
        $netExpense = $postedExpense - $reversedExpense;
        $currentBalance = $openingBalance + $netIncome - $netExpense + $netTransfers;

        return [
            'opening_balance' => $openingBalance,
            'posted_income' => $postedIncome,
            'posted_expense' => $postedExpense,
            'reversed_income' => $reversedIncome,
            'reversed_expense' => $reversedExpense,
            'net_income' => $netIncome,
            'net_expense' => $netExpense,
            'transfers_in' => $transfersIn,
            'transfers_out' => $transfersOut,
            'net_transfers' => $netTransfers,
            'current_balance' => $currentBalance,
            'total_transactions_count' => $totalCount,
            'reversed_transactions_count' => $reversedCount,
            'total_transfers_count' => $totalTransfersCount,
        ];
    }

    /**
     * Efficiently batch calculate current balance for a collection of accounts.
     * Prevents N+1 queries when rendering cash account lists.
     *
     * @param  Collection<int, CashAccount>  $accounts
     * @return array<string, int> Map of account_id => current_balance
     */
    public function batchCurrentBalances(Collection $accounts): array
    {
        if ($accounts->isEmpty()) {
            return [];
        }

        $accountIds = $accounts->pluck('id')->all();

        $txRows = DB::table('financial_transactions')
            ->whereIn('cash_account_id', $accountIds)
            ->groupBy('cash_account_id')
            ->select('cash_account_id')
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS posted_income,
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS posted_expense,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS reversed_income,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS reversed_expense
            SQL)
            ->get()
            ->keyBy('cash_account_id');

        $transfersInRows = DB::table('cash_transfers')
            ->whereIn('destination_cash_account_id', $accountIds)
            ->groupBy('destination_cash_account_id')
            ->select('destination_cash_account_id')
            ->selectRaw('COALESCE(SUM(amount), 0) AS transfers_in')
            ->get()
            ->keyBy('destination_cash_account_id');

        $transfersOutRows = DB::table('cash_transfers')
            ->whereIn('source_cash_account_id', $accountIds)
            ->groupBy('source_cash_account_id')
            ->select('source_cash_account_id')
            ->selectRaw('COALESCE(SUM(amount), 0) AS transfers_out')
            ->get()
            ->keyBy('source_cash_account_id');

        $balances = [];
        foreach ($accounts as $account) {
            $opening = (int) $account->opening_balance;
            $txRow = $txRows->get($account->id);

            $netOperating = 0;
            if ($txRow !== null) {
                $postedIncome = (int) $txRow->posted_income;
                $postedExpense = (int) $txRow->posted_expense;
                $reversedIncome = (int) $txRow->reversed_income;
                $reversedExpense = (int) $txRow->reversed_expense;
                $netOperating = ($postedIncome - $reversedIncome) - ($postedExpense - $reversedExpense);
            }

            $inRow = $transfersInRows->get($account->id);
            $transfersIn = $inRow !== null ? (int) $inRow->transfers_in : 0;

            $outRow = $transfersOutRows->get($account->id);
            $transfersOut = $outRow !== null ? (int) $outRow->transfers_out : 0;

            $balances[$account->id] = $opening + $netOperating + $transfersIn - $transfersOut;
        }

        return $balances;
    }
}
