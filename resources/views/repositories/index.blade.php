<x-app-layout>
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item">
                    <a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a>
                </li>
                <li class="breadcrumb-item active">Repositories</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 fw-bold mb-1">{{ $organization->name }} Repositories</h1>
                <p class="text-muted mb-0">Browse repositories for this organization.</p>
            </div>
            <div class="col-auto">
                <a href="{{ route('repositories.create', $organization) }}" class="btn btn-primary d-inline-flex align-items-center gap-1">
                    <x-octicon name="repo" />
                    <span>New Repository</span>
                </a>
            </div>
        </div>

        <livewire:repositories.repository-list :organization="$organization" />
    </div>
</x-app-layout>
