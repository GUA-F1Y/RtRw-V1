<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;

final class FinancialTransactionPolicy
{
    public function __construct(
        private readonly AccessResolver $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->scopesGranting($user, PermissionKey::FinanceTransactionView) !== [];
    }

    public function view(User $user, FinancialTransaction $transaction): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceTransactionView, $transaction->scopeRef());
    }

    public function create(User $user, ScopeRef|CashAccount|null $target = null): bool
    {
        if ($target === null) {
            return false;
        }

        $scope = $target instanceof CashAccount ? $target->scopeRef() : $target;

        return $this->access->allows($user, PermissionKey::FinanceTransactionRecord, $scope);
    }

    public function reverse(User $user, FinancialTransaction $transaction): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceTransactionReverse, $transaction->scopeRef());
    }

    public function attachEvidence(User $user, FinancialTransaction $transaction): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceTransactionRecord, $transaction->scopeRef());
    }

    public function downloadDocument(User $user, FinancialTransaction $transaction): bool
    {
        return $this->view($user, $transaction);
    }

    public function viewEvidence(User $user, FinancialTransaction $transaction): bool
    {
        return $this->view($user, $transaction);
    }
}
