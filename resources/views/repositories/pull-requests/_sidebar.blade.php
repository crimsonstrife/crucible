{{--
    PR sidebar partial — included in the Overview tab of pull-requests/show.blade.php
    Expects: $organization, $repository, $pullRequest, $readiness, $forgeIssue, $mergeStrategies
--}}

{{-- ── Draft banner ────────────────────────────────────────────────── --}}
@if ($pullRequest->isDraft() && $pullRequest->isOpen())
    <div class="card shadow-sm mb-4 border-warning">
        <div class="card-body text-center">
            <span class="badge text-bg-warning fs-6 mb-2">Draft</span>
            <p class="small text-muted mb-2">This pull request is not ready for review yet.</p>
            @can('close', $pullRequest)
                <form method="POST"
                      action="{{ route('repositories.pull-requests.ready', [$organization, $repository, $pullRequest]) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-success w-100">
                        Mark as Ready for Review
                    </button>
                </form>
            @endcan
        </div>
    </div>
@endif

{{-- ── Reviews ─────────────────────────────────────────────────────── --}}
@if ($pullRequest->isOpen() || $pullRequest->reviews->isNotEmpty())
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Reviews</h6></div>
        <div class="card-body">
            @if ($pullRequest->reviews->isNotEmpty())
                @php
                    $latestPerReviewer = $pullRequest->latestReviewPerReviewer();
                @endphp
                <ul class="list-unstyled mb-0">
                    @foreach ($latestPerReviewer as $review)
                        <li class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge {{ $review->state->badgeClass() }}" style="font-size:.65rem;">
                                {{ $review->state->label() }}
                            </span>
                            <span class="small">{{ $review->reviewer?->name ?? 'Unknown' }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="small text-muted mb-0">No reviews yet.</p>
            @endif

            @if ($pullRequest->requestedReviewers->isNotEmpty())
                <div class="mt-2 pt-2 border-top">
                    <div class="small text-muted fw-semibold mb-1">Requested</div>
                    @foreach ($pullRequest->requestedReviewers as $reviewer)
                        <span class="badge text-bg-secondary me-1">{{ $reviewer->name }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif

{{-- ── Actions ─────────────────────────────────────────────────────── --}}
@if ($pullRequest->isOpen())
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Actions</h6></div>
        <div class="card-body d-flex flex-column gap-2">

            {{-- Merge readiness status --}}
            @if (! empty($readiness['protection_violations']))
                <div class="alert alert-warning small py-2 mb-2">
                    <strong>Branch protection:</strong>
                    <ul class="mb-0 ps-3">
                        @foreach ($readiness['protection_violations'] as $violation)
                            <li>{{ $violation }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($readiness['conflicts'])
                <div class="alert alert-danger small py-2 mb-2">
                    This branch has conflicts that must be resolved before merging.
                </div>
            @endif

            @can('merge', $pullRequest)
                @if (! $pullRequest->isDraft())
                    <form method="POST"
                          action="{{ route('repositories.pull-requests.merge', [$organization, $repository, $pullRequest]) }}"
                          onsubmit="return confirm('Merge pull request #{{ $pullRequest->number }}?')">
                        @csrf

                        {{-- Merge strategy selector --}}
                        <div class="mb-2">
                            <select name="merge_strategy" class="form-select form-select-sm">
                                @foreach ($mergeStrategies as $strategy)
                                    <option value="{{ $strategy->value }}"
                                        {{ ($pullRequest->merge_strategy ?? \App\Enums\MergeStrategy::MergeCommit) === $strategy ? 'selected' : '' }}>
                                        {{ $strategy->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit"
                                class="btn {{ $readiness['mergeable'] ? 'btn-success' : 'btn-outline-success' }} w-100 d-inline-flex align-items-center justify-content-center gap-1"
                                {{ ! $readiness['mergeable'] ? '' : '' }}>
                            <x-octicon name="git-merge" />
                            <span>Merge Pull Request</span>
                            @if (! $readiness['mergeable'])
                                <span class="badge bg-warning  ms-1" title="Merge requirements not met">!</span>
                            @endif
                        </button>
                    </form>
                @endif
            @endcan
            @can('close', $pullRequest)
                <form method="POST"
                      action="{{ route('repositories.pull-requests.close', [$organization, $repository, $pullRequest]) }}">
                    @csrf
                    <button type="submit"
                            class="btn btn-outline-secondary w-100 d-inline-flex align-items-center justify-content-center gap-1">
                        <x-octicon name="git-pull-request-closed" />
                        <span>Close without Merging</span>
                    </button>
                </form>
            @endcan
        </div>
    </div>
@elseif ($pullRequest->isClosed())
    @can('reopen', $pullRequest)
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="POST"
                      action="{{ route('repositories.pull-requests.reopen', [$organization, $repository, $pullRequest]) }}">
                    @csrf
                    <button type="submit"
                            class="btn btn-outline-secondary w-100 d-inline-flex align-items-center justify-content-center gap-1">
                        <x-octicon name="git-pull-request" />
                        <span>Reopen Pull Request</span>
                    </button>
                </form>
            </div>
        </div>
    @endcan
@endif

{{-- ── Forge issue link ─────────────────────────────────────────────── --}}
@if ($pullRequest->forge_issue_key)
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Forge Issue</h6></div>
        <div class="card-body small">
            @if ($forgeIssue)
                <div class="d-flex align-items-start gap-2">
                    <span class="text-muted mt-1">🔗</span>
                    <div>
                        @php $forgeBaseUrl = rtrim(config('crucible.forge.url', ''), '/'); @endphp
                        <a href="{{ $forgeBaseUrl }}/issues/{{ $pullRequest->forge_issue_key }}"
                           target="_blank" rel="noopener" class="fw-semibold text-decoration-none">
                            {{ $pullRequest->forge_issue_key }}
                        </a>
                        @if (! empty($forgeIssue['summary'] ?? $forgeIssue['title']))
                            <div class="text-muted mt-1">{{ $forgeIssue['summary'] ?? $forgeIssue['title'] }}</div>
                        @endif
                        @if (! empty($forgeIssue['status']['name']))
                            <span class="badge bg-secondary mt-1">{{ $forgeIssue['status']['name'] }}</span>
                        @endif
                        @if (! empty($forgeIssue['assignee']['name']))
                            <div class="text-muted mt-1">Assigned to {{ $forgeIssue['assignee']['name'] }}</div>
                        @endif
                    </div>
                </div>
            @else
                <span class="font-monospace">{{ $pullRequest->forge_issue_key }}</span>
                @if (config('crucible.forge.url'))
                    <a href="{{ rtrim(config('crucible.forge.url'), '/') }}/issues/{{ $pullRequest->forge_issue_key }}"
                       target="_blank" rel="noopener"
                       class="ms-2 small text-decoration-none">View in Forge ↗</a>
                @endif
            @endif
        </div>
    </div>
@endif

{{-- ── Metadata ─────────────────────────────────────────────────────── --}}
<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0 fw-semibold">Info</h6></div>
    <div class="card-body small">
        <dl class="row mb-0">
            <dt class="col-5 text-muted">Author</dt>
            <dd class="col-7 mb-2">{{ $pullRequest->author->name }}</dd>

            <dt class="col-5 text-muted">Opened</dt>
            <dd class="col-7 mb-2" title="{{ $pullRequest->created_at }}">
                {{ $pullRequest->created_at->diffForHumans() }}
            </dd>

            @if ($pullRequest->merge_strategy && $pullRequest->isMerged())
                <dt class="col-5 text-muted">Strategy</dt>
                <dd class="col-7 mb-2">{{ $pullRequest->merge_strategy->label() }}</dd>
            @endif

            @if ($pullRequest->isMerged())
                <dt class="col-5 text-muted">Merged by</dt>
                <dd class="col-7 mb-2">{{ $pullRequest->mergedBy?->name ?? '—' }}</dd>

                <dt class="col-5 text-muted">Merged at</dt>
                <dd class="col-7 mb-0" title="{{ $pullRequest->merged_at }}">
                    {{ $pullRequest->merged_at?->diffForHumans() }}
                </dd>
            @endif
        </dl>

        @if ($pullRequest->merge_commit_sha)
            <div class="mt-2 pt-2 border-top">
                <a href="{{ route('repositories.commit', [$organization, $repository, $pullRequest->merge_commit_sha]) }}"
                   class="font-monospace small text-muted text-decoration-none">
                    Merge commit {{ substr($pullRequest->merge_commit_sha, 0, 8) }}
                </a>
            </div>
        @endif
    </div>
</div>
