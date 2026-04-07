<?php

namespace App\Enums;

enum VcsType: string
{
    case Git = 'git';
    case Svn = 'svn';

    public function label(): string
    {
        return match($this) {
            VcsType::Git => 'Git',
            VcsType::Svn => 'SVN',
        };
    }
}
