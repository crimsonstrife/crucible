<x-app-layout>
    <div class="container py-4">

        {{-- Breadcrumb --}}
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('organizations.index') }}">Organizations</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a>
                </li>
                <li class="breadcrumb-item active">Commits</li>
            </ol>
        </nav>

        {{-- Header --}}
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h4 fw-bold mb-0">Commits</h1>
                @if ($total > 0)
                    <small class="text-muted">{{ number_format($total) }} commit{{ $total !== 1 ? 's' : '' }} on
                        <code>{{ $ref }}</code></small>
                @endif
            </div>

            {{-- Branch switcher --}}
            @if (count($branches) > 1)
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <x-octicon name="git-branch" class="me-1" />
                        {{ $ref }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach ($branches as $branch)
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 {{ $branch === $ref ? 'active' : '' }}"
                                   href="{{ route('repositories.commits', [$organization, $repository, $branch]) }}">
                                    <code class="small">{{ $branch }}</code>
                                    @if ($branch === $defaultBranch)
                                        <span class="badge bg-primary ms-auto">default</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if (empty($commits))
            <div class="card shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <p class="mb-0">No commits found on <code>{{ $ref }}</code>.</p>
                </div>
            </div>
        @else
            <div class="card shadow-sm">
                <ul class="list-group list-group-flush">
                    @foreach ($commits as $commit)
                        <li class="list-group-item px-3 py-3">
                            <div class="d-flex align-items-start gap-3">
                                {{-- Avatar placeholder --}}
                                <div class="flex-shrink-0 rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center"
                                     style="width:34px;height:34px;font-size:.75rem;font-weight:600;color:#666;">
                                    {{ strtoupper(mb_substr($commit['author_name'], 0, 1)) }}
                                </div>

                                <div class="flex-grow-1 min-width-0">
                                    {{-- Subject --}}
                                    <div class="mb-1">
                                        <a href="{{ route('repositories.commit', [$organization, $repository, $commit['sha']]) }}"
                                           class="fw-semibold  text-decoration-none commit-subject-link">
                                            {{ $commit['subject'] ?: '(no message)' }}
                                        </a>
                                    </div>

                                    {{-- Meta --}}
                                    <div class="d-flex align-items-center flex-wrap gap-2 small text-muted">
                                        <span>{{ $commit['author_name'] }}</span>
                                        @if ($commit['author_date'])
                                            <span>&middot;</span>
                                            <time datetime="{{ $commit['author_date']->toIso8601String() }}"
                                                  title="{{ $commit['author_date']->toDateTimeString() }}">
                                                {{ $commit['author_date']->diffForHumans() }}
                                            </time>
                                        @endif
                                    </div>
                                </div>

                                {{-- SHA link --}}
                                <div class="flex-shrink-0 text-end">
                                    <a href="{{ route('repositories.commit', [$organization, $repository, $commit['sha']]) }}"
                                       class="font-monospace small text-muted text-decoration-none commit-sha-link"
                                       title="{{ $commit['sha'] }}">
                                        {{ $commit['short_sha'] }}
                                    </a>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Pagination --}}
            @if ($totalPages > 1)
                <nav class="mt-4 d-flex justify-content-between align-items-center" aria-label="Commit pagination">
                    <div class="text-muted small">
                        Page {{ $page }} of {{ number_format($totalPages) }}
                        &nbsp;&middot;&nbsp;
                        Showing {{ number_format(($page - 1) * $perPage + 1) }}–{{ number_format(min($page * $perPage, $total)) }}
                        of {{ number_format($total) }}
                    </div>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}">
                            <a class="page-link"
                               href="{{ route('repositories.commits', [$organization, $repository, $ref]) }}?page={{ $page - 1 }}"
                               aria-label="Previous">
                                &laquo; Newer
                            </a>
                        </li>
                        <li class="page-item {{ $page >= $totalPages ? 'disabled' : '' }}">
                            <a class="page-link"
                               href="{{ route('repositories.commits', [$organization, $repository, $ref]) }}?page={{ $page + 1 }}"
                               aria-label="Next">
                                Older &raquo;
                            </a>
                        </li>
                    </ul>
                </nav>
            @endif
        @endif

    </div>

    <style>
        .commit-subject-link:hover { text-decoration: underline !important; }
        .commit-sha-link { font-size: .8rem; }
        .commit-sha-link:hover { color: var(--bs-primary) !important; }
        .min-width-0 { min-width: 0; }
    </style>
</x-app-layout>
