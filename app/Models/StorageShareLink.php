<?php

namespace App\Models;

use App\Enums\StoragePermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageShareLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'storage_item_id',
        'created_by',
        'token',
        'permission',
        'max_views',
        'view_count',
        'expires_at',
    ];

    protected $casts = [
        'permission' => StoragePermission::class,
        'expires_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StorageItem::class, 'storage_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        if ($this->expires_at !== null && now()->greaterThan($this->expires_at)) {
            return true;
        }

        if ($this->max_views !== null && $this->view_count >= $this->max_views) {
            return true;
        }

        return false;
    }
}
