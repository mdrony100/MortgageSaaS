<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
        Horizon::auth(fn ($request) => $request->user()?->platform_admin === true && $request->user()?->hasVerifiedEmail());
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null) => $user?->platform_admin === true);
    }
}
