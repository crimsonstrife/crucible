<x-app-layout>
    <div class="container py-4">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a></li>
                <li class="breadcrumb-item active">Tags</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h4 fw-bold mb-0">Tags</h1>
                @if (count($tags) > 0)
                    <small class="text-muted">{{ number_format(count($tags)) }} {{ Str::plural('tag', count($tags)) }}</small>
                @endif
            </div>
        </div>

        <livewire:repositories.create-tag-form :repository="$repository" />

        @if (empty($tags))
            <div class="card shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <p class="fs-5 mb-1">&#127991; No tags yet</p>
                    <p class="small mb-0">Create a tag with <code>git tag v1.0.0 &amp;&amp; git push origin v1.0.0</code></p>
                </div>
            </div>
        @else
            <div class="card shadow-sm">
                <ul class="list-group list-group-flush">
                    @foreach ($tags as $tag)
                        <li class="list-group-item px-3 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <span class="text-muted fs-5 flex-shrink-0">&#127991;</span>

                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="fw-semibold font-monospace">{{ $tag['name'] }}</span>
                                        @if ($tag['type'] === 'tag')
                                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">annotated</span>
                                        @endif
                                    </div>
                                    @if ($tag['subject'] !== '')
                                        <div class="small text-muted text-truncate">{{ $tag['subject'] }}</div>
                                    @endif
                                </div>

                                <div class="flex-shrink-0 text-end d-flex align-items-center gap-2">
                                    @if ($tag['date'])
                                        <small class="text-muted" title="{{ $tag['date']->toDateTimeString() }}">
                                            {{ $tag['date']->diffForHumans() }}
                                        </small>
                                    @endif
                                    <a href="{{ route('repositories.commit', [$organization, $repository, $tag['sha']]) }}"
                                       class="font-monospace small text-muted text-decoration-none"
                                       title="{{ $tag['sha'] }}">
                                        {{ substr($tag['sha'], 0, 8) }}
                                    </a>
                                    <a href="{{ route('repositories.show', [$organization, $repository]) }}?ref={{ urlencode($tag['name']) }}"
                                       class="btn btn-sm btn-outline-secondary py-0 px-2">
                                        Browse
                                    </a>
                                    <a href="{{ route('repositories.commits', [$organization, $repository, $tag['name']]) }}"
                                       class="btn btn-sm btn-outline-secondary py-0 px-2">
                                        Commits
                                    </a>
                                    @can('manageReleases', $repository)
                                        <a href="{{ route('repositories.releases.create', [$organization, $repository]) }}?tag={{ urlencode($tag['name']) }}"
                                           class="btn btn-sm btn-outline-primary py-0 px-2">
                                            Release
                                        </a>
                                    @endcan
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

    </div>

    <style>
        .min-width-0 { min-width: 0; }
    </style>
</x-app-layout>
