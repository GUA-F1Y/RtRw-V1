<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Not mass-assignable on purpose: rows are written by EnsureIdempotency only.
 */
class IdempotencyKey extends Model
{
    use HasUuids;

    protected $table = 'idempotency_keys';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
