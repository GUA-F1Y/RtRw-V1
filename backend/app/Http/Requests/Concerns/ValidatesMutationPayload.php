<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\File\Models\StoredFile;
use App\Policies\StoredFilePolicy;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Validation\Validator;

trait ValidatesMutationPayload
{
    protected function validateUnexpectedFields(Validator $validator, string $operation): void
    {
        $unexpected = MutationContract::unexpectedFields($operation, $this->all());
        $reasons = MutationContract::rejected($operation);

        foreach ($unexpected as $field) {
            $reason = $reasons[$field] ?? "The {$field} field is not allowed for this operation.";
            $validator->errors()->add($field, $reason);
        }
    }

    protected function validateAttachmentAccess(Validator $validator, string $field = 'attachment_file_id'): void
    {
        if ($this->has($field) && $this->input($field) !== null) {
            $fileId = $this->input($field);
            if (! is_string($fileId) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $fileId)) {
                return; // Standard uuid rule reports syntax
            }

            /** @var StoredFile|null $file */
            $file = StoredFile::find($fileId);
            if ($file === null) {
                $validator->errors()->add($field, 'The referenced attachment file does not exist.');
                return;
            }

            $user = $this->user();
            if ($user === null || ! app(StoredFilePolicy::class)->view($user, $file)) {
                $validator->errors()->add($field, 'Unauthorized attachment file access.');
            }
        }
    }
}
