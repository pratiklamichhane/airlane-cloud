<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Models\StorageItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageItemController extends Controller
{
    public function show(Request $request, StorageItem $storageItem): Response|BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user === null || $storageItem->user_id !== $user->getKey()) {
            abort(404);
        }

        if ($storageItem->isFolder()) {
            return redirect()
                ->route('storage.index', ['folder' => $storageItem->getKey()]);
        }

        if ($storageItem->isFile()) {
            if ($storageItem->stored_path === null) {
                abort(404);
            }

            $disk = $storageItem->disk ?: config('airlane.storage_disk');

            if (! Storage::disk($disk)->exists($storageItem->stored_path)) {
                abort(404);
            }

            $name = $storageItem->metadata['original_name'] ?? $storageItem->name;
            $contentType = $storageItem->mime_type ?: 'application/octet-stream';
            $stream = Storage::disk($disk)->readStream($storageItem->stored_path);

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

        if ($storageItem->isNote()) {
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

        abort(404);
    }
}
