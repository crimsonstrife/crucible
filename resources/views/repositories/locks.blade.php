<x-app-layout>
    <x-slot name="header">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a></li>
                <li class="breadcrumb-item active">File Locks</li>
            </ol>
        </nav>
    </x-slot>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-semibold mb-0">File Locks</h4>
                <p class="text-muted small mb-0">Active LFS file locks for <strong>{{ $organization->slug }}/{{ $repository->name }}</strong></p>
            </div>
            <span class="badge bg-secondary fs-6">{{ $locks->count() }} {{ Str::plural('lock', $locks->count()) }}</span>
        </div>

        @if ($locks->isEmpty())
            <div class="card shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <p class="mb-0 fs-5">&#128274; No active file locks</p>
                    <p class="small mt-2 mb-0">File locks are created by git-lfs when developers lock binary assets for exclusive editing.</p>
                </div>
            </div>
        @else
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>File Path</th>
                                <th>Locked By</th>
                                <th>Ref / Branch</th>
                                <th>Locked At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($locks as $lock)
                                <tr>
                                    <td>
                                        <code class="text-break">{{ $lock->path }}</code>
                                    </td>
                                    <td>
                                        <span class="fw-medium">{{ $lock->owner_display_name }}</span>
                                        @if ($lock->owner_external)
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-2">External</span>
                                        @endif
                                        @if ($lock->owner_display_identifier)
                                            <br><small class="text-muted">{{ $lock->owner_display_identifier }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($lock->ref)
                                            <code class="small">{{ $lock->ref }}</code>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span title="{{ $lock->locked_at }}">
                                            {{ $lock->locked_at?->diffForHumans() ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @can('unlock', $lock)
                                            <form method="POST"
                                                  action="{{ route('repositories.locks.destroy', [$organization, $repository, $lock]) }}"
                                                  onsubmit="return confirm('Force-release lock on {{ addslashes($lock->path) }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Force Release
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-muted small mt-3">
                Force-releasing a lock removes it regardless of owner. Use with caution — notify the lock owner first.
            </p>
        @endif
    </div>
</x-app-layout>
