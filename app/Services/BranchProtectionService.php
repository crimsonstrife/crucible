<?php

namespace App\Services;

use App\Enums\CommitStatusState;
use App\Models\BranchProtectionRule;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\User;

class BranchProtectionService
{
    /**
     * Get all protection rules that match a branch name.
     *
     * @return \Illuminate\Support\Collection<int, BranchProtectionRule>
     */
    public function rulesFor(Repository $repository, string $branchName): \Illuminate\Support\Collection
    {
        return $repository->branchProtectionRules()
            ->get()
            ->filter(fn (BranchProtectionRule $rule) => $rule->matches($branchName));
    }

    /**
     * Check whether a user can push directly to the given branch.
     * Returns an array of violation reasons, empty if push is allowed.
     */
    public function canPush(Repository $repository, string $branchName, ?User $user = null): array
    {
        $violations = [];

        foreach ($this->rulesFor($repository, $branchName) as $rule) {
            // Require pull request — no direct pushes
            if ($rule->require_pull_request) {
                $violations[] = "Branch '{$branchName}' requires a pull request (matched rule: {$rule->pattern}).";
            }

            // Role-based push restriction
            if (! empty($rule->restrict_push_to_roles) && $user) {
                $userRole = $repository->collaboratorRoleFor($user);
                $allowed = false;

                if ($user->id === $repository->owner_id) {
                    $allowed = true; // Owner always allowed
                } elseif ($userRole) {
                    $allowed = in_array($userRole->value, $rule->restrict_push_to_roles, true);
                }

                if (! $allowed) {
                    $violations[] = "Your role does not allow direct pushes to '{$branchName}'.";
                }
            }
        }

        return $violations;
    }

    /**
     * Check whether a pull request meets the branch protection requirements for merge.
     * Returns an array of blocking reasons, empty if merge is allowed.
     */
    public function checkMergeRequirements(PullRequest $pullRequest): array
    {
        $repository = $pullRequest->repository;
        $targetBranch = $pullRequest->target_branch;
        $violations = [];

        foreach ($this->rulesFor($repository, $targetBranch) as $rule) {
            // Required approvals check
            if ($rule->required_approvals > 0) {
                $approvalCount = $pullRequest->approvalCount();

                if ($approvalCount < $rule->required_approvals) {
                    $violations[] = "Requires {$rule->required_approvals} approval(s), has {$approvalCount}.";
                }
            }

            // Check for unresolved "changes requested" reviews
            if ($rule->required_approvals > 0 && $pullRequest->hasChangesRequested()) {
                $violations[] = 'A reviewer has requested changes that must be addressed.';
            }

            // Status checks
            if ($rule->require_status_checks && ! empty($rule->required_status_checks)) {
                $headSha = $pullRequest->head_sha;

                if ($headSha) {
                    $statusViolations = $this->checkStatusChecks(
                        $repository,
                        $headSha,
                        $rule->required_status_checks,
                    );
                    array_push($violations, ...$statusViolations);
                }
            }
        }

        return $violations;
    }

    /**
     * Check whether required status checks pass for a given commit.
     *
     * @param  string[]  $requiredContexts  e.g. ["ci/build", "ci/tests"]
     * @return string[]  Violation messages
     */
    public function checkStatusChecks(Repository $repository, string $sha, array $requiredContexts): array
    {
        $violations = [];

        $statuses = $repository->commitStatuses()
            ->where('sha', $sha)
            ->get()
            ->keyBy('context');

        foreach ($requiredContexts as $context) {
            $status = $statuses->get($context);

            if (! $status) {
                $violations[] = "Required status check '{$context}' has not reported yet.";
            } elseif (! $status->state->isPassed()) {
                $violations[] = "Status check '{$context}' is {$status->state->value} (must be success).";
            }
        }

        return $violations;
    }

    /**
     * Check whether force push is allowed on a branch.
     */
    public function allowsForcePush(Repository $repository, string $branchName): bool
    {
        $rules = $this->rulesFor($repository, $branchName);

        if ($rules->isEmpty()) {
            return true; // No protection rules — allow
        }

        // All matching rules must allow force push
        return $rules->every(fn (BranchProtectionRule $rule) => $rule->allow_force_push);
    }

    /**
     * Check whether branch deletion is allowed.
     */
    public function allowsDeletion(Repository $repository, string $branchName): bool
    {
        $rules = $this->rulesFor($repository, $branchName);

        if ($rules->isEmpty()) {
            return true;
        }

        return $rules->every(fn (BranchProtectionRule $rule) => $rule->allow_deletion);
    }
}
