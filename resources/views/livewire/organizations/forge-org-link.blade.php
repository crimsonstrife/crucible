<div>
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold">Forge Organization</h5>
            @if ($organization->forge_org_id)
                <span class="badge bg-success">Linked</span>
            @endif
        </div>
        <div class="card-body">
            @if ($organization->forge_org_id)
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="fw-medium">{{ $organization->forge_org_slug }}</span>
                        <small class="text-muted ms-2">({{ $organization->forge_org_id }})</small>
                    </div>
                    <button class="btn btn-sm btn-outline-danger" wire:click="unlinkOrg"
                            wire:confirm="Are you sure you want to unlink this Forge organization?">
                        Unlink
                    </button>
                </div>
            @else
                <p class="text-muted small mb-3">
                    Link this Crucible organization to a Forge organization to enable cross-platform features
                    like org-scoped project-repository linking.
                </p>

                @if ($error)
                    <div class="alert alert-warning small py-2">{{ $error }}</div>
                @endif

                @if (count($forgeOrgs) === 0 && ! $loading)
                    <button class="btn btn-sm btn-outline-primary" wire:click="searchForgeOrgs">
                        Load Forge Organizations
                    </button>
                @endif

                @if ($loading)
                    <div class="d-flex align-items-center gap-2 text-muted small">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <span>Loading...</span>
                    </div>
                @endif

                @if (count($forgeOrgs) > 0)
                    <div class="list-group mb-3">
                        @foreach ($forgeOrgs as $org)
                            <button type="button"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                                           {{ $selectedOrgId === ($org['id'] ?? '') ? 'active' : '' }}"
                                    wire:click="selectOrg('{{ $org['id'] }}', '{{ $org['name'] }}', '{{ $org['slug'] }}')">
                                <span>{{ $org['name'] }} <small class="opacity-75">({{ $org['slug'] }})</small></span>
                                @if ($selectedOrgId === ($org['id'] ?? ''))
                                    <x-octicon name="check" size="16" />
                                @endif
                            </button>
                        @endforeach
                    </div>

                    @if ($selectedOrgId)
                        <button class="btn btn-sm btn-primary" wire:click="saveLink">
                            Link Organization
                        </button>
                    @endif
                @endif
            @endif
        </div>
    </div>
</div>
