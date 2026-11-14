<?php

declare(strict_types=1);

namespace App\Http\Requests\Resident;

use App\Domain\Resident\Enums\ResidencyType;
use App\Domain\Resident\Enums\ResidentBloodType;
use App\Domain\Resident\Enums\ResidentEducation;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterResidentRequest extends FormRequest
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
            'nik' => ['required', 'string', 'regex:/^[0-9]{16}$/'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'gender' => ['required', 'string', Rule::enum(ResidentGender::class)],
            'birth_place' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date_format:Y-m-d'],
            'religion' => ['required', 'string', 'max:50'],
            'marital_status' => ['required', 'string', Rule::enum(ResidentMaritalStatus::class)],
            'rt_id' => ['required', 'uuid', 'exists:rts,id'],
            'blood_type' => ['sometimes', 'string', Rule::enum(ResidentBloodType::class)],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:100'],
            'education' => ['sometimes', 'string', Rule::enum(ResidentEducation::class)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'residency_type' => ['sometimes', 'string', Rule::enum(ResidencyType::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::RESIDENT_REGISTER);
        });
    }
}
