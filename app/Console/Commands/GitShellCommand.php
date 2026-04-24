<?php

namespace App\Console\Commands;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\SshKey;
use App\Models\User;
use App\Services\NativeGitRepositoryService;
use Illuminate\Console\Command;
use Symfony\Component\Process\ExecutableFinder;

/**
 * crucible:git-shell {userId} [--key-id=KEY_UUID]
 *
 * Forced command injected by crucible:authorized-keys into every authorized_keys
 * entry.  Sshd executes this instead of giving the user a real shell; the
 * original git command the client requested is available in SSH_ORIGINAL_COMMAND.
 *
 * Flow:
 *   1. Parse SSH_ORIGINAL_COMMAND → verb + repo path
 *   2. Resolve org + repository from path
 *   3. Check user permissions (read for upload-pack, write for receive-pack)
 *   4. Stamp SshKey.last_used_at
 *   5. exec() the git binary — replaces this process, handing over stdin/stdout/stderr
 *
 * IMPORTANT: This command writes NOTHING to stdout before the exec.  Any message
 * written to stdout would corrupt the git pack protocol.  Errors go to stderr.
 */
class GitShellCommand extends Command
{
    protected $signature = 'crucible:git-shell
                            {userId  : UUID of the authenticated user}
                            {--key-id= : UUID of the SshKey used (for deploy key checks and audit)}';

    protected $description = 'Git shell — proxy an SSH git operation (called by sshd forced command)';

    /** Git subcommands accepted over SSH. */
    private const ALLOWED_VERBS = ['git-upload-pack', 'git-receive-pack'];

    public function handle(
        RepositoryDriverInterface $driver,
        NativeGitRepositoryService $nativeGit,
    ): int {
        // ── Require native driver ─────────────────────────────────────────────
        if (! ($driver instanceof NativeGitDriver)) {
            $this->errorOut('SSH git transport requires the native git backend.');

            return self::FAILURE;
        }

        // ── Resolve the authenticated user ────────────────────────────────────
        $user = User::find($this->argument('userId'));

        if (! $user) {
            $this->errorOut('Authentication failed: user not found.');

            return self::FAILURE;
        }

        // ── Resolve the SSH key (optional, for deploy key restriction) ────────
        $keyId = $this->option('key-id');
        $sshKey = $keyId ? SshKey::find($keyId) : null;

        // ── Read and validate SSH_ORIGINAL_COMMAND ────────────────────────────
        $originalCommand = (string) getenv('SSH_ORIGINAL_COMMAND');

        if ($originalCommand === '') {
            $this->errorOut('No git command received. This shell only accepts git operations.');

            return self::FAILURE;
        }

        [$verb, $rawPath] = $this->parseCommand($originalCommand);

        if ($verb === null || $rawPath === null) {
            $this->errorOut("Unsupported command: {$originalCommand}");

            return self::FAILURE;
        }

        // ── Parse org/repo from path ──────────────────────────────────────────
        [$orgSlug, $repoSlug] = $this->parsePath($rawPath);

        if ($orgSlug === null || $repoSlug === null) {
            $this->errorOut("Invalid repository path: {$rawPath}");

            return self::FAILURE;
        }

        // ── Resolve org and repository ────────────────────────────────────────
        $organization = Organization::where('slug', $orgSlug)->first();

        if (! $organization) {
            $this->errorOut('Repository not found.');

            return self::FAILURE;
        }

        $repository = $organization->repositories()->where('slug', $repoSlug)->first();

        if (! $repository) {
            $this->errorOut('Repository not found.');

            return self::FAILURE;
        }

        // ── Deploy key restriction ────────────────────────────────────────────
        if ($sshKey && $sshKey->is_deploy_key) {
            if ($sshKey->deploy_repo_id !== $repository->id) {
                $this->errorOut("This deploy key does not have access to {$orgSlug}/{$repoSlug}.");

                return self::FAILURE;
            }
        }

        // ── Permission checks ─────────────────────────────────────────────────
        if ($verb === 'git-receive-pack') {
            if ($repository->is_archived) {
                $this->errorOut("Repository {$orgSlug}/{$repoSlug} is archived and cannot receive pushes.");

                return self::FAILURE;
            }

            if ($user->cannot('push', $repository)) {
                $this->errorOut("You do not have push access to {$orgSlug}/{$repoSlug}.");

                return self::FAILURE;
            }
        } else {
            // git-upload-pack — read access
            if ($user->cannot('view', $repository)) {
                $this->errorOut("You do not have read access to {$orgSlug}/{$repoSlug}.");

                return self::FAILURE;
            }
        }

        // ── Ensure the repo is on disk ────────────────────────────────────────
        if (! $driver->exists($repository)) {
            $this->errorOut("Repository {$orgSlug}/{$repoSlug} is not yet initialized on disk.");

            return self::FAILURE;
        }

        // ── Stamp last-used on the key ────────────────────────────────────────
        if ($sshKey) {
            $sshKey->updateQuietly(['last_used_at' => now()]);
        }

        // ── Exec the git binary ───────────────────────────────────────────────
        $repoPath = $nativeGit->pathFor($repository);
        $finder = new ExecutableFinder;
        $gitBin = $finder->find('git') ?? '/usr/bin/git';

        // git-upload-pack → git upload-pack, git-receive-pack → git receive-pack
        $subcommand = str_replace('git-', '', $verb);

        // Optionally throttle git to yield CPU/disk to php-fpm under contention.
        $niceWrap = (bool) config('crucible.git.nice_wrap', false);
        if ($niceWrap) {
            $niceBin = $finder->find('nice') ?? '/usr/bin/nice';
            $ioniceBin = $finder->find('ionice') ?? '/usr/bin/ionice';
            $execPath = $niceBin;
            $execArgs = ['-n', '19', $ioniceBin, '-c', '3', $gitBin, $subcommand, $repoPath];
            $openCmd = [$niceBin, '-n', '19', $ioniceBin, '-c', '3', $gitBin, $subcommand, $repoPath];
        } else {
            $execPath = $gitBin;
            $execArgs = [$subcommand, $repoPath];
            $openCmd = [$gitBin, $subcommand, $repoPath];
        }

        // pcntl_exec replaces the current process image, inheriting all open
        // file descriptors (stdin/stdout/stderr == the SSH pipe).
        if (function_exists('pcntl_exec')) {
            pcntl_exec($execPath, $execArgs);
            // If we reach this line pcntl_exec failed.
            $this->errorOut('Failed to exec git.');

            return self::FAILURE;
        }

        // Fallback: proc_open with stdio passthrough.
        $descriptors = [0 => STDIN, 1 => STDOUT, 2 => STDERR];
        $process = proc_open($openCmd, $descriptors, $pipes);

        if (! is_resource($process)) {
            $this->errorOut('Failed to start git process.');

            return self::FAILURE;
        }

        return proc_close($process);
    }

    /**
     * Parse "git-upload-pack 'org/repo.git'" into [verb, path].
     * Returns [null, null] if the command is not an allowed git verb.
     */
    private function parseCommand(string $command): array
    {
        // Trim and split on first whitespace
        $command = trim($command);
        $space = strpos($command, ' ');

        if ($space === false) {
            return [null, null];
        }

        $verb = substr($command, 0, $space);
        $rest = trim(substr($command, $space + 1));

        if (! in_array($verb, self::ALLOWED_VERBS, true)) {
            return [null, null];
        }

        // Strip enclosing single quotes that git CLI adds.
        $path = trim($rest, "'\" \t");

        return [$verb, $path];
    }

    /**
     * Parse "org/repo.git" (or "/org/repo.git") into [orgSlug, repoSlug].
     * Returns [null, null] on invalid / path-traversal input.
     */
    private function parsePath(string $rawPath): array
    {
        // Strip leading slash, normalize separators, reject traversal.
        $path = ltrim(str_replace('\\', '/', $rawPath), '/');

        // Reject any path traversal attempts.
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            return [null, null];
        }

        // Strip .git suffix.
        $path = preg_replace('/\.git$/i', '', $path);

        $segments = array_values(array_filter(explode('/', $path)));

        if (count($segments) !== 2) {
            return [null, null];
        }

        return [$segments[0], $segments[1]];
    }

    /** Write an error message to stderr only (stdout must stay clean for git). */
    private function errorOut(string $message): void
    {
        fwrite(STDERR, "crucible: {$message}\n");
    }
}
