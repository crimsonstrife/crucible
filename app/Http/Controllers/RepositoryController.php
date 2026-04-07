<?php

namespace App\Http\Controllers;

use App\Http\Requests\Repositories\CreateRepositoryRequest;
use App\Http\Requests\Repositories\ImportRepositoryRequest;
use App\Http\Requests\Repositories\UpdateRepositoryRequest;
use App\Models\Organization;
use App\Models\Repository;
use App\Services\RepositoryService;

class RepositoryController extends Controller
{
    public function __construct(protected RepositoryService $service) {}

    public function index(Organization $organization)
    {
        $this->authorize('view', $organization);

        return view('repositories.index', compact('organization'));
    }

    public function create(Organization $organization)
    {
        $this->authorize('view', $organization);

        return view('repositories.create', compact('organization'));
    }

    public function store(CreateRepositoryRequest $request, Organization $organization)
    {
        $repo = $this->service->create($organization, auth()->user(), $request->validated());

        return redirect()->route('repositories.show', [$organization, $repo])->with('success', 'Repository created.');
    }

    public function show(Organization $organization, Repository $repository)
    {
        $this->authorize('view', $repository);

        return view('repositories.show', compact('organization', 'repository'));
    }

    public function edit(Organization $organization, Repository $repository)
    {
        $this->authorize('update', $repository);

        return view('repositories.edit', compact('organization', 'repository'));
    }

    public function update(UpdateRepositoryRequest $request, Organization $organization, Repository $repository)
    {
        $this->authorize('update', $repository);

        $repository->update($request->validated());

        return redirect()->route('repositories.show', [$organization, $repository])->with('success', 'Repository updated.');
    }

    public function destroy(Organization $organization, Repository $repository)
    {
        $this->authorize('delete', $repository);
        $this->service->delete($repository, auth()->user());

        return redirect()->route('organizations.show', $organization)->with('success', 'Repository deleted.');
    }

    public function archive(Organization $organization, Repository $repository)
    {
        $this->authorize('archive', $repository);
        $this->service->archive($repository, auth()->user());

        return back()->with('success', 'Repository archived.');
    }

    public function import(Organization $organization)
    {
        $this->authorize('view', $organization);

        return view('repositories.import', compact('organization'));
    }

    public function importStore(ImportRepositoryRequest $request, Organization $organization)
    {
        $repo = $this->service->importRemote(
            $organization,
            auth()->user(),
            $request->validated('remote_url'),
            $request->safe()->except('remote_url'),
        );

        return redirect()
            ->route('repositories.show', [$organization, $repo])
            ->with('success', 'Import queued. The repository will be cloned in the background.');
    }

    public function sync(Organization $organization, Repository $repository)
    {
        $this->authorize('push', $repository);

        $this->service->syncFromRemote($repository, auth()->user());

        return back()->with('success', 'Sync queued. Branches will update shortly.');
    }
}
