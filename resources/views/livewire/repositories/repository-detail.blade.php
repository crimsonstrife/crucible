<div>
    @php
        $sshCloneUrl = 'git@' . parse_url(config('app.url'), PHP_URL_HOST)
                     . ':' . $repository->organization->slug . '/' . $repository->slug . '.git';
        $browserUrl = function (?string $path = null, ?string $ref = null, ?string $preview = null) use ($repository, $browserSnapshot): string {
            $parameters = [
                'organization' => $repository->organization,
                'repository' => $repository,
                'ref' => $ref ?? $browserSnapshot['ref'],
            ];

            $resolvedPath = $path ?? $browserSnapshot['current_path'];

            if ($resolvedPath !== '') {
                $parameters['path'] = $resolvedPath;
            }

            $resolvedPreview = $preview ?? ($browserSnapshot['selected_file']['preview_mode'] ?? null);

            if ($resolvedPreview !== null && $resolvedPath !== '') {
                $parameters['preview'] = $resolvedPreview;
            }

            return route('repositories.show', $parameters);
        };
        $listingPath = $browserSnapshot['listing_path'] ?? '';
        $parentPath = $listingPath === ''
            ? null
            : (str_contains($listingPath, '/') ? dirname($listingPath) : '');

        if ($parentPath === '.') {
            $parentPath = '';
        }
    @endphp
    {{-- Not-yet-initialized banner --}}
    @unless ($exists)
        <div class="alert alert-info d-flex align-items-start gap-3 mb-4" role="alert">
            <x-octicon name="hourglass" size="24" class="text-info-emphasis flex-shrink-0 mt-1" />
            <div>
                <strong>Repository initializing&hellip;</strong>
                <p class="mb-0 small">The bare git repository is being created on disk. Refresh in a moment.
                    If this persists, check that <code>CRUCIBLE_REPOS_PATH</code> is writable and the queue worker is running.</p>
            </div>
        </div>
    @endunless

    {{-- LFS sync status banner --}}
    @if ($repository->lfs_sync_status && ! in_array($repository->lfs_sync_status, ['synced', 'skipped']))
        @if (in_array($repository->lfs_sync_status, ['pending', 'fetching', 'importing']))
            <div class="alert alert-info d-flex align-items-start gap-3 mb-4" role="alert" wire:poll.5s>
                <div class="spinner-border spinner-border-sm text-info-emphasis flex-shrink-0 mt-1" role="status">
                    <span class="visually-hidden">Syncing&hellip;</span>
                </div>
                <div>
                    <strong>LFS objects syncing&hellip;</strong>
                    <p class="mb-0 small">
                        @if ($repository->lfs_sync_status === 'pending')
                            Waiting for the LFS import job to start.
                        @elseif ($repository->lfs_sync_status === 'fetching')
                            Fetching LFS objects from the remote repository.
                        @else
                            Importing LFS objects into Crucible storage.
                        @endif
                        Large files may take a while. This banner will disappear when complete.
                    </p>
                </div>
            </div>
        @elseif ($repository->lfs_sync_status === 'failed')
            <div class="alert alert-warning d-flex align-items-start gap-3 mb-4" role="alert">
                <x-octicon name="alert" size="24" class="text-warning-emphasis flex-shrink-0 mt-1" />
                <div>
                    <strong>LFS sync failed</strong>
                    <p class="mb-0 small">Some Git LFS objects could not be fetched from the remote. Check that
                        <code>git-lfs</code> is installed, the remote URL is accessible, and the queue worker is running.
                        Trigger a manual sync to retry.</p>
                </div>
            </div>
        @endif
    @endif

    {{-- Tab navigation --}}
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'code' ? 'active' : '' }} d-inline-flex align-items-center gap-1"
                    wire:click="switchTab('code')" type="button">
                <x-octicon name="code" size="16" />
                <span>Code</span>
            </button>
        </li>
        <li class="nav-item">
            <a class="nav-link d-inline-flex align-items-center gap-1"
               href="{{ route('repositories.pull-requests.index', [$repository->organization, $repository]) }}">
                <x-octicon name="git-pull-request" size="16" />
                <span>Pull Requests</span>
                @if ($openPrCount > 0)
                    <span class="badge bg-success ms-1">{{ $openPrCount }}</span>
                @endif
            </a>
        </li>
        @if ($forgeIntegration?->is_active)
            <li class="nav-item">
                <button class="nav-link {{ $activeTab === 'issues' ? 'active' : '' }} d-inline-flex align-items-center gap-1"
                        wire:click="switchTab('issues')" type="button">
                    <x-octicon name="issue-opened" size="16" />
                    <span>Issues</span>
                </button>
            </li>
        @endif
    </ul>

    @if ($activeTab === 'issues' && $forgeIntegration?->is_active)
        <livewire:repositories.forge-issues-tab :repository="$repository" />
    @else
    <div class="row g-4">

        {{-- Left column: Clone + Branches --}}
        <div class="col-lg-8">

            {{-- Clone URLs --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                        <x-octicon name="repo-clone" class="text-body-secondary" />
                        <span>Clone</span>
                    </h6>
                    @if ($exists)
                        <span class="badge bg-success d-inline-flex align-items-center gap-1">
                            <x-octicon name="dot-fill" size="10" />
                            <span>On disk</span>
                        </span>
                    @else
                        <span class="badge bg-secondary">Pending</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label small text-muted text-uppercase fw-semibold mb-1">HTTPS</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace small"
                                   value="{{ rtrim(config('app.url'), '/') }}/{{ $repository->organization->slug }}/{{ $repository->slug }}.git"
                                   readonly id="cloneHttps">
                            <button class="btn btn-outline-secondary btn-sm" type="button"
                                    onclick="navigator.clipboard.writeText(document.getElementById('cloneHttps').value).then(() => this.textContent='Copied!').catch(() => {})">
                                Copy
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label small text-muted text-uppercase fw-semibold mb-1">SSH</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace small"
                                   value="{{ $sshCloneUrl }}"
                                   readonly id="cloneSsh">
                            <button class="btn btn-outline-secondary btn-sm" type="button"
                                    onclick="navigator.clipboard.writeText(document.getElementById('cloneSsh').value).then(() => this.textContent='Copied!').catch(() => {})">
                                Copy
                            </button>
                        </div>
                    </div>
                    @if ($diskSize > 0)
                        <div class="mt-2">
                            <small class="text-muted">
                                Disk usage: <strong>{{ number_format($diskSize / 1024, 1) }} KB</strong>
                                &middot; Backend: <code>{{ config('crucible.git.backend') }}</code>
                            </small>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Upstream / sync --}}
            @if ($repository->remote_url)
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                            <x-octicon name="repo-forked" class="text-body-secondary" />
                            <span>Upstream Remote</span>
                        </h6>
                        @can('push', $repository)
                            <form method="POST"
                                  action="{{ route('repositories.sync', [$repository->organization, $repository]) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                                    <x-octicon name="sync" />
                                    <span>Sync Now</span>
                                </button>
                            </form>
                        @endcan
                    </div>
                    <div class="card-body small">
                        @php
                            // Strip credentials from the URL before displaying it.
                            $displayUrl = preg_replace('#(https?://)([^@]+@)#', '$1', $repository->remote_url);
                        @endphp
                        <div class="font-monospace text-break mb-2">{{ $displayUrl }}</div>
                        @if ($repository->last_synced_at)
                            <div class="text-muted">
                                Last synced: <strong>{{ $repository->last_synced_at->diffForHumans() }}</strong>
                                <span class="ms-1" title="{{ $repository->last_synced_at->toDateTimeString() }}">
                                    ({{ $repository->last_synced_at->toDateTimeString() }})
                                </span>
                            </div>
                        @else
                            <div class="text-muted">Never synced — Sync Now to fetch upstream changes.</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Quick nav: Tags + Pull Requests --}}
            @if ($exists && $browserSnapshot['has_revision'])
                <div class="d-flex gap-2 mb-4">
                    <a href="{{ route('repositories.tags', [$repository->organization, $repository]) }}"
                       class="btn btn-sm btn-outline-secondary flex-fill text-center d-inline-flex align-items-center justify-content-center gap-1">
                        <x-octicon name="tag" />
                        <span>Tags</span>
                    </a>
                    @if ($repository->lfs_enabled)
                        <a href="{{ route('repositories.lfs.dashboard', [$repository->organization, $repository]) }}"
                           class="btn btn-sm btn-outline-secondary flex-fill text-center d-inline-flex align-items-center justify-content-center gap-1">
                            <x-octicon name="package" />
                            <span>LFS Storage</span>
                        </a>
                    @endif
                    <a href="{{ route('repositories.pull-requests.index', [$repository->organization, $repository]) }}"
                       class="btn btn-sm btn-outline-secondary flex-fill text-center position-relative d-inline-flex align-items-center justify-content-center gap-1">
                        <x-octicon name="git-pull-request" />
                        <span>Pull Requests</span>
                        @if ($openPrCount > 0)
                            <span class="badge bg-success ms-1">{{ $openPrCount }}</span>
                        @endif
                    </a>
                </div>
            @endif

            {{-- Forge integration badge --}}
            @if ($forgeIntegration)
                <div class="card shadow-sm mb-4 border-0">
                    <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
                        <div class="small">
                            <span class="fw-semibold">Forge</span>
                            <span class="text-muted ms-1">linked to</span>
                            <span class="fw-semibold ms-1">{{ $forgeIntegration->forge_project_name ?: $forgeIntegration->forge_project_id }}</span>
                            <span class="badge bg-success ms-2" style="font-size:.65rem;">active</span>
                        </div>
                        @can('update', $repository)
                            <a href="{{ route('repositories.forge.show', [$repository->organization, $repository]) }}"
                               class="btn btn-sm btn-outline-secondary py-0">Settings</a>
                        @endcan
                    </div>
                </div>
            @endif

            {{-- Branches --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">Branches</h6>
                    <span class="badge bg-secondary">{{ count($branches) }}</span>
                </div>
                @if (count($branches) > 0)
                    <ul class="list-group list-group-flush">
                        @foreach ($branches as $branch)
                            <li class="list-group-item d-flex align-items-center gap-2 py-2">
                                <x-octicon name="git-branch" class="text-muted flex-shrink-0" />
                                <a href="{{ $browserUrl($browserSnapshot['current_path'], $branch) }}"
                                   class="font-monospace text-decoration-none flex-grow-1 {{ $branch === $browserSnapshot['ref'] ? 'fw-semibold ' : '' }}">
                                    {{ $branch }}
                                </a>
                                @if ($branch === $browserSnapshot['ref'])
                                    <span class="badge bg-dark-subtle ">current</span>
                                @endif
                                @if ($branch === $defaultBranch)
                                    <span class="badge bg-primary">default</span>
                                @endif
                                <a href="{{ route('repositories.commits', [$repository->organization, $repository, $branch]) }}"
                                   class="btn btn-outline-secondary btn-sm py-0 px-2 ms-1"
                                   title="View commit history for {{ $branch }}">
                                    <small>Commits</small>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="card-body text-muted small">
                        @if ($exists)
                            No branches yet — push your first commit to get started.
                            <div class="mt-2 p-2 bg-auto rounded font-monospace" style="font-size: .8rem;">
                                git clone {{ rtrim(config('app.url'), '/') }}/{{ $repository->organization->slug }}/{{ $repository->slug }}.git<br>
                                cd {{ $repository->slug }}<br>
                                git checkout -b {{ $defaultBranch }}<br>
                                git commit --allow-empty -m "Initial commit"<br>
                                git push -u origin {{ $defaultBranch }}
                            </div>
                        @else
                            Branches will appear after initialization completes.
                        @endif
                    </div>
                @endif
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 fw-semibold">Repository Browser</h6>
                        @if ($browserSnapshot['has_revision'])
                            <small class="text-muted">
                                <code>{{ $browserSnapshot['ref'] }}</code>
                                @if ($browserSnapshot['current_path'] !== '')
                                    <span class="mx-1">&middot;</span>
                                    <code>{{ $browserSnapshot['current_path'] }}</code>
                                @endif
                            </small>
                        @endif
                    </div>
                    @if ($browserSnapshot['has_revision'])
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary">{{ count($browserSnapshot['entries']) }} items</span>
                            <a href="{{ route('repositories.commits', [$repository->organization, $repository, $browserSnapshot['ref']]) }}"
                               class="btn btn-sm btn-outline-secondary">
                                History
                            </a>
                        </div>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if (! $browserSnapshot['supported'])
                        <div class="p-3">
                            <p class="text-muted small mb-0">
                                File browsing is available when the native git backend is active.
                            </p>
                        </div>
                    @elseif (! $exists)
                        <div class="p-3">
                            <p class="text-muted small mb-0">
                                Files will appear after repository initialization completes.
                            </p>
                        </div>
                    @elseif (! $browserSnapshot['has_revision'])
                        <div class="p-3">
                            <p class="text-muted small mb-0">
                                No commits yet. Push content to browse branches, directories, and rendered READMEs.
                            </p>
                        </div>
                    @else
                        @if (! $browserSnapshot['path_exists'])
                            <div class="alert alert-warning rounded-0 border-0 border-bottom mb-0">
                                The requested path was not found on <code>{{ $browserSnapshot['ref'] }}</code>. Showing the repository root instead.
                            </div>
                        @endif

                        <div class="px-3 pt-3">
                            <nav aria-label="Repository path">
                                <ol class="breadcrumb small mb-0">
                                    @foreach ($browserSnapshot['breadcrumbs'] as $breadcrumb)
                                        @if ($loop->last && $browserSnapshot['path_exists'])
                                            <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb['label'] }}</li>
                                        @else
                                            <li class="breadcrumb-item">
                                                <a href="{{ $browserUrl($breadcrumb['path']) }}">{{ $breadcrumb['label'] }}</a>
                                            </li>
                                        @endif
                                    @endforeach
                                </ol>
                            </nav>
                        </div>

                        <div class="list-group list-group-flush mt-3">
                            @if ($listingPath !== '')
                                <a href="{{ $browserUrl($parentPath) }}"
                                   class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                                    <x-octicon name="arrow-up" class="text-muted flex-shrink-0" />
                                    <span class="font-monospace small">..</span>
                                </a>
                            @endif

                            @forelse ($browserSnapshot['entries'] as $entry)
                                <a href="{{ $browserUrl($entry['path']) }}"
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3 repo-browser-entry {{ $browserSnapshot['current_path'] === $entry['path'] ? 'repo-browser-entry-active' : '' }}">
                                    <div class="d-flex align-items-center gap-2 flex-grow-1 repo-browser-entry-main">
                                        @if ($entry['type'] === 'tree')
                                            <x-octicon name="file-directory-fill" class="text-warning flex-shrink-0" />
                                        @else
                                            <x-octicon name="file" class="text-muted flex-shrink-0" />
                                        @endif
                                        <span class="font-monospace small text-truncate repo-browser-entry-name">{{ $entry['name'] }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                        @if ($entry['is_locked'] ?? false)
                                            <span class="badge text-bg-danger d-inline-flex align-items-center gap-1" title="Locked by {{ $entry['locked_by'] }}">
                                                <x-octicon name="lock" size="12" />
                                                <span>{{ $entry['locked_by'] }}</span>
                                            </span>
                                        @endif
                                        @if ($entry['lfs_tracked'] ?? false)
                                            <span class="badge repo-lfs-badge">Git LFS</span>
                                        @endif
                                        <span class="badge {{ $entry['type'] === 'tree' ? 'text-bg-warning' : 'text-bg-light border' }}">
                                            {{ $entry['type'] === 'tree' ? 'DIR' : 'FILE' }}
                                        </span>
                                    </div>
                                </a>
                            @empty
                                <div class="px-3 py-4 text-muted small">
                                    No files are available at this location on <code>{{ $browserSnapshot['ref'] }}</code>.
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>

            @if ($browserSnapshot['selected_file'])
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-semibold">File Preview</h6>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <code class="small">{{ $browserSnapshot['selected_file']['path'] }}</code>
                                @if ($browserSnapshot['selected_file']['lfs_tracked'])
                                    <span class="badge repo-lfs-badge">Git LFS</span>
                                @endif
                            </div>
                        </div>
                        @if ($browserSnapshot['selected_file']['can_toggle_preview'])
                            <div class="btn-group btn-group-sm" role="group" aria-label="Preview mode">
                                <a href="{{ $browserUrl($browserSnapshot['selected_file']['path'], null, 'rendered') }}"
                                   class="btn {{ $browserSnapshot['selected_file']['preview_mode'] === 'rendered' ? 'btn-primary' : 'btn-outline-secondary' }}">
                                    Rendered
                                </a>
                                <a href="{{ $browserUrl($browserSnapshot['selected_file']['path'], null, 'source') }}"
                                   class="btn {{ $browserSnapshot['selected_file']['preview_mode'] === 'source' ? 'btn-primary' : 'btn-outline-secondary' }}">
                                    Source
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="card-body repo-file-preview">
                        @if ($browserSnapshot['selected_file']['lfs_object_missing'])
                            <div class="alert alert-warning small mb-3">
                                The referenced Git LFS object is not available in Crucible storage. Showing the pointer file instead.
                            </div>
                        @endif

                        @if ($browserSnapshot['selected_file']['preview_mode'] === 'rendered' && $browserSnapshot['selected_file']['image_data_uri'])
                            <div class="repo-image-preview">
                                <img src="{{ $browserSnapshot['selected_file']['image_data_uri'] }}"
                                     alt="Preview of {{ $browserSnapshot['selected_file']['path'] }}"
                                     class="img-fluid rounded border">
                            </div>
                        @elseif ($browserSnapshot['selected_file']['preview_mode'] === 'rendered' && $browserSnapshot['selected_file']['rendered_html'])
                            <div class="repo-readme">
                                {!! $browserSnapshot['selected_file']['rendered_html'] !!}
                            </div>
                        @elseif ($browserSnapshot['selected_file']['image_too_large'])
                            <p class="text-muted small mb-0">
                                This image is too large to preview inline.
                            </p>
                        @elseif ($browserSnapshot['selected_file']['is_binary'])
                            <p class="text-muted small mb-0">
                                Binary files are not previewed in the browser.
                            </p>
                        @elseif ($browserSnapshot['selected_file']['is_too_large'])
                            <p class="text-muted small mb-0">
                                This file is too large to preview inline.
                            </p>
                        @else
                            <pre class="mb-0"><code>{{ $browserSnapshot['selected_file']['source_contents'] }}</code></pre>
                        @endif
                    </div>
                </div>
            @endif

            @if ($browserSnapshot['readme_html'])
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">README</h6>
                        <code class="small">{{ $browserSnapshot['readme_path'] }}</code>
                    </div>
                    <div class="card-body repo-readme">
                        {!! $browserSnapshot['readme_html'] !!}
                    </div>
                </div>
            @endif

            {{-- Active File Locks --}}
            @if ($repository->lfs_enabled)
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                            <x-octicon name="lock" class="text-body-secondary" />
                            <span>Active LFS Locks</span>
                        </h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary">{{ $fileLocks->count() }}</span>
                            @can('manageLocks', $repository)
                                <a href="{{ route('repositories.locks.index', [$repository->organization, $repository]) }}"
                                   class="btn btn-sm btn-outline-secondary">Manage</a>
                            @endcan
                        </div>
                    </div>
                    @if ($fileLocks->isNotEmpty())
                        <ul class="list-group list-group-flush">
                            @foreach ($fileLocks->take(5) as $lock)
                                <li class="list-group-item py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <code class="small text-break">{{ $lock->path }}</code>
                                        <small class="text-muted ms-3 flex-shrink-0">
                                            {{ $lock->owner_display_name }}
                                            @if ($lock->owner_external)
                                                (external)
                                            @endif
                                            &middot; {{ $lock->locked_at?->diffForHumans() }}
                                        </small>
                                    </div>
                                </li>
                            @endforeach
                            @if ($fileLocks->count() > 5)
                                <li class="list-group-item text-center py-2">
                                    <a href="{{ route('repositories.locks.index', [$repository->organization, $repository]) }}" class="small">
                                        View all {{ $fileLocks->count() }} locks
                                    </a>
                                </li>
                            @endif
                        </ul>
                    @else
                        <div class="card-body text-muted small">No active file locks.</div>
                    @endif
                </div>
            @endif

        </div>{{-- /left col --}}

        {{-- Right column: Sidebar --}}
        <div class="col-lg-4">

            {{-- About --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">About</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">VCS</dt>
                        <dd class="col-7 mb-2">
                            <span class="badge bg-secondary">{{ $repository->vcs_type->label() }}</span>
                        </dd>

                        <dt class="col-5 text-muted">Visibility</dt>
                        <dd class="col-7 mb-2">
                            <div>{{ $repository->visibility->label() }}</div>
                            <div class="text-muted small">{{ $repository->visibility->description() }}</div>
                        </dd>

                        <dt class="col-5 text-muted">Default branch</dt>
                        <dd class="col-7 mb-2"><code>{{ $defaultBranch }}</code></dd>

                        <dt class="col-5 text-muted">LFS</dt>
                        <dd class="col-7 mb-2">
                            @if ($repository->lfs_enabled)
                                <span class="text-success d-inline-flex align-items-center gap-1">
                                    <x-octicon name="check" />
                                    <span>Enabled</span>
                                </span>
                            @else
                                <span class="text-muted">Disabled</span>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted">Created</dt>
                        <dd class="col-7 mb-0" title="{{ $repository->created_at }}">
                            {{ $repository->created_at->diffForHumans() }}
                        </dd>
                    </dl>
                </div>
                @can('update', $repository)
                    <div class="card-footer bg-transparent d-flex gap-2">
                        <a href="{{ route('repositories.edit', [$repository->organization, $repository]) }}"
                           class="btn btn-sm btn-outline-secondary flex-fill">Repository Settings</a>
                        <a href="{{ route('repositories.forge.show', [$repository->organization, $repository]) }}"
                           class="btn btn-sm btn-outline-secondary flex-fill">
                            Forge
                            @if ($forgeIntegration?->is_active)
                                <span class="badge bg-success ms-1" style="font-size:.6rem;">linked</span>
                            @endif
                        </a>
                    </div>
                @endcan
            </div>

            {{-- Collaborators --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">Collaborators</h6>
                    <span class="badge bg-secondary">{{ $collaborators->count() }}</span>
                </div>
                @if ($collaborators->isNotEmpty())
                    <ul class="list-group list-group-flush">
                        @foreach ($collaborators as $collab)
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span class="small">{{ $collab->name }}</span>
                                <span class="badge bg-auto border small">{{ $collab->pivot->role }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="card-body text-muted small">No explicit collaborators — organization members have access via their role.</div>
                @endif
            </div>

        </div>{{-- /right col --}}

    </div>
    @endif
</div>
