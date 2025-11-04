<?php

namespace App\Services\Storage;

use App\Enums\StorageItemType;
use App\Exceptions\Storage\FileSizeLimitExceededException;
use App\Models\StorageItem;
use App\Models\StorageItemVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class StorageService
{
    public function __construct(
        private readonly StorageQuotaManager $quotaManager,
    ) {
    }

    public function createFolder(User $user, string $name, ?StorageItem $parent = null, array $metadata = []): StorageItem
    {
        $this->assertParent($parent, $user);

        return DB::transaction(function () use ($user, $name, $parent, $metadata) {
            $item = StorageItem::create([
                'user_id' => $user->getKey(),
                'parent_id' => $parent?->getKey(),
                'type' => StorageItemType::Folder,
                'name' => $name,
                'metadata' => $metadata,
            ]);

            return $item->fresh();
        });
    }

    public function uploadFile(User $user, UploadedFile $file, ?StorageItem $parent = null, array $metadata = []): StorageItem
    {
        $this->assertParent($parent, $user);

        $size = (int) ($file->getSize() ?? 0);

        if ($size <= 0 && $file->isValid() && $file->getRealPath() !== false) {
            $realPath = $file->getRealPath();
            $derived = $realPath !== false ? filesize($realPath) : false;
            if ($derived !== false) {
                $size = (int) $derived;
            }
        }

        if ($size <= 0 && $file->isValid()) {
            $size = strlen($file->getContent());
        }

        if ($size <= 0) {
            throw new RuntimeException('Unable to determine uploaded file size.');
        }

        if ($size > $user->max_file_size_bytes) {
            throw FileSizeLimitExceededException::forLimit($size, (int) $user->max_file_size_bytes);
        }

        $this->quotaManager->ensureCanStore($user, $size);

        $disk = $this->disk();
        $checksum = null;
        if ($file->isValid() && $file->getRealPath() !== false) {
            $realPath = $file->getRealPath();
            $checksum = $realPath !== false ? hash_file('sha256', $realPath) : null;
        }

        $path = $this->storeFile($file, $user, $disk);
        $originalName = $file->getClientOriginalName();
        $displayName = $originalName !== '' ? $originalName : $file->hashName();
        $extension = $file->getClientOriginalExtension();

        return DB::transaction(function () use ($user, $parent, $metadata, $size, $disk, $path, $checksum, $displayName, $extension, $file) {
            $itemMetadata = array_merge($metadata, [
                'original_name' => $displayName,
                'extension' => $extension,
            ]);

            $item = StorageItem::create([
                'user_id' => $user->getKey(),
                'parent_id' => $parent?->getKey(),
                'type' => StorageItemType::File,
                'name' => $displayName,
                'stored_path' => $path,
                'disk' => $disk,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $size,
                'checksum' => $checksum,
                'metadata' => $itemMetadata,
            ]);

            $versionMetadata = array_merge($itemMetadata, [
                'uploaded_via' => 'app',
            ]);

            $version = $this->createVersion($item, [
                'created_by' => $user->getKey(),
                'disk' => $disk,
                'stored_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $size,
                'checksum' => $checksum,
                'metadata' => $versionMetadata,
            ]);

            $item->forceFill([
                'latest_version_id' => $version->getKey(),
            ])->save();

            $this->quotaManager->addUsage($user, $size);
            $this->quotaManager->enforceVersionCap($item);

            return $item->fresh(['latestVersion']);
        });
    }

    public function createNote(User $user, string $name, string $content, ?StorageItem $parent = null, array $metadata = []): StorageItem
    {
        $this->assertParent($parent, $user);

        $size = mb_strlen($content, '8bit');
        $this->quotaManager->ensureCanStore($user, $size);

        return DB::transaction(function () use ($user, $parent, $name, $content, $metadata, $size) {
            $item = StorageItem::create([
                'user_id' => $user->getKey(),
                'parent_id' => $parent?->getKey(),
                'type' => StorageItemType::Note,
                'name' => $name,
                'size_bytes' => $size,
                'metadata' => $metadata,
            ]);

            $version = $this->createVersion($item, [
                'created_by' => $user->getKey(),
                'disk' => 'database',
                'size_bytes' => $size,
                'content' => $content,
                'metadata' => $metadata,
            ]);

            $item->forceFill([
                'latest_version_id' => $version->getKey(),
            ])->save();

            $this->quotaManager->addUsage($user, $size);
            $this->quotaManager->enforceVersionCap($item);

            return $item->fresh(['latestVersion']);
        });
    }

    public function updateNote(StorageItem $item, string $content, ?User $author = null, array $metadata = []): StorageItemVersion
    {
        if (! $item->isNote()) {
            throw new InvalidArgumentException('Item is not a note.');
        }

        $user = $author ?? $item->owner;
    $size = mb_strlen($content, '8bit');

        $this->quotaManager->ensureCanStore($user, $size);

        return DB::transaction(function () use ($item, $content, $metadata, $size, $user) {
            $version = $this->createVersion($item, [
                'created_by' => $user->getKey(),
                'disk' => 'database',
                'size_bytes' => $size,
                'content' => $content,
                'metadata' => $metadata,
            ]);

            $item->forceFill([
                'latest_version_id' => $version->getKey(),
                'size_bytes' => $size,
            ])->save();

            $this->quotaManager->addUsage($user, $size);
            $this->quotaManager->enforceVersionCap($item);

            return $version->fresh();
        });
    }

    public function rename(StorageItem $item, string $name): StorageItem
    {
        $item->name = $name;
        $item->save();

        return $item->refresh();
    }

    public function move(StorageItem $item, ?StorageItem $parent): StorageItem
    {
        if ($parent !== null) {
            $this->assertParent($parent, $item->owner);

            if ($parent->is($item)) {
                throw new InvalidArgumentException('Cannot move an item into itself.');
            }

            $ancestor = $parent;
            while ($ancestor !== null) {
                if ($ancestor->is($item)) {
                    throw new InvalidArgumentException('Cannot move an item into its own descendant.');
                }

                $ancestor = $ancestor->parent;
            }
        }

        $item->parent()->associate($parent);
        $item->save();

        return $item->refresh();
    }

    public function delete(StorageItem $item): void
    {
        $item->delete();
    }

    public function restore(StorageItem $item): void
    {
        $item->restore();
    }

    private function createVersion(StorageItem $item, array $attributes): StorageItemVersion
    {
        $versionNumber = ($item->versions()->max('version') ?? 0) + 1;

        return $item->versions()->create(array_merge($attributes, [
            'version' => $versionNumber,
        ]));
    }

    private function assertParent(?StorageItem $parent, User $user): void
    {
        if ($parent === null) {
            return;
        }

        if (! $parent->isFolder()) {
            throw new InvalidArgumentException('Parent must be a folder.');
        }

        if ($parent->user_id !== $user->getKey()) {
            throw new InvalidArgumentException('Parent item belongs to a different user.');
        }
    }

    private function storeFile(UploadedFile $file, User $user, string $disk): string
    {
        $path = $this->userRoot($user);
        $filename = Str::uuid()->toString().($file->getClientOriginalExtension() ? '.'.$file->getClientOriginalExtension() : '');

        $storedPath = Storage::disk($disk)->putFileAs($path, $file, $filename);

        if ($storedPath === false) {
            throw new RuntimeException('Failed to persist uploaded file.');
        }

        return $storedPath;
    }

    private function disk(): string
    {
        return config('airlane.storage_disk', config('filesystems.default', 'local'));
    }

    private function userRoot(User $user): string
    {
        return 'users/'.$user->getKey();
    }
}
