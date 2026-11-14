<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ListBudgetPlansRequest extends FormRequest
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
            'year' => ['sometimes', 'nullable', 'integer', 'between:2000,2100'],
            'month' => ['sometimes', 'nullable', 'integer', 'between:1,12'],
            'status' => ['sometimes', 'nullable', 'string', 'in:DRAFT,PUBLISHED,SUPERSEDED,draft,published,superseded'],
            'version' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rw_id' => ['sometimes', 'nullable', 'uuid'],
            'rt_id' => ['sometimes', 'nullable'],
            'q' => ['sometimes', 'nullable', 'string', 'min:3', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
