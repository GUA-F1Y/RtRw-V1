<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\UpdateCashAccountInput;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateCashAccountDetailsAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CashAccount $account, UpdateCashAccountInput $input, ?User $actor = null): CashAccount
    {
        return DB::transaction(function () use ($account, $input, $actor): CashAccount {
            $changes = [];

            if ($input->name !== null && $input->name !== $account->name) {
                $changes['name'] = ['from' => $account->name, 'to' => $input->name];
                $account->name = $input->name;
            }

            if ($input->accountNumber !== null && $input->accountNumber !== $account->account_number) {
                $changes['account_number'] = ['from' => $account->account_number, 'to' => $input->accountNumber];
                $account->account_number = $input->accountNumber;
            }

            if ($input->description !== null && $input->description !== $account->description) {
                $changes['description'] = ['from' => $account->description, 'to' => $input->description];
                $account->description = $input->description;
            }

            if ($changes !== []) {
                $account->save();

                $this->auditLogger->record(
                    actor: $actor,
                    event: 'finance.account.updated',
                    description: "Cash account updated: {$account->name}",
                    subject: $account,
                    scope: $account->scopeRef(),
                    properties: [
                        'account_id' => $account->id,
                        'changes' => $changes,
                    ]
                );
            }

            return $account;
        });
    }
}
