<?php

namespace App\Models;

use App\Enums\StorageItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StorageItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_id',
        'type',
        'name',
        'slug',
        'size_bytes',
        'disk',
        'stored_path',
        'mime_type',
        'checksum',
        'metadata',
        'is_pinned',
        'is_favorite',
        'latest_version_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if ($item->slug === null) {
                $item->slug = Str::slug($item->name);
            }

            $item->slug = static::uniqueSlug($item);
        });

        static::updating(function (self $item): void {
            if ($item->isDirty('name') && ! $item->isDirty('slug')) {
                $item->slug = Str::slug($item->name);
            }

            if ($item->isDirty('slug')) {
                $item->slug = static::uniqueSlug($item);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_pinned' => 'boolean',
            'is_favorite' => 'boolean',
            'type' => StorageItemType::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(StorageItemVersion::class);
    }

    public function latestVersion(): BelongsTo
    {
        return $this->belongsTo(StorageItemVersion::class, 'latest_version_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(StorageItemPermission::class);
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(StorageShareLink::class);
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(StorageItemAudience::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(StorageTag::class)->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(StorageActivity::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    public function isFolder(): bool
    {
        return $this->type === StorageItemType::Folder;
    }

    public function isFile(): bool
    {
        return $this->type === StorageItemType::File;
    }

    public function isNote(): bool
    {
        return $this->type === StorageItemType::Note;
    }

    private static function uniqueSlug(self $item): string
    {
        $baseSlug = $item->slug !== '' ? $item->slug : Str::random(8);
        $slug = $baseSlug;
        $suffix = 0;

        while (static::query()
            ->where('user_id', $item->user_id)
            ->where('parent_id', $item->parent_id)
            ->where('slug', $slug)
            ->when($item->exists, fn (Builder $query) => $query->whereKeyNot($item->getKey()))
            ->exists()) {
            $slug = $baseSlug.'-'.(++$suffix);
        }

        return $slug;
    }
}
