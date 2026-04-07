<?php

namespace App\Console\Commands;

use App\Models\FileLock;
use Illuminate\Console\Command;

/**
 * crucible:expire-locks
 *
 * Deletes file locks that have passed their expires_at timestamp.
 * Intended to be run on a schedule (e.g. every 15 minutes).
 */
class ExpireStaleLocksCommand extends Command
{
    protected $signature = 'crucible:expire-locks';

    protected $description = 'Remove expired file locks';

    public function handle(): int
    {
        $count = FileLock::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        if ($count > 0) {
            $this->info("Expired {$count} stale lock(s).");
        }

        return self::SUCCESS;
    }
}
