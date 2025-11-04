<?php

namespace App\Http\Controllers\Storage\Concerns;

use App\Models\StorageItem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HandlesStorageItemResponses
{
    protected function streamStorageFile(StorageItem $storageItem): StreamedResponse
    {
        $disk = $storageItem->disk ?: config('airlane.storage_disk');
        $storedPath = $storageItem->stored_path;

        if ($storedPath === null || ! Storage::disk($disk)->exists($storedPath)) {
            abort(404);
        }

        $name = $storageItem->metadata['original_name'] ?? $storageItem->name;
        $contentType = $storageItem->mime_type ?: 'application/octet-stream';
        $stream = Storage::disk($disk)->readStream($storedPath);

        if ($stream === false) {
            abort(404);
        }

        $headers = [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ];

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }

    protected function renderStorageNote(StorageItem $storageItem): Response
    {
        $storageItem->loadMissing('latestVersion');

        $content = $storageItem->latestVersion?->content;

        if ($content === null) {
            abort(404);
        }

        $noteName = $storageItem->metadata['original_name'] ?? $storageItem->name;

        return response($content)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Content-Disposition', 'inline; filename="'.$noteName.'.txt"');
    }
}
