<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Identity\Models\User;

final class AuditPolicy
{
    public function __construct(private readonly AccessResolver $access) {}

    public function view(User $user, AuditLog $log): bool
    {
        return $this->access->allows($user, PermissionKey::AuditView, $log->scopeRef());
    }
}
