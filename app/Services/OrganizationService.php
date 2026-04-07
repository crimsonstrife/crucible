<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Str;

class OrganizationService
{
    public function create(User $owner, array $data): Organization
    {
        $org = Organization::create([
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'is_personal' => $data['is_personal'] ?? false,
        ]);

        $org->members()->attach($owner->id, ['role' => 'owner']);

        return $org;
    }

    public function addMember(Organization $org, User $user, string $role = 'member'): OrganizationMember
    {
        $org->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);

        return OrganizationMember::where('organization_id', $org->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function removeMember(Organization $org, User $user): void
    {
        $org->members()->detach($user->id);
    }

    public function changeRole(Organization $org, User $user, string $role): void
    {
        $org->members()->updateExistingPivot($user->id, ['role' => $role]);
    }

    public function transferOwnership(Organization $org, User $newOwner, User $actor): void
    {
        // Demote current owner(s)
        $org->members()
            ->wherePivot('role', 'owner')
            ->each(fn ($u) => $org->members()->updateExistingPivot($u->id, ['role' => 'admin']));

        // Promote new owner
        $org->members()->syncWithoutDetaching([
            $newOwner->id => ['role' => 'owner'],
        ]);
    }
}
