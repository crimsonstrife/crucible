<div>
    @if (! $integration?->is_active)
        <div class="alert alert-info">
            No Forge project is linked to this repository. Link one from the
            <a href="{{ route('repositories.forge.show', [$repository->organization, $repository]) }}">Forge settings</a>.
        </div>
    @else
        {{-- Search & filter bar --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body py-2">
                <div class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <input type="text"
                               class="form-control form-control-sm"
                               placeholder="Search issues..."
                               wire:model.live.debounce.300ms="search">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" wire:model.live="statusFilter">
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-end">
                        <small class="text-muted">{{ $total }} issue{{ $total !== 1 ? 's' : '' }}</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Issues table --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                    <x-octicon name="issue-opened" class="text-body-secondary" />
                    <span>Issues</span>
                    @if ($integration->forge_project_name)
                        <span class="text-muted fw-normal">&mdash; {{ $integration->forge_project_name }}</span>
                    @endif
                </h6>
                @if ($forgeProjectUrl)
                    <a href="{{ $forgeProjectUrl }}"
                       target="_blank"
                       rel="noopener"
                       class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                        <x-octicon name="link-external" size="14" />
                        <span>Open in Forge</span>
                    </a>
                @endif
            </div>

            @if (count($issues) > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small text-muted text-uppercase" style="width:100px">Key</th>
                                <th class="small text-muted text-uppercase">Summary</th>
                                <th class="small text-muted text-uppercase" style="width:100px">Type</th>
                                <th class="small text-muted text-uppercase" style="width:100px">Priority</th>
                                <th class="small text-muted text-uppercase" style="width:110px">Status</th>
                                <th class="small text-muted text-uppercase" style="width:130px">Assignee</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($issues as $issue)
                                <tr>
                                    <td>
                                        @if ($forgeProjectUrl && ! empty($issue['key']))
                                            <a href="{{ $forgeProjectUrl }}/issues/{{ $issue['key'] }}"
                                               target="_blank"
                                               rel="noopener"
                                               class="font-monospace text-decoration-none small fw-semibold">
                                                {{ $issue['key'] }}
                                            </a>
                                        @else
                                            <span class="font-monospace small">{{ $issue['key'] ?? '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="small text-truncate" style="max-width:400px" title="{{ $issue['summary'] ?? '' }}">
                                        {{ $issue['summary'] ?? '(no summary)' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-auto border small">{{ $issue['type']['name'] ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <span class="small">{{ $issue['priority']['name'] ?? '—' }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $isDone = $issue['status']['is_done'] ?? false;
                                        @endphp
                                        <span class="badge {{ $isDone ? 'bg-success' : 'bg-primary' }}">
                                            {{ $issue['status']['name'] ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="small">
                                        {{ $issue['assignee']['name'] ?? 'Unassigned' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if ($lastPage > 1)
                    <div class="card-footer d-flex justify-content-between align-items-center py-2">
                        <button class="btn btn-sm btn-outline-secondary"
                                wire:click="previousPage"
                                @disabled($page <= 1)>
                            Previous
                        </button>
                        <small class="text-muted">Page {{ $page }} of {{ $lastPage }}</small>
                        <button class="btn btn-sm btn-outline-secondary"
                                wire:click="nextPage"
                                @disabled($page >= $lastPage)>
                            Next
                        </button>
                    </div>
                @endif
            @else
                <div class="card-body text-muted small">
                    @if ($search !== '')
                        No issues found matching "{{ $search }}".
                    @else
                        No {{ $statusFilter !== 'all' ? $statusFilter : '' }} issues found in this project.
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
