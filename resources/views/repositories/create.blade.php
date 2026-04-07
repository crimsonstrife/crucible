<x-app-layout>
    <x-slot name="header">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item active">New Repository</li>
            </ol>
        </nav>
    </x-slot>

    <div class="container" style="max-width: 680px;">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0 fw-semibold">Create Repository</h5>
                <p class="text-muted small mb-0 mt-1">Under <strong>{{ $organization->name }}</strong></p>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('repositories.store', $organization) }}">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label fw-medium">Repository Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}"
                               class="form-control @error('name') is-invalid @enderror"
                               placeholder="my-game-project" required autofocus maxlength="100"
                               pattern="[a-zA-Z0-9._\-]+" title="Letters, numbers, dots, underscores, and hyphens only">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Only letters, numbers, dots, underscores, and hyphens.</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-medium">Description</label>
                        <textarea id="description" name="description" rows="2"
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Short description of this repository" maxlength="500">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="vcs_type" class="form-label fw-medium">VCS Type <span class="text-danger">*</span></label>
                            <select id="vcs_type" name="vcs_type"
                                    class="form-select @error('vcs_type') is-invalid @enderror" required>
                                <option value="git" {{ old('vcs_type', 'git') === 'git' ? 'selected' : '' }}>Git</option>
                                <option value="svn" {{ old('vcs_type') === 'svn' ? 'selected' : '' }}>SVN</option>
                            </select>
                            @error('vcs_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="visibility" class="form-label fw-medium">Visibility <span class="text-danger">*</span></label>
                            <select id="visibility" name="visibility"
                                    class="form-select @error('visibility') is-invalid @enderror" required>
                                @foreach (\App\Enums\RepositoryVisibility::cases() as $visibility)
                                    <option value="{{ $visibility->value }}"
                                        {{ old('visibility', 'private') === $visibility->value ? 'selected' : '' }}>
                                        {{ $visibility->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('visibility')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                <strong>Public</strong>: anyone can view and clone.
                                <strong>Internal</strong>: organization members can view and clone.
                                <strong>Private</strong>: only authorized users can view and clone.
                                Pushes always require an authorized account.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="default_branch" class="form-label fw-medium">Default Branch</label>
                        <input type="text" id="default_branch" name="default_branch"
                               value="{{ old('default_branch', 'main') }}"
                               class="form-control @error('default_branch') is-invalid @enderror"
                               placeholder="main" maxlength="60">
                        @error('default_branch')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="lfs_enabled" name="lfs_enabled" value="1"
                                   {{ old('lfs_enabled', true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-medium" for="lfs_enabled">
                                Enable Git LFS
                            </label>
                        </div>
                        <div class="form-text">Recommended for repositories containing large binary assets (textures, audio, models).</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create Repository</button>
                        <a href="{{ route('organizations.show', $organization) }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
