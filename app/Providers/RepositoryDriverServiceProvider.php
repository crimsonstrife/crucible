<?php

namespace App\Providers;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Drivers\ServiceGitDriver;
use App\Drivers\StubRepositoryDriver;
use Illuminate\Support\ServiceProvider;

class RepositoryDriverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RepositoryDriverInterface::class, match(config('crucible.git.backend', 'stub')) {
            'native'  => NativeGitDriver::class,
            'service' => ServiceGitDriver::class,
            default   => StubRepositoryDriver::class,
        });
    }
}
