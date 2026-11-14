<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ChangeHouseholdHeadRequest extends FormRequest
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
            'new_head_resident_id' => ['required', 'uuid', 'exists:residents,id'],
            'old_head_new_relationship' => [
                'sometimes',
                'nullable',
                'string',
                Rule::enum(HouseholdMemberRelationship::class),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::HOUSEHOLD_CHANGE_HEAD);
        });
    }
}
