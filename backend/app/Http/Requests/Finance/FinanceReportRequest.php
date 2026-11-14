<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class FinanceReportRequest extends FormRequest
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
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'cash_account_id' => ['sometimes', 'nullable', 'uuid'],
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'type' => ['sometimes', 'nullable', 'string', 'in:INCOME,EXPENSE,TRANSFER,income,expense,transfer'],
            'is_reversal' => ['sometimes', 'nullable', 'string'],
            'interval' => ['sometimes', 'nullable', 'string', 'in:daily,monthly'],
            'rw_id' => ['sometimes', 'nullable', 'uuid'],
            'rt_id' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', 'string', 'in:active,closed,ACTIVE,CLOSED'],
        ];
    }
}
