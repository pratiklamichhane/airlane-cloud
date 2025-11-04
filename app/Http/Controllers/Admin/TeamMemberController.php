<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Plan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamMemberController extends Controller
{
    public function store(StoreTeamMemberRequest $request, Team $team): RedirectResponse
    {
        $email = $request->validated('email');
        $name = $request->validated('name');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $plan = Plan::default();

            $user = User::create([
                'name' => $name !== null && $name !== '' ? $name : Str::headline(Str::before($email, '@')),
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'plan' => $plan,
                'storage_used_bytes' => 0,
                'max_storage_bytes' => $plan->storageLimitBytes(),
                'max_file_size_bytes' => $plan->maxFileSizeBytes(),
                'version_cap' => $plan->versionCap(),
                'is_admin' => false,
            ]);
        }

        $role = $request->desiredRole();

        if ($team->users()->whereKey($user->getKey())->exists()) {
            $team->users()->updateExistingPivot($user->getKey(), [
                'role' => $role,
            ]);
        } else {
            $team->users()->attach($user->getKey(), [
                'role' => $role,
            ]);
        }

        return redirect()->back();
    }

    public function destroy(Team $team, User $user): RedirectResponse
    {
        if (! $team->users()->whereKey($user->getKey())->exists()) {
            abort(404);
        }

        $team->users()->detach($user->getKey());

        return redirect()->back();
    }
}
