<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storage\Concerns\HandlesStorageItemResponses;
use App\Models\StorageItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageItemController extends Controller
{
    use HandlesStorageItemResponses;

    public function show(Request $request, StorageItem $storageItem): Response|BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $storageItem->loadMissing('latestVersion');

        if (! $this->userHasAccess($request->user(), $storageItem)) {
            abort(404);
        }

        if ($storageItem->isFolder()) {
            return redirect()
                ->route('storage.index', ['folder' => $storageItem->getKey()]);
        }

        if ($storageItem->isFile()) {
            return $this->streamStorageFile($storageItem);
        }

        if ($storageItem->isNote()) {
            return $this->renderStorageNote($storageItem);
        }

        abort(404);
    }

    private function userHasAccess(?User $user, StorageItem $storageItem): bool
    {
        if ($user === null) {
            return false;
        }

        if ($storageItem->user_id === $user->getKey()) {
            return true;
        }

        return $storageItem->permissions()
            ->where('user_id', $user->getKey())
            ->where(static function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
