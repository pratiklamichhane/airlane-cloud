<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;
use Illuminate\Support\Str;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $team): void {
            if ($team->slug === null || $team->slug === '') {
                $team->slug = Str::slug($team->name);
            }

            $team->slug = static::uniqueSlug($team);
        });
    }

    public function users(): EloquentBelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function audiences(): EloquentHasMany
    {
        return $this->hasMany(StorageItemAudience::class);
    }

    private static function uniqueSlug(self $team): string
    {
        $base = $team->slug !== '' ? $team->slug : Str::random(8);
        $slug = $base;
        $suffix = 0;

        while (static::query()
            ->where('slug', $slug)
            ->when($team->exists, fn ($query) => $query->whereKeyNot($team->getKey()))
            ->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
