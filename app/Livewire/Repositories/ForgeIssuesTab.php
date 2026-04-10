<?php

namespace App\Livewire\Repositories;

use App\Models\Repository;
use App\Services\ForgeService;
use Livewire\Component;

class ForgeIssuesTab extends Component
{
    public Repository $repository;

    public string $search = '';
    public string $statusFilter = 'open';
    public int $page = 1;
    public int $lastPage = 1;
    public int $total = 0;

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function updatedStatusFilter(): void
    {
        $this->page = 1;
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function nextPage(): void
    {
        if ($this->page < $this->lastPage) {
            $this->page++;
        }
    }

    public function render(ForgeService $forge)
    {
        $integration = $this->repository->forgeIntegration;
        $issues = [];
        $forgeProjectUrl = null;

        if ($integration?->is_active && $integration->forge_project_id) {
            $user = auth()->user();
            $forgeUserId = $user?->forge_user_id;

            $filters = array_filter([
                'q' => $this->search !== '' ? $this->search : null,
                'status' => $this->statusFilter !== 'all' ? $this->statusFilter : null,
            ]);

            $result = $forge->getProjectIssues(
                $integration->forge_project_id,
                $forgeUserId,
                $filters,
                $this->page,
                25,
            );

            $issues = $result['data'];
            $this->lastPage = $result['last_page'];
            $this->total = $result['total'];

            $forgeProjectUrl = rtrim($integration->forge_url ?: config('crucible.forge.url'), '/');
        }

        return view('livewire.repositories.forge-issues-tab', [
            'issues' => $issues,
            'forgeProjectUrl' => $forgeProjectUrl,
            'integration' => $integration,
        ]);
    }
}
