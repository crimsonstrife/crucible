<x-app-layout>
    <div class="container py-4">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.pull-requests.index', [$organization, $repository]) }}">Pull Requests</a></li>
                <li class="breadcrumb-item active">#{{ $pullRequest->number }}</li>
            </ol>
        </nav>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->has('merge') || $errors->has('close') || $errors->has('reopen') || $errors->has('draft'))
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        {{-- ── Header ──────────────────────────────────────────────────────── --}}
        <div class="mb-4">
            <h1 class="h4 fw-bold mb-1">
                {{ $pullRequest->title }}
                <span class="text-muted fw-normal fs-5">#{{ $pullRequest->number }}</span>
            </h1>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge fs-6 {{ $pullRequest->status->badgeClass() }} d-inline-flex align-items-center gap-1">
                    <x-octicon :name="$pullRequest->status->iconName()" />
                    <span>{{ $pullRequest->status->label() }}</span>
                </span>
                @if ($pullRequest->isDraft())
                    <span class="badge fs-6 text-bg-warning d-inline-flex align-items-center gap-1">
                        Draft
                    </span>
                @endif
                <span class="small text-muted">
                    <strong>{{ $pullRequest->author->name }}</strong>
                    wants to merge
                    <a href="{{ route('repositories.commits', [$organization, $repository, $pullRequest->source_branch]) }}"
                       class="text-decoration-none font-monospace">{{ $pullRequest->source_branch }}</a>
                    into
                    <a href="{{ route('repositories.commits', [$organization, $repository, $pullRequest->target_branch]) }}"
                       class="text-decoration-none font-monospace">{{ $pullRequest->target_branch }}</a>
                    &middot; opened {{ $pullRequest->created_at->diffForHumans() }}
                </span>
            </div>
        </div>

        {{-- ── Tab nav ─────────────────────────────────────────────────────── --}}
        <ul class="nav nav-tabs mb-4" id="prTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-overview" data-bs-toggle="tab"
                        data-bs-target="#pane-overview" type="button" role="tab">
                    Overview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-commits" data-bs-toggle="tab"
                        data-bs-target="#pane-commits" type="button" role="tab">
                    Commits
                    @if (! empty($diff['commits']))
                        <span class="badge bg-secondary ms-1">{{ count($diff['commits']) }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-files" data-bs-toggle="tab"
                        data-bs-target="#pane-files" type="button" role="tab">
                    Files changed
                    @if (! empty($diff['files']))
                        <span class="badge bg-secondary ms-1">{{ count($diff['files']) }}</span>
                    @endif
                </button>
            </li>
        </ul>

        <div class="tab-content" id="prTabContent">

            {{-- ════════════════════════════════════════════════════════════════
                 TAB 1 — Overview
            ════════════════════════════════════════════════════════════════ --}}
            <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
                <div class="row g-4">

                    {{-- Main column --}}
                    <div class="col-lg-9">

                        {{-- Description --}}
                        @if ($pullRequest->description)
                            <div class="card shadow-sm mb-4">
                                <div class="card-body" style="white-space: pre-wrap;">{{ $pullRequest->description }}</div>
                            </div>
                        @endif

                        {{-- Diff stat summary --}}
                        @if (! empty($diff['files']))
                            <div class="card shadow-sm mb-4">
                                <div class="card-body py-2 px-3 d-flex align-items-center gap-3 flex-wrap small">
                                    <span class="text-muted">
                                        <strong class="text-body">{{ count($diff['files']) }}</strong>
                                        {{ Str::plural('file', count($diff['files'])) }} changed
                                    </span>
                                    @if ($diff['total_additions'] > 0)
                                        <span class="text-success fw-semibold">+{{ number_format($diff['total_additions']) }}</span>
                                    @endif
                                    @if ($diff['total_deletions'] > 0)
                                        <span class="text-danger fw-semibold">−{{ number_format($diff['total_deletions']) }}</span>
                                    @endif
                                    <a href="#pane-files" class="ms-auto text-decoration-none small"
                                       onclick="document.getElementById('tab-files').click(); return false;">
                                        View full diff →
                                    </a>
                                </div>
                            </div>
                        @endif

                        {{-- Changed files quick-list --}}
                        @if (! empty($diff['files']))
                            <div class="card shadow-sm mb-4">
                                <div class="card-header py-2">
                                    <h6 class="mb-0 fw-semibold small">Changed files</h6>
                                </div>
                                <ul class="list-group list-group-flush">
                                    @foreach ($diff['files'] as $fileIdx => $file)
                                        <li class="list-group-item px-3 py-2 d-flex align-items-center gap-2 small">
                                            @if ($file['is_new'])
                                                <span class="badge bg-success bg-opacity-75 text-white flex-shrink-0" style="font-size:.65rem;">NEW</span>
                                            @elseif ($file['is_deleted'])
                                                <span class="badge bg-danger bg-opacity-75 text-white flex-shrink-0" style="font-size:.65rem;">DEL</span>
                                            @elseif ($file['is_renamed'])
                                                <span class="badge bg-warning  flex-shrink-0" style="font-size:.65rem;">REN</span>
                                            @elseif ($file['is_binary'])
                                                <span class="badge bg-secondary flex-shrink-0" style="font-size:.65rem;">BIN</span>
                                            @else
                                                <span class="badge bg-secondary bg-opacity-50 flex-shrink-0" style="font-size:.65rem;">MOD</span>
                                            @endif

                                            <a href="#" class="text-decoration-none font-monospace  flex-grow-1 text-truncate"
                                               onclick="jumpToFile({{ $fileIdx }}); return false;"
                                               title="{{ $file['file_name'] }}">{{ $file['file_name'] }}</a>

                                            @if (! $file['is_binary'])
                                                <span class="text-muted flex-shrink-0" style="min-width:80px; text-align:right;">
                                                    <span class="text-success">+{{ $file['additions'] }}</span>
                                                    <span class="text-muted mx-1">/</span>
                                                    <span class="text-danger">−{{ $file['deletions'] }}</span>
                                                </span>
                                            @else
                                                <span class="text-muted small flex-shrink-0">binary</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @elseif (empty($diff['commits']))
                            <div class="card shadow-sm">
                                <div class="card-body text-muted small">
                                    No differences between <code>{{ $pullRequest->source_branch }}</code>
                                    and <code>{{ $pullRequest->target_branch }}</code>.
                                </div>
                            </div>
                        @endif

                    </div>

                    {{-- Sidebar --}}
                    <div class="col-lg-3">
                        @include('repositories.pull-requests._sidebar')
                    </div>

                </div>
            </div>

            {{-- ════════════════════════════════════════════════════════════════
                 TAB 2 — Commits
            ════════════════════════════════════════════════════════════════ --}}
            <div class="tab-pane fade" id="pane-commits" role="tabpanel">
                @if (! empty($diff['commits']))
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-semibold">Commits ({{ $pullRequest->source_branch }} → {{ $pullRequest->target_branch }})</h6>
                            <span class="badge bg-secondary">{{ count($diff['commits']) }}</span>
                        </div>
                        <ul class="list-group list-group-flush">
                            @foreach ($diff['commits'] as $commit)
                                <li class="list-group-item px-3 py-2 d-flex align-items-center gap-3">
                                    <div class="flex-grow-1 min-width-0">
                                        <a href="{{ route('repositories.commit', [$organization, $repository, $commit['sha']]) }}"
                                           class="text-decoration-none  small">
                                            {{ $commit['subject'] ?: '(no message)' }}
                                        </a>
                                        <div class="text-muted" style="font-size:.75rem;">
                                            {{ $commit['author_name'] }}
                                            @if ($commit['author_date'])
                                                &middot; {{ $commit['author_date']->diffForHumans() }}
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ route('repositories.commit', [$organization, $repository, $commit['sha']]) }}"
                                       class="font-monospace text-muted text-decoration-none flex-shrink-0"
                                       style="font-size:.75rem;">
                                        {{ $commit['short_sha'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="card shadow-sm">
                        <div class="card-body text-muted small">No commits found between these branches.</div>
                    </div>
                @endif
            </div>

            {{-- ════════════════════════════════════════════════════════════════
                 TAB 3 — Files changed
            ════════════════════════════════════════════════════════════════ --}}
            <div class="tab-pane fade" id="pane-files" role="tabpanel">
                @include('partials._diff-files', [
                    'diffFiles'        => $diff['files'],
                    'diffTotalAdds'    => $diff['total_additions'],
                    'diffTotalDels'    => $diff['total_deletions'],
                    'diffEmptyMessage' => 'No differences between ' . $pullRequest->source_branch . ' and ' . $pullRequest->target_branch . '.',
                    'diffOrganization' => $organization,
                    'diffRepository'   => $repository,
                    'diffBaseRef'      => $pullRequest->isMerged() ? $pullRequest->base_sha : $pullRequest->target_branch,
                    'diffHeadRef'      => $pullRequest->isMerged() ? $pullRequest->head_sha : $pullRequest->source_branch,
                ])
            </div>{{-- /pane-files --}}

        </div>{{-- /tab-content --}}
    </div>

    <script>
        // Switch to Files tab and scroll to the given file card by index
        function jumpToFile(idx) {
            const tab = document.getElementById('tab-files');
            if (!tab) return;
            tab.click();
            setTimeout(function () {
                const card = document.getElementById('diff-file-' + idx);
                if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 150);
        }
    </script>
</x-app-layout>
