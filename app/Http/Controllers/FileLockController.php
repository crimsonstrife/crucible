<?php

namespace App\Http\Controllers;

use App\Models\FileLock;
use App\Models\Organization;
use App\Models\Repository;
use App\Services\FileLockService;

class FileLockController extends Controller
{
    public function __construct(protected FileLockService $service) {}

    public function index(Organization $organization, Repository $repository)
    {
        $this->authorize('manageLocks', $repository);
        $locks = $this->service->listLocks($repository);

        return view('repositories.locks', compact('organization', 'repository', 'locks'));
    }

    public function destroy(Organization $organization, Repository $repository, FileLock $fileLock)
    {
        abort_unless($fileLock->repository_id === $repository->id, 404);
        $this->authorize('unlock', $fileLock);
        $this->service->forceUnlock($repository, auth()->user(), $fileLock->path);

        return back()->with('success', 'Lock released.');
    }
}
