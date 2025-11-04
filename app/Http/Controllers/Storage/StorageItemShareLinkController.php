<?php

namespace App\Http\Controllers\Storage;

use App\Enums\StoragePermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\StoreShareLinkRequest;
use App\Http\Requests\Storage\UpdateShareLinkRequest;
use App\Models\StorageItem;
use App\Models\StorageShareLink;
use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StorageItemShareLinkController extends Controller
{
    public function store(
        StoreShareLinkRequest $request,
        StorageItem $storageItem,
        StorageService $storageService,
    ): RedirectResponse {
        $actor = $request->user();
        $this->ensureOwner($storageItem, $actor);

        $storageService->enablePublicLink(
            $storageItem,
            $actor,
            StoragePermission::Viewer,
            $request->expiresAt(),
            $request->maxViews(),
        );

        return redirect()->back();
    }

    public function update(
        UpdateShareLinkRequest $request,
        StorageItem $storageItem,
        StorageShareLink $storageShareLink,
        StorageService $storageService,
    ): RedirectResponse {
        $actor = $request->user();
        $this->ensureOwner($storageItem, $actor);
        $this->ensureLinkBelongsToItem($storageItem, $storageShareLink);

        $storageService->updatePublicLink(
            $storageShareLink,
            StoragePermission::Viewer,
            $request->expiresAt(),
            $request->maxViews(),
        );

        return redirect()->back();
    }

    public function destroy(
        Request $request,
        StorageItem $storageItem,
        StorageShareLink $storageShareLink,
        StorageService $storageService,
    ): RedirectResponse {
        $actor = $request->user();
        $this->ensureOwner($storageItem, $actor);
        $this->ensureLinkBelongsToItem($storageItem, $storageShareLink);

        $storageService->disablePublicLink($storageShareLink);

        return redirect()->back();
    }

    private function ensureOwner(StorageItem $item, ?User $user): void
    {
        if ($user === null || $item->user_id !== $user->getKey()) {
            abort(404);
        }
    }

    private function ensureLinkBelongsToItem(StorageItem $item, StorageShareLink $link): void
    {
        if ($link->storage_item_id !== $item->getKey()) {
            abort(404);
        }
    }
}
