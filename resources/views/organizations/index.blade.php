<x-app-layout>
    <div class="container">
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 fw-bold">Organizations</h1>
            </div>
            <div class="col-auto">
                <a href="{{ route('organizations.create') }}" class="btn btn-primary">
                    New Organization
                </a>
            </div>
        </div>
        <livewire:organizations.organization-list />
    </div>
</x-app-layout>
