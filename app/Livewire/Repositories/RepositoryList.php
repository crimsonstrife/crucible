<?php

namespace App\Livewire\Repositories;

use App\Models\Organization;
use Livewire\Component;

class RepositoryList extends Component
{
    public Organization $organization;
    public string $search = '';

    public function render()
    {
        $repositories = $this->organization
            ->repositories()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withCount(['fileLocks', 'lfsObjects'])
            ->latest()
            ->get();

        return view('livewire.repositories.repository-list', compact('repositories'));
    }
}
