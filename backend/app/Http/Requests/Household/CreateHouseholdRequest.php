<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateHouseholdRequest extends FormRequest
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
            'kk_number' => ['required', 'string', 'regex:/^[0-9]{16}$/'],
            'address' => ['required', 'string', 'min:3', 'max:500'],
            'rt_id' => ['required', 'uuid', 'exists:rts,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'initial_head_resident_id' => ['sometimes', 'nullable', 'uuid', 'exists:residents,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::HOUSEHOLD_CREATE);
        });
    }
}
