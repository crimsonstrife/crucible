<?php

namespace App\Providers;

use App\Contracts\LfsBackendInterface;
use App\Drivers\LocalLfsBackend;
use App\Drivers\S3LfsBackend;
use Illuminate\Support\ServiceProvider;

class LfsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LfsBackendInterface::class, function ($app) {
            $backend = config('crucible.lfs.backend', 'local');

            return match ($backend) {
                's3' => new S3LfsBackend(config('crucible.lfs.s3_disk', 's3')),
                default => $app->make(LocalLfsBackend::class),
            };
        });
    }
}
