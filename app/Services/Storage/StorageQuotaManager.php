<?php

namespace App\Services\Storage;

use App\Exceptions\Storage\StorageQuotaExceededException;
use App\Models\StorageItem;
use App\Models\StorageItemVersion;
use App\Models\User;

class StorageQuotaManager
{
    public function canStore(User $user, int $bytes): bool
    {
        return $user->quotaRemainingBytes() >= $bytes;
    }

    public function ensureCanStore(User $user, int $bytes): void
    {
        if (! $this->canStore($user, $bytes)) {
            throw StorageQuotaExceededException::forUser($bytes, $user->quotaRemainingBytes());
        }
    }

    public function addUsage(User $user, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }

        $user->forceFill([
            'storage_used_bytes' => $user->storage_used_bytes + $bytes,
        ])->save();
    }

    public function subtractUsage(User $user, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }

        $user->forceFill([
            'storage_used_bytes' => max(0, $user->storage_used_bytes - $bytes),
        ])->save();
    }

    public function recalculate(User $user): void
    {
        $usage = (int) StorageItemVersion::query()
            ->whereHas('item', fn ($query) => $query->where('user_id', $user->getKey()))
            ->sum('size_bytes');

        $user->forceFill([
            'storage_used_bytes' => $usage,
        ])->save();
    }

    public function enforceVersionCap(StorageItem $item): void
    {
        $item->loadMissing('owner');

        $owner = $item->owner;

        if ($owner === null) {
            return;
        }

        $cap = (int) $owner->version_cap;

        if ($cap <= 0) {
            return;
        }

        $versionCount = $item->versions()->count();

        if ($versionCount <= $cap) {
            return;
        }

        $excessVersions = $item->versions()
            ->orderBy('version')
            ->take($versionCount - $cap)
            ->get();

        foreach ($excessVersions as $version) {
            $this->subtractUsage($owner, (int) $version->size_bytes);
            $version->delete();
        }
    }
}
