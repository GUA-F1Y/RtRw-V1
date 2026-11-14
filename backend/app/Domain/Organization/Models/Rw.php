<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Organization\Enums\UnitStatus;
use Database\Factories\RwFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'number', 'name', 'status'])]
#[UseFactory(RwFactory::class)]
class Rw extends Model implements ScopedResource
{
    /** @use HasFactory<RwFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => UnitStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<Rt, $this>
     */
    public function rts(): HasMany
    {
        return $this->hasMany(Rt::class);
    }

    public function scopeRef(): ScopeRef
    {
        return ScopeRef::rw($this->organization_id, $this->getKey());
    }
}
