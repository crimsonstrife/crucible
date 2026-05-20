<x-app-layout>
    <div class="container py-4">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.releases.index', [$organization, $repository]) }}">Releases</a></li>
                <li class="breadcrumb-item active">{{ $release->tag_name }}</li>
            </ol>
        </nav>

        <div class="d-flex align-items-start justify-content-between gap-2 mb-2 flex-wrap">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 class="h3 fw-bold mb-0">{{ $release->name ?: $release->tag_name }}</h1>
                @if ($release->is_latest)
                    <span class="badge bg-success">Latest</span>
                @endif
                @if ($release->is_prerelease)
                    <span class="badge bg-warning text-dark">Pre-release</span>
                @endif
                @if ($release->is_draft)
                    <span class="badge bg-secondary">Draft</span>
                @endif
            </div>
            @if ($canManage)
                <a href="{{ route('repositories.releases.edit', [$organization, $repository, $release]) }}"
                   class="btn btn-sm btn-outline-secondary">Edit release</a>
            @endif
        </div>

        <div class="text-muted small mb-4 d-flex align-items-center gap-2 flex-wrap">
            <span class="font-monospace">{{ $release->tag_name }}</span>
            <span>&middot;</span>
            <a href="{{ route('repositories.commit', [$organization, $repository, $release->commit_sha]) }}"
               class="font-monospace text-decoration-none"
               title="{{ $release->commit_sha }}">
                {{ substr($release->commit_sha, 0, 8) }}
            </a>
            @if ($release->published_at)
                <span>&middot;</span>
                <span title="{{ $release->published_at->toDateTimeString() }}">
                    Published {{ $release->published_at->diffForHumans() }}
                </span>
            @endif
            @if ($release->author)
                <span>&middot;</span>
                <span>by {{ $release->author->name }}</span>
            @endif
        </div>

        @if ($release->body)
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    {{-- Plain rendering for now; markdown rendering can be added by reusing the PR description renderer. --}}
                    <div class="release-body" style="white-space: pre-wrap; font-family: inherit;">{{ $release->body }}</div>
                </div>
            </div>
        @endif

        @if ($release->links->isNotEmpty())
            <div class="mb-4 d-flex flex-wrap gap-2">
                @foreach ($release->links as $link)
                    <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                       class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2">
                        @if ($link->platform)
                            <i class="bi {{ $link->platform->icon() }}" aria-hidden="true"></i>
                        @endif
                        <span>{{ $link->label }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if (! $release->is_draft && $release->published_at !== null)
            @php($archiveBase = url("/api/v1/{$organization->slug}/{$repository->slug}/releases/{$release->slug}"))
            <div class="mb-4 d-flex flex-wrap gap-2">
                <a href="{{ $archiveBase }}/source.zip"
                   class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-zip" aria-hidden="true"></i>
                    <span>Source code (zip)</span>
                </a>
                <a href="{{ $archiveBase }}/source.tar.gz"
                   class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-zip" aria-hidden="true"></i>
                    <span>Source code (tar.gz)</span>
                </a>
            </div>
        @endif

        @if ($release->entries->isNotEmpty())
            @foreach ($release->entries->groupBy(fn ($e) => $e->category->value) as $categoryValue => $items)
                @php($cat = $items->first()->category)
                <div class="card shadow-sm mb-3">
                    <div class="card-header fw-semibold">
                        {{ $cat->label() }}
                        <span class="badge bg-secondary ms-1">{{ $items->count() }}</span>
                    </div>
                    <ul class="list-group list-group-flush">
                        @foreach ($items->sortBy('position') as $entry)
                            <li class="list-group-item">{{ $entry->description }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        @endif

    </div>
</x-app-layout>
