<?php

namespace App\Enums;

enum MergeStrategy: string
{
    case MergeCommit  = 'merge_commit';
    case Squash       = 'squash';
    case Rebase       = 'rebase';
    case FastForward  = 'fast_forward';

    public function label(): string
    {
        return match ($this) {
            self::MergeCommit => 'Create a merge commit',
            self::Squash      => 'Squash and merge',
            self::Rebase      => 'Rebase and merge',
            self::FastForward => 'Fast-forward',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MergeCommit => 'All commits will be added to the target branch via a merge commit.',
            self::Squash      => 'All commits will be combined into a single commit on the target branch.',
            self::Rebase      => 'All commits will be rebased onto the target branch.',
            self::FastForward => 'The target branch pointer will be moved forward (only works when there are no divergent commits).',
        };
    }
}
