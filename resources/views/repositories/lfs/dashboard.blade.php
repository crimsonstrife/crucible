<x-app-layout>
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                <li class="breadcrumb-item">
                    <a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('repositories.show', [$organization, $repository]) }}">{{ $repository->name }}</a>
                </li>
                <li class="breadcrumb-item active">LFS Storage</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center gap-2 mb-4">
            <x-octicon name="package" size="20" class="text-body-secondary" />
            <h1 class="h3 fw-bold mb-0">LFS Storage</h1>
            @if ($repository->lfs_enabled)
                <span class="badge bg-success">Enabled</span>
            @else
                <span class="badge bg-secondary">Disabled</span>
            @endif
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row g-4 mb-4">
            {{-- Storage overview cards --}}
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small text-uppercase fw-semibold">Total Objects</div>
                        <div class="display-6 fw-bold">{{ number_format($stats['total_objects']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small text-uppercase fw-semibold">Total Size</div>
                        <div class="display-6 fw-bold">
                            @if ($stats['total_size'] >= 1073741824)
                                {{ number_format($stats['total_size'] / 1073741824, 2) }} GB
                            @elseif ($stats['total_size'] >= 1048576)
                                {{ number_format($stats['total_size'] / 1048576, 1) }} MB
                            @elseif ($stats['total_size'] >= 1024)
                                {{ number_format($stats['total_size'] / 1024, 1) }} KB
                            @else
                                {{ number_format($stats['total_size']) }} B
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small text-uppercase fw-semibold">LFS Policies</div>
                        <div class="display-6 fw-bold">{{ $policies->count() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Storage by type --}}
                @if (count($stats['by_mime_type']) > 0)
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0 fw-semibold">Storage by Type</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>MIME Type</th>
                                        <th class="text-end">Objects</th>
                                        <th class="text-end">Size</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($stats['by_mime_type'] as $row)
                                        <tr>
                                            <td><code class="small">{{ $row['mime_type'] }}</code></td>
                                            <td class="text-end">{{ number_format($row['count']) }}</td>
                                            <td class="text-end">
                                                @if ($row['total_size'] >= 1048576)
                                                    {{ number_format($row['total_size'] / 1048576, 1) }} MB
                                                @elseif ($row['total_size'] >= 1024)
                                                    {{ number_format($row['total_size'] / 1024, 1) }} KB
                                                @else
                                                    {{ number_format($row['total_size']) }} B
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Largest objects --}}
                @if (count($largestObjects) > 0)
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0 fw-semibold">Largest Objects</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>OID</th>
                                        <th>Type</th>
                                        <th class="text-end">Size</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($largestObjects as $obj)
                                        <tr>
                                            <td><code class="small">{{ substr($obj['oid'], 0, 12) }}...</code></td>
                                            <td><code class="small">{{ $obj['mime_type'] ?? 'unknown' }}</code></td>
                                            <td class="text-end">
                                                @if ($obj['size'] >= 1048576)
                                                    {{ number_format($obj['size'] / 1048576, 1) }} MB
                                                @elseif ($obj['size'] >= 1024)
                                                    {{ number_format($obj['size'] / 1024, 1) }} KB
                                                @else
                                                    {{ number_format($obj['size']) }} B
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                {{-- LFS Policies --}}
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">LFS Tracking Policies</h6>
                        <span class="badge bg-secondary">{{ $policies->count() }}</span>
                    </div>
                    @if ($policies->isNotEmpty())
                        <ul class="list-group list-group-flush">
                            @foreach ($policies as $policy)
                                <li class="list-group-item py-2">
                                    <code class="small">{{ $policy->pattern }}</code>
                                    @if ($policy->description)
                                        <div class="text-muted small">{{ $policy->description }}</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="card-body text-muted small">
                            No LFS policies configured. Apply a template via the API to get started.
                        </div>
                    @endif
                </div>

                {{-- Lock Policies --}}
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">Lock Policies</h6>
                        <span class="badge bg-secondary">{{ $lockPolicies->count() }}</span>
                    </div>
                    @if ($lockPolicies->isNotEmpty())
                        <ul class="list-group list-group-flush">
                            @foreach ($lockPolicies as $policy)
                                <li class="list-group-item py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <code class="small">{{ $policy->pattern }}</code>
                                        <span class="badge {{ $policy->isMandatory() ? 'text-bg-danger' : 'text-bg-warning' }}">
                                            {{ $policy->lock_mode->label() }}
                                        </span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="card-body text-muted small">No lock policies configured.</div>
                    @endif
                </div>

                {{-- Engine templates --}}
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">Engine Templates</h6>
                    </div>
                    <div class="card-body">
                        @can('update', $repository)
                            <p class="small text-muted mb-3">
                                Apply a curated set of LFS tracking patterns for a specific engine. Existing patterns are preserved.
                            </p>
                            <form
                                method="POST"
                                action="{{ route('repositories.lfs.apply-template', [$organization, $repository]) }}"
                                onsubmit="return confirm('Apply this template to the repository? Existing LFS policies will be preserved.');"
                            >
                                @csrf
                                <div class="mb-3">
                                    <label for="lfs-template-select" class="form-label small fw-semibold">Template</label>
                                    <select id="lfs-template-select" name="template" class="form-select form-select-sm" required>
                                        @foreach ($templates as $template)
                                            <option value="{{ $template }}">{{ \Illuminate\Support\Str::headline($template) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <x-octicon name="check" size="14" /> Apply Template
                                </button>
                            </form>
                        @else
                            <p class="small text-muted mb-2">Available templates:</p>
                            <ul class="small mb-0">
                                @foreach ($templates as $template)
                                    <li><code>{{ $template }}</code></li>
                                @endforeach
                            </ul>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
