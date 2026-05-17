<x-app-layout>
    <div class="container py-4">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a></li>
                <li class="breadcrumb-item active">Releases</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h4 fw-bold mb-0">Releases</h1>
                @if ($releases->total() > 0)
                    <small class="text-muted">{{ number_format($releases->total()) }} {{ Str::plural('release', $releases->total()) }}</small>
                @endif
            </div>
            @if ($canManage)
                <a href="{{ route('repositories.releases.create', [$organization, $repository]) }}"
                   class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <span>Draft new release</span>
                </a>
            @endif
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($releases->isEmpty())
            <div class="card shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <p class="fs-5 mb-1">&#128230; No releases yet</p>
                    <p class="small mb-0">
                        Tag a commit (e.g. <code>git tag v1.0.0 &amp;&amp; git push origin v1.0.0</code>),
                        then create a release via the API.
                    </p>
                </div>
            </div>
        @else
            <div class="d-flex flex-column gap-3">
                @foreach ($releases as $release)
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <a href="{{ route('repositories.releases.show', [$organization, $repository, $release]) }}"
                                           class="h5 mb-0 fw-semibold text-decoration-none">
                                            {{ $release->name ?: $release->tag_name }}
                                        </a>
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
                                    <div class="small text-muted d-flex align-items-center gap-2 flex-wrap">
                                        <span class="font-monospace">{{ $release->tag_name }}</span>
                                        <span>&middot;</span>
                                        <span class="font-monospace" title="{{ $release->commit_sha }}">{{ substr($release->commit_sha, 0, 8) }}</span>
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
                                </div>
                            </div>

                            @if ($release->body)
                                <div class="mt-2 small">
                                    {{ Str::limit($release->body, 280) }}
                                </div>
                            @endif

                            @if ($release->entries->isNotEmpty())
                                <div class="mt-3 d-flex gap-2 flex-wrap">
                                    @foreach ($release->entries->groupBy(fn ($e) => $e->category->value) as $category => $items)
                                        <span class="badge bg-light text-dark border">
                                            {{ $items->first()->category->label() }}: {{ $items->count() }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($canManage)
                                <div class="mt-3 d-flex gap-2">
                                    <a href="{{ route('repositories.releases.edit', [$organization, $repository, $release]) }}"
                                       class="btn btn-sm btn-outline-secondary py-0 px-2">Edit</a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $releases->links() }}
            </div>
        @endif

    </div>

    <style>
        .min-width-0 { min-width: 0; }
    </style>
</x-app-layout>
