<?php

namespace App\Services;

use App\Enums\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Config;

class PlanManager
{
    public function assign(User $user, Plan $plan): User
    {
        $user->forceFill([
            'plan' => $plan,
            'max_storage_bytes' => $plan->storageLimitBytes(),
            'max_file_size_bytes' => $plan->maxFileSizeBytes(),
            'version_cap' => $plan->versionCap(),
        ])->save();

        return $user->refresh();
    }

    /**
     * @return array<string, int|string>
     */
    public function details(Plan $plan): array
    {
        $key = 'airlane.plans.'.$plan->value;

        return (array) Config::get($key, []);
    }
}
