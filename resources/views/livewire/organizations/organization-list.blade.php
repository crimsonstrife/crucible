<div>
    <div class="mb-3">
        <input wire:model.live.debounce.300ms="search" type="search"
               class="form-control" placeholder="Search organizations...">
    </div>

    @forelse ($organizations as $org)
        <div class="card mb-3 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="card-title mb-1">
                            <i class="fas fa-building me-1 text-body-secondary"></i>
                            <a href="{{ route('organizations.show', $org) }}" class="text-decoration-none">
                                {{ $org->name }}
                            </a>
                        </h5>
                        @if ($org->description)
                            <p class="card-text text-muted small mb-2">{{ $org->description }}</p>
                        @endif
                        <small class="text-muted">
                            <i class="fas fa-users me-1"></i>{{ $org->members_count }} {{ Str::plural('member', $org->members_count) }}
                            &middot;
                            <x-octicon name="repo" class="me-1" />{{ $org->repositories_count }} {{ Str::plural('repository', $org->repositories_count) }}
                        </small>
                    </div>
                    <a href="{{ route('organizations.show', $org) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-eye me-1"></i>View
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="text-center py-5 text-muted">
            <i class="fas fa-building fa-3x mb-3 opacity-25 d-block"></i>
            <p>No organizations yet.</p>
            <a href="{{ route('organizations.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Create one
            </a>
        </div>
    @endforelse
</div>
