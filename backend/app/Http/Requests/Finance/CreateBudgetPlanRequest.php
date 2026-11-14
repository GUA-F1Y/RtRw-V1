<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateBudgetPlanRequest extends FormRequest
{
    use ValidatesMutationPayload;

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
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'rt_id' => ['sometimes', 'nullable', 'uuid', 'exists:rts,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.category_id' => ['required', 'uuid', 'exists:finance_categories,id'],
            'lines.*.planned_amount' => ['required', 'integer', 'min:0', 'max:100000000000000'],
            'lines.*.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::FINANCE_BUDGET_CREATE);
        });
    }
}
