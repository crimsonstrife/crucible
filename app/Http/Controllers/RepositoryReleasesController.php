<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Release;
use App\Models\Repository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RepositoryReleasesController extends Controller
{
    public function index(Request $request, Organization $organization, Repository $repository): View
    {
        $this->authorize('viewReleases', $repository);

        $canManage = $request->user()?->can('manageReleases', $repository) ?? false;

        $query = $repository->releases()->with(['author', 'entries', 'links', 'repository.organization']);
        if (! $canManage) {
            $query->published();
        }

        $releases = $query->orderByDesc('published_at')->orderByDesc('created_at')->paginate(20);

        return view('repositories.releases.index', [
            'organization' => $organization,
            'repository'   => $repository,
            'releases'     => $releases,
            'canManage'    => $canManage,
        ]);
    }

    public function show(Request $request, Organization $organization, Repository $repository, Release $release): View
    {
        $this->authorize('viewReleases', $repository);
        abort_unless($release->repository_id === $repository->id, 404);

        $canManage = $request->user()?->can('manageReleases', $repository) ?? false;

        if (! $canManage && ($release->is_draft || $release->published_at === null)) {
            abort(404);
        }

        $release->load(['author', 'entries', 'links', 'repository.organization']);

        return view('repositories.releases.show', [
            'organization' => $organization,
            'repository'   => $repository,
            'release'      => $release,
            'canManage'    => $canManage,
        ]);
    }

    public function create(Request $request, Organization $organization, Repository $repository): View
    {
        $this->authorize('manageReleases', $repository);

        return view('repositories.releases.create', [
            'organization'     => $organization,
            'repository'       => $repository,
            'preselectedTag'   => $request->query('tag') ?: null,
        ]);
    }

    public function edit(Request $request, Organization $organization, Repository $repository, Release $release): View
    {
        $this->authorize('manageReleases', $repository);
        abort_unless($release->repository_id === $repository->id, 404);

        $release->load(['entries', 'links']);

        return view('repositories.releases.edit', [
            'organization' => $organization,
            'repository'   => $repository,
            'release'      => $release,
        ]);
    }
}
