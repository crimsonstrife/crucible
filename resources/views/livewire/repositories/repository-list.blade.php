<div>
    <div class="mb-3">
        <input wire:model.live.debounce.300ms="search" type="search"
               class="form-control" placeholder="Search repositories...">
    </div>

    @forelse ($repositories as $repo)
        <div class="card mb-3 repo-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h5 class="mb-0 d-flex align-items-center gap-2">
                                <x-octicon name="repo" class="text-body-secondary flex-shrink-0" />
                                <a href="{{ route('repositories.show', [$repo->organization, $repo]) }}"
                                   class="text-decoration-none">{{ $repo->name }}</a>
                            </h5>
                            <span class="badge bg-secondary badge-vcs">{{ $repo->vcs_type->label() }}</span>
                            <span class="badge bg-auto  border">{{ $repo->visibility->label() }}</span>
                            @if ($repo->lfs_enabled)
                                <span class="lfs-badge">LFS</span>
                            @endif
                            @if ($repo->is_archived)
                                <span class="badge bg-warning ">Archived</span>
                            @endif
                        </div>
                        @if ($repo->description)
                            <p class="text-muted small mb-1">{{ $repo->description }}</p>
                        @endif
                        <small class="text-muted">
                            {{ $repo->file_locks_count }} {{ Str::plural('lock', $repo->file_locks_count) }} &middot;
                            {{ $repo->lfs_objects_count }} LFS {{ Str::plural('object', $repo->lfs_objects_count) }}
                        </small>
                    </div>
                    <a href="{{ route('repositories.show', [$repo->organization, $repo]) }}"
                       class="btn btn-sm btn-outline-secondary">View</a>
                </div>
            </div>
        </div>
    @empty
        <div class="text-center py-5 text-muted">
            <p>No repositories yet.</p>
            <a href="{{ route('repositories.create', $this->organization) }}" class="btn btn-primary d-inline-flex align-items-center gap-1">
                <x-octicon name="repo" />
                <span>Create Repository</span>
            </a>
        </div>
    @endforelse
</div>
