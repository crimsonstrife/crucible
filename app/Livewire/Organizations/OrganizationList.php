<?php

namespace App\Livewire\Organizations;

use Livewire\Component;

class OrganizationList extends Component
{
    public string $search = '';

    public function render()
    {
        $organizations = auth()->user()
            ->organizations()
            ->withCount(['members', 'repositories'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->get();

        return view('livewire.organizations.organization-list', compact('organizations'));
    }
}
