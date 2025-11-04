<?php

use App\Http\Controllers\Storage\StorageBrowserController;
use App\Http\Controllers\Storage\StorageFolderController;
use App\Http\Controllers\Storage\StorageNoteController;
use App\Http\Controllers\Storage\StorageUploadController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('storage', [StorageBrowserController::class, 'index'])->name('storage.index');
    Route::post('storage/folders', [StorageFolderController::class, 'store'])->name('storage.folders.store');
    Route::post('storage/notes', [StorageNoteController::class, 'store'])->name('storage.notes.store');
    Route::post('storage/upload', [StorageUploadController::class, 'store'])->name('storage.upload.store');
});

require __DIR__.'/settings.php';
