<?php

namespace App\Console\Commands;

use App\Models\LfsUploadSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * crucible:clean-uploads
 *
 * Deletes expired LFS upload sessions and their temporary files.
 * Intended to be run on a schedule (e.g. hourly).
 */
class CleanExpiredUploadsCommand extends Command
{
    protected $signature = 'crucible:clean-uploads';

    protected $description = 'Remove expired LFS upload sessions and temporary files';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $count = 0;

        LfsUploadSession::query()
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($sessions) use ($disk, &$count) {
                foreach ($sessions as $session) {
                    $tempPath = sprintf('crucible-lfs-uploads/%s/%s', $session->repository_id, $session->oid);

                    if ($disk->exists($tempPath)) {
                        $disk->delete($tempPath);
                    }

                    $session->delete();
                    $count++;
                }
            });

        if ($count > 0) {
            $this->info("Cleaned {$count} expired upload session(s).");
        }

        return self::SUCCESS;
    }
}
