<?php

namespace App\Providers;

use App\Listeners\DispatchWebhooks;
use App\Services\ForgeService;
use App\Socialite\ForgeProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ForgeService::class);
    }

    public function boot(): void
    {
        // Bootstrap 5 pagination
        Paginator::useBootstrapFive();

        // Register webhook event subscriber
        Event::subscribe(DispatchWebhooks::class);

        // Register Forge Socialite driver (only when the integration is enabled)
        if (config('crucible.forge.enabled')) {
            Socialite::extend('forge', function () {
                return Socialite::buildProvider(ForgeProvider::class, [
                    'client_id'     => config('crucible.forge.client_id'),
                    'client_secret' => config('crucible.forge.client_secret'),
                    'redirect'      => config('crucible.forge.redirect_uri'),
                ]);
            });
        }
    }
}
