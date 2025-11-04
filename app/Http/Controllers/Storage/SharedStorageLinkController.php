<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storage\Concerns\HandlesStorageItemResponses;
use App\Models\StorageShareLink;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharedStorageLinkController extends Controller
{
    use HandlesStorageItemResponses;

    public function show(Request $request, string $token): Response|BinaryFileResponse|StreamedResponse
    {
        $shareLink = StorageShareLink::query()
            ->where('token', $token)
            ->whereHas('item', static function ($query): void {
                $query->whereNull('deleted_at');
            })
            ->with(['item.latestVersion'])
            ->firstOrFail();

        if ($shareLink->isExpired()) {
            abort(404);
        }

        $shareLink->increment('view_count');

        $item = $shareLink->item;

        if ($item === null) {
            abort(404);
        }

        if ($item->isFile()) {
            return $this->streamStorageFile($item);
        }

        if ($item->isNote()) {
            return $this->renderStorageNote($item);
        }

        abort(404);
    }
}
