<?php

declare(strict_types=1);

namespace App\Domain\File\Services;

use App\Domain\Authorization\ScopeRef;
use App\Domain\File\Enums\FileVisibility;
use App\Domain\File\Models\StoredFile;
use App\Domain\Identity\Models\User;
use App\Support\Api\Exceptions\ApiException;
use App\Support\Api\Exceptions\BusinessRuleViolation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FileStorage
{
    /**
     * Store an uploaded file with strict validation and metadata extraction.
     */
    public function store(
        UploadedFile $file,
        ScopeRef $scope,
        ?User $owner = null,
        FileVisibility $visibility = FileVisibility::Private,
        ?string $category = null
    ): StoredFile {
        $this->validateFile($file);

        $diskName = $visibility === FileVisibility::Private
            ? (string) config('rtrw.files.private_disk', 'private')
            : (string) config('rtrw.files.public_disk', 'public');

        $disk = Storage::disk($diskName);

        $extension = mb_strtolower($file->getClientOriginalExtension());
        $filename = (string) Str::uuid().'.'.$extension;
        $relativePath = date('Y/m').'/'.$filename;

        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $sizeBytes = $file->getSize() ?: 0;
        $checksum = hash_file('sha256', $file->getRealPath()) ?: '';

        // Store file stream to disk
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new ApiException('Could not read uploaded file content.', 500);
        }

        try {
            $disk->put($relativePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return DB::transaction(function () use (
            $owner,
            $scope,
            $diskName,
            $relativePath,
            $file,
            $extension,
            $mimeType,
            $sizeBytes,
            $checksum,
            $visibility,
            $category
        ): StoredFile {
            /** @var StoredFile $storedFile */
            $storedFile = StoredFile::query()->create([
                'owner_id' => $owner?->id,
                'organization_id' => $scope->organizationId,
                'rw_id' => $scope->rwId,
                'rt_id' => $scope->rtId,
                'disk' => $diskName,
                'path' => $relativePath,
                'original_name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'extension' => $extension,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'checksum_sha256' => $checksum,
                'visibility' => $visibility,
                'category' => $category,
            ]);

            return $storedFile;
        });
    }

    /**
     * Download or stream a stored file.
     */
    public function download(StoredFile $file): StreamedResponse
    {
        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            throw new ApiException('File not found in storage.', 404, 'file_not_found');
        }

        $downloadName = $file->original_name.'.'.$file->extension;

        return $disk->download($file->path, $downloadName, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Delete stored file from database and disk.
     */
    public function delete(StoredFile $file): void
    {
        DB::transaction(function () use ($file): void {
            $file->delete(); // soft delete in DB
        });
    }

    /**
     * Hard-delete from storage and database.
     */
    public function purge(StoredFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->forceDelete();
    }

    private function validateFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new BusinessRuleViolation('Uploaded file is invalid.');
        }

        $maxKilobytes = (int) config('rtrw.files.max_kilobytes', 5120);
        if (($file->getSize() ?: 0) > $maxKilobytes * 1024) {
            throw new BusinessRuleViolation("File size exceeds the maximum limit of {$maxKilobytes}KB.");
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());
        $allowed = config('rtrw.files.allowed', []);

        if (! array_key_exists($extension, $allowed)) {
            throw new BusinessRuleViolation("File extension '.{$extension}' is not allowed.");
        }

        $detectedMime = $file->getMimeType();
        $allowedMimes = $allowed[$extension] ?? [];

        if (! in_array($detectedMime, $allowedMimes, true)) {
            throw new BusinessRuleViolation("Invalid file content type '{$detectedMime}' for extension '.{$extension}'.");
        }
    }
}
