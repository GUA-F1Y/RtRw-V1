<?php

declare(strict_types=1);

namespace App\Http\Requests\Resident;

use App\Domain\Resident\Enums\ResidencyType;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Support\Api\Contracts\QueryContract;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListResidentsRequest extends FormRequest
{
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
            'status' => ['sometimes', 'string', Rule::enum(ResidentStatus::class)],
            'rt_id' => ['sometimes', 'uuid'],
            'rw_id' => ['sometimes', 'uuid'],
            'household_id' => ['sometimes', 'uuid'],
            'gender' => ['sometimes', 'string', Rule::enum(ResidentGender::class)],
            'residency_type' => ['sometimes', 'string', Rule::enum(ResidencyType::class)],
            'marital_status' => ['sometimes', 'string', Rule::enum(ResidentMaritalStatus::class)],
            'q' => ['sometimes', 'string', 'min:' . QueryContract::MIN_SEARCH_LENGTH, 'max:' . QueryContract::MAX_SEARCH_LENGTH],
            'sort' => ['sometimes', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (QueryContract::parseSort((string) $value, QueryContract::RESIDENT) === null) {
                    $fail('The selected sort field is invalid.');
                }
            }],
            'include' => ['sometimes', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (QueryContract::parseIncludes((string) $value, QueryContract::RESIDENT) === null) {
                    $fail('The selected include parameter contains invalid or unsupported relations.');
                }
            }],
            'per_page' => ['sometimes', 'integer', 'min:' . QueryContract::MIN_PER_PAGE, 'max:' . QueryContract::MAX_PER_PAGE],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $disallowed = ['nik', 'phone', 'user_id', 'birth_date', 'religion', 'blood_type'];
            foreach ($disallowed as $field) {
                if ($this->has($field)) {
                    $v->errors()->add($field, "Filtering by {$field} is prohibited.");
                }
            }
        });
    }
}
