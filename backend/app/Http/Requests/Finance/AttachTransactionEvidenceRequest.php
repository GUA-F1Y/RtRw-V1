<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttachTransactionEvidenceRequest extends FormRequest
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
            'file_id' => ['sometimes', 'nullable', 'uuid', 'exists:files,id'],
            'file' => ['sometimes', 'nullable', 'file'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::FINANCE_TRANSACTION_ATTACH_EVIDENCE);

            if (! $this->has('file_id') && ! $this->hasFile('file')) {
                $v->errors()->add('file_id', 'Either a file_id or an uploaded file must be provided.');
            }
        });
    }
}
