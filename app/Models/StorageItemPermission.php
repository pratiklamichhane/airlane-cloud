<?php

namespace App\Models;

use App\Enums\StoragePermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageItemPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'storage_item_id',
        'user_id',
        'granted_by',
        'permission',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
