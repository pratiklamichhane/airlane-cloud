<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class StorageTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'color',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $tag): void {
            $tag->slug = static::resolveSlug($tag);
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(StorageItem::class)->withTimestamps();
    }

    private static function resolveSlug(self $tag): string
    {
        $baseSlug = Str::slug($tag->name);
        $slug = $baseSlug;
        $suffix = 0;

        while (static::query()
            ->where('user_id', $tag->user_id)
            ->where('slug', $slug)
            ->when($tag->exists, fn (Builder $query) => $query->whereKeyNot($tag->getKey()))
            ->exists()) {
            $slug = $baseSlug.'-'.(++$suffix);
        }

        return $slug;
    }
}
