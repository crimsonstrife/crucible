<x-app-layout>
    <x-slot name="header">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ol>
        </nav>
    </x-slot>

    <div class="container" style="max-width: 680px;">

        {{-- General Settings --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0 fw-semibold">Organization Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('organizations.update', $organization) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $organization->name) }}"
                               class="form-control @error('name') is-invalid @enderror"
                               required maxlength="100">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-medium">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="form-control @error('description') is-invalid @enderror"
                                  maxlength="500">{{ old('description', $organization->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="website_url" class="form-label fw-medium">Website URL</label>
                        <input type="url" id="website_url" name="website_url"
                               value="{{ old('website_url', $organization->website_url) }}"
                               class="form-control @error('website_url') is-invalid @enderror"
                               placeholder="https://example.com">
                        @error('website_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="{{ route('organizations.show', $organization) }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Forge Organization Linking --}}
        @if (config('crucible.forge.enabled'))
            <livewire:organizations.forge-org-link :organization="$organization" />
        @endif

        {{-- Danger Zone --}}
        <div class="card border-danger shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0 fw-semibold">Danger Zone</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <strong>Delete this organization</strong>
                        <p class="text-muted small mb-0">Once deleted, all repositories and data will be permanently removed.</p>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm"
                            data-bs-toggle="modal" data-bs-target="#deleteOrgModal">
                        Delete Organization
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div class="modal fade" id="deleteOrgModal" tabindex="-1" aria-labelledby="deleteOrgModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteOrgModalLabel">Delete Organization</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete <strong>{{ $organization->name }}</strong>?</p>
                    <p class="text-muted small">This will delete all repositories belonging to this organization. This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="{{ route('organizations.destroy', $organization) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete Organization</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
