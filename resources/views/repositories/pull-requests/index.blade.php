<x-app-layout>
    <div class="container py-4">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a></li>
                <li class="breadcrumb-item active">Pull Requests</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h1 class="h4 fw-bold mb-0 d-flex align-items-center gap-2">
                <x-octicon name="git-pull-request" class="text-body-secondary" />
                <span>Pull Requests</span>
            </h1>
            @can('push', $repository)
                <a href="{{ route('repositories.pull-requests.create', [$organization, $repository]) }}"
                   class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <x-octicon name="git-pull-request" />
                    <span>New Pull Request</span>
                </a>
            @endcan
        </div>

        {{-- Status filter tabs --}}
        <ul class="nav nav-tabs mb-4">
            @foreach (\App\Enums\PullRequestStatus::cases() as $s)
                <li class="nav-item">
                    <a class="nav-link {{ $status === $s ? 'active' : '' }}"
                       href="{{ route('repositories.pull-requests.index', [$organization, $repository]) }}?status={{ $s->value }}">
                        <span class="d-inline-flex align-items-center gap-1">
                            <x-octicon :name="$s->iconName()" />
                            <span>{{ $s->label() }}</span>
                        </span>
                        <span class="badge {{ $status === $s ? 'bg-dark' : 'bg-secondary' }} ms-1">{{ $counts[$s->value] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($pullRequests->isEmpty())
            <div class="card shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <p class="fs-5 mb-1">No {{ $status->label() }} pull requests</p>
                    @if ($status === \App\Enums\PullRequestStatus::Open)
                        <p class="small mb-0">
                            Open a pull request to propose and discuss changes before merging.
                        </p>
                    @endif
                </div>
            </div>
        @else
            <div class="card shadow-sm">
                <ul class="list-group list-group-flush">
                    @foreach ($pullRequests as $pr)
                        <li class="list-group-item px-3 py-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="badge {{ $pr->status->badgeClass() }} mt-1 flex-shrink-0 d-inline-flex align-items-center gap-1">
                                    <x-octicon :name="$pr->status->iconName()" />
                                    <span>{{ $pr->status->label() }}</span>
                                </span>
                                <div class="flex-grow-1 min-width-0">
                                    <a href="{{ route('repositories.pull-requests.show', [$organization, $repository, $pr]) }}"
                                       class="fw-semibold  text-decoration-none">
                                        {{ $pr->title }}
                                    </a>
                                    @if ($pr->isDraft())
                                        <span class="badge text-bg-warning ms-1" style="font-size:.65rem;">Draft</span>
                                    @endif
                                    <div class="small text-muted mt-1">
                                        #{{ $pr->number }}
                                        &middot;
                                        <code>{{ $pr->source_branch }}</code>
                                        &#8594;
                                        <code>{{ $pr->target_branch }}</code>
                                        &middot;
                                        opened by <strong>{{ $pr->author->name }}</strong>
                                        {{ $pr->created_at->diffForHumans() }}
                                        @if ($pr->isMerged())
                                            &middot; merged {{ $pr->merged_at?->diffForHumans() }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-3">{{ $pullRequests->links() }}</div>
        @endif

    </div>
    <style>.min-width-0 { min-width: 0; }</style>
</x-app-layout>
