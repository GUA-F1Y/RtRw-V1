<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Authorization\Contracts\ScopedResource;
use App\Domain\Authorization\ScopeRef;
use Spatie\Activitylog\Models\Activity;

/**
 * Custom audit log model backed by PostgreSQL append-only activity_log table.
 */
class AuditLog extends Activity implements ScopedResource
{
    protected $casts = [
        'properties' => 'collection',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeRef(): ScopeRef
    {
        return new ScopeRef($this->organization_id, $this->rw_id, $this->rt_id);
    }
}
