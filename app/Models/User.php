<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Plan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'plan',
        'storage_used_bytes',
        'max_storage_bytes',
        'max_file_size_bytes',
        'version_cap',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'plan' => Plan::class,
            'is_admin' => 'boolean',
        ];
    }

    public function storageItems(): HasMany
    {
        return $this->hasMany(StorageItem::class);
    }

    public function storageTags(): HasMany
    {
        return $this->hasMany(StorageTag::class);
    }

    public function sharedStorageItems(): BelongsToMany
    {
        return $this->belongsToMany(StorageItem::class, 'storage_item_permissions')
            ->withPivot(['permission', 'granted_by', 'expires_at'])
            ->withTimestamps();
    }

    public function storageActivities(): HasMany
    {
        return $this->hasMany(StorageActivity::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function storageUsagePercent(): float
    {
        if ($this->max_storage_bytes === 0) {
            return 0.0;
        }

        return min(100, round(($this->storage_used_bytes / $this->max_storage_bytes) * 100, 2));
    }

    public function quotaRemainingBytes(): int
    {
        return max(0, (int) ($this->max_storage_bytes - $this->storage_used_bytes));
    }
}
