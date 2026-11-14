<?php

declare(strict_types=1);

namespace App\Http\Requests\Resident;

use App\Domain\Resident\Enums\ResidentEducation;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateResidentProfileRequest extends FormRequest
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
            'occupation' => ['sometimes', 'nullable', 'string', 'max:100'],
            'education' => ['sometimes', 'string', Rule::enum(ResidentEducation::class)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'marital_status' => ['sometimes', 'string', Rule::enum(ResidentMaritalStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::RESIDENT_UPDATE);
        });
    }
}
