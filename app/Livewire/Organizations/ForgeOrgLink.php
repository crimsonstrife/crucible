<?php

namespace App\Livewire\Organizations;

use App\Models\Organization;
use App\Services\ForgeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ForgeOrgLink extends Component
{
    use AuthorizesRequests;

    public Organization $organization;

    public string $search = '';
    public ?string $selectedOrgId = null;
    public ?string $selectedOrgName = null;
    public ?string $selectedOrgSlug = null;

    /** @var array<int, array<string, mixed>> */
    public array $forgeOrgs = [];
    public bool $loading = false;
    public ?string $error = null;

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->selectedOrgId = $organization->forge_org_id;
        $this->selectedOrgSlug = $organization->forge_org_slug;
    }

    public function searchForgeOrgs(): void
    {
        $this->authorize('update', $this->organization);

        $this->loading = true;
        $this->error = null;
        $this->forgeOrgs = [];

        $forge = app(ForgeService::class);

        if (! $forge->isConfigured()) {
            $this->error = 'Forge integration is not configured.';
            $this->loading = false;
            return;
        }

        $forgeUserId = auth()->user()?->forge_user_id;
        if (! $forgeUserId) {
            $this->error = 'Your account is not linked to Forge via SSO. Sign in with Forge first.';
            $this->loading = false;
            return;
        }

        $this->forgeOrgs = $forge->getOrganizations($forgeUserId);
        $this->loading = false;
    }

    public function selectOrg(string $orgId, string $orgName, string $orgSlug): void
    {
        $this->authorize('update', $this->organization);

        $this->selectedOrgId = $orgId;
        $this->selectedOrgName = $orgName;
        $this->selectedOrgSlug = $orgSlug;
    }

    public function saveLink(): void
    {
        $this->authorize('update', $this->organization);

        $this->organization->update([
            'forge_org_id' => $this->selectedOrgId,
            'forge_org_slug' => $this->selectedOrgSlug,
        ]);

        $this->dispatch('notify', body: 'Forge organization linked.');
    }

    public function unlinkOrg(): void
    {
        $this->authorize('update', $this->organization);

        $this->organization->update([
            'forge_org_id' => null,
            'forge_org_slug' => null,
        ]);

        $this->selectedOrgId = null;
        $this->selectedOrgName = null;
        $this->selectedOrgSlug = null;

        $this->dispatch('notify', body: 'Forge organization unlinked.');
    }

    public function render()
    {
        return view('livewire.organizations.forge-org-link');
    }
}
