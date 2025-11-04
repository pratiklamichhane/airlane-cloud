<?php

namespace App\Http\Requests\Storage;

use App\Enums\StorageAudience;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShareItemWithAudienceRequest extends FormRequest
{
    private ?Team $team = null;

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
        if ($this->has('audience')) {
            $this->merge([
                'audience' => strtolower((string) $this->input('audience')),
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
        $audiences = array_map(static fn (StorageAudience $audience) => $audience->value, StorageAudience::cases());

        return [
            'audience' => ['required', Rule::in($audiences)],
            'team_id' => ['required_if:audience,'.StorageAudience::Team->value, 'nullable', 'integer', 'exists:teams,id'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->audience() !== StorageAudience::Team) {
            return;
        }

        $team = $this->team();
        $user = $this->user();

        if ($team === null || $user === null || ! $team->users()->whereKey($user->getKey())->exists()) {
            throw ValidationException::withMessages([
                'team_id' => 'You must belong to the selected team to share with it.',
            ]);
        }
    }

    public function audience(): StorageAudience
    {
        return StorageAudience::from($this->validated('audience'));
    }

    public function team(): ?Team
    {
        if ($this->audience() !== StorageAudience::Team) {
            return null;
        }

        if ($this->team !== null) {
            return $this->team;
        }

        $teamId = $this->validated('team_id');

        /** @var Team $team */
        $team = Team::query()->findOrFail($teamId);

        return $this->team = $team;
    }

    public function expiresAt(): ?CarbonImmutable
    {
        $expiresAt = $this->validated('expires_at');

        return $expiresAt ? CarbonImmutable::parse($expiresAt) : null;
    }
}
