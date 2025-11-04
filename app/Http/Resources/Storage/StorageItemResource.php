<?php

namespace App\Http\Resources\Storage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StorageItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->type instanceof \App\Enums\StorageItemType
            ? $this->type
            : \App\Enums\StorageItemType::from($this->type);

        return [
            'id' => $this->getKey(),
            'type' => $type->value,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'size_bytes' => (int) $this->size_bytes,
            'disk' => $this->disk,
            'stored_path' => $this->stored_path,
            'mime_type' => $this->mime_type,
            'checksum' => $this->checksum,
            'metadata' => $this->metadata ?? [],
            'is_folder' => $type === \App\Enums\StorageItemType::Folder,
            'is_file' => $type === \App\Enums\StorageItemType::File,
            'is_note' => $type === \App\Enums\StorageItemType::Note,
            'is_pinned' => (bool) $this->is_pinned,
            'is_favorite' => (bool) $this->is_favorite,
            'latest_version' => $this->whenLoaded('latestVersion', function () {
                $version = $this->latestVersion;

                if ($version === null) {
                    return null;
                }

                return [
                    'id' => $version->getKey(),
                    'version' => (int) $version->version,
                    'size_bytes' => (int) $version->size_bytes,
                    'mime_type' => $version->mime_type,
                    'checksum' => $version->checksum,
                    'created_at' => $version->created_at?->toIso8601String(),
                ];
            }),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(static fn ($tag) => [
                    'id' => $tag->getKey(),
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])->all();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
