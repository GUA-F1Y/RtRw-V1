<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PostExpenditureRequest extends FormRequest
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
            'actual_amount' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000000000'],
            'transaction_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'cash_account_id' => ['sometimes', 'nullable', 'uuid', 'exists:cash_accounts,id'],
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'evidence_file_id' => ['sometimes', 'nullable', 'uuid', 'exists:files,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::FINANCE_EXPENDITURE_POST);
            $this->validateAttachmentAccess($v, 'evidence_file_id');
        });
    }
}
