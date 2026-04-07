<?php

namespace App\Console\Commands;

use App\Models\Repository;
use App\Services\BranchProtectionService;
use App\Services\FileLockService;
use App\Services\NativeGitRepositoryService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * crucible:pre-receive-hook --repo-id=UUID
 *
 * Invoked by the pre-receive git hook installed in each bare repository.
 * Reads ref updates from stdin, determines which files changed, and validates
 * lock policy compliance. Exits non-zero (rejecting the push) on violations.
 *
 * Stdin format (one line per ref update):
 *   <old-sha> <new-sha> <ref-name>
 */
class GitPreReceiveHookCommand extends Command
{
    protected $signature = 'crucible:pre-receive-hook
                            {--repo-id= : UUID of the repository}
                            {--pusher-env=* : Key=value pairs identifying the pusher}';

    protected $description = 'Pre-receive hook — enforce lock policies on incoming pushes';

    /** The zero SHA used by git for branch creation/deletion. */
    private const ZERO_SHA = '0000000000000000000000000000000000000000';

    public function handle(
        FileLockService $lockService,
        NativeGitRepositoryService $git,
        BranchProtectionService $branchProtection,
    ): int {
        $repoId = $this->option('repo-id');

        if (! $repoId) {
            // No repo ID — allow push (hook may be misconfigured).
            return self::SUCCESS;
        }

        try {
            $repository = Repository::find($repoId);
        } catch (\Throwable) {
            // Database unavailable — fail open to avoid blocking pushes.
            return self::SUCCESS;
        }

        if (! $repository) {
            // Repository deleted from DB but hook still on disk — allow push.
            return self::SUCCESS;
        }

        // Read ref updates from stdin.
        $stdin = file_get_contents('php://stdin');

        if (trim($stdin) === '') {
            return self::SUCCESS;
        }

        // Resolve the pushing user from the environment.
        $pusher = $this->resolvePusher();

        // ── Branch Protection Checks ──────────────────────────────────────────
        $branchViolations = $this->checkBranchProtection(
            $repository, $branchProtection, $stdin, $pusher
        );

        if (! empty($branchViolations)) {
            $this->errorOut('Push rejected by branch protection rules:');
            foreach ($branchViolations as $v) {
                $this->errorOut("  - {$v}");
            }

            return self::FAILURE;
        }

        // ── Lock Policy Checks ────────────────────────────────────────────────
        $hasMandatoryPolicies = $repository->lockPolicies()
            ->where('lock_mode', 'mandatory')
            ->exists();

        if (! $hasMandatoryPolicies) {
            return self::SUCCESS;
        }

        $changedPaths = $this->collectChangedPaths($repository, $git, $stdin);

        if (empty($changedPaths)) {
            return self::SUCCESS;
        }

        $violations = $lockService->checkPushCompliance($repository, $pusher, $changedPaths);

        if (empty($violations)) {
            return self::SUCCESS;
        }

        $this->errorOut('Push rejected: the following files require a lock before pushing:');

        foreach ($violations as $v) {
            $this->errorOut("  - {$v['path']}: {$v['reason']}");
        }

        $this->errorOut('');
        $this->errorOut('Lock the files with: git lfs lock <path>');

        return self::FAILURE;
    }

    /**
     * Parse stdin ref updates and collect all changed file paths across all refs.
     */
    private function collectChangedPaths(
        Repository $repository,
        NativeGitRepositoryService $git,
        string $stdin,
    ): array {
        $paths = [];
        $repoPath = $git->pathFor($repository);

        foreach (preg_split('/\R/', trim($stdin)) as $line) {
            $parts = preg_split('/\s+/', trim($line), 3);

            if (count($parts) !== 3) {
                continue;
            }

            [$oldSha, $newSha, $refName] = $parts;

            // Branch deletion — nothing to check.
            if ($newSha === self::ZERO_SHA) {
                continue;
            }

            // Determine the diff range.
            $range = $oldSha === self::ZERO_SHA
                ? $newSha           // New branch — check all files in new commits.
                : "{$oldSha}..{$newSha}";

            try {
                $process = new Process([
                    'git', '--git-dir', $repoPath,
                    'diff', '--name-only', $range,
                ]);
                $process->setTimeout(60);
                $process->run();

                if ($process->isSuccessful()) {
                    $output = trim($process->getOutput());

                    if ($output !== '') {
                        foreach (preg_split('/\R/', $output) as $path) {
                            $path = trim($path);
                            if ($path !== '') {
                                $paths[$path] = true;
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                // If we can't determine changed files, allow the push through
                // rather than blocking all pushes on a git error.
            }
        }

        return array_keys($paths);
    }

    /**
     * Attempt to resolve the pushing user from environment variables.
     */
    private function resolvePusher(): ?\App\Models\User
    {
        // The GitHttpController and GitShellCommand both set GL_USERNAME or
        // pass the user through environment. For HTTP pushes, the authenticated
        // user is passed via the CRUCIBLE_PUSHER_ID env var set by the HTTP layer.
        $pusherId = getenv('CRUCIBLE_PUSHER_ID') ?: null;

        if ($pusherId) {
            return \App\Models\User::find($pusherId);
        }

        // Fallback: check GL_USERNAME (common in git hosting).
        $username = getenv('GL_USERNAME') ?: null;

        if ($username) {
            return \App\Models\User::where('email', $username)->first()
                ?? \App\Models\User::where('name', $username)->first();
        }

        return null;
    }

    /**
     * Check branch protection rules for each ref update.
     * Returns an array of violation messages.
     */
    private function checkBranchProtection(
        Repository $repository,
        BranchProtectionService $branchProtection,
        string $stdin,
        ?\App\Models\User $pusher,
    ): array {
        $violations = [];

        foreach (preg_split('/\R/', trim($stdin)) as $line) {
            $parts = preg_split('/\s+/', trim($line), 3);

            if (count($parts) !== 3) {
                continue;
            }

            [$oldSha, $newSha, $refName] = $parts;

            // Only check branch refs (refs/heads/...)
            if (! str_starts_with($refName, 'refs/heads/')) {
                continue;
            }

            $branchName = substr($refName, strlen('refs/heads/'));

            // Branch deletion check
            if ($newSha === self::ZERO_SHA) {
                if (! $branchProtection->allowsDeletion($repository, $branchName)) {
                    $violations[] = "Deletion of branch '{$branchName}' is not allowed by branch protection.";
                }

                continue;
            }

            // Direct push check (require_pull_request, role restrictions)
            $pushViolations = $branchProtection->canPush($repository, $branchName, $pusher);
            $violations = array_merge($violations, $pushViolations);

            // Force push detection (old SHA is not an ancestor of new SHA)
            if ($oldSha !== self::ZERO_SHA) {
                // Note: Force push detection is best-effort here.
                // The actual git merge-base --is-ancestor check could be added,
                // but for now we rely on the git config to prevent force pushes.
            }
        }

        return $violations;
    }

    /** Write an error message to stderr (stdout must stay clean for git). */
    private function errorOut(string $message): void
    {
        fwrite(STDERR, "crucible: {$message}\n");
    }
}
