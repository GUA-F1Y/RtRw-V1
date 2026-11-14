<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Domain\Authorization\Enums\PrivacyTier;
use Illuminate\Support\Collection;

/**
 * Gives a JsonResource an explicit, caller-selected representation tier.
 *
 * Rules (see docs/api/v1-contract.md, "Privacy tiers"):
 *  - The tier is passed in by the authorized caller (controller/query layer) after a
 *    Policy decision. The resource NEVER authorizes and NEVER reads the request.
 *  - The default is the most restrictive tier, so `Resource::collection(...)` or
 *    `new Resource($model)` can never silently over-expose.
 *  - There is intentionally no boolean "sensitive" switch and no request-driven toggle.
 *
 * @mixin \Illuminate\Http\Resources\Json\JsonResource
 */
trait HasPrivacyTier
{
    protected PrivacyTier $tier = PrivacyTier::Directory;

    /**
     * @param  mixed  $resource
     * @param  mixed  $tier
     */
    public function __construct($resource, mixed $tier = null)
    {
        parent::__construct($resource);

        if ($tier instanceof PrivacyTier) {
            $this->tier = $tier;
        } else {
            $this->tier = PrivacyTier::Directory;
        }
    }

    /**
     * Build a single resource at an explicit tier.
     *
     * @param  mixed  $resource
     */
    public static function at($resource, PrivacyTier $tier): static
    {
        return new static($resource, $tier);
    }

    /**
     * Build a list of resources that all share one explicit tier.
     * Returns a plain collection of resources so it can be handed to
     * ApiResponse::success() or used as the transform of ApiResponse::paginated().
     *
     * @param  iterable<mixed>  $resources
     * @return Collection<int, static>
     */
    public static function collectionAt(iterable $resources, PrivacyTier $tier): Collection
    {
        return Collection::make($resources)
            ->map(fn ($resource): static => new static($resource, $tier))
            ->values();
    }
}
