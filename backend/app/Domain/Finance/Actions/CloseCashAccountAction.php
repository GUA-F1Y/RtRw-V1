<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\CloseCashAccountInput;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Exceptions\CashAccountClosedException;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Services\AccountBalanceService;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CloseCashAccountAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AccountBalanceService $balanceService,
    ) {}

    public function execute(CashAccount $account, CloseCashAccountInput $input, ?User $actor = null): CashAccount
    {
        return DB::transaction(function () use ($account, $input, $actor): CashAccount {
            /** @var CashAccount $locked */
            $locked = CashAccount::where('id', $account->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isClosed()) {
                throw new CashAccountClosedException('This cash account is already closed.');
            }

            $balanceSummary = $this->balanceService->calculate($locked);

            $locked->status = CashAccountStatus::Closed;
            $locked->closed_at = Carbon::now();
            $locked->closed_reason = $input->reason;
            $locked->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.account.closed',
                description: "Cash account closed: {$locked->name}",
                subject: $locked,
                scope: $locked->scopeRef(),
                properties: [
                    'account_id' => $locked->id,
                    'closed_reason' => $input->reason,
                    'closed_at' => $locked->closed_at->toIso8601String(),
                    'final_balance' => $balanceSummary['current_balance'],
                    'balance_summary' => $balanceSummary,
                ]
            );

            return $locked;
        });
    }
}
