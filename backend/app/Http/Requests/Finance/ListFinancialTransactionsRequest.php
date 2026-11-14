<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ListFinancialTransactionsRequest extends FormRequest
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
            'cash_account_id' => ['sometimes', 'nullable', 'uuid'],
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'type' => ['sometimes', 'nullable', 'string', 'in:INCOME,EXPENSE'],
            'is_reversal' => ['sometimes', 'nullable', 'string'],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'rw_id' => ['sometimes', 'nullable', 'uuid'],
            'rt_id' => ['sometimes', 'nullable', 'uuid'],
            'q' => ['sometimes', 'nullable', 'string', 'min:3', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
