<?php

namespace App\Http\Controllers;

use App\Enums\CollaboratorRole;
use App\Http\Requests\Repositories\AddCollaboratorRequest;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use App\Services\RepositoryService;

class CollaboratorController extends Controller
{
    public function __construct(protected RepositoryService $service) {}

    public function store(AddCollaboratorRequest $request, Organization $organization, Repository $repository)
    {
        $user = User::findOrFail($request->user_id);
        $role = CollaboratorRole::from($request->role);
        $this->service->addCollaborator($repository, $user, $role, auth()->user());
        return back()->with('success', 'Collaborator added.');
    }

    public function destroy(Organization $organization, Repository $repository, User $user)
    {
        $this->authorize('manageCollaborators', $repository);
        $this->service->removeCollaborator($repository, $user, auth()->user());
        return back()->with('success', 'Collaborator removed.');
    }
}
