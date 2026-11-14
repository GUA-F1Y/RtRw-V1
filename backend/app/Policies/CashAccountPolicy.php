<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Identity\Models\User;

final class CashAccountPolicy
{
    public function __construct(
        private readonly AccessResolver $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->scopesGranting($user, PermissionKey::FinanceAccountView) !== [];
    }

    public function view(User $user, CashAccount $account): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceAccountView, $account->scopeRef());
    }

    public function create(User $user, ScopeRef|CashAccount|null $target = null): bool
    {
        if ($target === null) {
            return false;
        }

        $scope = $target instanceof CashAccount ? $target->scopeRef() : $target;

        return $this->access->allows($user, PermissionKey::FinanceAccountManage, $scope);
    }

    public function update(User $user, CashAccount $account): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceAccountManage, $account->scopeRef());
    }

    public function close(User $user, CashAccount $account): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceAccountManage, $account->scopeRef());
    }
}
