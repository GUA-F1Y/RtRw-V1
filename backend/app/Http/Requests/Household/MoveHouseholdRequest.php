<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MoveHouseholdRequest extends FormRequest
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
            'move_type' => ['required', 'string', Rule::in(['INTER_RT'])],
            'target_rt_id' => ['required', 'uuid', 'exists:rts,id'],
            'new_address' => ['required', 'string', 'min:3', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::HOUSEHOLD_MOVE);
        });
    }
}
