<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationMemberController extends Controller
{
    public function __construct(protected OrganizationService $service) {}

    /**
     * Add a new member to the organization.
     */
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageMember', $organization);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role'  => ['required', 'in:member,admin'],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        // Prevent adding if already a member.
        if ($organization->members()->where('users.id', $user->id)->exists()) {
            return back()->withErrors(['email' => 'That user is already a member of this organization.']);
        }

        $this->service->addMember($organization, $user, $data['role']);

        return back()->with('success', "{$user->name} added as {$data['role']}.");
    }

    /**
     * Change the role of an existing member.
     */
    public function update(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $this->authorize('manageMember', $organization);

        $data = $request->validate([
            'role' => ['required', 'in:member,admin,owner'],
        ]);

        // Prevent removing the last owner.
        if ($data['role'] !== 'owner') {
            $ownerCount = $organization->members()->wherePivot('role', 'owner')->count();
            $isCurrentlyOwner = $organization->members()
                ->where('users.id', $user->id)
                ->wherePivot('role', 'owner')
                ->exists();

            if ($isCurrentlyOwner && $ownerCount <= 1) {
                return back()->withErrors(['role' => 'Cannot demote the last owner.']);
            }
        }

        $this->service->changeRole($organization, $user, $data['role']);

        return back()->with('success', "{$user->name}'s role updated to {$data['role']}.");
    }

    /**
     * Remove a member from the organization.
     */
    public function destroy(Organization $organization, User $user): RedirectResponse
    {
        $this->authorize('manageMember', $organization);

        // Prevent removing the last owner.
        $isLastOwner = $organization->members()
                ->where('users.id', $user->id)
                ->wherePivot('role', 'owner')
                ->exists()
            && $organization->members()->wherePivot('role', 'owner')->count() <= 1;

        if ($isLastOwner) {
            return back()->withErrors(['member' => 'Cannot remove the last owner.']);
        }

        $this->service->removeMember($organization, $user);

        return back()->with('success', "{$user->name} removed from the organization.");
    }
}
