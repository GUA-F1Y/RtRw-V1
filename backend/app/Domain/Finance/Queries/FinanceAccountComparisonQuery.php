<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class FinanceAccountComparisonQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
    ) {}

    /**
     * Compare financial performance across permitted cash accounts for a reporting period.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     accounts: list<array{
     *         account_id: string,
     *         name: string,
     *         account_number: string|null,
     *         scope_type: 'RW'|'RT',
     *         rt_id: string|null,
     *         rt_number: string|null,
     *         status: string,
     *         opening_balance: int,
     *         effective_income: int,
     *         effective_expense: int,
     *         net_movement: int,
     *         closing_balance: int,
     *         transaction_count: int
     *     }>,
     *     summary: array{
     *         total_accounts: int,
     *         total_opening_balance: int,
     *         total_effective_income: int,
     *         total_effective_expense: int,
     *         total_net_movement: int,
     *         total_closing_balance: int,
     *         total_transactions: int
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

        // 1. Authoritative scope constraint
        $accountQuery = CashAccount::query()->with(['rt']);
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

        if (! empty($filters['status'])) {
            $accountQuery->where('status', strtoupper((string) $filters['status']));
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

        $accounts = $accountQuery->orderBy('name')->get();

        if ($accounts->isEmpty()) {
            return [
                'accounts' => [],
                'summary' => [
                    'total_accounts' => 0,
                    'total_opening_balance' => 0,
                    'total_effective_income' => 0,
                    'total_effective_expense' => 0,
                    'total_net_movement' => 0,
                    'total_closing_balance' => 0,
                    'total_transactions' => 0,
                ],
            ];
        }

        $accountIds = $accounts->pluck('id')->all();

        // 2. Pre-period aggregates per account
        $prePeriodByAccount = DB::table('financial_transactions')
            ->whereIn('cash_account_id', $accountIds)
            ->where('transaction_date', '<', $dateFrom)
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

        $preTransfersInByAccount = DB::table('cash_transfers')
            ->whereIn('destination_cash_account_id', $accountIds)
            ->where('transfer_date', '<', $dateFrom)
            ->groupBy('destination_cash_account_id')
            ->select('destination_cash_account_id')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_transfers_in')
            ->get()
            ->keyBy('destination_cash_account_id');

        $preTransfersOutByAccount = DB::table('cash_transfers')
            ->whereIn('source_cash_account_id', $accountIds)
            ->where('transfer_date', '<', $dateFrom)
            ->groupBy('source_cash_account_id')
            ->select('source_cash_account_id')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_transfers_out')
            ->get()
            ->keyBy('source_cash_account_id');

        // 3. In-period aggregates per account
        $inPeriodByAccount = DB::table('financial_transactions')
            ->whereIn('cash_account_id', $accountIds)
            ->where('transaction_date', '>=', $dateFrom)
            ->where('transaction_date', '<=', $dateTo)
            ->groupBy('cash_account_id')
            ->select('cash_account_id')
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS posted_income,
                COALESCE(SUM(CASE WHEN is_reversal = FALSE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS posted_expense,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'INCOME' THEN amount ELSE 0 END), 0) AS reversed_income,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS reversed_expense,
                COUNT(*) AS transaction_count
            SQL)
            ->get()
            ->keyBy('cash_account_id');

        $inTransfersInByAccount = DB::table('cash_transfers')
            ->whereIn('destination_cash_account_id', $accountIds)
            ->where('transfer_date', '>=', $dateFrom)
            ->where('transfer_date', '<=', $dateTo)
            ->groupBy('destination_cash_account_id')
            ->select('destination_cash_account_id')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_transfers_in, COUNT(*) AS count_in')
            ->get()
            ->keyBy('destination_cash_account_id');

        $inTransfersOutByAccount = DB::table('cash_transfers')
            ->whereIn('source_cash_account_id', $accountIds)
            ->where('transfer_date', '>=', $dateFrom)
            ->where('transfer_date', '<=', $dateTo)
            ->groupBy('source_cash_account_id')
            ->select('source_cash_account_id')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_transfers_out, COUNT(*) AS count_out')
            ->get()
            ->keyBy('source_cash_account_id');

        $accountItems = [];
        $summary = [
            'total_accounts' => $accounts->count(),
            'total_opening_balance' => 0,
            'total_effective_income' => 0,
            'total_effective_expense' => 0,
            'total_transfers_in' => 0,
            'total_transfers_out' => 0,
            'total_net_transfer_movement' => 0,
            'total_net_movement' => 0,
            'total_closing_balance' => 0,
            'total_transactions' => 0,
        ];

        foreach ($accounts as $account) {
            $baseOpening = (int) $account->opening_balance;
            $pre = $prePeriodByAccount->get($account->id);
            $preNet = $pre !== null
                ? (((int) $pre->posted_income - (int) $pre->reversed_income) - ((int) $pre->posted_expense - (int) $pre->reversed_expense))
                : 0;

            $preTransIn = (int) ($preTransfersInByAccount->get($account->id)?->total_transfers_in ?? 0);
            $preTransOut = (int) ($preTransfersOutByAccount->get($account->id)?->total_transfers_out ?? 0);
            $preNetTrans = $preTransIn - $preTransOut;

            $openingBalance = $baseOpening + $preNet + $preNetTrans;

            $in = $inPeriodByAccount->get($account->id);
            $effectiveIncome = $in !== null ? ((int) $in->posted_income - (int) $in->reversed_income) : 0;
            $effectiveExpense = $in !== null ? ((int) $in->posted_expense - (int) $in->reversed_expense) : 0;
            $txCount = $in !== null ? (int) $in->transaction_count : 0;

            $transIn = (int) ($inTransfersInByAccount->get($account->id)?->total_transfers_in ?? 0);
            $transOut = (int) ($inTransfersOutByAccount->get($account->id)?->total_transfers_out ?? 0);
            $transfersCount = (int) ($inTransfersInByAccount->get($account->id)?->count_in ?? 0)
                + (int) ($inTransfersOutByAccount->get($account->id)?->count_out ?? 0);
            $netTransferMovement = $transIn - $transOut;

            $netMovement = $effectiveIncome - $effectiveExpense + $netTransferMovement;
            $closingBalance = $openingBalance + $netMovement;

            $rtNumber = $account->rt ? str_pad((string) $account->rt->number, 3, '0', STR_PAD_LEFT) : null;

            $accountItems[] = [
                'account_id' => (string) $account->id,
                'name' => (string) $account->name,
                'account_number' => $account->account_number !== null ? (string) $account->account_number : null,
                'scope_type' => $account->rt_id !== null ? 'RT' : 'RW',
                'rt_id' => $account->rt_id !== null ? (string) $account->rt_id : null,
                'rt_number' => $rtNumber,
                'status' => (string) $account->status->value,
                'opening_balance' => $openingBalance,
                'effective_income' => $effectiveIncome,
                'effective_expense' => $effectiveExpense,
                'transfers_in' => $transIn,
                'transfers_out' => $transOut,
                'net_transfer_movement' => $netTransferMovement,
                'net_movement' => $netMovement,
                'closing_balance' => $closingBalance,
                'transaction_count' => $txCount + $transfersCount,
            ];

            $summary['total_opening_balance'] += $openingBalance;
            $summary['total_effective_income'] += $effectiveIncome;
            $summary['total_effective_expense'] += $effectiveExpense;
            $summary['total_transfers_in'] += $transIn;
            $summary['total_transfers_out'] += $transOut;
            $summary['total_net_transfer_movement'] += $netTransferMovement;
            $summary['total_net_movement'] += $netMovement;
            $summary['total_closing_balance'] += $closingBalance;
            $summary['total_transactions'] += ($txCount + $transfersCount);
        }

        return [
            'accounts' => $accountItems,
            'summary' => $summary,
        ];
    }
}
