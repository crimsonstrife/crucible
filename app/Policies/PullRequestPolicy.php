<?php

namespace App\Policies;

use App\Enums\CollaboratorRole;
use App\Models\PullRequest;
use App\Models\PullRequestComment;
use App\Models\User;

class PullRequestPolicy
{
    /**
     * Super-admins bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasPermissionTo('is-super-admin') ? true : null;
    }

    /**
     * Anyone who can view the repository can view its pull requests.
     */
    public function view(User $user, PullRequest $pullRequest): bool
    {
        return $user->can('view', $pullRequest->repository);
    }

    /**
     * Anyone with push access to the repository can open a PR.
     */
    public function create(User $user, PullRequest $pullRequest): bool
    {
        return $user->can('push', $pullRequest->repository);
    }

    /**
     * Merge requires Maintain-level access (or ownership).
     * The PR must also be open — business logic enforced in the service.
     */
    public function merge(User $user, PullRequest $pullRequest): bool
    {
        $repo = $pullRequest->repository;

        if ($user->id === $repo->owner_id) {
            return true;
        }

        $role = $repo->collaboratorRoleFor($user);

        return $role !== null && $role->rank() >= CollaboratorRole::Maintain->rank();
    }

    /**
     * The PR author, or anyone with Maintain access, can close a PR.
     */
    public function close(User $user, PullRequest $pullRequest): bool
    {
        if ($user->id === $pullRequest->author_id) {
            return true;
        }

        return $this->merge($user, $pullRequest);
    }

    /**
     * Same rules as close for reopening.
     */
    public function reopen(User $user, PullRequest $pullRequest): bool
    {
        return $this->close($user, $pullRequest);
    }

    /**
     * Anyone who can view the PR can leave a comment.
     */
    public function comment(User $user, PullRequest $pullRequest): bool
    {
        return $this->view($user, $pullRequest);
    }

    /**
     * A comment can be edited by its author or a Maintain-level collaborator.
     */
    public function updateComment(User $user, PullRequestComment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    /**
     * A comment can be deleted by its author or anyone with Maintain access.
     */
    public function deleteComment(User $user, PullRequestComment $comment): bool
    {
        if ($user->id === $comment->user_id) {
            return true;
        }

        $repo = $comment->pullRequest->repository;

        if ($user->id === $repo->owner_id) {
            return true;
        }

        $role = $repo->collaboratorRoleFor($user);

        return $role !== null && $role->rank() >= CollaboratorRole::Maintain->rank();
    }
}
