<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Enums\ScopeLevel;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Authorization\Services\ScopeResolver;
use App\Domain\File\Enums\FileVisibility;
use App\Domain\File\Models\StoredFile;
use App\Domain\File\Services\FileStorage;
use App\Http\Controllers\Controller;
use App\Http\Requests\File\UploadFileRequest;
use App\Http\Resources\StoredFileResource;
use App\Support\Api\ApiResponse;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly AccessResolver $access,
        private readonly FileStorage $fileStorage,
        private readonly AuditLogger $audit
    ) {}

    public function upload(UploadFileRequest $request): JsonResponse
    {
        $scopeLevel = ScopeLevel::from($request->validated('scope_type'));
        $scopeId = (string) $request->validated('scope_id');

        $scope = $this->scopeResolver->resolve($scopeLevel, $scopeId);
        if ($scope === null) {
            throw new ApiException('The target organizational scope was not found.', 404, 'scope_not_found');
        }

        // Authorize caller has FileUpload permission at the target scope
        if (! $this->access->allows($request->user(), PermissionKey::FileUpload, $scope)) {
            throw new AuthorizationException('You are not authorized to upload files to this scope.');
        }

        $visibility = $request->validated('visibility')
            ? FileVisibility::from($request->validated('visibility'))
            : FileVisibility::Private;

        $storedFile = $this->fileStorage->store(
            file: $request->file('file'),
            scope: $scope,
            owner: $request->user(),
            visibility: $visibility,
            category: $request->validated('category')
        );

        $this->audit->record(
            actor: $request->user(),
            event: 'file.uploaded',
            description: "Uploaded file {$storedFile->original_name}.{$storedFile->extension} to {$scopeLevel->value} scope.",
            subject: $storedFile,
            scope: $scope
        );

        return ApiResponse::success(new StoredFileResource($storedFile), status: 201);
    }

    public function show(StoredFile $file): JsonResponse
    {
        $this->authorize('view', $file);

        return ApiResponse::success(new StoredFileResource($file));
    }

    public function download(StoredFile $file): StreamedResponse
    {
        $this->authorize('download', $file);

        return $this->fileStorage->download($file);
    }
}
