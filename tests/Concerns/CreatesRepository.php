<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesRepository
{
    /**
     * Create an owner + organization + bare-DB repository (no on-disk git).
     *
     * @return array{0: User, 1: Organization, 2: Repository}
     */
    protected function createRepositoryRecord(string $visibility = 'private'): array
    {
        $owner = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Studio '.Str::random(6),
            'slug' => 'studio-'.Str::lower(Str::random(8)),
        ]);

        $organization->members()->attach($owner, ['role' => 'owner']);

        $repository = $organization->repositories()->create([
            'owner_id' => $owner->id,
            'name' => 'Repo '.Str::random(6),
            'slug' => 'repo-'.Str::lower(Str::random(8)),
            'vcs_type' => 'git',
            'visibility' => $visibility,
            'default_branch' => 'main',
            'lfs_enabled' => false,
        ]);

        return [$owner, $organization, $repository];
    }
}
