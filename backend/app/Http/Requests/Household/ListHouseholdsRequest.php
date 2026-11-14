<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use App\Domain\Household\Enums\HouseholdStatus;
use App\Support\Api\Contracts\QueryContract;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListHouseholdsRequest extends FormRequest
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
            'status' => ['sometimes', 'string', Rule::enum(HouseholdStatus::class)],
            'rt_id' => ['sometimes', 'uuid'],
            'rw_id' => ['sometimes', 'uuid'],
            'head_availability' => ['sometimes', 'string', Rule::in(QueryContract::HEAD_AVAILABILITY)],
            'q' => ['sometimes', 'string', 'min:' . QueryContract::MIN_SEARCH_LENGTH, 'max:' . QueryContract::MAX_SEARCH_LENGTH],
            'sort' => ['sometimes', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (QueryContract::parseSort((string) $value, QueryContract::HOUSEHOLD) === null) {
                    $fail('The selected sort field is invalid.');
                }
            }],
            'include' => ['sometimes', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (QueryContract::parseIncludes((string) $value, QueryContract::HOUSEHOLD) === null) {
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
            $disallowed = ['kk_number', 'nik', 'phone'];
            foreach ($disallowed as $field) {
                if ($this->has($field)) {
                    $v->errors()->add($field, "Filtering by {$field} is prohibited.");
                }
            }
        });
    }
}
