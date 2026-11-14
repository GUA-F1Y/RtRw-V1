<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Identity\Models\User;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class FinanceCashFlowTrendQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
    ) {}

    /**
     * Generate periodic cash flow trend aggregated by daily or monthly buckets.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     interval: 'daily'|'monthly',
     *     buckets: list<array{
     *         period: string,
     *         label: string,
     *         posted_income: int,
     *         reversed_income: int,
     *         effective_income: int,
     *         posted_expense: int,
     *         reversed_expense: int,
     *         effective_expense: int,
     *         net_movement: int,
     *         transaction_count: int
     *     }>
     * }
     */
    public function execute(User $user, array $filters = []): array
    {
        $interval = ($filters['interval'] ?? 'daily') === 'monthly' ? 'monthly' : 'daily';

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
                'interval' => $interval,
                'buckets' => [],
            ];
        }

        // 2. Query aggregate grouped by interval expression
        $dateFormatSql = $interval === 'monthly'
            ? "TO_CHAR(transaction_date, 'YYYY-MM')"
            : "TO_CHAR(transaction_date, 'YYYY-MM-DD')";

        $rows = DB::table('financial_transactions')
            ->whereIn('cash_account_id', $accountIds)
            ->where('transaction_date', '>=', $dateFrom)
            ->where('transaction_date', '<=', $dateTo)
            ->selectRaw("{$dateFormatSql} AS bucket_key")
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS posted_income,
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS posted_expense,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS reversed_income,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS reversed_expense,
                COUNT(*) AS transaction_count
            SQL)
            ->groupByRaw($dateFormatSql)
            ->orderBy('bucket_key')
            ->get()
            ->keyBy('bucket_key');

        $transferDateFormatSql = $interval === 'monthly'
            ? "TO_CHAR(transfer_date, 'YYYY-MM')"
            : "TO_CHAR(transfer_date, 'YYYY-MM-DD')";

        $transferInRows = DB::table('cash_transfers')
            ->whereIn('destination_cash_account_id', $accountIds)
            ->where('transfer_date', '>=', $dateFrom)
            ->where('transfer_date', '<=', $dateTo)
            ->selectRaw("{$transferDateFormatSql} AS bucket_key")
            ->selectRaw('COALESCE(SUM(amount), 0) AS transfers_in, COUNT(*) AS count_in')
            ->groupByRaw($transferDateFormatSql)
            ->get()
            ->keyBy('bucket_key');

        $transferOutRows = DB::table('cash_transfers')
            ->whereIn('source_cash_account_id', $accountIds)
            ->where('transfer_date', '>=', $dateFrom)
            ->where('transfer_date', '<=', $dateTo)
            ->selectRaw("{$transferDateFormatSql} AS bucket_key")
            ->selectRaw('COALESCE(SUM(amount), 0) AS transfers_out, COUNT(*) AS count_out')
            ->groupByRaw($transferDateFormatSql)
            ->get()
            ->keyBy('bucket_key');

        // 3. Build sequence of continuous buckets between dateFrom and dateTo
        $buckets = [];
        $start = new DateTimeImmutable($dateFrom);
        $end = new DateTimeImmutable($dateTo);

        if ($interval === 'monthly') {
            $current = $start->modify('first day of this month');
            $endMonth = $end->modify('first day of this month');
            $periodInterval = new DateInterval('P1M');

            while ($current <= $endMonth) {
                $key = $current->format('Y-m');
                $label = $current->format('M Y');
                $row = $rows->get($key);

                $postedIncome = (int) ($row->posted_income ?? 0);
                $reversedIncome = (int) ($row->reversed_income ?? 0);
                $effectiveIncome = $postedIncome - $reversedIncome;

                $postedExpense = (int) ($row->posted_expense ?? 0);
                $reversedExpense = (int) ($row->reversed_expense ?? 0);
                $effectiveExpense = $postedExpense - $reversedExpense;

                $transIn = (int) ($transferInRows->get($key)?->transfers_in ?? 0);
                $transOut = (int) ($transferOutRows->get($key)?->transfers_out ?? 0);
                $netTransMovement = $transIn - $transOut;
                $transCount = (int) ($transferInRows->get($key)?->count_in ?? 0) + (int) ($transferOutRows->get($key)?->count_out ?? 0);

                $netMovement = $effectiveIncome - $effectiveExpense + $netTransMovement;
                $txCount = (int) ($row->transaction_count ?? 0) + $transCount;

                $buckets[] = [
                    'period' => $key,
                    'label' => $label,
                    'posted_income' => $postedIncome,
                    'reversed_income' => $reversedIncome,
                    'effective_income' => $effectiveIncome,
                    'posted_expense' => $postedExpense,
                    'reversed_expense' => $reversedExpense,
                    'effective_expense' => $effectiveExpense,
                    'transfers_in' => $transIn,
                    'transfers_out' => $transOut,
                    'net_transfer_movement' => $netTransMovement,
                    'net_movement' => $netMovement,
                    'transaction_count' => $txCount,
                ];

                $current = $current->add($periodInterval);
            }
        } else {
            // Daily interval
            $current = $start;
            $periodInterval = new DateInterval('P1D');

            while ($current <= $end) {
                $key = $current->format('Y-m-d');
                $label = $current->format('d M');
                $row = $rows->get($key);

                $postedIncome = (int) ($row->posted_income ?? 0);
                $reversedIncome = (int) ($row->reversed_income ?? 0);
                $effectiveIncome = $postedIncome - $reversedIncome;

                $postedExpense = (int) ($row->posted_expense ?? 0);
                $reversedExpense = (int) ($row->reversed_expense ?? 0);
                $effectiveExpense = $postedExpense - $reversedExpense;

                $transIn = (int) ($transferInRows->get($key)?->transfers_in ?? 0);
                $transOut = (int) ($transferOutRows->get($key)?->transfers_out ?? 0);
                $netTransMovement = $transIn - $transOut;
                $transCount = (int) ($transferInRows->get($key)?->count_in ?? 0) + (int) ($transferOutRows->get($key)?->count_out ?? 0);

                $netMovement = $effectiveIncome - $effectiveExpense + $netTransMovement;
                $txCount = (int) ($row->transaction_count ?? 0) + $transCount;

                $buckets[] = [
                    'period' => $key,
                    'label' => $label,
                    'posted_income' => $postedIncome,
                    'reversed_income' => $reversedIncome,
                    'effective_income' => $effectiveIncome,
                    'posted_expense' => $postedExpense,
                    'reversed_expense' => $reversedExpense,
                    'effective_expense' => $effectiveExpense,
                    'transfers_in' => $transIn,
                    'transfers_out' => $transOut,
                    'net_transfer_movement' => $netTransMovement,
                    'net_movement' => $netMovement,
                    'transaction_count' => $txCount,
                ];

                $current = $current->add($periodInterval);
            }
        }

        return [
            'interval' => $interval,
            'buckets' => $buckets,
        ];
    }
}
