<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBudgetPlanRequest extends FormRequest
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
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.category_id' => ['required_with:lines', 'uuid', 'exists:finance_categories,id'],
            'lines.*.planned_amount' => ['required_with:lines', 'integer', 'min:0', 'max:100000000000000'],
            'lines.*.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::FINANCE_BUDGET_UPDATE);
        });
    }
}
