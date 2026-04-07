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
                <li class="breadcrumb-item">
                    <a href="{{ route('repositories.commits', [$organization, $repository]) }}">Commits</a>
                </li>
                <li class="breadcrumb-item active font-monospace">{{ $commit['short_sha'] }}</li>
            </ol>
        </nav>

        {{-- Commit header card --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h1 class="h5 fw-bold mb-1">{{ $commit['subject'] ?: '(no commit message)' }}</h1>

                @if ($commit['body'] !== '')
                    <pre class="text-muted small mb-3 mt-2" style="white-space: pre-wrap;">{{ $commit['body'] }}</pre>
                @endif

                <dl class="row mb-0 small">
                    <dt class="col-sm-2 text-muted">Author</dt>
                    <dd class="col-sm-10 mb-1">
                        {{ $commit['author_name'] }}
                        <span class="text-muted">&lt;{{ $commit['author_email'] }}&gt;</span>
                    </dd>

                    <dt class="col-sm-2 text-muted">Date</dt>
                    <dd class="col-sm-10 mb-1">
                        @if ($commit['author_date'])
                            <time datetime="{{ $commit['author_date']->toIso8601String() }}"
                                  title="{{ $commit['author_date']->toDateTimeString() }}">
                                {{ $commit['author_date']->toDateTimeString() }}
                            </time>
                            <span class="text-muted ms-2">({{ $commit['author_date']->diffForHumans() }})</span>
                        @else
                            <span class="text-muted">Unknown</span>
                        @endif
                    </dd>

                    <dt class="col-sm-2 text-muted">Commit</dt>
                    <dd class="col-sm-10 mb-1 font-monospace">{{ $commit['sha'] }}</dd>

                    @if (! empty($commit['parent_shas']))
                        <dt class="col-sm-2 text-muted">
                            {{ Str::plural('Parent', count($commit['parent_shas'])) }}
                        </dt>
                        <dd class="col-sm-10 mb-0">
                            @foreach ($commit['parent_shas'] as $parentSha)
                                <a href="{{ route('repositories.commit', [$organization, $repository, $parentSha]) }}"
                                   class="font-monospace small text-decoration-none me-2">
                                    {{ substr($parentSha, 0, 8) }}
                                </a>
                            @endforeach
                        </dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Per-file diff using shared partial --}}
        @include('partials._diff-files', [
            'diffFiles'      => $diffFiles,
            'diffTotalAdds'  => $diffTotalAdds,
            'diffTotalDels'  => $diffTotalDels,
            'diffEmptyMessage' => 'No diff available for this commit (initial commit or empty commit).',
            'diffOrganization' => $organization,
            'diffRepository'   => $repository,
            'diffBaseRef'      => $commit['parent_shas'][0] ?? null,
            'diffHeadRef'      => $commit['sha'],
        ])

    </div>
</x-app-layout>
