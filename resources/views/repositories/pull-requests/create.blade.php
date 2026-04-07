<x-app-layout>
    <div class="container py-4" style="max-width: 740px;">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('repositories.pull-requests.index', [$organization, $repository]) }}">Pull Requests</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </nav>

        <h1 class="h4 fw-bold mb-4">Open a Pull Request</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('repositories.pull-requests.store', [$organization, $repository]) }}">
            @csrf

            {{-- Branch selection --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Branches</h6></div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <label for="source_branch" class="form-label fw-medium">
                                Source branch <span class="text-danger">*</span>
                                <small class="text-muted fw-normal d-block">Branch with your changes</small>
                            </label>
                            <select class="form-select @error('source_branch') is-invalid @enderror"
                                    id="source_branch" name="source_branch" required>
                                <option value="">— select —</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch }}"
                                        {{ old('source_branch', $sourceBranch) === $branch ? 'selected' : '' }}>
                                        {{ $branch }}
                                    </option>
                                @endforeach
                            </select>
                            @error('source_branch')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-2 text-center d-none d-md-block pt-4">
                            <span class="text-muted fs-5">&#8594;</span>
                        </div>

                        <div class="col-md-5">
                            <label for="target_branch" class="form-label fw-medium">
                                Target branch <span class="text-danger">*</span>
                                <small class="text-muted fw-normal d-block">Branch to merge into</small>
                            </label>
                            <select class="form-select @error('target_branch') is-invalid @enderror"
                                    id="target_branch" name="target_branch" required>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch }}"
                                        {{ old('target_branch', $targetBranch) === $branch ? 'selected' : '' }}>
                                        {{ $branch }}
                                    </option>
                                @endforeach
                            </select>
                            @error('target_branch')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Details --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Details</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label fw-medium">
                            Title <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control @error('title') is-invalid @enderror"
                               id="title" name="title"
                               value="{{ old('title') }}"
                               placeholder="Describe your changes in one line"
                               required autofocus>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="description" class="form-label fw-medium">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                                  id="description" name="description"
                                  rows="5"
                                  placeholder="What does this PR do? Reference any related issues here.">{{ old('description', $prTemplate ?? '') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- Options --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Options</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="merge_strategy" class="form-label fw-medium">Merge Strategy</label>
                            <select class="form-select" id="merge_strategy" name="merge_strategy">
                                @foreach ($mergeStrategies as $strategy)
                                    <option value="{{ $strategy->value }}"
                                        {{ old('merge_strategy', 'merge_commit') === $strategy->value ? 'selected' : '' }}>
                                        {{ $strategy->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_draft"
                                       name="is_draft" value="1" {{ old('is_draft') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_draft">
                                    Open as draft
                                    <small class="text-muted d-block">Draft PRs cannot be merged until marked as ready.</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Forge issue link (only shown when Forge integration is active) --}}
            @if ($forgeEnabled)
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            Forge Issue
                            <small class="text-muted fw-normal ms-1">optional</small>
                        </h6>
                    </div>
                    <div class="card-body">
                        <label for="forge_issue_key" class="form-label fw-medium">Issue Key</label>
                        <input type="text"
                               class="form-control @error('forge_issue_key') is-invalid @enderror"
                               id="forge_issue_key" name="forge_issue_key"
                               value="{{ old('forge_issue_key', $forgeIssueKey) }}"
                               placeholder="e.g. PROJ-123">
                        @error('forge_issue_key')<div class="invalid-feedback">{{ $message }}</div>@enderror

                        @if ($forgeIssue)
                            <div class="mt-2 p-2 bg-auto rounded small d-flex align-items-center gap-2">
                                <span class="text-muted">🔗</span>
                                <div>
                                    <strong>{{ $forgeIssue['key'] ?? $forgeIssueKey }}</strong>
                                    — {{ $forgeIssue['summary'] ?? $forgeIssue['title'] ?? 'Issue found' }}
                                    @if (! empty($forgeIssue['status']['name']))
                                        <span class="badge bg-secondary ms-1">{{ $forgeIssue['status']['name'] }}</span>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="form-text">
                                Enter a Forge issue key to link this PR.
                                When merged, Forge will be notified automatically.
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Open Pull Request</button>
                <a href="{{ route('repositories.pull-requests.index', [$organization, $repository]) }}"
                   class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
