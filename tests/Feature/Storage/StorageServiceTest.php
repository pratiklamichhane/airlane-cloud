<?php

use App\Enums\StorageItemType;
use App\Exceptions\Storage\FileSizeLimitExceededException;
use App\Models\StorageItem;
use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe(StorageService::class, function () {
    beforeEach(function (): void {
        Storage::fake(config('airlane.storage_disk'));
    });

    it('creates folders for a user', function (): void {
        $user = User::factory()->create();

        $service = app(StorageService::class);

        $folder = $service->createFolder($user, 'Projects');

        expect($folder)
            ->toBeInstanceOf(StorageItem::class)
            ->and($folder->type)
            ->toBe(StorageItemType::Folder)
            ->and($folder->name)
            ->toBe('Projects')
            ->and($folder->parent_id)
            ->toBeNull();
    });

    it('uploads files and registers usage', function (): void {
        $user = User::factory()->create();
        $service = app(StorageService::class);
        $file = UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4');

        $item = $service->uploadFile($user, $file);
        $disk = config('airlane.storage_disk');

    expect(Storage::disk($disk)->exists($item->stored_path))->toBeTrue();

        $expectedBytes = 1024 * 1024;

        expect($item->type)
            ->toBe(StorageItemType::File)
            ->and($item->size_bytes)
            ->toBe($expectedBytes)
            ->and($item->metadata['original_name'])
            ->toBe('clip.mp4')
            ->and($item->latestVersion)
            ->not->toBeNull()
            ->and($item->latestVersion->size_bytes)
            ->toBe($expectedBytes);

        expect($user->fresh()->storage_used_bytes)->toBe($expectedBytes);
    });

    it('rejects uploads that exceed the max file size', function (): void {
        $user = User::factory()->create();
        $service = app(StorageService::class);
        $file = UploadedFile::fake()->create('large-archive.zip', 25_000); // ~24.4 MB

        expect(fn () => $service->uploadFile($user, $file))
            ->toThrow(FileSizeLimitExceededException::class);
    });

    it('enforces the version cap on notes', function (): void {
        $user = User::factory()->create([
            'version_cap' => 2,
        ]);

        $service = app(StorageService::class);

        $note = $service->createNote($user, 'Journal', 'Alpha');
        $service->updateNote($note, 'Bravo');
        $service->updateNote($note->fresh(), 'Charlie');

        $item = $note->fresh(['versions']);
        $versions = $item->versions()->orderBy('version')->get();

        expect($versions)->toHaveCount(2)
            ->and($versions->first()->version)
            ->toBe(2)
            ->and($versions->last()->version)
            ->toBe(3)
            ->and($user->fresh()->storage_used_bytes)
            ->toBe(12);
    });
});
