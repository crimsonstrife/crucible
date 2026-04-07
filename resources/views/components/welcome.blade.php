<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span style="font-size: 1.75rem;">&#9878;</span>
            <h4 class="mb-0 fw-bold">Crucible</h4>
        </div>

        <h5 class="fw-semibold mb-3">Welcome to Crucible</h5>

        <p class="text-muted mb-0">
            Crucible is a source control management platform built for game development teams.
            Manage your repositories, track file locks, coordinate with your team, and ship great games.
        </p>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.5rem; height: 1.5rem; color: #6c757d;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                    </svg>
                    <h6 class="mb-0 fw-semibold">Repositories</h6>
                </div>
                <p class="text-muted small mb-3">
                    Create and manage Git and Perforce repositories for your game projects. Track binary assets with LFS support.
                </p>
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-primary">View Repositories</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.5rem; height: 1.5rem; color: #6c757d;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                    </svg>
                    <h6 class="mb-0 fw-semibold">Teams</h6>
                </div>
                <p class="text-muted small mb-3">
                    Collaborate with your studio team. Manage roles, permissions, and access control for your repositories.
                </p>
                @if (Laravel\Jetstream\Jetstream::hasTeamFeatures())
                    <a href="{{ route('teams.show', Auth::user()->currentTeam->id) }}" class="btn btn-sm btn-outline-primary">Team Settings</a>
                @endif
            </div>
        </div>
    </div>
</div>
