<?php

namespace App\Http\Resources\Storage;

use App\Enums\StorageAudience;
use App\Enums\StorageItemType;
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
        $type = $this->type instanceof StorageItemType
            ? $this->type
            : StorageItemType::from($this->type);

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
            'is_folder' => $type === StorageItemType::Folder,
            'is_file' => $type === StorageItemType::File,
            'is_note' => $type === StorageItemType::Note,
            'is_pinned' => (bool) $this->is_pinned,
            'is_favorite' => (bool) $this->is_favorite,
            'latest_version' => $this->whenLoaded('latestVersion', function () use ($type) {
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
                    'content' => $type === StorageItemType::Note ? $version->content : null,
                ];
            }),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(static fn ($tag) => [
                    'id' => $tag->getKey(),
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])->all();
            }),
            'sharing' => [
                'public_link' => $this->whenLoaded('shareLinks', function () {
                    $link = $this->shareLinks->first();

                    if ($link === null) {
                        return null;
                    }

                    return [
                        'id' => $link->getKey(),
                        'permission' => $link->permission->value,
                        'max_views' => $link->max_views,
                        'view_count' => $link->view_count,
                        'expires_at' => $link->expires_at?->toIso8601String(),
                        'token' => $link->token,
                        'url' => route('storage.share-links.show', ['token' => $link->token]),
                    ];
                }, null),
                'company' => $this->whenLoaded('audiences', function () {
                    $audience = $this->audiences->firstWhere('audience', StorageAudience::Company);

                    if ($audience === null) {
                        return null;
                    }

                    return [
                        'id' => $audience->getKey(),
                        'expires_at' => $audience->expires_at?->toIso8601String(),
                    ];
                }, null),
                'teams' => $this->whenLoaded('audiences', function () {
                    return $this->audiences
                        ->filter(fn ($audience) => $audience->audience === StorageAudience::Team)
                        ->map(function ($audience) {
                            return [
                                'id' => $audience->getKey(),
                                'expires_at' => $audience->expires_at?->toIso8601String(),
                                'team' => $audience->relationLoaded('team') && $audience->team
                                    ? [
                                        'id' => $audience->team->getKey(),
                                        'name' => $audience->team->name,
                                        'slug' => $audience->team->slug,
                                    ]
                                    : null,
                            ];
                        })
                        ->values()
                        ->all();
                }, []),
                'permissions' => $this->whenLoaded('permissions', function () {
                    return $this->permissions->map(static function ($permission) {
                        return [
                            'id' => $permission->getKey(),
                            'permission' => $permission->permission->value,
                            'expires_at' => $permission->expires_at?->toIso8601String(),
                            'user' => $permission->relationLoaded('user') && $permission->user
                                ? [
                                    'id' => $permission->user->getKey(),
                                    'name' => $permission->user->name,
                                    'email' => $permission->user->email,
                                ]
                                : null,
                        ];
                    })->all();
                }, []),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
