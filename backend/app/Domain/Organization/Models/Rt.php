<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Organization\Enums\UnitStatus;
use Database\Factories\RtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rw_id', 'number', 'name', 'status'])]
#[UseFactory(RtFactory::class)]
class Rt extends Model implements ScopedResource
{
    /** @use HasFactory<RtFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => UnitStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Rw, $this>
     */
    public function rw(): BelongsTo
    {
        return $this->belongsTo(Rw::class);
    }

    /**
     * The organization id is only reachable through the RW; the relation is loaded on
     * demand and should be eager loaded (`with('rw')`) when handling many RTs.
     */
    public function scopeRef(): ScopeRef
    {
        $rw = $this->relationLoaded('rw') ? $this->rw : $this->rw()->first();

        return ScopeRef::rt($rw->organization_id, $this->rw_id, $this->getKey());
    }
}
