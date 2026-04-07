<x-app-layout>
    <x-slot name="header">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ol>
        </nav>
    </x-slot>

    <div class="container" style="max-width: 720px;">

        {{-- General Settings --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0 fw-semibold">Repository Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('repositories.update', [$organization, $repository]) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label fw-medium">Repository Name</label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name', $repository->name) }}"
                               class="form-control @error('name') is-invalid @enderror"
                               required maxlength="100"
                               pattern="[a-zA-Z0-9._\-]+" title="Letters, numbers, dots, underscores, and hyphens only">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-medium">Description</label>
                        <textarea id="description" name="description" rows="2"
                                  class="form-control @error('description') is-invalid @enderror"
                                  maxlength="500">{{ old('description', $repository->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="visibility" class="form-label fw-medium">Visibility</label>
                            <select id="visibility" name="visibility"
                                    class="form-select @error('visibility') is-invalid @enderror">
                                @foreach (\App\Enums\RepositoryVisibility::cases() as $v)
                                    <option value="{{ $v->value }}"
                                        {{ old('visibility', $repository->visibility->value) === $v->value ? 'selected' : '' }}>
                                        {{ $v->label() }} — {{ $v->description() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('visibility')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Viewing and cloning follow the selected visibility.
                                Pushes still require an authorized account for every repository.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="default_branch" class="form-label fw-medium">Default Branch</label>
                            <input type="text" id="default_branch" name="default_branch"
                                   value="{{ old('default_branch', $repository->default_branch) }}"
                                   class="form-control @error('default_branch') is-invalid @enderror"
                                   maxlength="60">
                            @error('default_branch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="lfs_enabled" name="lfs_enabled" value="1"
                                   {{ old('lfs_enabled', $repository->lfs_enabled) ? 'checked' : '' }}>
                            <label class="form-check-label fw-medium" for="lfs_enabled">Enable Git LFS</label>
                        </div>
                        <div class="form-text">Large File Storage for binary assets (textures, audio, models).</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="{{ route('repositories.show', [$organization, $repository]) }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Archive --}}
        @if (! $repository->is_archived)
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0 fw-semibold">Archive Repository</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Archiving marks this repository as read-only. It remains accessible but no new pushes are allowed.
                    You can unarchive it at any time.
                </p>
                <form method="POST" action="{{ route('repositories.archive', [$organization, $repository]) }}">
                    @csrf
                    <button type="submit" class="btn btn-warning ">Archive Repository</button>
                </form>
            </div>
        </div>
        @endif

        {{-- Danger Zone --}}
        <div class="card border-danger shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0 fw-semibold">Danger Zone</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <strong>Delete this repository</strong>
                        <p class="text-muted small mb-0">All data including commits and LFS objects will be permanently deleted.</p>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm"
                            data-bs-toggle="modal" data-bs-target="#deleteRepoModal">
                        Delete Repository
                    </button>
                </div>
            </div>
        </div>

    </div>

    {{-- Delete Confirmation Modal --}}
    <div class="modal fade" id="deleteRepoModal" tabindex="-1" aria-labelledby="deleteRepoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteRepoModalLabel">Delete Repository</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete <strong>{{ $organization->slug }}/{{ $repository->name }}</strong>?</p>
                    <p class="text-muted small">This deletes all commits, branches, LFS objects, and file locks. This cannot be undone.</p>
                    <div class="mb-2">
                        <label class="form-label small fw-medium">Type <code>{{ $repository->name }}</code> to confirm:</label>
                        <input type="text" id="confirmName" class="form-control form-control-sm"
                               placeholder="{{ $repository->name }}" oninput="checkConfirm(this)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="{{ route('repositories.destroy', [$organization, $repository]) }}">
                        @csrf
                        @method('DELETE')
                        <button id="deleteBtn" type="submit" class="btn btn-danger" disabled>Delete Repository</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        function checkConfirm(input) {
            document.getElementById('deleteBtn').disabled = input.value !== '{{ $repository->name }}';
        }
    </script>
</x-app-layout>
