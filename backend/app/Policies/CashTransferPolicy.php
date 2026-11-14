<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\CashTransfer;
use App\Domain\Identity\Models\User;

final class CashTransferPolicy
{
    public function __construct(
        private readonly AccessResolver $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->scopesGranting($user, PermissionKey::FinanceTransferView) !== [];
    }

    public function view(User $user, CashTransfer $transfer): bool
    {
        if ($this->access->allows($user, PermissionKey::FinanceTransferView, $transfer->scopeRef())) {
            return true;
        }

        if ($transfer->relationLoaded('sourceAccount') && $transfer->sourceAccount !== null) {
            if ($this->access->allows($user, PermissionKey::FinanceTransferView, $transfer->sourceAccount->scopeRef())) {
                return true;
            }
        }

        if ($transfer->relationLoaded('destinationAccount') && $transfer->destinationAccount !== null) {
            if ($this->access->allows($user, PermissionKey::FinanceTransferView, $transfer->destinationAccount->scopeRef())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create transfer requires authorization over BOTH source AND destination accounts.
     *
     * @param array{source: CashAccount, destination: CashAccount}|null $accounts
     */
    public function create(User $user, ?array $accounts = null): bool
    {
        if ($accounts === null || ! isset($accounts['source'], $accounts['destination'])) {
            return $this->access->scopesGranting($user, PermissionKey::FinanceTransferCreate) !== [];
        }

        $source = $accounts['source'];
        $dest = $accounts['destination'];

        $canSource = $this->access->allows($user, PermissionKey::FinanceTransferCreate, $source->scopeRef());
        $canDest = $this->access->allows($user, PermissionKey::FinanceTransferCreate, $dest->scopeRef());

        return $canSource && $canDest;
    }

    /**
     * Reversal requires authorization over BOTH source AND destination accounts.
     */
    public function reverse(User $user, CashTransfer $transfer): bool
    {
        $transfer->loadMissing(['sourceAccount', 'destinationAccount']);

        $source = $transfer->sourceAccount;
        $dest = $transfer->destinationAccount;

        if ($source === null || $dest === null) {
            return false;
        }

        $canSource = $this->access->allows($user, PermissionKey::FinanceTransferReverse, $source->scopeRef());
        $canDest = $this->access->allows($user, PermissionKey::FinanceTransferReverse, $dest->scopeRef());

        return $canSource && $canDest;
    }
}
