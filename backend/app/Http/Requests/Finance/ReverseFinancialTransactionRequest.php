<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ValidatesMutationPayload;
use App\Support\Api\Contracts\MutationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReverseFinancialTransactionRequest extends FormRequest
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
            'reversal_reason' => ['required', 'string', 'min:3', 'max:500'],
            'transaction_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateUnexpectedFields($v, MutationContract::FINANCE_TRANSACTION_REVERSE);
        });
    }
}
