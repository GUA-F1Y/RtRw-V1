<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\CloseFinancialPeriodInput;
use App\Domain\Finance\Exceptions\InvalidTransactionException;
use App\Domain\Finance\Exceptions\PeriodAlreadyClosedException;
use App\Domain\Finance\Exceptions\PeriodNotEligibleException;
use App\Domain\Finance\Exceptions\PeriodReconciliationException;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinancialPeriodClosure;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CloseFinancialPeriodAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CloseFinancialPeriodInput $input, ?User $actor = null): FinancialPeriodClosure
    {
        if ($actor === null) {
            throw new InvalidTransactionException('Authenticated user is required to close an accounting period.');
        }

        if ($input->year < 2000 || $input->year > 2100) {
            throw new InvalidArgumentException("Invalid year {$input->year}. Must be between 2000 and 2100.");
        }

        if ($input->month < 1 || $input->month > 12) {
            throw new InvalidArgumentException("Invalid month {$input->month}. Must be between 1 and 12.");
        }

        return DB::transaction(function () use ($input, $actor): FinancialPeriodClosure {
            /** @var CashAccount|null $lockedAccount */
            $lockedAccount = CashAccount::where('id', $input->cashAccountId)
                ->lockForUpdate()
                ->first();

            if ($lockedAccount === null) {
                throw new InvalidTransactionException("Cash account '{$input->cashAccountId}' not found.");
            }

            // 1. Validate period dates & eligibility
            $periodStart = Carbon::createFromDate($input->year, $input->month, 1)->startOfMonth()->toDateString();
            $periodEnd = Carbon::createFromDate($input->year, $input->month, 1)->endOfMonth()->toDateString();

            $currentMonthStart = Carbon::now()->startOfMonth();
            if (Carbon::parse($periodEnd)->greaterThanOrEqualTo($currentMonthStart)) {
                throw new PeriodNotEligibleException(
                    'Cannot close the current or a future period. Only completed calendar months prior to the current month can be closed.'
                );
            }

            // 2. Check if period has already been closed under lock
            $alreadyClosed = FinancialPeriodClosure::query()
                ->where('cash_account_id', $lockedAccount->id)
                ->where('period_year', $input->year)
                ->where('period_month', $input->month)
                ->exists();

            if ($alreadyClosed) {
                $monthFormatted = str_pad((string) $input->month, 2, '0', STR_PAD_LEFT);
                throw new PeriodAlreadyClosedException(
                    "Accounting period {$input->year}-{$monthFormatted} for account '{$lockedAccount->name}' has already been closed."
                );
            }

            // 3. Authoritative ledger snapshot calculation under lock
            // Pre-period movements (strictly before period_start)
            $prePeriod = DB::table('financial_transactions')
                ->where('cash_account_id', $lockedAccount->id)
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
                ->where('destination_cash_account_id', $lockedAccount->id)
                ->where('transfer_date', '<', $periodStart)
                ->sum('amount');

            $preTransfersOut = (int) DB::table('cash_transfers')
                ->where('source_cash_account_id', $lockedAccount->id)
                ->where('transfer_date', '<', $periodStart)
                ->sum('amount');

            $preNetMovement = ($prePostedIncome - $preReversedIncome) - ($prePostedExpense - $preReversedExpense) + ($preTransfersIn - $preTransfersOut);
            $openingBalance = (int) $lockedAccount->opening_balance + $preNetMovement;

            // In-period movements (period_start <= transaction_date <= period_end)
            $inPeriod = DB::table('financial_transactions')
                ->where('cash_account_id', $lockedAccount->id)
                ->where('transaction_date', '>=', $periodStart)
                ->where('transaction_date', '<=', $periodEnd)
                ->selectRaw(<<<'SQL'
                    COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS posted_income,
                    COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS posted_expense,
                    COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS reversed_income,
                    COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS reversed_expense,
                    COUNT(*) AS total_count,
                    COALESCE(SUM(CASE WHEN is_reversal = TRUE THEN 1 ELSE 0 END), 0) AS reversed_count
                SQL)
                ->first();

            $postedIncome = (int) ($inPeriod->posted_income ?? 0);
            $postedExpense = (int) ($inPeriod->posted_expense ?? 0);
            $reversedIncome = (int) ($inPeriod->reversed_income ?? 0);
            $reversedExpense = (int) ($inPeriod->reversed_expense ?? 0);

            $effectiveIncome = $postedIncome - $reversedIncome;
            $effectiveExpense = $postedExpense - $reversedExpense;

            $inTransfersIn = (int) DB::table('cash_transfers')
                ->where('destination_cash_account_id', $lockedAccount->id)
                ->where('transfer_date', '>=', $periodStart)
                ->where('transfer_date', '<=', $periodEnd)
                ->sum('amount');

            $inTransfersOut = (int) DB::table('cash_transfers')
                ->where('source_cash_account_id', $lockedAccount->id)
                ->where('transfer_date', '>=', $periodStart)
                ->where('transfer_date', '<=', $periodEnd)
                ->sum('amount');

            $netTransferMovement = $inTransfersIn - $inTransfersOut;

            $inTransfersCount = (int) DB::table('cash_transfers')
                ->where(function ($q) use ($lockedAccount) {
                    $q->where('source_cash_account_id', $lockedAccount->id)
                        ->orWhere('destination_cash_account_id', $lockedAccount->id);
                })
                ->where('transfer_date', '>=', $periodStart)
                ->where('transfer_date', '<=', $periodEnd)
                ->count();

            $netMovement = $effectiveIncome - $effectiveExpense + $netTransferMovement;
            $closingBalance = $openingBalance + $netMovement;

            $totalCount = (int) ($inPeriod->total_count ?? 0);
            $reversedCount = (int) ($inPeriod->reversed_count ?? 0);

            // 4. Physical count & reconciliation difference
            $countedCash = $input->countedCashBalance;
            $difference = null;

            if ($countedCash !== null) {
                if ($countedCash < 0) {
                    throw new InvalidArgumentException('Counted physical cash balance cannot be negative.');
                }

                // Invariant: reconciliation_difference = counted_cash_balance - ledger_closing_balance
                $difference = $countedCash - $closingBalance;

                if ($difference !== 0 && ($input->reconciliationNotes === null || trim($input->reconciliationNotes) === '')) {
                    throw new PeriodReconciliationException(
                        "A non-zero discrepancy of Rp {$difference} between physical cash and ledger closing balance requires an explanation in reconciliation notes."
                    );
                }
            }

            // 5. Persist immutable closure snapshot
            /** @var FinancialPeriodClosure $closure */
            $closure = FinancialPeriodClosure::create([
                'organization_id' => $lockedAccount->organization_id,
                'rw_id' => $lockedAccount->rw_id,
                'rt_id' => $lockedAccount->rt_id,
                'cash_account_id' => $lockedAccount->id,
                'period_year' => $input->year,
                'period_month' => $input->month,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
                'total_income' => $effectiveIncome,
                'total_expense' => $effectiveExpense,
                'posted_income' => $postedIncome,
                'reversed_income' => $reversedIncome,
                'posted_expense' => $postedExpense,
                'reversed_expense' => $reversedExpense,
                'transfers_in' => $inTransfersIn,
                'transfers_out' => $inTransfersOut,
                'net_transfer_movement' => $netTransferMovement,
                'transfer_count' => $inTransfersCount,
                'net_movement' => $netMovement,
                'transaction_count' => $totalCount,
                'reversal_count' => $reversedCount,
                'counted_cash_balance' => $countedCash,
                'reconciliation_difference' => $difference,
                'reconciliation_notes' => $input->reconciliationNotes !== null ? trim($input->reconciliationNotes) : null,
                'closed_by' => $actor->id,
                'closed_at' => Carbon::now(),
            ]);

            // 6. Record audit log event atomically
            $monthFormatted = str_pad((string) $input->month, 2, '0', STR_PAD_LEFT);
            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.period.closed',
                description: "Closed accounting period {$input->year}-{$monthFormatted} for {$lockedAccount->name}",
                subject: $closure,
                scope: $closure->scopeRef(),
                properties: [
                    'closure_id' => $closure->id,
                    'cash_account_id' => $lockedAccount->id,
                    'period_year' => $input->year,
                    'period_month' => $input->month,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'opening_balance' => $closure->opening_balance,
                    'closing_balance' => $closure->closing_balance,
                    'effective_income' => $closure->total_income,
                    'effective_expense' => $closure->total_expense,
                    'counted_cash_balance' => $closure->counted_cash_balance,
                    'reconciliation_difference' => $closure->reconciliation_difference,
                ]
            );

            return $closure;
        });
    }
}
