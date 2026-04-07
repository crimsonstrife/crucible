<x-app-layout>
    <div class="container">
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 fw-bold">Dashboard</h1>
                <p class="text-muted">Welcome back, {{ Auth::user()->name }}</p>
            </div>
            <div class="col-auto">
                <a href="{{ route('organizations.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>New Organization
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <livewire:organizations.organization-list />
            </div>
        </div>
    </div>
</x-app-layout>
