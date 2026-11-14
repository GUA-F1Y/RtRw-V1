<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class BudgetUtilizationRequest extends FormRequest
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
            'rw_id' => ['sometimes', 'nullable', 'uuid'],
            'rt_id' => ['sometimes', 'nullable'],
        ];
    }
}
