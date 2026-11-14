<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use App\Support\Api\Contracts\QueryContract;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class GetHouseholdRequest extends FormRequest
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
            'include' => ['sometimes', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (QueryContract::parseIncludes((string) $value, QueryContract::HOUSEHOLD) === null) {
                    $fail('The selected include parameter contains invalid or unsupported relations.');
                }
            }],
        ];
    }
}
