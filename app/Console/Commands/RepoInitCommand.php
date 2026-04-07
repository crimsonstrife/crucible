<?php

namespace App\Console\Commands;

use App\Contracts\RepositoryDriverInterface;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Console\Command;

class RepoInitCommand extends Command
{
    protected $signature = 'repo:init
                            {org? : Organization slug (omit to initialize ALL pending repos)}
                            {repo? : Repository slug (requires org)}
                            {--force : Re-initialize even if the repo already exists on disk}';

    protected $description = 'Initialize bare git repositories on disk for pending or stuck repos';

    public function handle(RepositoryDriverInterface $driver): int
    {
        $orgSlug  = $this->argument('org');
        $repoSlug = $this->argument('repo');
        $force    = $this->option('force');

        // ── Single repo ────────────────────────────────────────────────────
        if ($orgSlug && $repoSlug) {
            $org  = Organization::where('slug', $orgSlug)->firstOrFail();
            $repo = $org->repositories()->where('slug', $repoSlug)->firstOrFail();

            return $this->initOne($driver, $repo, $force);
        }

        // ── All repos in one org ───────────────────────────────────────────
        if ($orgSlug) {
            $org   = Organization::where('slug', $orgSlug)->firstOrFail();
            $repos = $org->repositories()->get();
        } else {
            // ── All pending repos across every org ─────────────────────────
            $repos = Repository::with('organization')->get();
        }

        $pending = $repos->filter(fn ($r) => $force || ! $driver->exists($r));

        if ($pending->isEmpty()) {
            $this->info('All repositories are already initialized. Use --force to re-initialize.');
            return self::SUCCESS;
        }

        $this->info("Initializing {$pending->count()} repositor" . ($pending->count() === 1 ? 'y' : 'ies') . '…');

        $bar = $this->output->createProgressBar($pending->count());
        $bar->start();

        $errors = 0;
        foreach ($pending as $repo) {
            try {
                $driver->initialize($repo);
                $bar->advance();
            } catch (\Throwable $e) {
                $errors++;
                $bar->advance();
                $this->newLine();
                $this->error("  ✗ {$repo->organization->slug}/{$repo->slug}: {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine();

        if ($errors > 0) {
            $this->warn("{$errors} repositor" . ($errors === 1 ? 'y' : 'ies') . ' failed to initialize.');
            return self::FAILURE;
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function initOne(RepositoryDriverInterface $driver, Repository $repo, bool $force): int
    {
        $label = "{$repo->organization->slug}/{$repo->slug}";

        if (! $force && $driver->exists($repo)) {
            $this->line("  Already initialized: <info>{$label}</info>");
            return self::SUCCESS;
        }

        try {
            $driver->initialize($repo);
            $this->info("  ✓ Initialized: {$label}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("  ✗ Failed to initialize {$label}: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
