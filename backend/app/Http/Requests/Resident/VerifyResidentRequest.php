<?php

declare(strict_types=1);

namespace App\Http\Requests\Resident;

use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VerifyResidentRequest extends FormRequest
{
    use ValidatesMutationPayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'reference_letter_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'attachment_file_id' => ['sometimes', 'nullable', 'uuid', 'exists:files,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::RESIDENT_VERIFY);
            $this->validateAttachmentAccess($v);
        });
    }
}
