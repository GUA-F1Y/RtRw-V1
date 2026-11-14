<?php

declare(strict_types=1);

namespace App\Domain\File\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use App\Domain\File\Enums\FileVisibility;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'owner_id',
    'organization_id',
    'rw_id',
    'rt_id',
    'disk',
    'path',
    'original_name',
    'extension',
    'mime_type',
    'size_bytes',
    'checksum_sha256',
    'visibility',
    'category',
])]
class StoredFile extends Model implements ScopedResource
{
    use HasUuids, SoftDeletes;

    protected $table = 'files';

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'visibility' => FileVisibility::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Rw, $this>
     */
    public function rw(): BelongsTo
    {
        return $this->belongsTo(Rw::class);
    }

    /**
     * @return BelongsTo<Rt, $this>
     */
    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'file_id');
    }

    public function scopeRef(): ScopeRef
    {
        return new ScopeRef($this->organization_id, $this->rw_id, $this->rt_id);
    }

    public function isPrivate(): bool
    {
        return $this->visibility === FileVisibility::Private;
    }
}
