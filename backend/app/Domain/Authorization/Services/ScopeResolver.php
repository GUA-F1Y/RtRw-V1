<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Authorization\Enums\ScopeLevel;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;

/**
 * Turns an identifier supplied by a client (scope_type + scope_id) into a trusted
 * ScopeRef built from persisted records. The client's claim is only a lookup key;
 * authorization is decided separately against the resolved scope.
 */
final class ScopeResolver
{
    public function resolve(ScopeLevel $level, string $id): ?ScopeRef
    {
        return match ($level) {
            ScopeLevel::Organization => Organization::query()->find($id)?->scopeRef(),
            ScopeLevel::Rw => Rw::query()->find($id)?->scopeRef(),
            ScopeLevel::Rt => Rt::query()->with('rw')->find($id)?->scopeRef(),
        };
    }
}
