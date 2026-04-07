<x-app-layout>
    <div class="container py-4" style="max-width: 740px;">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>
                <li class="breadcrumb-item">
                    <a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a>
                </li>
                <li class="breadcrumb-item active">Forge Integration</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center gap-3 mb-1">
            <h1 class="h4 fw-bold mb-0">Forge Integration</h1>
            @if ($integration?->is_active)
                <span class="badge bg-success">Linked</span>
            @endif
        </div>
        <p class="text-muted small mb-4">
            Link <strong>{{ $repository->name }}</strong> to a Forge project so that issues in Forge can
            create branches and pull requests directly in this repository (1:1).
        </p>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- System not configured --}}
        @if (! $forgeEnabled)
            <div class="card shadow-sm border-warning mb-4">
                <div class="card-body">
                    <h6 class="card-title text-warning fw-semibold">⚠ Forge not configured</h6>
                    <p class="mb-0 small text-muted">
                        The Forge integration is disabled or not fully configured for this Crucible instance.
                        A site administrator needs to set <code>FORGE_ENABLED=true</code>, <code>FORGE_URL</code>,
                        and the M2M client credentials (<code>FORGE_M2M_CLIENT_ID</code> / <code>FORGE_M2M_CLIENT_SECRET</code>)
                        before repositories can be linked.
                    </p>
                </div>
            </div>
        @elseif (! auth()->user()->forge_user_id)
            <div class="card shadow-sm border-info mb-4">
                <div class="card-body">
                    <h6 class="card-title text-info fw-semibold">Sign in with Forge to load projects</h6>
                    <p class="small text-muted mb-2">
                        Your account is not yet linked to Forge. Sign in via Forge SSO to load your
                        project list — or enter a project ID manually below.
                    </p>
                    <a href="{{ route('auth.forge') }}" class="btn btn-sm btn-outline-primary">
                        Sign in with Forge
                    </a>
                </div>
            </div>
        @endif

        {{-- Current integration status --}}
        @if ($integration)
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">Current Integration</h6>
                    <span class="badge {{ $integration->is_active ? 'bg-success' : 'bg-secondary' }}">
                        {{ $integration->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-4 text-muted">Forge Project</dt>
                        <dd class="col-8 mb-2">
                            <strong>{{ $integration->forge_project_name ?: $integration->forge_project_id }}</strong>
                            @if ($integration->forge_project_name && $integration->forge_project_name !== $integration->forge_project_id)
                                <span class="text-muted ms-1">({{ $integration->forge_project_id }})</span>
                            @endif
                        </dd>

                        <dt class="col-4 text-muted">Forge URL</dt>
                        <dd class="col-8 mb-2">
                            @if ($integration->forge_url)
                                <a href="{{ $integration->forge_url }}" target="_blank" rel="noopener" class="text-decoration-none">
                                    {{ $integration->forge_url }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-4 text-muted">Last Synced</dt>
                        <dd class="col-8 mb-2">
                            {{ $integration->last_synced_at?->diffForHumans() ?? 'Never' }}
                        </dd>

                        <dt class="col-4 text-muted">Forge API Auth</dt>
                        <dd class="col-8 mb-2">
                            <span class="badge bg-success">Global App Token</span>
                            @if ($integration->hasApiToken())
                                <span class="text-muted ms-2">Legacy repo token present</span>
                            @endif
                        </dd>

                        <dt class="col-4 text-muted">API endpoints</dt>
                        <dd class="col-8 mb-0 font-monospace" style="font-size:.75rem; word-break:break-all;">
                            {{ url('/api/v1/' . $organization->slug . '/' . $repository->slug) }}/branches<br>
                            {{ url('/api/v1/' . $organization->slug . '/' . $repository->slug) }}/pull-requests
                        </dd>
                    </dl>
                </div>
                <div class="card-footer d-flex gap-2">
                    <form method="POST" action="{{ route('repositories.forge.sync', [$organization, $repository]) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                {{ ! $forgeEnabled ? 'disabled' : '' }}>
                            ↺ Sync Now
                        </button>
                    </form>
                    <form method="POST" action="{{ route('repositories.forge.destroy', [$organization, $repository]) }}"
                          onsubmit="return confirm('Remove the Forge integration from this repository?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            Remove Integration
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Link / change project form --}}
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">{{ $integration ? 'Change Linked Project' : 'Link a Forge Project' }}</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('repositories.forge.update', [$organization, $repository]) }}">
                    @csrf @method('PUT')

                    @if ($forgeEnabled && ! empty($projects))
                        {{-- Project picker populated from Forge API --}}
                        <div class="mb-3">
                            <label for="forge_project_id" class="form-label fw-medium">
                                Forge Project <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('forge_project_id') is-invalid @enderror"
                                    id="forge_project_id" name="forge_project_id" required>
                                <option value="">— select a project —</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project['id'] }}"
                                            data-name="{{ $project['name'] ?? '' }}"
                                            {{ old('forge_project_id', $integration?->forge_project_id) === (string) $project['id'] ? 'selected' : '' }}>
                                        {{ $project['name'] ?? $project['key'] ?? $project['id'] }}
                                        @if (! empty($project['key'])) ({{ $project['key'] }}) @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('forge_project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <input type="hidden" id="forge_project_name" name="forge_project_name"
                               value="{{ old('forge_project_name', $integration?->forge_project_name) }}">
                    @else
                        {{-- Manual entry fallback --}}
                        <div class="mb-3">
                            <label for="forge_project_id" class="form-label fw-medium">
                                Forge Project ID <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control @error('forge_project_id') is-invalid @enderror"
                                   id="forge_project_id" name="forge_project_id"
                                   value="{{ old('forge_project_id', $integration?->forge_project_id) }}"
                                   placeholder="e.g. 01HXYZ… or the project's UUID"
                                   {{ ! $forgeEnabled ? 'disabled' : '' }}
                                   required>
                            @error('forge_project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="forge_project_name" class="form-label fw-medium">
                                Project Display Name <small class="text-muted fw-normal">(optional)</small>
                            </label>
                            <input type="text"
                                   class="form-control @error('forge_project_name') is-invalid @enderror"
                                   id="forge_project_name" name="forge_project_name"
                                   value="{{ old('forge_project_name', $integration?->forge_project_name) }}"
                                   placeholder="e.g. My Game Project"
                                   {{ ! $forgeEnabled ? 'disabled' : '' }}>
                            @error('forge_project_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary" {{ ! $forgeEnabled ? 'disabled' : '' }}>
                        {{ $integration ? 'Update Integration' : 'Link Project' }}
                    </button>
                </form>
            </div>
        </div>

        @if ($integration)
            <div class="card shadow-sm mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">Crucible App Token</h6>
                    <span class="badge bg-success">Preferred</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Forge should use a single instance-level app token when calling Crucible. To keep the results
                        user-limited, include the acting Forge user ID as <code>for_forge_user_id=&lt;forge-user-id&gt;</code>,
                        <code>for_user=&lt;forge-user-id&gt;</code>, or the <code>X-Forge-User-Id</code> header on every request.
                    </p>

                    <dl class="row small mb-0">
                        <dt class="col-4 text-muted">Generate</dt>
                        <dd class="col-8 font-monospace" style="font-size:.75rem; word-break:break-all;">
                            php artisan app-token:create Forge --abilities=forge.api
                        </dd>

                        <dt class="col-4 text-muted">Forge Env</dt>
                        <dd class="col-8"><code>CRUCIBLE_APP_TOKEN=&lt;copied-token&gt;</code></dd>

                        <dt class="col-4 text-muted">Branch Example</dt>
                        <dd class="col-8 font-monospace" style="font-size:.75rem; word-break:break-all;">
                            GET {{ url('/api/v1/' . $organization->slug . '/' . $repository->slug . '/branches?for_forge_user_id=<forge-user-id>') }}
                        </dd>

                        <dt class="col-4 text-muted">PR Example</dt>
                        <dd class="col-8 font-monospace" style="font-size:.75rem; word-break:break-all;">
                            POST {{ url('/api/v1/' . $organization->slug . '/' . $repository->slug . '/pull-requests') }}
                        </dd>
                    </dl>
                </div>
            </div>
        @endif

        {{-- How it works --}}
        <div class="card shadow-sm mt-4 border-0 bg-info-subtle">
            <div class="card-body small text-muted">
                <strong>How it works</strong>
                <ul class="mb-0 mt-1 ps-3">
                    <li>Forge issues in the linked project can <strong>create branches</strong> directly in <em>{{ $repository->name }}</em>.</li>
                    <li>Forge issues can <strong>open pull requests</strong> against this repository from the issue board.</li>
                    <li>When a PR is <strong>merged in Crucible</strong>, Forge is notified and the linked issue is updated automatically.</li>
                    <li>The relationship is <strong>1:1</strong> — one Forge project maps to exactly one Crucible repository.</li>
                    <li>Forge uses the Crucible API at <code>/api/v1/{{ $organization->slug }}/{{ $repository->slug }}/…</code> authenticated via a global app token and a Forge user scope.</li>
                </ul>
            </div>
        </div>

    </div>

    @if ($forgeEnabled && ! empty($projects))
        <script>
            document.getElementById('forge_project_id').addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                document.getElementById('forge_project_name').value = opt.dataset.name || opt.text;
            });
        </script>
    @endif
</x-app-layout>
