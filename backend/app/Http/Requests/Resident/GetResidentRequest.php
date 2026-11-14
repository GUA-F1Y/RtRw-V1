<?php

declare(strict_types=1);

namespace App\Http\Requests\Resident;

use App\Support\Api\Contracts\QueryContract;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class GetResidentRequest extends FormRequest
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
                if (QueryContract::parseIncludes((string) $value, QueryContract::RESIDENT) === null) {
                    $fail('The selected include parameter contains invalid or unsupported relations.');
                }
            }],
        ];
    }
}
