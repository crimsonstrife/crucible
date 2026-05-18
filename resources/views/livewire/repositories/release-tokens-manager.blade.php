<div class="card shadow-sm mt-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Release Tokens</h6>
        <small class="text-muted">Read-only API tokens scoped to this repository's changelog.</small>
    </div>
    <div class="card-body">

        @if (session('release-tokens-success'))
            <div class="alert alert-success py-2 mb-3">{{ session('release-tokens-success') }}</div>
        @endif

        @if ($generatedTokenRaw)
            <div class="alert alert-warning mb-3">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div class="flex-grow-1">
                        <strong>Copy this token now &mdash; you won't see it again.</strong>
                        <div class="text-muted small mb-2">Token: <code>{{ $generatedTokenName }}</code></div>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace"
                                   value="{{ $generatedTokenRaw }}" readonly
                                   onclick="this.select()" id="generated-release-token">
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="navigator.clipboard.writeText(document.getElementById('generated-release-token').value)">
                                Copy
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-close" aria-label="Dismiss"
                            wire:click="dismissGeneratedToken"></button>
                </div>
            </div>
        @endif

        <form wire:submit="createToken" class="row g-3 align-items-end mb-4">
            <div class="col-md-6">
                <label for="token-name" class="form-label">Token name</label>
                <input id="token-name" type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       wire:model="name" placeholder="e.g. Game website prod" autocomplete="off">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label for="token-expires" class="form-label">Expires</label>
                <select id="token-expires"
                        class="form-select @error('expiresIn') is-invalid @enderror"
                        wire:model="expiresIn">
                    <option value="never">Never</option>
                    <option value="30d">In 30 days</option>
                    <option value="90d">In 90 days</option>
                    <option value="1y">In 1 year</option>
                </select>
                @error('expiresIn')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Generate token</button>
            </div>
        </form>

        @if ($tokens->isEmpty())
            <p class="text-muted mb-0">No release tokens yet. Generated tokens can read this repo's published releases without a user login.</p>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Prefix</th>
                            <th>Created by</th>
                            <th>Last used</th>
                            <th>Expires</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tokens as $token)
                            <tr>
                                <td class="fw-medium">{{ $token->name }}</td>
                                <td>
                                    <code class="text-muted">{{ $token->token_prefix }}…</code>
                                </td>
                                <td class="text-muted small">
                                    {{ $token->creator?->name ?? '—' }}
                                </td>
                                <td class="text-muted small">
                                    {{ $token->last_used_at?->diffForHumans() ?? 'Never' }}
                                </td>
                                <td class="text-muted small">
                                    @if ($token->expires_at === null)
                                        Never
                                    @elseif ($token->isExpired())
                                        <span class="text-danger">Expired {{ $token->expires_at->diffForHumans() }}</span>
                                    @else
                                        {{ $token->expires_at->diffForHumans() }}
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            wire:click="revokeToken('{{ $token->id }}')"
                                            wire:confirm="Revoke this token? Any service using it will immediately stop working.">
                                        Revoke
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
