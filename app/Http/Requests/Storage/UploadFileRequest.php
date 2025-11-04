<?php

namespace App\Http\Requests\Storage;

use App\Enums\StorageItemType;
use App\Models\StorageItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadFileRequest extends FormRequest
{
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
            'file' => ['required', 'file', 'max:'.$this->maxKilobytes()],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('storage_items', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->getKey())
                    ->where('type', StorageItemType::Folder->value)),
            ],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    public function parent(): ?StorageItem
    {
        $parentId = $this->validated('parent_id');

        if ($parentId === null) {
            return null;
        }

        return StorageItem::query()->find($parentId);
    }

    private function maxKilobytes(): int
    {
        $maxBytes = (int) ($this->user()?->max_file_size_bytes ?? 0);

        if ($maxBytes <= 0) {
            return 1;
        }

        return (int) max(1, (int) ceil($maxBytes / 1024));
    }
}
