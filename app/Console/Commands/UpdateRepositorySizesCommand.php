<?php

namespace App\Console\Commands;

use App\Models\Repository;
use App\Services\NativeGitRepositoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * crucible:update-repo-sizes
 *
 * Scans all repositories and updates their size_kb and lfs_size_kb columns.
 * Intended to be run on a schedule (e.g. hourly or daily).
 */
class UpdateRepositorySizesCommand extends Command
{
    protected $signature = 'crucible:update-repo-sizes
                            {--repository= : Update only a specific repository by ID}';

    protected $description = 'Recalculate and update repository and LFS storage sizes';

    public function handle(NativeGitRepositoryService $git): int
    {
        $query = Repository::query()->with('organization');

        if ($repoId = $this->option('repository')) {
            $query->where('id', $repoId);
        }

        $updated = 0;
        $errors = 0;

        $query->chunkById(100, function ($repositories) use ($git, &$updated, &$errors) {
            foreach ($repositories as $repository) {
                try {
                    $this->updateRepository($repository, $git);
                    $updated++;
                } catch (\Throwable $e) {
                    $errors++;
                    Log::error('[UpdateRepositorySizesCommand] Failed to update', [
                        'repository' => $repository->id,
                        'error'      => $e->getMessage(),
                    ]);
                    $this->error("  Failed: {$repository->name} — {$e->getMessage()}");
                }
            }
        });

        $this->info("Updated {$updated} repository size(s)." . ($errors ? " ({$errors} error(s))" : ''));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function updateRepository(Repository $repository, NativeGitRepositoryService $git): void
    {
        // Git repo size (on-disk bare repo)
        $gitSizeBytes = 0;

        if ($git->exists($repository)) {
            $gitSizeBytes = $git->size($repository);
        }

        $gitSizeKb = (int) ceil($gitSizeBytes / 1024);

        // LFS size (sum of all LFS object sizes in bytes, convert to KB)
        $lfsSizeBytes = (int) $repository->lfsObjects()
            ->selectRaw('COALESCE(SUM(size), 0) as total')
            ->value('total');

        $lfsSizeKb = (int) ceil($lfsSizeBytes / 1024);

        $repository->update([
            'size_kb'     => $gitSizeKb,
            'lfs_size_kb' => $lfsSizeKb,
        ]);
    }
}
