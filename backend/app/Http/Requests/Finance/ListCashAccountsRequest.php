<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ListCashAccountsRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', 'string', 'in:ACTIVE,CLOSED'],
            'rw_id' => ['sometimes', 'nullable', 'uuid'],
            'rt_id' => ['sometimes', 'nullable', 'string'],
            'q' => ['sometimes', 'nullable', 'string', 'min:3', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
