<?php

use App\Models\User;
use App\Services\Storage\StorageService;
use Inertia\Testing\AssertableInertia as Assert;

test('note content is exposed in the storage browser response', function (): void {
    $user = User::factory()->create();
    $service = app(StorageService::class);

    $note = $service->createNote($user, 'Sprint Summary', "Retro notes\n- celebrate small wins");

    $this->actingAs($user)
        ->get(route('storage.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('storage/Browse')
            ->has('items.data', 1, fn (Assert $item) => $item
                ->where('id', $note->getKey())
                ->where('is_note', true)
                ->where('latest_version.content', "Retro notes\n- celebrate small wins")
                ->etc()
            )
        );
});
