<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddHouseholdMemberRequest extends FormRequest
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
            'resident_id' => ['required', 'uuid', 'exists:residents,id'],
            'relationship' => ['required', 'string', Rule::enum(HouseholdMemberRelationship::class)],
            'joined_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::HOUSEHOLD_ADD_MEMBER);
        });
    }
}
