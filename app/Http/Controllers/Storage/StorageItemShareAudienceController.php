<?php

namespace App\Http\Controllers\Storage;

use App\Enums\StorageAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\ShareItemWithAudienceRequest;
use App\Models\StorageItem;
use App\Models\StorageItemAudience;
use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StorageItemShareAudienceController extends Controller
{
    public function store(
        ShareItemWithAudienceRequest $request,
        StorageItem $storageItem,
        StorageService $storageService,
    ): RedirectResponse {
        $actor = $request->user();
        $this->ensureOwner($storageItem, $actor);

        $audience = $request->audience();

        if ($audience === StorageAudience::Company) {
            $storageService->shareWithCompany($storageItem, $actor, $request->expiresAt());
        } elseif ($audience === StorageAudience::Team) {
            $team = $request->team();

            if ($team === null) {
                abort(422);
            }

            $storageService->shareWithTeam($storageItem, $team, $actor, $request->expiresAt());
        }

        return redirect()->back();
    }

    public function destroy(
        Request $request,
        StorageItem $storageItem,
        StorageItemAudience $storageItemAudience,
        StorageService $storageService,
    ): RedirectResponse {
        $actor = $request->user();
        $this->ensureOwner($storageItem, $actor);
        $this->ensureAudienceBelongsToItem($storageItem, $storageItemAudience);

        $storageService->revokeAudience($storageItemAudience);

        return redirect()->back();
    }

    private function ensureOwner(StorageItem $item, ?User $user): void
    {
        if ($user === null || $item->user_id !== $user->getKey()) {
            abort(404);
        }
    }

    private function ensureAudienceBelongsToItem(StorageItem $item, StorageItemAudience $audience): void
    {
        if ($audience->storage_item_id !== $item->getKey()) {
            abort(404);
        }
    }
}
