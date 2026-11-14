<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinancialPeriodClosure;
use App\Domain\Identity\Models\User;

final class FinancialPeriodClosurePolicy
{
    public function __construct(
        private readonly AccessResolver $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->scopesGranting($user, PermissionKey::FinanceAccountView) !== []
            || $this->access->scopesGranting($user, PermissionKey::FinanceTransactionView) !== [];
    }

    public function view(User $user, FinancialPeriodClosure $closure): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceAccountView, $closure->scopeRef())
            || $this->access->allows($user, PermissionKey::FinanceTransactionView, $closure->scopeRef());
    }

    public function close(User $user, ScopeRef|CashAccount|FinancialPeriodClosure $target): bool
    {
        $scope = match (true) {
            $target instanceof ScopeRef => $target,
            $target instanceof CashAccount => $target->scopeRef(),
            $target instanceof FinancialPeriodClosure => $target->scopeRef(),
        };

        return $this->access->allows($user, PermissionKey::FinancePeriodClose, $scope);
    }

    public function preview(User $user, CashAccount $account): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceAccountView, $account->scopeRef())
            || $this->access->allows($user, PermissionKey::FinancePeriodClose, $account->scopeRef());
    }

    public function update(User $user, FinancialPeriodClosure $closure): bool
    {
        return false; // Historical closures are immutable
    }

    public function delete(User $user, FinancialPeriodClosure $closure): bool
    {
        return false; // Historical closures cannot be deleted
    }
}
