<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Contracts;

use App\Domain\Authorization\ScopeRef;

/**
 * Implemented by every model that lives somewhere in the organizational hierarchy,
 * so policies can authorize it by scope without knowing its concrete type.
 */
interface ScopedResource
{
    public function scopeRef(): ScopeRef;
}
