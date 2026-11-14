<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Domain\Authorization\Enums\ScopeLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'scope_type' => ['required', Rule::enum(ScopeLevel::class)],
            'scope_id' => ['required', 'uuid'],
        ];
    }
}
