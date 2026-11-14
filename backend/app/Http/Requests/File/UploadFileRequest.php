<?php

declare(strict_types=1);

namespace App\Http\Requests\File;

use App\Domain\Authorization\Enums\ScopeLevel;
use App\Domain\File\Enums\FileVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadFileRequest extends FormRequest
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
            'file' => ['required', 'file'],
            'scope_type' => ['required', Rule::enum(ScopeLevel::class)],
            'scope_id' => ['required', 'uuid'],
            'visibility' => ['nullable', Rule::enum(FileVisibility::class)],
            'category' => ['nullable', 'string', 'max:64'],
        ];
    }
}
