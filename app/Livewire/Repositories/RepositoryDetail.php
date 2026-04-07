<?php

namespace App\Livewire\Repositories;

use App\Contracts\RepositoryDriverInterface;
use App\Models\Repository;
use App\Services\RepositoryBrowserService;
use Livewire\Component;

class RepositoryDetail extends Component
{
    public Repository $repository;

    public function render(RepositoryDriverInterface $driver, RepositoryBrowserService $browser)
    {
        $collaborators    = $this->repository->collaborators()->get();
        $fileLocks        = $this->repository->fileLocks()->with('lockedBy')->latest('locked_at')->get();
        $openPrCount      = $this->repository->pullRequests()->where('status', 'open')->count();
        $forgeIntegration = $this->repository->forgeIntegration;
        $browserSnapshot = $browser->snapshot(
            $this->repository,
            request()->query('ref'),
            request()->query('path'),
            request()->query('preview'),
        );

        // Pull live data from the git driver (safe — returns defaults if repo not on disk yet)
        $exists = $driver->exists($this->repository);
        $branches = $exists ? $driver->branches($this->repository) : [];
        $defaultBranch = $exists ? $driver->defaultBranch($this->repository) : ($this->repository->default_branch ?? 'main');
        $diskSize = $exists ? $driver->size($this->repository) : 0;

        return view('livewire.repositories.repository-detail', compact(
            'collaborators',
            'fileLocks',
            'openPrCount',
            'forgeIntegration',
            'browserSnapshot',
            'exists',
            'branches',
            'defaultBranch',
            'diskSize',
        ));
    }
}
