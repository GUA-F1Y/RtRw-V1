<?php

declare(strict_types=1);

namespace App\Http\Requests\Resident;

use App\Domain\Resident\Enums\ResidencyMovementType;
use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RelocateResidentRequest extends FormRequest
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
        $allowedMovementTypes = [
            ResidencyMovementType::TransferInternal->value,
            ResidencyMovementType::MoveOutExternal->value,
            ResidencyMovementType::HouseholdSplit->value,
        ];

        return [
            'movement_type' => ['required', 'string', Rule::in($allowedMovementTypes)],
            'target_rt_id' => [
                'required_if:movement_type,' . ResidencyMovementType::TransferInternal->value,
                'nullable',
                'uuid',
                'exists:rts,id',
            ],
            'target_household_id' => ['nullable', 'uuid', 'exists:households,id'],
            'effective_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'reference_letter_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'attachment_file_id' => ['sometimes', 'nullable', 'uuid', 'exists:files,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::RESIDENT_RELOCATE);
            $this->validateAttachmentAccess($v);
        });
    }
}
