<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ListExpenditureRequestsRequest extends FormRequest
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
            'budget_line_id' => ['sometimes', 'nullable', 'uuid'],
            'cash_account_id' => ['sometimes', 'nullable', 'uuid'],
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', 'nullable', 'string', 'in:DRAFT,SUBMITTED,APPROVED,REJECTED,CANCELLED,POSTED,draft,submitted,approved,rejected,cancelled,posted'],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'rw_id' => ['sometimes', 'nullable', 'uuid'],
            'rt_id' => ['sometimes', 'nullable'],
            'q' => ['sometimes', 'nullable', 'string', 'min:3', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
