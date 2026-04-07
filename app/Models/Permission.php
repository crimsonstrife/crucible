<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Thin wrapper so config/permission.php can reference App\Models\Permission.
 * Permissions use standard auto-increment integer PKs — no UUID needed here.
 */
class Permission extends SpatiePermission
{
    //
}
