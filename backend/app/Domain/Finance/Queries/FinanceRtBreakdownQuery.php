<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Rt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class FinanceRtBreakdownQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
    ) {}

    /**
     * Compute financial breakdown grouped by RT and RW-level holding accounts.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     items: list<array{
     *         rt_id: string|null,
     *         rt_number: string|null,
     *         label: string,
     *         scope_type: 'RW'|'RT',
     *         account_count: int,
     *         opening_balance: int,
     *         effective_income: int,
     *         effective_expense: int,
     *         net_movement: int,
     *         closing_balance: int
     *     }>,
     *     summary: array{
     *         total_accounts: int,
     *         total_opening_balance: int,
     *         total_effective_income: int,
     *         total_effective_expense: int,
     *         total_net_movement: int,
     *         total_closing_balance: int
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

        // 1. Authoritative scope constraint for cash accounts
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

        if (! empty($filters['rw_id'])) {
            $accountQuery->where('rw_id', $filters['rw_id']);
        }

        $accounts = $accountQuery->get(['id', 'rw_id', 'rt_id', 'opening_balance']);

        if ($accounts->isEmpty()) {
            return [
                'items' => [],
                'summary' => [
                    'total_accounts' => 0,
                    'total_opening_balance' => 0,
                    'total_effective_income' => 0,
                    'total_effective_expense' => 0,
                    'total_net_movement' => 0,
                    'total_closing_balance' => 0,
                ],
            ];
        }

        $accountIds = $accounts->pluck('id')->all();

        // 2. Load pre-period activity per account
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

        // 3. Load in-period activity per account
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
                COALESCE(SUM(CASE WHEN is_reversal = TRUE AND type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS reversed_expense
            SQL)
            ->get()
            ->keyBy('cash_account_id');

        $inTransfersInByAccount = DB::table('cash_transfers')
            ->whereIn('destination_cash_account_id', $accountIds)
            ->where('transfer_date', '>=', $dateFrom)
            ->where('transfer_date', '<=', $dateTo)
            ->groupBy('destination_cash_account_id')
            ->select('destination_cash_account_id')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_transfers_in')
            ->get()
            ->keyBy('destination_cash_account_id');

        $inTransfersOutByAccount = DB::table('cash_transfers')
            ->whereIn('source_cash_account_id', $accountIds)
            ->where('transfer_date', '>=', $dateFrom)
            ->where('transfer_date', '<=', $dateTo)
            ->groupBy('source_cash_account_id')
            ->select('source_cash_account_id')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_transfers_out')
            ->get()
            ->keyBy('source_cash_account_id');

        // 4. Resolve RT metadata
        $rtIds = $accounts->pluck('rt_id')->filter()->unique()->values()->all();
        $rtModels = ! empty($rtIds)
            ? Rt::whereIn('id', $rtIds)->get(['id', 'number'])->keyBy('id')
            : collect();

        // 5. Group accounts by rt_id (null for RW-level)
        $grouped = $accounts->groupBy(fn ($acc) => $acc->rt_id ?? '__RW__');

        $items = [];
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
        ];

        foreach ($grouped as $groupKey => $groupAccounts) {
            $isRw = $groupKey === '__RW__';
            $rtId = $isRw ? null : (string) $groupKey;
            $rtModel = $rtId !== null ? $rtModels->get($rtId) : null;
            $rtNumber = $rtModel ? str_pad((string) $rtModel->number, 3, '0', STR_PAD_LEFT) : null;
            $label = $isRw ? 'Kas Tingkat RW' : "RT {$rtNumber}";

            $groupOpeningBalance = 0;
            $groupEffectiveIncome = 0;
            $groupEffectiveExpense = 0;
            $groupTransfersIn = 0;
            $groupTransfersOut = 0;

            foreach ($groupAccounts as $account) {
                $baseOpening = (int) $account->opening_balance;
                $pre = $prePeriodByAccount->get($account->id);
                $preNet = $pre !== null
                    ? (((int) $pre->posted_income - (int) $pre->reversed_income) - ((int) $pre->posted_expense - (int) $pre->reversed_expense))
                    : 0;

                $preTransIn = (int) ($preTransfersInByAccount->get($account->id)?->total_transfers_in ?? 0);
                $preTransOut = (int) ($preTransfersOutByAccount->get($account->id)?->total_transfers_out ?? 0);
                $accountOpening = $baseOpening + $preNet + ($preTransIn - $preTransOut);
                $groupOpeningBalance += $accountOpening;

                $in = $inPeriodByAccount->get($account->id);
                if ($in !== null) {
                    $effInc = (int) $in->posted_income - (int) $in->reversed_income;
                    $effExp = (int) $in->posted_expense - (int) $in->reversed_expense;
                    $groupEffectiveIncome += $effInc;
                    $groupEffectiveExpense += $effExp;
                }

                $inTransIn = (int) ($inTransfersInByAccount->get($account->id)?->total_transfers_in ?? 0);
                $inTransOut = (int) ($inTransfersOutByAccount->get($account->id)?->total_transfers_out ?? 0);
                $groupTransfersIn += $inTransIn;
                $groupTransfersOut += $inTransOut;
            }

            $groupNetTransferMovement = $groupTransfersIn - $groupTransfersOut;
            $groupNetMovement = $groupEffectiveIncome - $groupEffectiveExpense + $groupNetTransferMovement;
            $groupClosingBalance = $groupOpeningBalance + $groupNetMovement;

            $items[] = [
                'rt_id' => $rtId,
                'rt_number' => $rtNumber,
                'label' => $label,
                'scope_type' => $isRw ? 'RW' : 'RT',
                'account_count' => $groupAccounts->count(),
                'opening_balance' => $groupOpeningBalance,
                'effective_income' => $groupEffectiveIncome,
                'effective_expense' => $groupEffectiveExpense,
                'transfers_in' => $groupTransfersIn,
                'transfers_out' => $groupTransfersOut,
                'net_transfer_movement' => $groupNetTransferMovement,
                'net_movement' => $groupNetMovement,
                'closing_balance' => $groupClosingBalance,
            ];

            $summary['total_opening_balance'] += $groupOpeningBalance;
            $summary['total_effective_income'] += $groupEffectiveIncome;
            $summary['total_effective_expense'] += $groupEffectiveExpense;
            $summary['total_transfers_in'] += $groupTransfersIn;
            $summary['total_transfers_out'] += $groupTransfersOut;
            $summary['total_net_transfer_movement'] += $groupNetTransferMovement;
            $summary['total_net_movement'] += $groupNetMovement;
            $summary['total_closing_balance'] += $groupClosingBalance;
        }

        // Sort: RW first, then by RT number
        usort($items, function ($a, $b) {
            if ($a['scope_type'] === 'RW' && $b['scope_type'] !== 'RW') return -1;
            if ($a['scope_type'] !== 'RW' && $b['scope_type'] === 'RW') return 1;
            return strcmp((string) $a['rt_number'], (string) $b['rt_number']);
        });

        return [
            'items' => $items,
            'summary' => $summary,
        ];
    }
}
