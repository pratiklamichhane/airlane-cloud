<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTeamRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $teams = Team::query()
            ->with(['users' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/Teams/Index', [
            'teams' => $teams->map(static function ($team) {
                return [
                    'id' => $team->getKey(),
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'description' => $team->description,
                    'member_count' => $team->users->count(),
                    'members' => $team->users->map(static function ($user) {
                        return [
                            'id' => $user->getKey(),
                            'name' => $user->name,
                            'email' => $user->email,
                            'role' => $user->pivot?->role ?? 'member',
                        ];
                    })->all(),
                ];
            }),
        ]);
    }

    public function store(CreateTeamRequest $request): RedirectResponse
    {
        Team::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
        ]);

        return redirect()->back();
    }
}
