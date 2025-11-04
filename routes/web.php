<?php

use App\Http\Controllers\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Admin\TeamMemberController;
use App\Http\Controllers\Storage\SharedStorageLinkController;
use App\Http\Controllers\Storage\StorageBrowserController;
use App\Http\Controllers\Storage\StorageFolderController;
use App\Http\Controllers\Storage\StorageItemController;
use App\Http\Controllers\Storage\StorageItemShareAudienceController;
use App\Http\Controllers\Storage\StorageItemShareLinkController;
use App\Http\Controllers\Storage\StorageItemSharePermissionController;
use App\Http\Controllers\Storage\StorageNoteController;
use App\Http\Controllers\Storage\StorageUploadController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('storage.index')
        : Inertia::render('Welcome', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('storage', [StorageBrowserController::class, 'index'])->name('storage.index');
    Route::get('storage/items/{storageItem}', [StorageItemController::class, 'show'])
        ->name('storage.items.show');
    Route::prefix('storage/items/{storageItem}/share')->name('storage.items.share.')->group(function () {
        Route::post('permissions', [StorageItemSharePermissionController::class, 'store'])->name('permissions.store');
        Route::patch('permissions/{storageItemPermission}', [StorageItemSharePermissionController::class, 'update'])->name('permissions.update');
        Route::delete('permissions/{storageItemPermission}', [StorageItemSharePermissionController::class, 'destroy'])->name('permissions.destroy');

        Route::post('audiences', [StorageItemShareAudienceController::class, 'store'])->name('audiences.store');
        Route::delete('audiences/{storageItemAudience}', [StorageItemShareAudienceController::class, 'destroy'])->name('audiences.destroy');

        Route::post('link', [StorageItemShareLinkController::class, 'store'])->name('link.store');
        Route::patch('link/{storageShareLink}', [StorageItemShareLinkController::class, 'update'])->name('link.update');
        Route::delete('link/{storageShareLink}', [StorageItemShareLinkController::class, 'destroy'])->name('link.destroy');
    });
    Route::post('storage/folders', [StorageFolderController::class, 'store'])->name('storage.folders.store');
    Route::post('storage/notes', [StorageNoteController::class, 'store'])->name('storage.notes.store');
    Route::post('storage/upload', [StorageUploadController::class, 'store'])->name('storage.upload.store');
});

Route::middleware(['auth', 'verified', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.teams.index'))->name('index');

    Route::get('teams', [AdminTeamController::class, 'index'])->name('teams.index');
    Route::post('teams', [AdminTeamController::class, 'store'])->name('teams.store');
    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('teams.members.store');
    Route::delete('teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');
});

Route::get('share/{token}', [SharedStorageLinkController::class, 'show'])
    ->name('storage.share-links.show');

require __DIR__.'/settings.php';
