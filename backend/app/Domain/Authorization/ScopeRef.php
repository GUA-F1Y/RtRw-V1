<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Domain\Authorization\Enums\ScopeLevel;

/**
 * A resolved point in the organizational hierarchy: organization, RW or RT.
 *
 * Always built server-side from persisted records. It carries the full ancestry so
 * "does this grant cover that target" never needs a database query.
 */
final readonly class ScopeRef
{
    public function __construct(
        public string $organizationId,
        public ?string $rwId = null,
        public ?string $rtId = null,
    ) {
        if ($rtId !== null && $rwId === null) {
            throw new \InvalidArgumentException('An RT scope requires its RW id.');
        }
    }

    public static function organization(string $organizationId): self
    {
        return new self($organizationId);
    }

    public static function rw(string $organizationId, string $rwId): self
    {
        return new self($organizationId, $rwId);
    }

    public static function rt(string $organizationId, string $rwId, string $rtId): self
    {
        return new self($organizationId, $rwId, $rtId);
    }

    public function level(): ScopeLevel
    {
        return match (true) {
            $this->rtId !== null => ScopeLevel::Rt,
            $this->rwId !== null => ScopeLevel::Rw,
            default => ScopeLevel::Organization,
        };
    }

    /**
     * Does a grant held at this scope extend to $target?
     *
     *  - organization scope covers everything in the same organization
     *  - RW scope covers that RW and the RTs inside it (never other RWs)
     *  - RT scope covers only that RT
     */
    public function covers(self $target): bool
    {
        if ($this->organizationId !== $target->organizationId) {
            return false;
        }

        return match ($this->level()) {
            ScopeLevel::Organization => true,
            ScopeLevel::Rw => $this->rwId === $target->rwId,
            ScopeLevel::Rt => $this->rtId === $target->rtId,
        };
    }

    public function equals(self $other): bool
    {
        return $this->organizationId === $other->organizationId
            && $this->rwId === $other->rwId
            && $this->rtId === $other->rtId;
    }
}
