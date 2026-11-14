<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\File\Models\StoredFile;
use App\Domain\Identity\Models\User;

final class StoredFilePolicy
{
    public function __construct(private readonly AccessResolver $access) {}

    public function view(User $user, StoredFile $file): bool
    {
        if (! $file->isPrivate()) {
            return true;
        }

        if ($file->owner_id === $user->id) {
            return true;
        }

        return $this->access->allows($user, PermissionKey::FileView, $file->scopeRef());
    }

    public function download(User $user, StoredFile $file): bool
    {
        return $this->view($user, $file);
    }

    public function delete(User $user, StoredFile $file): bool
    {
        if ($file->owner_id === $user->id) {
            return true;
        }

        return $this->access->allows($user, PermissionKey::FileUpload, $file->scopeRef());
    }
}
