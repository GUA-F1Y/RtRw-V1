<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Finance\DTOs\CreateCashAccountInput;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Exceptions\FinanceScopeMismatch;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class CreateCashAccountAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CreateCashAccountInput $input, ?User $actor = null): CashAccount
    {
        if ($input->openingBalance < 0) {
            throw new ApiException('Opening balance must be greater than or equal to zero.', 422, 'invalid_opening_balance');
        }

        /** @var Rw|null $rw */
        $rw = Rw::find($input->rwId);
        if ($rw === null) {
            throw new FinanceScopeMismatch("RW '{$input->rwId}' does not exist.");
        }

        $organizationId = $rw->organization_id;
        $rtId = null;

        if ($input->rtId !== null) {
            /** @var Rt|null $rt */
            $rt = Rt::find($input->rtId);
            if ($rt === null) {
                throw new FinanceScopeMismatch("RT '{$input->rtId}' does not exist.");
            }
            if ($rt->rw_id !== $rw->id) {
                throw new FinanceScopeMismatch("RT '{$input->rtId}' does not belong to RW '{$rw->id}'.");
            }
            $rtId = $rt->id;
        }

        return DB::transaction(function () use ($input, $organizationId, $rw, $rtId, $actor): CashAccount {
            /** @var CashAccount $account */
            $account = CashAccount::create([
                'organization_id' => $organizationId,
                'rw_id' => $rw->id,
                'rt_id' => $rtId,
                'name' => $input->name,
                'account_number' => $input->accountNumber,
                'description' => $input->description,
                'status' => CashAccountStatus::Active,
                'opening_balance' => $input->openingBalance,
            ]);

            $scope = $rtId !== null
                ? ScopeRef::rt($organizationId, $rw->id, $rtId)
                : ScopeRef::rw($organizationId, $rw->id);

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.account.created',
                description: "Cash account created: {$account->name}",
                subject: $account,
                scope: $scope,
                properties: [
                    'account_id' => $account->id,
                    'name' => $account->name,
                    'scope_level' => $account->isRtAccount() ? 'RT' : 'RW',
                    'opening_balance' => $account->opening_balance,
                ]
            );

            return $account;
        });
    }
}
