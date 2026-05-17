<x-app-layout>
    <div class="container py-4">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.releases.index', [$organization, $repository]) }}">Releases</a></li>
                <li class="breadcrumb-item active">Draft new release</li>
            </ol>
        </nav>

        <h1 class="h4 fw-bold mb-4">Draft a new release</h1>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <livewire:repositories.release-form
            :repository="$repository"
            :preselected-tag="$preselectedTag" />
    </div>
</x-app-layout>
