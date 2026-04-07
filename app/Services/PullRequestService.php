<?php

namespace App\Services;

use App\Enums\MergeStrategy;
use App\Enums\PullRequestStatus;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\User;
use App\Support\DiffParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PullRequestService
{
    public function __construct(
        protected NativeGitRepositoryService $git,
        protected ForgeService $forge,
        protected BranchProtectionService $branchProtection,
    ) {}

    /**
     * Open a new pull request.  Captures the current tip SHAs of both branches
     * so the diff is always anchored to what existed when the PR was created.
     *
     * @throws RuntimeException if either branch cannot be resolved or source === target.
     */
    public function create(
        Repository $repository,
        User $author,
        string $title,
        ?string $description,
        string $sourceBranch,
        string $targetBranch,
        ?string $forgeIssueKey = null,
        bool $isDraft = false,
        ?MergeStrategy $mergeStrategy = null,
    ): PullRequest {
        if ($sourceBranch === $targetBranch) {
            throw new RuntimeException('Source and target branch must differ.');
        }

        $headSha = $this->git->resolveSha($repository, $sourceBranch);
        $baseSha = $this->git->resolveSha($repository, $targetBranch);

        if (! $headSha) {
            throw new RuntimeException("Branch '{$sourceBranch}' not found in this repository.");
        }

        if (! $baseSha) {
            throw new RuntimeException("Branch '{$targetBranch}' not found in this repository.");
        }

        $pr = DB::transaction(function () use (
            $repository, $author, $title, $description,
            $sourceBranch, $targetBranch, $headSha, $baseSha,
            $forgeIssueKey, $isDraft, $mergeStrategy
        ): PullRequest {
            // Atomic per-repo sequential number.
            $number = ($repository->pullRequests()->max('number') ?? 0) + 1;

            return $repository->pullRequests()->create([
                'number'          => $number,
                'title'           => $title,
                'description'     => $description,
                'author_id'       => $author->id,
                'source_branch'   => $sourceBranch,
                'target_branch'   => $targetBranch,
                'status'          => PullRequestStatus::Open,
                'merge_strategy'  => $mergeStrategy ?? MergeStrategy::MergeCommit,
                'is_draft'        => $isDraft,
                'head_sha'        => $headSha,
                'base_sha'        => $baseSha,
                'forge_issue_key' => $forgeIssueKey,
            ]);
        });

        // Notify Forge that a PR has been linked to the issue (best-effort, non-blocking).
        if ($forgeIssueKey) {
            $this->notifyForgeOfPr($pr, 'open', $author);
        }

        return $pr;
    }

    /**
     * Merge the pull request using the configured merge strategy.
     *
     * @throws RuntimeException on merge conflicts, unmet requirements, or if the PR is not open.
     */
    public function merge(PullRequest $pullRequest, User $actor, ?MergeStrategy $strategyOverride = null): void
    {
        if (! $pullRequest->isOpen()) {
            throw new RuntimeException('Only open pull requests can be merged.');
        }

        if ($pullRequest->isDraft()) {
            throw new RuntimeException('Draft pull requests cannot be merged. Mark as ready for review first.');
        }

        // Check branch protection requirements
        $protectionViolations = $this->branchProtection->checkMergeRequirements($pullRequest);
        if (! empty($protectionViolations)) {
            throw new RuntimeException(
                'Merge blocked by branch protection: '.implode(' ', $protectionViolations)
            );
        }

        $repository = $pullRequest->repository;
        $strategy   = $strategyOverride ?? $pullRequest->merge_strategy ?? MergeStrategy::MergeCommit;
        $message    = "Merge pull request #{$pullRequest->number}: {$pullRequest->title}";

        $mergeCommitSha = match ($strategy) {
            MergeStrategy::Squash => $this->git->squashMerge(
                $repository,
                $pullRequest->source_branch,
                $pullRequest->target_branch,
                $message,
                $actor->name,
                $actor->email,
            ),
            MergeStrategy::Rebase => $this->git->rebaseMerge(
                $repository,
                $pullRequest->source_branch,
                $pullRequest->target_branch,
                $actor->name,
                $actor->email,
            ),
            MergeStrategy::FastForward => $this->git->fastForwardMerge(
                $repository,
                $pullRequest->source_branch,
                $pullRequest->target_branch,
            ),
            default => $this->git->mergeBranches(
                $repository,
                $pullRequest->source_branch,
                $pullRequest->target_branch,
                $message,
                $actor->name,
                $actor->email,
            ),
        };

        $pullRequest->update([
            'status'           => PullRequestStatus::Merged,
            'merge_strategy'   => $strategy,
            'merged_by_id'     => $actor->id,
            'merge_commit_sha' => $mergeCommitSha,
            'merged_at'        => now(),
        ]);

        // Notify Forge that the PR was merged (best-effort).
        if ($pullRequest->forge_issue_key) {
            $this->notifyForgeOfPr($pullRequest, 'merged', $actor);
        }
    }

    /**
     * Close a pull request without merging.
     */
    public function close(PullRequest $pullRequest, User $actor): void
    {
        if (! $pullRequest->isOpen()) {
            throw new RuntimeException('Only open pull requests can be closed.');
        }

        $pullRequest->update(['status' => PullRequestStatus::Closed]);
    }

    /**
     * Reopen a previously closed pull request.
     */
    public function reopen(PullRequest $pullRequest, User $actor): void
    {
        if (! $pullRequest->isClosed()) {
            throw new RuntimeException('Only closed pull requests can be reopened.');
        }

        // Refresh head SHA in case source branch has moved.
        $headSha = $this->git->resolveSha($pullRequest->repository, $pullRequest->source_branch);

        $pullRequest->update([
            'status'   => PullRequestStatus::Open,
            'head_sha' => $headSha ?? $pullRequest->head_sha,
        ]);
    }

    /**
     * Mark a draft PR as ready for review.
     */
    public function markReady(PullRequest $pullRequest, User $actor): void
    {
        if (! $pullRequest->isOpen()) {
            throw new RuntimeException('Only open pull requests can be updated.');
        }

        if (! $pullRequest->isDraft()) {
            throw new RuntimeException('Pull request is already marked as ready for review.');
        }

        $pullRequest->update(['is_draft' => false]);
    }

    /**
     * Compute the diff payload for displaying a PR:
     * - commits: commits on source_branch not in target_branch
     * - diff:    unified diff (merge-base to head)
     */
    public function diff(PullRequest $pullRequest): array
    {
        $repository = $pullRequest->repository;
        $source     = $pullRequest->source_branch;
        $target     = $pullRequest->target_branch;

        // For merged PRs use the stored SHAs so the diff is stable.
        if ($pullRequest->isMerged() && $pullRequest->head_sha && $pullRequest->base_sha) {
            $source = $pullRequest->head_sha;
            $target = $pullRequest->base_sha;
        }

        $rawDiff  = $this->git->compareDiff($repository, $target, $source);
        $numStat  = $this->git->compareNumStat($repository, $target, $source);
        $files    = DiffParser::parse($rawDiff);

        // Merge numstat data into parsed files so the header bar has accurate counts
        // even for files where the parser couldn't count lines (binary, renames, etc.).
        foreach ($files as &$file) {
            $name = $file['file_name'];
            if (isset($numStat[$name])) {
                $file['additions'] = max($file['additions'], $numStat[$name]['additions']);
                $file['deletions'] = max($file['deletions'], $numStat[$name]['deletions']);
            }
        }
        unset($file);

        $totalAdditions = array_sum(array_column($files, 'additions'));
        $totalDeletions = array_sum(array_column($files, 'deletions'));

        return [
            'commits'         => $this->git->compareCommits($repository, $target, $source),
            'diff'            => $rawDiff,   // kept for backwards compat
            'files'           => $files,
            'total_additions' => $totalAdditions,
            'total_deletions' => $totalDeletions,
        ];
    }

    /**
     * Check whether the source branch can be merged into target without conflicts.
     * Uses `git merge-tree` (Git 2.38+) for accurate conflict detection.
     */
    public function canMerge(PullRequest $pullRequest): bool
    {
        if (! $pullRequest->isOpen()) {
            return false;
        }

        return $this->git->canMergeCleanly(
            $pullRequest->repository,
            $pullRequest->source_branch,
            $pullRequest->target_branch,
        );
    }

    /**
     * Get merge readiness status including branch protection checks.
     *
     * @return array{mergeable: bool, conflicts: bool, protection_violations: array, is_draft: bool}
     */
    public function mergeReadiness(PullRequest $pullRequest): array
    {
        $hasConflicts = ! $this->canMerge($pullRequest);
        $protectionViolations = $this->branchProtection->checkMergeRequirements($pullRequest);

        return [
            'mergeable'             => ! $hasConflicts && empty($protectionViolations) && ! $pullRequest->isDraft(),
            'conflicts'             => $hasConflicts,
            'protection_violations' => $protectionViolations,
            'is_draft'              => $pullRequest->isDraft(),
        ];
    }

    /**
     * Load a PR template from the repository's default branch.
     * Looks for `.crucible/pull_request_template.md` in the repo.
     */
    public function loadTemplate(Repository $repository): ?string
    {
        $ref = $repository->default_branch ?? 'main';

        return $this->git->readFile($repository, $ref, '.crucible/pull_request_template.md');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Fire-and-forget Forge notification for a PR state change.
     * Passes the acting User so ForgeService can use their OAuth token.
     */
    private function notifyForgeOfPr(PullRequest $pr, string $state, User $actor): void
    {
        try {
            $pr->loadMissing(['repository.organization']);
            $org  = $pr->repository->organization;
            $repo = $pr->repository;

            $url = route('repositories.pull-requests.show', [$org, $repo, $pr]);

            $this->forge->linkPrToIssue($pr->forge_issue_key, [
                'number' => $pr->number,
                'title'  => $pr->title,
                'state'  => $state,
                'url'    => $url,
            ], $actor);
        } catch (\Throwable $e) {
            Log::warning('[PullRequestService] Forge PR notification failed', [
                'pr'    => $pr->id,
                'issue' => $pr->forge_issue_key,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
