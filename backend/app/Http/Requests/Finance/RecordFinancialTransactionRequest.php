<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RecordFinancialTransactionRequest extends FormRequest
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
            'cash_account_id' => ['required', 'uuid', 'exists:cash_accounts,id'],
            'category_id' => ['required', 'uuid', 'exists:finance_categories,id'],
            'type' => ['required', 'string', 'in:INCOME,EXPENSE'],
            'amount' => ['required', 'integer', 'min:1'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'min:3', 'max:1000'],
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'evidence_file_id' => ['sometimes', 'nullable', 'uuid', 'exists:files,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::FINANCE_TRANSACTION_RECORD);
        });
    }
}
