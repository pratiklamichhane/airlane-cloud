<?php

use App\Enums\StoragePermission;
use App\Models\Team;
use App\Models\User;
use App\Services\Storage\StorageService;
use Inertia\Testing\AssertableInertia as Assert;

test('note content is exposed in the storage browser response', function (): void {
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Product Design',
        'description' => 'Design group',
    ]);

    $team->users()->attach($user->getKey());
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
            ->has('teams', 1, fn (Assert $teamAssert) => $teamAssert
                ->where('id', $team->getKey())
                ->where('name', $team->name)
                ->where('slug', $team->slug)
                ->etc()
            )
        );
});

test('shared items are exposed via the storage browser props', function (): void {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $team = Team::create([
        'name' => 'Engineering',
        'description' => 'Engineering team',
    ]);

    $team->users()->attach($user->getKey());

    $service = app(StorageService::class);

    $companyItem = $service->createNote($owner, 'Company Plan', 'Shared with the entire company');
    $service->shareWithCompany($companyItem, $owner);

    $teamItem = $service->createNote($owner, 'Team Roadmap', 'Shared with the engineering team');
    $service->shareWithTeam($teamItem, $team, $owner);

    $directItem = $service->createNote($owner, 'Direct Share', 'Just for a single teammate');
    $service->grantPermission($directItem, $user, $owner, StoragePermission::Viewer);

    $this->actingAs($user)
        ->get(route('storage.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('storage/Browse')
            ->has('shared.company', 1, fn (Assert $item) => $item
                ->where('id', $companyItem->getKey())
                ->where('name', $companyItem->name)
                ->etc()
            )
            ->has('shared.teams', 1, fn (Assert $group) => $group
                ->where('team.id', $team->getKey())
                ->where('team.name', $team->name)
                ->has('items', 1, fn (Assert $item) => $item
                    ->where('id', $teamItem->getKey())
                    ->where('name', $teamItem->name)
                    ->etc()
                )
            )
            ->has('shared.personal', 1, fn (Assert $item) => $item
                ->where('id', $directItem->getKey())
                ->where('name', $directItem->name)
                ->etc()
            )
        );
});
