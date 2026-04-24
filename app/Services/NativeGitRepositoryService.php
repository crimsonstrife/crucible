<?php

namespace App\Services;

use App\Models\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class NativeGitRepositoryService
{
    public function __construct(
        protected Filesystem $files,
    ) {}

    /**
     * Normalize an HTTPS remote URL so that a bare token used as the username
     * is rewritten to `x-access-token:<token>@host`.
     *
     * GitHub fine-grained PATs (github_pat_*) require this format — they fail
     * with 403 when placed in the username-only position.  Classic PATs (ghp_*)
     * work either way, but we normalise them too for consistency.
     *
     * SSH URLs and URLs that already carry user:pass are returned unchanged.
     */
    public static function normalizeRemoteUrl(string $url): string
    {
        $parts = parse_url($url);

        // Not an HTTP(S) URL, or no user component → nothing to do.
        if (! isset($parts['scheme'], $parts['host'], $parts['user'])) {
            return $url;
        }

        if (! in_array($parts['scheme'], ['http', 'https'], true)) {
            return $url;
        }

        // Already has a password (user:pass format) → leave as-is.
        if (isset($parts['pass'])) {
            return $url;
        }

        $token = $parts['user'];

        // Only rewrite when the username looks like a known token format.
        $looksLikeToken = str_starts_with($token, 'ghp_')
            || str_starts_with($token, 'github_pat_')
            || str_starts_with($token, 'glpat-')
            || preg_match('/^[a-f0-9]{40,}$/i', $token);

        if (! $looksLikeToken) {
            return $url;
        }

        // Rebuild the URL with x-access-token:<token>@ auth.
        $parts['user'] = 'x-access-token';
        $parts['pass'] = $token;

        return self::buildUrl($parts);
    }

    /**
     * Redact credentials from a git command array for safe logging.
     *
     * @param  array<int, string>  $command
     * @return array<int, string>
     */
    public static function redactCommand(array $command): array
    {
        return array_map(function (string $arg): string {
            // Match HTTPS URLs containing userinfo (user:pass@ or user@).
            return preg_replace(
                '#(https?://)([^@]+)@#i',
                '$1***@',
                $arg,
            );
        }, $command);
    }

    /** Rebuild a URL from parse_url() parts. */
    private static function buildUrl(array $parts): string
    {
        $url = $parts['scheme'].'://';

        if (isset($parts['user'])) {
            $url .= $parts['user'];
            if (isset($parts['pass'])) {
                $url .= ':'.$parts['pass'];
            }
            $url .= '@';
        }

        $url .= $parts['host'];

        if (isset($parts['port'])) {
            $url .= ':'.$parts['port'];
        }

        $url .= $parts['path'] ?? '';

        if (isset($parts['query'])) {
            $url .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $url .= '#'.$parts['fragment'];
        }

        return $url;
    }

    public function pathFor(Repository $repository): string
    {
        $repository->loadMissing('organization');

        return sprintf(
            '%s/%s/%s.git',
            $this->basePath(),
            $repository->organization->slug,
            $repository->slug,
        );
    }

    public function initialize(Repository $repository): void
    {
        if ($this->exists($repository)) {
            return;
        }

        $path = $this->pathFor($repository);

        $this->ensureParentDirectoryExists($path);

        $this->run([
            'git',
            'init',
            '--bare',
            '--initial-branch='.$this->configuredDefaultBranch($repository),
            $path,
        ]);

        $this->installHooks($repository);
    }

    public function clone(string $remote, Repository $repository): void
    {
        if ($this->exists($repository)) {
            throw new RuntimeException('Repository already exists on disk.');
        }

        $path = $this->pathFor($repository);

        $this->ensureParentDirectoryExists($path);

        $this->run([
            'git',
            'clone',
            '--bare',
            self::normalizeRemoteUrl($remote),
            $path,
        ]);

        $this->installHooks($repository);
    }

    /**
     * Fetch all branches and tags from a remote into the bare repo.
     *
     * Uses explicit refspecs so this works on bare repos cloned with --bare
     * (which don't have automatic fetch refspecs like --mirror clones do).
     */
    public function fetchRemote(Repository $repository, string $remoteUrl): void
    {
        $path = $this->pathFor($repository);
        $remoteUrl = self::normalizeRemoteUrl($remoteUrl);

        // Ensure the origin remote points to the current URL (handles URL changes / re-imports).
        try {
            $this->runAndCapture(['git', '--git-dir', $path, 'remote', 'get-url', 'origin']);
            $this->run(['git', '--git-dir', $path, 'remote', 'set-url', 'origin', $remoteUrl]);
        } catch (RuntimeException) {
            $this->run(['git', '--git-dir', $path, 'remote', 'add', 'origin', $remoteUrl]);
        }

        $this->run([
            'git',
            '--git-dir', $path,
            'fetch',
            'origin',
            '+refs/heads/*:refs/heads/*',
            '+refs/tags/*:refs/tags/*',
            '--prune',
            '--prune-tags',
        ]);
    }

    public function branches(Repository $repository): array
    {
        if (! $this->exists($repository)) {
            return [];
        }

        $output = $this->runAndCapture([
            'git',
            '--git-dir',
            $this->pathFor($repository),
            'for-each-ref',
            '--format=%(refname:short)',
            'refs/heads',
        ]);

        $branches = collect(preg_split('/\R/', trim($output) ?: ''))
            ->filter()
            ->values()
            ->all();

        if ($branches === []) {
            return [$this->defaultBranch($repository)];
        }

        return $branches;
    }

    /**
     * List all tags with their resolved commit SHA, tagger date, and subject.
     *
     * Returns newest-first. Annotated tags expose the tagger date; lightweight
     * tags fall back to the commit author date.
     *
     * Each entry: name, sha (commit), type (tag|commit), date (Carbon|null), subject
     */
    public function tags(Repository $repository): array
    {
        if (! $this->exists($repository)) {
            return [];
        }

        $output = trim($this->runAndCapture([
            'git',
            '--git-dir', $this->pathFor($repository),
            'for-each-ref',
            '--sort=-version:refname',
            '--format=%(refname:short)%09%(*objectname)%(objectname)%09%(objecttype)%09%(creatordate:iso-strict)%09%(subject)',
            'refs/tags',
        ]));

        if ($output === '') {
            return [];
        }

        return collect(preg_split('/\R/', $output))
            ->filter()
            ->map(function (string $line): ?array {
                $parts = explode("\t", $line, 5);

                if (count($parts) < 4) {
                    return null;
                }

                [$nameRaw, $shaRaw, $type, $dateRaw, $subject] = array_pad($parts, 5, '');

                // *objectname is set for annotated tags (points at the commit);
                // objectname alone is the tag object SHA for annotated, commit SHA for lightweight.
                // The format emits them concatenated when *objectname is empty.
                $sha = strlen($shaRaw) === 40 ? $shaRaw : substr($shaRaw, 40);

                if (strlen($sha) !== 40) {
                    $sha = substr($shaRaw, 0, 40);
                }

                return [
                    'name' => trim($nameRaw),
                    'sha' => $sha,
                    'type' => trim($type),
                    'date' => $dateRaw !== '' ? Carbon::parse(trim($dateRaw)) : null,
                    'subject' => trim($subject ?? ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Resolve any ref (branch, tag, SHA) to its full 40-char commit SHA.
     * Returns null if the ref does not exist.
     */
    public function resolveSha(Repository $repository, string $ref): ?string
    {
        try {
            $sha = trim($this->runAndCapture([
                'git',
                '--git-dir', $this->pathFor($repository),
                'rev-parse', '--verify', $ref.'^{commit}',
            ]));

            return $sha !== '' ? $sha : null;
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Create a new branch pointing at the given SHA.
     * Throws RuntimeException if the branch already exists or the SHA is invalid.
     */
    public function createBranch(Repository $repository, string $branch, string $sha): void
    {
        $this->run([
            'git',
            '--git-dir', $this->pathFor($repository),
            'branch', $branch, $sha,
        ]);
    }

    /**
     * Find the common ancestor commit of two refs (merge-base).
     */
    public function mergeBase(Repository $repository, string $a, string $b): ?string
    {
        try {
            $sha = trim($this->runAndCapture([
                'git',
                '--git-dir', $this->pathFor($repository),
                'merge-base', $a, $b,
            ]));

            return $sha !== '' ? $sha : null;
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Commits reachable from $head but not from $base  (i.e. $base..$head).
     * Returns the same structure as log().
     */
    public function compareCommits(Repository $repository, string $base, string $head, int $limit = 250): array
    {
        if (! $this->hasRevision($repository, $base) || ! $this->hasRevision($repository, $head)) {
            return [];
        }

        return $this->log($repository, "{$base}..{$head}", $limit, 0);
    }

    /**
     * Unified diff between two refs.
     *
     * Uses triple-dot notation ($base...$head) when a common merge-base exists,
     * which diffs from the merge-base to the tip of $head — the canonical "what
     * does this PR add?" view.
     *
     * Falls back to double-dot ($base..$head) for unrelated histories (no merge
     * base), which diffs the two branch tips directly.
     */
    public function compareDiff(Repository $repository, string $base, string $head): string
    {
        if (! $this->hasRevision($repository, $base) || ! $this->hasRevision($repository, $head)) {
            return '';
        }

        $hasMergeBase = $this->mergeBase($repository, $base, $head) !== null;
        $range = $hasMergeBase ? "{$base}...{$head}" : "{$base}..{$head}";

        try {
            return $this->runAndCapture([
                'git',
                '--git-dir', $this->pathFor($repository),
                'diff',
                $range,
            ]);
        } catch (RuntimeException) {
            return '';
        }
    }

    /**
     * Machine-readable per-file change counts between two refs.
     *
     * Uses triple-dot (merge-base) when available, falls back to double-dot
     * for unrelated histories. Returns an array keyed by file path:
     *   [ 'src/Foo.php' => ['additions' => 12, 'deletions' => 4], … ]
     *
     * Binary files show additions = 0, deletions = 0.
     */
    public function compareNumStat(Repository $repository, string $base, string $head): array
    {
        if (! $this->hasRevision($repository, $base) || ! $this->hasRevision($repository, $head)) {
            return [];
        }

        $hasMergeBase = $this->mergeBase($repository, $base, $head) !== null;
        $range = $hasMergeBase ? "{$base}...{$head}" : "{$base}..{$head}";

        try {
            $output = $this->runAndCapture([
                'git',
                '--git-dir', $this->pathFor($repository),
                'diff',
                '--numstat',
                $range,
            ]);
        } catch (RuntimeException) {
            return [];
        }

        $result = [];

        foreach (explode("\n", trim($output)) as $line) {
            if ($line === '') {
                continue;
            }

            // Format: "<additions>\t<deletions>\t<path>"
            // Binary files show "-\t-\t<path>"
            $parts = explode("\t", $line, 3);

            if (count($parts) < 3) {
                continue;
            }

            [$additions, $deletions, $path] = $parts;

            $result[$path] = [
                'additions' => is_numeric($additions) ? (int) $additions : 0,
                'deletions' => is_numeric($deletions) ? (int) $deletions : 0,
            ];
        }

        return $result;
    }

    /**
     * Attempt a no-fast-forward merge of $sourceBranch into $targetBranch using a
     * temporary local clone of the bare repository (portable; no working tree needed
     * in the bare repo itself).
     *
     * Returns the resulting merge commit SHA on success.
     * Throws RuntimeException on merge conflicts or other git errors.
     *
     * @throws RuntimeException
     */
    public function mergeBranches(
        Repository $repository,
        string $sourceBranch,
        string $targetBranch,
        string $commitMessage,
        string $authorName,
        string $authorEmail,
    ): string {
        $barePath = $this->pathFor($repository);
        $tempDir = sys_get_temp_dir().'/crucible-merge-'.uniqid('', true);

        $env = [
            'GIT_AUTHOR_NAME' => $authorName,
            'GIT_AUTHOR_EMAIL' => $authorEmail,
            'GIT_COMMITTER_NAME' => $authorName,
            'GIT_COMMITTER_EMAIL' => $authorEmail,
            'HOME' => sys_get_temp_dir(),
            'GIT_LFS_SKIP_SMUDGE' => '1',
        ];

        try {
            // Fast local clone using hard-links — very cheap on same filesystem.
            // GIT_LFS_SKIP_SMUDGE prevents checkout from failing when git-lfs is not installed.
            $this->runIn(sys_get_temp_dir(), ['git', 'clone', '--local', $barePath, $tempDir], $env);

            $this->runIn($tempDir, ['git', 'checkout', $targetBranch], $env);

            $this->runIn($tempDir, [
                'git', 'merge',
                '--no-ff',
                '-m', $commitMessage,
                'origin/'.$sourceBranch,
            ], $env);

            $sha = trim($this->runAndCaptureIn($tempDir, ['git', 'rev-parse', 'HEAD']));

            $this->runIn($tempDir, ['git', 'push', 'origin', $targetBranch]);

            return $sha;
        } finally {
            if (is_dir($tempDir)) {
                (new Filesystem)->deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Squash-merge: combine all commits from $sourceBranch into a single commit on $targetBranch.
     *
     * Returns the resulting commit SHA.
     *
     * @throws RuntimeException
     */
    public function squashMerge(
        Repository $repository,
        string $sourceBranch,
        string $targetBranch,
        string $commitMessage,
        string $authorName,
        string $authorEmail,
    ): string {
        $barePath = $this->pathFor($repository);
        $tempDir = sys_get_temp_dir().'/crucible-squash-'.uniqid('', true);

        $env = [
            'GIT_AUTHOR_NAME' => $authorName,
            'GIT_AUTHOR_EMAIL' => $authorEmail,
            'GIT_COMMITTER_NAME' => $authorName,
            'GIT_COMMITTER_EMAIL' => $authorEmail,
            'HOME' => sys_get_temp_dir(),
            'GIT_LFS_SKIP_SMUDGE' => '1',
        ];

        try {
            $this->runIn(sys_get_temp_dir(), ['git', 'clone', '--local', $barePath, $tempDir], $env);
            $this->runIn($tempDir, ['git', 'checkout', $targetBranch], $env);
            $this->runIn($tempDir, ['git', 'merge', '--squash', 'origin/'.$sourceBranch], $env);
            $this->runIn($tempDir, ['git', 'commit', '-m', $commitMessage], $env);

            $sha = trim($this->runAndCaptureIn($tempDir, ['git', 'rev-parse', 'HEAD']));
            $this->runIn($tempDir, ['git', 'push', 'origin', $targetBranch]);

            return $sha;
        } finally {
            if (is_dir($tempDir)) {
                (new Filesystem)->deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Rebase-merge: rebase all commits from $sourceBranch onto $targetBranch,
     * then fast-forward $targetBranch to the result.
     *
     * Returns the SHA of the new tip.
     *
     * @throws RuntimeException
     */
    public function rebaseMerge(
        Repository $repository,
        string $sourceBranch,
        string $targetBranch,
        string $authorName,
        string $authorEmail,
    ): string {
        $barePath = $this->pathFor($repository);
        $tempDir = sys_get_temp_dir().'/crucible-rebase-'.uniqid('', true);

        $env = [
            'GIT_AUTHOR_NAME' => $authorName,
            'GIT_AUTHOR_EMAIL' => $authorEmail,
            'GIT_COMMITTER_NAME' => $authorName,
            'GIT_COMMITTER_EMAIL' => $authorEmail,
            'HOME' => sys_get_temp_dir(),
            'GIT_LFS_SKIP_SMUDGE' => '1',
        ];

        try {
            $this->runIn(sys_get_temp_dir(), ['git', 'clone', '--local', $barePath, $tempDir], $env);
            $this->runIn($tempDir, ['git', 'checkout', $sourceBranch], $env);
            $this->runIn($tempDir, ['git', 'rebase', 'origin/'.$targetBranch], $env);

            // Fast-forward the target branch to the rebased result
            $this->runIn($tempDir, ['git', 'checkout', $targetBranch], $env);
            $this->runIn($tempDir, ['git', 'merge', '--ff-only', $sourceBranch], $env);

            $sha = trim($this->runAndCaptureIn($tempDir, ['git', 'rev-parse', 'HEAD']));
            $this->runIn($tempDir, ['git', 'push', 'origin', $targetBranch]);

            // Also push the rebased source branch so refs stay consistent
            $this->runIn($tempDir, ['git', 'push', 'origin', $sourceBranch, '--force-with-lease'], $env);

            return $sha;
        } finally {
            if (is_dir($tempDir)) {
                (new Filesystem)->deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Fast-forward merge: move $targetBranch pointer forward to $sourceBranch tip.
     * Only works when target is an ancestor of source.
     *
     * @throws RuntimeException if fast-forward is not possible.
     */
    public function fastForwardMerge(
        Repository $repository,
        string $sourceBranch,
        string $targetBranch,
    ): string {
        $barePath = $this->pathFor($repository);
        $tempDir = sys_get_temp_dir().'/crucible-ff-'.uniqid('', true);

        $env = [
            'HOME' => sys_get_temp_dir(),
            'GIT_LFS_SKIP_SMUDGE' => '1',
        ];

        try {
            $this->runIn(sys_get_temp_dir(), ['git', 'clone', '--local', $barePath, $tempDir], $env);
            $this->runIn($tempDir, ['git', 'checkout', $targetBranch], $env);
            $this->runIn($tempDir, ['git', 'merge', '--ff-only', 'origin/'.$sourceBranch], $env);

            $sha = trim($this->runAndCaptureIn($tempDir, ['git', 'rev-parse', 'HEAD']));
            $this->runIn($tempDir, ['git', 'push', 'origin', $targetBranch]);

            return $sha;
        } finally {
            if (is_dir($tempDir)) {
                (new Filesystem)->deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Check if a merge would produce conflicts using `git merge-tree` (Git 2.38+).
     * Returns true if the merge would be clean, false if conflicts exist.
     */
    public function canMergeCleanly(Repository $repository, string $sourceBranch, string $targetBranch): bool
    {
        $repoPath = $this->pathFor($repository);

        try {
            $process = new Process($this->wrapGitCommand([
                'git', '--git-dir', $repoPath,
                'merge-tree', '--write-tree', '--no-messages',
                $targetBranch, $sourceBranch,
            ]));
            $process->setTimeout(60);
            $process->run();

            // Exit code 0 = clean merge, non-zero = conflicts
            return $process->isSuccessful();
        } catch (\Throwable) {
            // Fallback: try the merge-base approach for older git versions
            return $this->canMergeCleanlyFallback($repository, $sourceBranch, $targetBranch);
        }
    }

    /**
     * Fallback conflict detection for git < 2.38 (no merge-tree --write-tree).
     * Uses a temporary clone to attempt the merge.
     */
    private function canMergeCleanlyFallback(Repository $repository, string $sourceBranch, string $targetBranch): bool
    {
        $barePath = $this->pathFor($repository);
        $tempDir = sys_get_temp_dir().'/crucible-check-'.uniqid('', true);

        try {
            $cloneProcess = new Process($this->wrapGitCommand([
                'git', 'clone', '--local', '--no-checkout', $barePath, $tempDir,
            ]), sys_get_temp_dir(), ['GIT_LFS_SKIP_SMUDGE' => '1']);
            $cloneProcess->setTimeout(60);
            $cloneProcess->run();

            if (! $cloneProcess->isSuccessful()) {
                return false;
            }

            $checkoutProcess = new Process($this->wrapGitCommand(['git', 'checkout', $targetBranch]), $tempDir);
            $checkoutProcess->setTimeout(30);
            $checkoutProcess->run();

            if (! $checkoutProcess->isSuccessful()) {
                return false;
            }

            $mergeProcess = new Process($this->wrapGitCommand([
                'git', 'merge', '--no-commit', '--no-ff', 'origin/'.$sourceBranch,
            ]), $tempDir);
            $mergeProcess->setTimeout(60);
            $mergeProcess->run();

            return $mergeProcess->isSuccessful();
        } catch (\Throwable) {
            return false;
        } finally {
            if (is_dir($tempDir)) {
                (new Filesystem)->deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Read a file from the repository's default branch by path.
     * Returns null if the file does not exist.
     */
    public function readFile(Repository $repository, string $ref, string $path): ?string
    {
        $repoPath = $this->pathFor($repository);

        try {
            $process = new Process($this->wrapGitCommand([
                'git', '--git-dir', $repoPath,
                'show', "{$ref}:{$path}",
            ]));
            $process->setTimeout(15);
            $process->run();

            if ($process->isSuccessful()) {
                return $process->getOutput();
            }
        } catch (\Throwable) {
            // File doesn't exist or ref is invalid
        }

        return null;
    }

    /**
     * Commit a file directly to a bare repository without a working tree.
     *
     * Uses git hash-object / git mktree / git commit-tree / git update-ref
     * to create a commit containing the file at the given path on the specified branch.
     *
     * @return string The SHA of the new commit.
     *
     * @throws RuntimeException
     */
    public function commitFile(
        Repository $repository,
        string $branch,
        string $filePath,
        string $contents,
        string $commitMessage,
        string $authorName,
        string $authorEmail,
    ): string {
        $repoPath = $this->pathFor($repository);

        $env = [
            'GIT_AUTHOR_NAME' => $authorName,
            'GIT_AUTHOR_EMAIL' => $authorEmail,
            'GIT_COMMITTER_NAME' => $authorName,
            'GIT_COMMITTER_EMAIL' => $authorEmail,
        ];

        // 1. Hash the file contents into a blob
        $blobSha = trim($this->runAndCapture([
            'git', '--git-dir', $repoPath,
            'hash-object', '-w', '--stdin',
        ], $contents));

        // 2. Get the current tree for the branch (if it exists)
        $parentSha = null;
        $existingTree = null;

        try {
            $parentSha = trim($this->runAndCapture([
                'git', '--git-dir', $repoPath,
                'rev-parse', '--verify', "refs/heads/{$branch}^{commit}",
            ]));

            $existingTree = trim($this->runAndCapture([
                'git', '--git-dir', $repoPath,
                'rev-parse', "{$parentSha}^{tree}",
            ]));
        } catch (RuntimeException) {
            // Branch doesn't exist yet — we'll create a root commit
        }

        // 3. Build the new tree by reading the existing tree and adding/replacing our file
        if ($existingTree) {
            // Read existing tree into index, update the entry, write new tree
            $indexFile = sys_get_temp_dir().'/crucible-index-'.uniqid('', true);

            try {
                $indexEnv = array_merge($env, ['GIT_INDEX_FILE' => $indexFile]);

                // Read existing tree into temporary index
                $this->runWithEnv([
                    'git', '--git-dir', $repoPath,
                    'read-tree', $existingTree,
                ], $indexEnv);

                // Update the index with our new blob
                $this->runWithEnv([
                    'git', '--git-dir', $repoPath,
                    'update-index', '--add', '--cacheinfo', '100644', $blobSha, $filePath,
                ], $indexEnv);

                // Write the tree
                $treeSha = trim($this->runAndCaptureWithEnv([
                    'git', '--git-dir', $repoPath,
                    'write-tree',
                ], $indexEnv));
            } finally {
                @unlink($indexFile);
            }
        } else {
            // No existing tree — build a minimal tree with just this file
            // mktree expects: "<mode> <type> <sha>\t<name>"
            $treeInput = "100644 blob {$blobSha}\t{$filePath}\n";
            $treeSha = trim($this->runAndCapture([
                'git', '--git-dir', $repoPath,
                'mktree',
            ], $treeInput));
        }

        // 4. Create the commit
        $commitCmd = [
            'git', '--git-dir', $repoPath,
            'commit-tree', $treeSha,
            '-m', $commitMessage,
        ];

        if ($parentSha) {
            $commitCmd[] = '-p';
            $commitCmd[] = $parentSha;
        }

        $commitProcess = new Process($this->wrapGitCommand($commitCmd), null, $env);
        $commitProcess->setTimeout(30);
        $commitProcess->run();

        if (! $commitProcess->isSuccessful()) {
            throw new RuntimeException(
                trim($commitProcess->getErrorOutput()) ?: 'Failed to create commit'
            );
        }

        $commitSha = trim($commitProcess->getOutput());

        // 5. Update the branch ref
        $this->run([
            'git', '--git-dir', $repoPath,
            'update-ref', "refs/heads/{$branch}", $commitSha,
        ]);

        return $commitSha;
    }

    /** Run a git command with custom environment variables. */
    protected function runWithEnv(array $command, array $env): Process
    {
        $process = new Process($this->wrapGitCommand($command), null, $env);
        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful()) {
            return $process;
        }

        $message = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Git command failed.';
        throw new RuntimeException($message);
    }

    /** Run a git command with custom env and capture output. */
    protected function runAndCaptureWithEnv(array $command, array $env): string
    {
        return $this->runWithEnv($command, $env)->getOutput();
    }

    /** Run a command inside a specific directory (non-git-dir form). */
    protected function runIn(string $dir, array $command, array $env = []): Process
    {
        $process = new Process($this->wrapGitCommand($command), $dir, $env ?: null);
        $process->setTimeout(180);
        $process->run();

        if ($process->isSuccessful()) {
            return $process;
        }

        $message = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Git command failed.';

        Log::error('[NativeGitRepositoryService] command failed in dir', [
            'dir' => $dir,
            'command' => self::redactCommand($command),
            'message' => $message,
        ]);

        throw new RuntimeException($message);
    }

    /** Capture output of a command run inside a specific directory. */
    protected function runAndCaptureIn(string $dir, array $command, array $env = []): string
    {
        return $this->runIn($dir, $command, $env)->getOutput();
    }

    public function defaultBranch(Repository $repository): string
    {
        if (! $this->exists($repository)) {
            return $this->configuredDefaultBranch($repository);
        }

        try {
            $branch = trim($this->runAndCapture([
                'git',
                '--git-dir',
                $this->pathFor($repository),
                'symbolic-ref',
                '--quiet',
                '--short',
                'HEAD',
            ]));

            return $branch !== '' ? $branch : $this->configuredDefaultBranch($repository);
        } catch (RuntimeException) {
            return $this->configuredDefaultBranch($repository);
        }
    }

    public function exists(Repository $repository): bool
    {
        $path = $this->pathFor($repository);

        return $this->files->isDirectory($path)
            && $this->files->exists($path.'/HEAD')
            && $this->files->isDirectory($path.'/objects');
    }

    public function delete(Repository $repository): void
    {
        $path = $this->pathFor($repository);

        if (! $this->files->exists($path)) {
            return;
        }

        $basePath = $this->basePath();

        if (! str_starts_with($path, $basePath.'/')) {
            throw new RuntimeException('Refusing to delete a repository outside the configured repositories path.');
        }

        $this->files->deleteDirectory($path);
        $this->deleteEmptyAncestorDirectories(dirname($path), $basePath);
    }

    public function size(Repository $repository): int
    {
        if (! $this->exists($repository)) {
            return 0;
        }

        $output = $this->runAndCapture([
            'du',
            '-sk',
            $this->pathFor($repository),
        ]);

        $kilobytes = (int) preg_split('/\s+/', trim($output), 2)[0];

        return $kilobytes * 1024;
    }

    public function hasRevision(Repository $repository, ?string $ref = null): bool
    {
        if (! $this->exists($repository)) {
            return false;
        }

        $ref ??= $this->defaultBranch($repository);

        try {
            $this->runAndCapture([
                'git',
                '--git-dir',
                $this->pathFor($repository),
                'rev-parse',
                '--verify',
                $ref.'^{commit}',
            ]);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function recursiveTree(Repository $repository, ?string $ref = null): array
    {
        $ref ??= $this->defaultBranch($repository);

        if (! $this->hasRevision($repository, $ref)) {
            return [];
        }

        $output = trim($this->runAndCapture([
            'git',
            '--git-dir',
            $this->pathFor($repository),
            'ls-tree',
            '-r',
            '-t',
            $ref,
        ]));

        if ($output === '') {
            return [];
        }

        $entries = collect(preg_split('/\R/', $output))
            ->filter()
            ->map(fn (string $line) => $this->parseTreeLine($line))
            ->filter()
            ->values()
            ->all();

        return $this->buildTree($entries);
    }

    /**
     * Enumerate every blob reachable from $ref with its byte size.
     *
     * Returns a list of ['path' => string, 'size' => int]. Tree entries are skipped.
     * Unlike recursiveTree() this uses `git ls-tree -r -l` to include the size column,
     * suitable for aggregating per-language byte totals.
     */
    public function blobSizes(Repository $repository, ?string $ref = null): array
    {
        $ref ??= $this->defaultBranch($repository);

        if (! $this->hasRevision($repository, $ref)) {
            return [];
        }

        $output = trim($this->runAndCapture([
            'git',
            '--git-dir',
            $this->pathFor($repository),
            'ls-tree',
            '-r',
            '-l',
            $ref,
        ]));

        if ($output === '') {
            return [];
        }

        $entries = [];

        foreach (preg_split('/\R/', $output) as $line) {
            if ($line === '' || $line === null) {
                continue;
            }

            // Format: "<mode> <type> <sha> <size>\t<path>"  (size is "-" for trees)
            if (! preg_match('/^\d+\s+(?<type>\w+)\s+[0-9a-f]+\s+(?<size>\S+)\t(?<path>.+)$/', $line, $matches)) {
                continue;
            }

            if ($matches['type'] !== 'blob' || $matches['size'] === '-') {
                continue;
            }

            $entries[] = [
                'path' => $matches['path'],
                'size' => (int) $matches['size'],
            ];
        }

        return $entries;
    }

    /**
     * Resolve the full SHA of the commit at $ref, or null if the ref does not exist.
     */
    public function headSha(Repository $repository, ?string $ref = null): ?string
    {
        $ref ??= $this->defaultBranch($repository);

        if (! $this->hasRevision($repository, $ref)) {
            return null;
        }

        try {
            $sha = trim($this->runAndCapture([
                'git',
                '--git-dir',
                $this->pathFor($repository),
                'rev-parse',
                $ref.'^{commit}',
            ]));

            return $sha === '' ? null : $sha;
        } catch (RuntimeException) {
            return null;
        }
    }

    public function fileContents(Repository $repository, string $path, ?string $ref = null): ?string
    {
        $ref ??= $this->defaultBranch($repository);

        if (! $this->hasRevision($repository, $ref)) {
            return null;
        }

        try {
            return $this->runAndCapture([
                'git',
                '--git-dir',
                $this->pathFor($repository),
                'show',
                sprintf('%s:%s', $ref, $path),
            ]);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Check whether the git-lfs binary is available on this system.
     *
     * Only caches a positive result so that installing git-lfs at runtime
     * is picked up without restarting the queue worker.
     */
    public function isLfsInstalled(): bool
    {
        static $installed = false;

        if ($installed) {
            return true;
        }

        try {
            $process = new Process($this->wrapGitCommand(['git', 'lfs', 'version']));
            $process->setTimeout(10);
            $process->run();

            $installed = $process->isSuccessful();
        } catch (\Throwable) {
            $installed = false;
        }

        return $installed;
    }

    /**
     * Fetch all LFS objects from a remote into the bare repo's LFS cache.
     *
     * The timeout is generous (3600s / 1 hour) because large game repos can
     * have tens of thousands of LFS objects.  git-lfs fetch is resumable —
     * objects already in the cache are skipped on subsequent runs.
     */
    public function fetchLfsObjects(Repository $repository, string $remoteUrl): void
    {
        $path = $this->pathFor($repository);
        $remoteUrl = self::normalizeRemoteUrl($remoteUrl);

        // Ensure the origin remote points to the current URL.
        try {
            $this->runAndCapture(['git', '--git-dir', $path, 'remote', 'get-url', 'origin']);
            $this->run(['git', '--git-dir', $path, 'remote', 'set-url', 'origin', $remoteUrl]);
        } catch (RuntimeException) {
            $this->run(['git', '--git-dir', $path, 'remote', 'add', 'origin', $remoteUrl]);
        }

        $command = ['git', '--git-dir', $path, 'lfs', 'fetch', '--all', 'origin'];

        Log::debug('[NativeGit] running: '.implode(' ', $this->redactCommand($command)));

        $process = new Process($this->wrapGitCommand($command));
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'git lfs fetch failed: '.$process->getErrorOutput(),
            );
        }
    }

    /**
     * Scan the bare repo's LFS object cache and return all cached objects.
     *
     * @return array<int, array{oid: string, size: int}>
     */
    public function listCachedLfsObjects(Repository $repository): array
    {
        $lfsDir = $this->pathFor($repository).'/lfs/objects';

        if (! is_dir($lfsDir)) {
            return [];
        }

        $objects = [];

        // LFS cache structure: lfs/objects/{oid[0:2]}/{oid[2:4]}/{oid}
        foreach (glob($lfsDir.'/*/*') as $subdir) {
            if (! is_dir($subdir)) {
                continue;
            }

            foreach (glob($subdir.'/*') as $file) {
                if (! is_file($file)) {
                    continue;
                }

                $oid = basename($file);

                if (! preg_match('/\A[a-f0-9]{64}\z/i', $oid)) {
                    continue;
                }

                $objects[] = [
                    'oid' => strtolower($oid),
                    'size' => (int) filesize($file),
                ];
            }
        }

        return $objects;
    }

    /**
     * Return the filesystem path to a cached LFS object in the bare repo.
     */
    public function lfsObjectCachePath(Repository $repository, string $oid): string
    {
        return sprintf(
            '%s/lfs/objects/%s/%s/%s',
            $this->pathFor($repository),
            substr($oid, 0, 2),
            substr($oid, 2, 2),
            $oid,
        );
    }

    /**
     * Return a map of LFS object OID → tracked file path for this repository.
     *
     * Uses `git lfs ls-files --all --long` so historical OIDs (from any ref)
     * are included, not just the current HEAD.  Returns an empty array when
     * git-lfs is not installed or the command fails.
     *
     * @return array<string, string>
     */
    public function lfsOidPathMap(Repository $repository): array
    {
        if (! $this->isLfsInstalled()) {
            return [];
        }

        try {
            $output = $this->runAndCapture([
                'git',
                '--git-dir',
                $this->pathFor($repository),
                'lfs',
                'ls-files',
                '--all',
                '--long',
            ]);
        } catch (RuntimeException) {
            return [];
        }

        $map = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Format: "<oid> <status> <path>" where status is "*" (present) or "-" (missing).
            if (preg_match('/^([a-f0-9]{64})\s+[-*]\s+(.+)$/i', $line, $matches) !== 1) {
                continue;
            }

            $oid = strtolower($matches[1]);

            // Keep the first path seen for an OID — identical content may live at multiple
            // paths, but any path with the same extension yields the same mime type.
            if (! isset($map[$oid])) {
                $map[$oid] = $matches[2];
            }
        }

        return $map;
    }

    public function lfsTrackedPaths(Repository $repository, array $paths, ?string $ref = null): array
    {
        $paths = collect($paths)
            ->filter(fn (?string $path) => filled($path))
            ->unique()
            ->values()
            ->all();

        if ($paths === []) {
            return [];
        }

        $ref ??= $this->defaultBranch($repository);

        if (! $this->hasRevision($repository, $ref)) {
            return array_fill_keys($paths, false);
        }

        try {
            $output = $this->runAndCapture([
                'git',
                '--git-dir',
                $this->pathFor($repository),
                'check-attr',
                '--stdin',
                '-z',
                '--source='.$ref,
                'filter',
            ], implode("\0", $paths)."\0");
        } catch (RuntimeException) {
            return array_fill_keys($paths, false);
        }

        $attributes = $this->parseAttributeOutput($output);

        return collect($paths)
            ->mapWithKeys(fn (string $path) => [$path => ($attributes[$path] ?? null) === 'lfs'])
            ->all();
    }

    /**
     * Return a page of commits reachable from $ref.
     *
     * Each entry: sha, short_sha, subject, author_name, author_email, author_date (Carbon)
     */
    public function log(
        Repository $repository,
        ?string $ref = null,
        int $limit = 30,
        int $skip = 0,
        ?string $path = null,
    ): array {
        $ref ??= $this->defaultBranch($repository);

        // Range expressions (e.g. "master..main", "abc...def") are not single
        // revisions — skip the hasRevision guard and let git evaluate the range.
        $isRange = str_contains((string) $ref, '..');

        if (! $isRange && ! $this->hasRevision($repository, $ref)) {
            return [];
        }

        $command = [
            'git', '--git-dir', $this->pathFor($repository),
            'log',
            '--format=%H%x1f%h%x1f%s%x1f%an%x1f%ae%x1f%aI%x1e',
            '--max-count='.max(1, $limit),
            '--skip='.max(0, $skip),
            $ref,
        ];

        if (filled($path)) {
            array_push($command, '--', $path);
        }

        $output = trim($this->runAndCapture($command));

        if ($output === '') {
            return [];
        }

        return collect(explode("\x1e", $output))
            ->map(fn (string $r) => trim($r))
            ->filter()
            ->map(function (string $record): ?array {
                $f = explode("\x1f", $record, 6);

                if (count($f) < 6) {
                    return null;
                }

                return [
                    'sha' => $f[0],
                    'short_sha' => $f[1],
                    'subject' => $f[2],
                    'author_name' => $f[3],
                    'author_email' => $f[4],
                    'author_date' => $f[5] !== '' ? Carbon::parse($f[5]) : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Total number of commits reachable from $ref (optionally filtered by $path).
     */
    public function commitCount(Repository $repository, ?string $ref = null, ?string $path = null): int
    {
        $ref ??= $this->defaultBranch($repository);

        if (! $this->hasRevision($repository, $ref)) {
            return 0;
        }

        $command = [
            'git', '--git-dir', $this->pathFor($repository),
            'rev-list', '--count', $ref,
        ];

        if (filled($path)) {
            array_push($command, '--', $path);
        }

        try {
            return (int) trim($this->runAndCapture($command));
        } catch (RuntimeException) {
            return 0;
        }
    }

    /**
     * Full details for a single commit: metadata, file stat summary, and unified diff.
     *
     * Returns null if $sha is not a valid commit in this repository.
     */
    public function commitShow(Repository $repository, string $sha): ?array
    {
        if (! $this->exists($repository)) {
            return null;
        }

        // Resolve & verify
        try {
            $resolvedSha = trim($this->runAndCapture([
                'git', '--git-dir', $this->pathFor($repository),
                'rev-parse', '--verify', $sha.'^{commit}',
            ]));
        } catch (RuntimeException) {
            return null;
        }

        // Metadata: sha, short sha, subject, body, author name, author email, date, parent shas
        $meta = trim($this->runAndCapture([
            'git', '--git-dir', $this->pathFor($repository),
            'log', '-1',
            '--format=%H%x1f%h%x1f%s%x1f%b%x1f%an%x1f%ae%x1f%aI%x1f%P',
            $resolvedSha,
        ]));

        $f = explode("\x1f", $meta, 8);

        if (count($f) < 8) {
            return null;
        }

        // File-change summary (--stat)
        try {
            $stat = trim($this->runAndCapture([
                'git', '--git-dir', $this->pathFor($repository),
                'diff-tree', '--stat', '--no-commit-id', '-r', $resolvedSha,
            ]));
        } catch (RuntimeException) {
            $stat = '';
        }

        // Unified diff
        try {
            $diff = $this->runAndCapture([
                'git', '--git-dir', $this->pathFor($repository),
                'diff-tree', '-p', '--cc', '--no-commit-id', '-r', $resolvedSha,
            ]);
        } catch (RuntimeException) {
            $diff = '';
        }

        return [
            'sha' => $f[0],
            'short_sha' => $f[1],
            'subject' => $f[2],
            'body' => trim($f[3]),
            'author_name' => $f[4],
            'author_email' => $f[5],
            'author_date' => $f[6] !== '' ? Carbon::parse($f[6]) : null,
            'parent_shas' => array_values(array_filter(explode(' ', trim($f[7])))),
            'stat' => $stat,
            'diff' => $diff,
        ];
    }

    public function advertiseRefs(Repository $repository, string $service): string
    {
        return $this->runAndCapture($this->serviceCommand($repository, $service, true));
    }

    public function handleStatelessRpc(Repository $repository, string $service, string $input = ''): string
    {
        return $this->runAndCapture(
            $this->serviceCommand($repository, $service),
            $input,
        );
    }

    /**
     * Install Crucible server-side hooks into a bare repository.
     */
    public function installHooks(Repository $repository): void
    {
        // Skip hook installation in testing to avoid subprocess DB access issues.
        if (app()->environment('testing')) {
            return;
        }

        $repoPath = $this->pathFor($repository);
        $hooksDir = $repoPath.'/hooks';

        $this->files->ensureDirectoryExists($hooksDir);

        $stubPath = base_path('stubs/hooks/pre-receive');

        if (! $this->files->exists($stubPath)) {
            Log::warning('[NativeGitRepositoryService] pre-receive hook stub not found', [
                'stub_path' => $stubPath,
            ]);

            return;
        }

        $phpBinary = config('crucible.ssh.php_binary', PHP_BINARY);
        $artisanPath = config('crucible.ssh.artisan_path') ?: base_path('artisan');

        $hookContent = str_replace(
            ['__CRUCIBLE_PHP_BINARY__', '__CRUCIBLE_ARTISAN_PATH__', '__CRUCIBLE_REPO_ID__'],
            [$phpBinary, $artisanPath, $repository->id],
            $this->files->get($stubPath),
        );

        $hookPath = $hooksDir.'/pre-receive';
        $this->files->put($hookPath, $hookContent);
        chmod($hookPath, 0755);
    }

    protected function runAndCapture(array $command, ?string $input = null): string
    {
        return $this->run($command, $input)->getOutput();
    }

    /**
     * Prepend `nice -n 19 ionice -c 3` to a git command when throttling is
     * enabled. The wrapped git process runs at the lowest CPU priority and in
     * the idle I/O class, so php-fpm and Valkey always win under contention.
     * No-op when CRUCIBLE_GIT_NICE_WRAP is false.
     */
    protected function wrapGitCommand(array $command): array
    {
        if (! config('crucible.git.nice_wrap', false)) {
            return $command;
        }

        return ['nice', '-n', '19', 'ionice', '-c', '3', ...$command];
    }

    protected function run(array $command, ?string $input = null): Process
    {
        $process = new Process($this->wrapGitCommand($command));
        $process->setTimeout(120);

        if ($input !== null) {
            $process->setInput($input);
        }

        $process->run();

        if ($process->isSuccessful()) {
            return $process;
        }

        $message = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Git command failed.';

        Log::error('[NativeGitRepositoryService] command failed', [
            'command' => self::redactCommand($command),
            'message' => $message,
        ]);

        throw new RuntimeException($message);
    }

    protected function serviceCommand(Repository $repository, string $service, bool $advertiseRefs = false): array
    {
        if (! in_array($service, ['git-upload-pack', 'git-receive-pack'], true)) {
            throw new RuntimeException('Unsupported git service requested.');
        }

        $command = ['git', str_replace('git-', '', $service), '--stateless-rpc'];

        if ($advertiseRefs) {
            $command[] = '--advertise-refs';
        }

        $command[] = $this->pathFor($repository);

        return $command;
    }

    protected function configuredDefaultBranch(Repository $repository): string
    {
        return $repository->default_branch ?: 'main';
    }

    protected function ensureParentDirectoryExists(string $path): void
    {
        $this->files->ensureDirectoryExists(dirname($path));
    }

    protected function parseTreeLine(string $line): ?array
    {
        if (! preg_match('/^(?<mode>\d+)\s+(?<type>\w+)\s+(?<sha>[0-9a-f]+)\t(?<path>.+)$/', $line, $matches)) {
            return null;
        }

        return [
            'type' => $matches['type'],
            'path' => $matches['path'],
        ];
    }

    protected function buildTree(array $entries): array
    {
        $root = [];

        foreach ($entries as $entry) {
            $segments = explode('/', $entry['path']);
            $currentPath = '';
            $current = &$root;

            foreach ($segments as $index => $segment) {
                $currentPath = ltrim($currentPath.'/'.$segment, '/');
                $isLeaf = $index === array_key_last($segments);

                if (! isset($current[$segment])) {
                    $current[$segment] = [
                        'name' => $segment,
                        'path' => $currentPath,
                        'type' => $isLeaf ? $entry['type'] : 'tree',
                        'children' => [],
                    ];
                }

                if ($isLeaf) {
                    $current[$segment]['type'] = $entry['type'];
                } else {
                    $current[$segment]['type'] = 'tree';
                    $current = &$current[$segment]['children'];
                }
            }

            unset($current);
        }

        return $this->normalizeTree($root);
    }

    protected function normalizeTree(array $nodes): array
    {
        $normalized = collect($nodes)
            ->map(function (array $node): array {
                $node['children'] = $this->normalizeTree($node['children']);

                return $node;
            })
            ->values()
            ->all();

        usort($normalized, function (array $left, array $right): int {
            if ($left['type'] !== $right['type']) {
                return $left['type'] === 'tree' ? -1 : 1;
            }

            return strnatcasecmp($left['name'], $right['name']);
        });

        return $normalized;
    }

    protected function parseAttributeOutput(string $output): array
    {
        $records = explode("\0", rtrim($output, "\0"));
        $attributes = [];

        foreach (array_chunk($records, 3) as $record) {
            if (count($record) !== 3) {
                continue;
            }

            [$path, $attribute, $value] = $record;

            if ($attribute !== 'filter') {
                continue;
            }

            $attributes[$path] = $value;
        }

        return $attributes;
    }

    protected function deleteEmptyAncestorDirectories(string $path, string $basePath): void
    {
        while ($path !== $basePath && $path !== dirname($path)) {
            if ($this->files->files($path) !== [] || $this->files->directories($path) !== []) {
                return;
            }

            $this->files->deleteDirectory($path);
            $path = dirname($path);
        }
    }

    protected function basePath(): string
    {
        return rtrim((string) config('crucible.git.repos_path'), '/');
    }
}
