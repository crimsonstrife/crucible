<?php

use App\Jobs\SyncRepositoryJob;
use App\Models\Repository;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expire stale file locks whose timeout has elapsed.
Schedule::command('crucible:expire-locks')
    ->everyFifteenMinutes()
    ->name('expire-stale-file-locks')
    ->withoutOverlapping();

// Auto-sync repositories that have a remote URL and auto_sync enabled.
// Runs every 15 minutes; each repo is dispatched as an independent job
// so a slow clone does not block others.
Schedule::call(function () {
    Repository::query()
        ->where('auto_sync', true)
        ->whereNotNull('remote_url')
        ->each(fn (Repository $repo) => SyncRepositoryJob::dispatch($repo));
})->everyFifteenMinutes()->name('sync-auto-sync-repositories')->withoutOverlapping();

// Recalculate repository and LFS storage sizes.
Schedule::command('crucible:update-repo-sizes')
    ->hourly()
    ->name('update-repository-sizes')
    ->withoutOverlapping();

// Clean up expired LFS upload sessions and temporary files.
Schedule::command('crucible:clean-uploads')
    ->hourly()
    ->name('clean-expired-uploads')
    ->withoutOverlapping();
