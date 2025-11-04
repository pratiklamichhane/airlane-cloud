<?php

namespace App\Http\Requests\Storage;

use App\Enums\StoragePermission;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShareLinkRequest extends FormRequest
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
            'max_views' => ['nullable', 'integer', 'min:1'],
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

    public function maxViews(): ?int
    {
        $maxViews = $this->validated('max_views');

        return $maxViews !== null ? (int) $maxViews : null;
    }
}
