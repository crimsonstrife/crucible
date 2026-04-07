<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BranchProtectionRule extends BaseModel
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'repository_id',
        'pattern',
        'require_pull_request',
        'required_approvals',
        'require_status_checks',
        'required_status_checks',
        'restrict_push_to_roles',
        'allow_force_push',
        'allow_deletion',
    ];

    protected $casts = [
        'require_pull_request'   => 'boolean',
        'required_approvals'     => 'integer',
        'require_status_checks'  => 'boolean',
        'required_status_checks' => 'array',
        'restrict_push_to_roles' => 'array',
        'allow_force_push'       => 'boolean',
        'allow_deletion'         => 'boolean',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * Check whether this rule matches a given branch name.
     * Supports glob patterns (e.g. "release/*") and exact names.
     */
    public function matches(string $branchName): bool
    {
        return fnmatch($this->pattern, $branchName, FNM_CASEFOLD);
    }
}
