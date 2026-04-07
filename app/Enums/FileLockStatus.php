<?php

namespace App\Enums;

enum FileLockStatus: string
{
    case Locked   = 'locked';
    case Unlocked = 'unlocked';
}
