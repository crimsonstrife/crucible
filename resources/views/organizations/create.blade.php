<x-app-layout>
    <x-slot name="header">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item active">New Organization</li>
            </ol>
        </nav>
    </x-slot>

    <div class="container" style="max-width: 680px;">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0 fw-semibold">Create Organization</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('organizations.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}"
                               class="form-control @error('name') is-invalid @enderror"
                               placeholder="e.g. Acme Studios" required autofocus maxlength="100">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Unique name for your organization. Use letters, numbers, spaces, and hyphens.</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-medium">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Brief description of your organization" maxlength="500">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="website_url" class="form-label fw-medium">Website URL</label>
                        <input type="url" id="website_url" name="website_url" value="{{ old('website_url') }}"
                               class="form-control @error('website_url') is-invalid @enderror"
                               placeholder="https://example.com">
                        @error('website_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create Organization</button>
                        <a href="{{ route('organizations.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
