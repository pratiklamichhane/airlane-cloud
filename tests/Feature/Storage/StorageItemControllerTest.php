<?php

use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('airlane.storage_disk'));
});

it('streams file items for their owner', function (): void {
    $user = User::factory()->create();
    $service = app(StorageService::class);
    $file = UploadedFile::fake()->create('report.pdf', 32, 'application/pdf');

    $item = $service->uploadFile($user, $file);

    $response = $this
        ->actingAs($user)
        ->get(route('storage.items.show', $item));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

it('renders note content inline for the owner', function (): void {
    $user = User::factory()->create();
    $service = app(StorageService::class);

    $note = $service->createNote($user, 'Meeting Notes', 'Sprint recap');

    $response = $this
        ->actingAs($user)
        ->get(route('storage.items.show', $note));

    $response->assertOk();
    $response->assertSee('Sprint recap');

    expect($response->headers->get('content-type'))
        ->toBe('text/plain; charset=UTF-8');
    expect($response->headers->get('content-disposition'))
        ->toContain('inline');
});

it('returns not found for items owned by another user', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $service = app(StorageService::class);
    $file = UploadedFile::fake()->create('secret.txt', 4, 'text/plain');

    $item = $service->uploadFile($owner, $file);

    $response = $this
        ->actingAs($intruder)
        ->get(route('storage.items.show', $item));

    $response->assertNotFound();
});
