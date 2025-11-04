<?php

namespace App\Http\Requests\Storage;

use App\Enums\StoragePermission;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSharedItemPermissionRequest extends FormRequest
{
    private const ASSIGNABLE_PERMISSIONS = [
        StoragePermission::Viewer->value,
        StoragePermission::Editor->value,
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permission' => ['required', Rule::in(self::ASSIGNABLE_PERMISSIONS)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function permission(): StoragePermission
    {
        return StoragePermission::from($this->validated('permission'));
    }

    public function expiresAt(): ?CarbonImmutable
    {
        $expiresAt = $this->validated('expires_at');

        return $expiresAt ? CarbonImmutable::parse($expiresAt) : null;
    }
}
