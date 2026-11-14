<?php

declare(strict_types=1);

namespace App\Http\Requests\Overview;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GetOverviewRequest extends FormRequest
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
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $disallowed = ['organization_id', 'rw_id', 'rt_id'];
            foreach ($disallowed as $field) {
                if ($this->has($field)) {
                    $v->errors()->add($field, "Parameter '{$field}' is not permitted. Scope is strictly server-derived.");
                }
            }
        });
    }
}
