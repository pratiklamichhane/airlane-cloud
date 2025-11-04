<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\ShareItemWithUserRequest;
use App\Http\Requests\Storage\UpdateSharedItemPermissionRequest;
use App\Models\StorageItem;
use App\Models\StorageItemPermission;
use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StorageItemSharePermissionController extends Controller
{
    public function store(
        ShareItemWithUserRequest $request,
        StorageItem $storageItem,
        StorageService $storageService,
    ): RedirectResponse {
        $actor = $request->user();
        $this->ensureOwner($storageItem, $actor);

        $targets = $request->targetUsers()
            ->unique(fn (User $user) => $user->getKey())
            ->reject(fn (User $user) => $user->is($actor) || $user->is($storageItem->owner))
            ->values();

        if ($targets->isEmpty()) {
            throw ValidationException::withMessages([
                'emails' => 'Provide at least one collaborator other than yourself.',
            ]);
        }

        foreach ($targets as $target) {
            $storageService->grantPermission(
                $storageItem,
                $target,
                $actor,
                $request->permission(),
                $request->expiresAt(),
            );
        }

        return redirect()->back();
    }

    public function update(
        UpdateSharedItemPermissionRequest $request,
        StorageItem $storageItem,
        StorageItemPermission $storageItemPermission,
        StorageService $storageService,
    ): RedirectResponse {
        $actor = $request->user();
        $this->ensureOwner($storageItem, $actor);
        $this->ensurePermissionBelongsToItem($storageItem, $storageItemPermission);

        $storageService->updatePermission(
            $storageItemPermission,
            $request->permission(),
            $request->expiresAt(),
        );

        return redirect()->back();
    }

    public function destroy(
        Request $request,
        StorageItem $storageItem,
        StorageItemPermission $storageItemPermission,
        StorageService $storageService,
    ): RedirectResponse {
        $actor = $request->user();
        $this->ensureOwner($storageItem, $actor);
        $this->ensurePermissionBelongsToItem($storageItem, $storageItemPermission);

        $storageService->revokePermission($storageItemPermission);

        return redirect()->back();
    }

    private function ensureOwner(StorageItem $item, ?User $user): void
    {
        if ($user === null || $item->user_id !== $user->getKey()) {
            abort(404);
        }
    }

    private function ensurePermissionBelongsToItem(StorageItem $item, StorageItemPermission $permission): void
    {
        if ($permission->storage_item_id !== $item->getKey()) {
            abort(404);
        }
    }
}
