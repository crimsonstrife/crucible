<?php

namespace App\Livewire\Repositories;

use App\Enums\CollaboratorRole;
use App\Models\Repository;
use App\Models\User;
use App\Services\RepositoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class CollaboratorManager extends Component
{
    use AuthorizesRequests;

    public Repository $repository;

    public string $userId = '';

    public string $role = 'read';

    public function addCollaborator(RepositoryService $repositoryService): void
    {
        $this->authorize('manageCollaborators', $this->repository);

        $validated = $this->validate([
            'userId' => ['required', 'uuid'],
            'role' => ['required', 'in:read,write,maintain,admin'],
        ]);

        $user = $this->repository
            ->organization
            ->members()
            ->whereKey($validated['userId'])
            ->firstOrFail();

        $repositoryService->addCollaborator(
            $this->repository,
            $user,
            CollaboratorRole::from($validated['role']),
            auth()->user(),
        );

        $this->reset('userId');
        session()->flash('success', 'Collaborator added.');
    }

    public function removeCollaborator(string $userId, RepositoryService $repositoryService): void
    {
        $this->authorize('manageCollaborators', $this->repository);

        $user = User::query()->findOrFail($userId);

        $repositoryService->removeCollaborator($this->repository, $user, auth()->user());
        session()->flash('success', 'Collaborator removed.');
    }

    public function render()
    {
        $collaborators = $this->repository->collaborators()->orderBy('name')->get();
        $existingIds = $collaborators->pluck('id')->push($this->repository->owner_id)->all();

        $availableUsers = $this->repository
            ->organization
            ->members()
            ->whereNotIn('users.id', $existingIds)
            ->orderBy('name')
            ->get();

        return view('livewire.repositories.collaborator-manager', [
            'availableUsers' => $availableUsers,
            'collaborators' => $collaborators,
            'roles' => CollaboratorRole::cases(),
        ]);
    }
}
