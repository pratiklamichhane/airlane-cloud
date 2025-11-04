<?php

namespace App\Http\Controllers\Storage;

use App\Enums\StorageAudience;
use App\Enums\StorageItemType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Storage\StorageItemResource;
use App\Models\StorageItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StorageBrowserController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $currentFolder = $this->resolveFolder($request);
        $relations = ['latestVersion', 'tags', 'permissions.user', 'shareLinks', 'audiences.team'];

        $items = StorageItem::query()
            ->forUser($user)
            ->whereNull('deleted_at')
            ->where('parent_id', $currentFolder?->getKey())
            ->with($relations)
            ->orderByDesc('is_pinned')
            ->orderByDesc('is_favorite')
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $teams = $user->teams()
            ->orderBy('name')
            ->get(['teams.id', 'teams.name', 'teams.slug']);

        $teamIds = $teams->pluck('id');

        $companySharedItems = StorageItem::query()
            ->whereNull('deleted_at')
            ->whereHas('audiences', static function ($query): void {
                $query->where('audience', StorageAudience::Company);
            })
            ->with($relations)
            ->orderByDesc('is_pinned')
            ->orderByDesc('is_favorite')
            ->orderBy('type')
            ->orderBy('name')
            ->limit(200)
            ->get();

        $teamSharedItems = $teamIds->isEmpty()
            ? collect()
            : StorageItem::query()
                ->whereNull('deleted_at')
                ->whereHas('audiences', static function ($query) use ($teamIds): void {
                    $query->where('audience', StorageAudience::Team)
                        ->whereIn('team_id', $teamIds);
                })
                ->with($relations)
                ->orderByDesc('is_pinned')
                ->orderByDesc('is_favorite')
                ->orderBy('type')
                ->orderBy('name')
                ->limit(200)
                ->get();

        $teamItemsByTeam = [];

        foreach ($teamSharedItems as $sharedItem) {
            $sharedItem->loadMissing('audiences');

            foreach ($sharedItem->audiences
                ->where('audience', StorageAudience::Team)
                ->whereIn('team_id', $teamIds) as $audience) {
                $teamItemsByTeam[$audience->team_id] ??= collect();

                if (! $teamItemsByTeam[$audience->team_id]->contains(fn ($candidate) => $candidate->getKey() === $sharedItem->getKey())) {
                    $teamItemsByTeam[$audience->team_id]->push($sharedItem);
                }
            }
        }

        $personalSharedItems = StorageItem::query()
            ->whereNull('deleted_at')
            ->whereHas('permissions', static function ($query) use ($user): void {
                $query->where('user_id', $user->getKey());
            })
            ->with($relations)
            ->orderByDesc('is_pinned')
            ->orderByDesc('is_favorite')
            ->orderBy('type')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return Inertia::render('storage/Browse', [
            'filters' => [
                'folder' => $currentFolder?->getKey(),
            ],
            'items' => StorageItemResource::collection($items),
            'folder' => $currentFolder ? StorageItemResource::make($currentFolder->loadMissing('parent')) : null,
            'breadcrumbs' => $this->breadcrumbs($currentFolder),
            'summary' => [
                'plan' => [
                    'key' => $user->plan->value,
                    'name' => $user->plan->name(),
                ],
                'usage' => [
                    'used_bytes' => (int) $user->storage_used_bytes,
                    'max_bytes' => (int) $user->max_storage_bytes,
                    'remaining_bytes' => $user->quotaRemainingBytes(),
                    'percent' => $user->storageUsagePercent(),
                ],
                'limits' => [
                    'max_file_bytes' => (int) $user->max_file_size_bytes,
                    'version_cap' => (int) $user->version_cap,
                    'trash_retention_days' => (int) config('airlane.trash_retention_days'),
                ],
            ],
            'teams' => $teams->map(static fn ($team) => [
                'id' => $team->getKey(),
                'name' => $team->name,
                'slug' => $team->slug,
            ]),
            'shared' => [
                'company' => StorageItemResource::collection($companySharedItems)->resolve(),
                'teams' => $teams->map(function ($team) use ($teamItemsByTeam) {
                    $itemsForTeam = $teamItemsByTeam[$team->getKey()] ?? collect();

                    if ($itemsForTeam->isEmpty()) {
                        return null;
                    }

                    return [
                        'team' => [
                            'id' => $team->getKey(),
                            'name' => $team->name,
                            'slug' => $team->slug,
                        ],
                        'items' => StorageItemResource::collection($itemsForTeam)->resolve(),
                    ];
                })->filter()->values(),
                'personal' => StorageItemResource::collection($personalSharedItems)->resolve(),
            ],
        ]);
    }

    private function resolveFolder(Request $request): ?StorageItem
    {
        $folderId = $request->query('folder');

        if ($folderId === null || $folderId === '') {
            return null;
        }

        if (! is_numeric($folderId)) {
            abort(404);
        }

        /** @var StorageItem|null $folder */
        $folder = StorageItem::query()
            ->forUser($request->user())
            ->where('type', StorageItemType::Folder)
            ->find($folderId);

        if ($folder === null) {
            abort(404);
        }

        return $folder;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function breadcrumbs(?StorageItem $current): array
    {
        $trail = collect([
            [
                'title' => 'All Files',
                'href' => route('storage.index'),
            ],
        ]);

        $ancestors = $this->collectAncestors($current);

        foreach ($ancestors as $folder) {
            $trail->push([
                'title' => $folder->name,
                'href' => route('storage.index', ['folder' => $folder->getKey()]),
            ]);
        }

        return $trail->values()->all();
    }

    /**
     * @return Collection<int, StorageItem>
     */
    private function collectAncestors(?StorageItem $current): Collection
    {
        if ($current === null) {
            return collect();
        }

        $ancestors = collect();
        $cursor = $current;

        while ($cursor !== null) {
            $ancestors->prepend($cursor);
            $cursor->loadMissing('parent');
            $cursor = $cursor->parent;
        }

        return $ancestors;
    }
}
