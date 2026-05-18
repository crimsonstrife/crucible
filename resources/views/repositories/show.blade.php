<x-app-layout>
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item">
                    <a href="{{ route('organizations.show', $repository->organization) }}">
                        {{ $repository->organization->name }}
                    </a>
                </li>
                <li class="breadcrumb-item active">{{ $repository->name }}</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col">
                <div class="d-flex align-items-center gap-2">
                    <x-octicon name="repo" size="20" class="text-body-secondary flex-shrink-0" />
                    <h1 class="h3 fw-bold mb-0">{{ $repository->name }}</h1>
                    <span class="badge bg-secondary badge-vcs">{{ $repository->vcs_type->label() }}</span>
                    @if ($repository->lfs_enabled)
                        <span class="lfs-badge">LFS</span>
                    @endif
                    @if ($repository->is_archived)
                        <span class="badge bg-warning ">Archived</span>
                    @endif
                </div>
                @if ($repository->description)
                    <p class="text-muted mt-1">{{ $repository->description }}</p>
                @endif
            </div>
        </div>

        <livewire:repositories.repository-detail :repository="$repository" />

        @can('manageCollaborators', $repository)
            <livewire:repositories.collaborator-manager :repository="$repository" />
        @endcan

        @can('manageReleases', $repository)
            <livewire:repositories.release-tokens-manager :repository="$repository" />
        @endcan
    </div>
</x-app-layout>
