<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Top of the hierarchy (e.g. a housing complex / kelurahan area) that groups RWs.
 */
#[Fillable(['name', 'slug'])]
#[UseFactory(OrganizationFactory::class)]
class Organization extends Model implements ScopedResource
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUuids;

    /**
     * @return HasMany<Rw, $this>
     */
    public function rws(): HasMany
    {
        return $this->hasMany(Rw::class);
    }

    public function scopeRef(): ScopeRef
    {
        return ScopeRef::organization($this->getKey());
    }
}
