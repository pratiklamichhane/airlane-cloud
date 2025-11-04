<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\StoreFolderRequest;
use App\Services\Storage\StorageService;
use Illuminate\Http\RedirectResponse;

class StorageFolderController extends Controller
{
    public function store(StoreFolderRequest $request, StorageService $storageService): RedirectResponse
    {
        $user = $request->user();
        $parent = $request->parent();

        $storageService->createFolder($user, $request->validated('name'), $parent);

        $parameters = array_filter([
            'folder' => $parent?->getKey(),
        ], static fn ($value) => $value !== null);

        return to_route('storage.index', $parameters);
    }
}
