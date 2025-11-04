<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\UploadFileRequest;
use App\Services\Storage\StorageService;
use Illuminate\Http\RedirectResponse;

class StorageUploadController extends Controller
{
    public function store(UploadFileRequest $request, StorageService $storageService): RedirectResponse
    {
        $user = $request->user();
        $parent = $request->parent();
        $file = $request->file('file');

        if ($file === null) {
            abort(422, 'Uploaded file is missing.');
        }

        $storageService->uploadFile(
            $user,
            $file,
            $parent,
            $request->validated('metadata', []),
        );

        $parameters = array_filter([
            'folder' => $parent?->getKey(),
        ], static fn ($value) => $value !== null);

        return to_route('storage.index', $parameters);
    }
}
