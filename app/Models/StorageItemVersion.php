<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageItemVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'storage_item_id',
        'created_by',
        'version',
        'disk',
        'stored_path',
        'mime_type',
        'size_bytes',
        'checksum',
        'metadata',
        'content',
    ];

    protected $touches = [
        'item',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StorageItem::class, 'storage_item_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
