<?php

namespace App\Models;

use App\Enums\StorageAudience;
use App\Enums\StoragePermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageItemAudience extends Model
{
    use HasFactory;

    protected $fillable = [
        'storage_item_id',
        'audience',
        'team_id',
        'created_by',
        'permission',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'audience' => StorageAudience::class,
            'permission' => StoragePermission::class,
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StorageItem::class, 'storage_item_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
