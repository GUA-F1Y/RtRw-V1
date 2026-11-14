<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\File\Models\StoredFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StoredFile
 */
class StoredFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'visibility' => $this->visibility->value,
            'category' => $this->category,
            'organization_id' => $this->organization_id,
            'rw_id' => $this->rw_id,
            'rt_id' => $this->rt_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
