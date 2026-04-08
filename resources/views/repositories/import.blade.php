<x-app-layout>
    <x-slot name="header">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item active">Import Repository</li>
            </ol>
        </nav>
    </x-slot>

    <div class="container" style="max-width: 680px;">

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
                <x-octicon name="repo-clone" size="20" class="text-body-secondary flex-shrink-0" />
                <span>Import a Repository</span>
            </h1>
            <p class="text-muted mb-0">
                Clone any publicly accessible git repository into <strong>{{ $organization->name }}</strong>.
                For private repositories, embed a personal access token in the URL.
            </p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('repositories.import.store', $organization) }}">
            @csrf

            {{-- Remote URL --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Source Repository</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="remote_url" class="form-label fw-medium">
                            Remote URL <span class="text-danger">*</span>
                        </label>
                        <input type="url" class="form-control font-monospace @error('remote_url') is-invalid @enderror"
                               id="remote_url" name="remote_url"
                               value="{{ old('remote_url') }}"
                               placeholder="https://github.com/owner/repo.git"
                               autofocus>
                        @error('remote_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            For <strong>private repos</strong>, include a personal access token:<br>
                            <code>https://&lt;token&gt;@github.com/owner/repo.git</code><br>
                            For GitHub <strong>fine-grained PATs</strong>, either format works:<br>
                            <code>https://x-access-token:&lt;token&gt;@github.com/owner/repo.git</code><br>
                            The URL (including any token) is stored encrypted.
                        </div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="auto_sync" name="auto_sync" value="1"
                               {{ old('auto_sync') ? 'checked' : '' }}>
                        <label class="form-check-label" for="auto_sync">
                            Enable manual sync
                            <small class="text-muted d-block">
                                Stores the remote URL so you can sync later via the repository page.
                            </small>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Repository settings --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Repository Settings</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-medium">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                               id="name" name="name"
                               value="{{ old('name') }}"
                               placeholder="my-imported-repo">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Letters, numbers, hyphens, underscores, and dots only.</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-medium">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                                  id="description" name="description"
                                  rows="2"
                                  placeholder="Optional description">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Visibility <span class="text-danger">*</span></label>
                        <div class="d-flex flex-column gap-2">
                            @foreach (['private' => ['Private', 'Only you and explicit collaborators can access.'], 'internal' => ['Internal', 'All authenticated users can read.'], 'public' => ['Public', 'Anyone, including anonymous visitors, can read.']] as $value => [$label, $hint])
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="visibility"
                                           id="visibility_{{ $value }}" value="{{ $value }}"
                                           {{ old('visibility', 'private') === $value ? 'checked' : '' }}>
                                    <label class="form-check-label" for="visibility_{{ $value }}">
                                        <span class="fw-medium">{{ $label }}</span>
                                        <small class="text-muted d-block">{{ $hint }}</small>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('visibility')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="lfs_enabled" name="lfs_enabled" value="1"
                                   {{ old('lfs_enabled') ? 'checked' : '' }}>
                            <label class="form-check-label" for="lfs_enabled">
                                Enable Git LFS
                                <small class="text-muted d-block">Required if the upstream repo uses LFS-tracked files.</small>
                            </label>
                        </div>
                    </div>

                    {{-- Hidden defaults --}}
                    <input type="hidden" name="vcs_type" value="git">
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1">
                    <x-octicon name="repo-clone" />
                    <span>Import Repository</span>
                </button>
                <a href="{{ route('organizations.show', $organization) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>

        <div class="mt-4 p-3 bg-info-subtle rounded border small text-muted">
            <strong>How it works:</strong>
            The repository is cloned in the background using <code>git clone --bare</code>.
            You will be taken to the repository page immediately — it will show
            <em>initializing</em> until the clone completes.
            For large repositories this may take a few minutes.
        </div>

    </div>
</x-app-layout>
