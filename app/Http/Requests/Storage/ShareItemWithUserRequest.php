<?php

namespace App\Http\Requests\Storage;

use App\Enums\StoragePermission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ShareItemWithUserRequest extends FormRequest
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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $emails = $this->input('emails');

        if (is_string($emails)) {
            $segments = preg_split('/[\s,]+/', $emails, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $this->merge([
                'emails' => array_map(static fn (string $email) => strtolower(trim($email)), $segments),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'emails' => ['required', 'array', 'min:1'],
            'emails.*' => ['required', 'email', Rule::exists('users', 'email')],
            'permission' => ['required', Rule::in(self::ASSIGNABLE_PERMISSIONS)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function targetUsers(): Collection
    {
        $emails = $this->validated('emails');

        if (! is_array($emails)) {
            $emails = [$emails];
        }

        return User::query()
            ->whereIn('email', $emails)
            ->get();
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
