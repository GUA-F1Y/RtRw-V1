<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateExpenditureRequest extends FormRequest
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
            'budget_line_id' => ['required', 'uuid', 'exists:budget_lines,id'],
            'cash_account_id' => ['required', 'uuid', 'exists:cash_accounts,id'],
            'proposed_amount' => ['required', 'integer', 'min:1', 'max:100000000000000'],
            'planned_date' => ['required', 'date_format:Y-m-d'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'evidence_file_id' => ['sometimes', 'nullable', 'uuid', 'exists:files,id'],
            'submit' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::FINANCE_EXPENDITURE_CREATE);
            $this->validateAttachmentAccess($v, 'evidence_file_id');
        });
    }
}
