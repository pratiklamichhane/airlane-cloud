<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\StoreNoteRequest;
use App\Services\Storage\StorageService;
use Illuminate\Http\RedirectResponse;

class StorageNoteController extends Controller
{
    public function store(StoreNoteRequest $request, StorageService $storageService): RedirectResponse
    {
        $user = $request->user();
        $parent = $request->parent();

        $storageService->createNote(
            $user,
            $request->validated('name'),
            $request->validated('content'),
            $parent,
            $request->validated('metadata', []),
        );

        $parameters = array_filter([
            'folder' => $parent?->getKey(),
        ], static fn ($value) => $value !== null);

        return to_route('storage.index', $parameters);
    }
}
