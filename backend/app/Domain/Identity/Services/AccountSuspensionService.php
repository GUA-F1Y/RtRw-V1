<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Enums\AccountStatus;
use App\Domain\Identity\Models\User;

final class AccountSuspensionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Suspend a linked user account and revoke all authentication tokens upon civil death.
     */
    public function suspendOnCivilDeath(string $userId, string $residentId, ?User $actor = null): void
    {
        /** @var User|null $user */
        $user = User::where('id', $userId)
            ->lockForUpdate()
            ->first();

        if ($user === null) {
            return;
        }

        $user->status = AccountStatus::Suspended;
        $user->save();

        // Revoke all active authentication tokens
        $user->tokens()->delete();

        $this->auditLogger->record(
            actor: $actor,
            event: 'user.suspended_on_death',
            description: 'Linked user account suspended and tokens revoked upon resident civil death',
            subject: $user,
            properties: [
                'user_id' => $user->id,
                'resident_id' => $residentId,
            ]
        );
    }
}
