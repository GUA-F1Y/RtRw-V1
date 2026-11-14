<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ListFinancialPeriodClosuresRequest extends FormRequest
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
            'period_year' => ['sometimes', 'nullable', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:12'],
            'rw_id' => ['sometimes', 'nullable', 'uuid'],
            'rt_id' => ['sometimes', 'nullable'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
