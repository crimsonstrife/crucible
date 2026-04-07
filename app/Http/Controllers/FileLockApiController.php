<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileLocks\LockFileRequest;
use App\Models\FileLock;
use App\Models\Repository;
use App\Services\FileLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class FileLockApiController extends Controller
{
    public function index(Request $request, Repository $repository, FileLockService $service): JsonResponse
    {
        $this->authorize('view', $repository);

        $locks = $service->paginateLocks(
            $repository,
            $request->query('cursor'),
            (int) $request->integer('limit', 100),
            $request->only(['path', 'id']),
        );

        return response()->json([
            'locks' => $locks['locks']->map(fn (FileLock $lock) => $lock->toLfsArray())->values(),
            'next_cursor' => $locks['next_cursor'],
        ]);
    }

    public function store(LockFileRequest $request, Repository $repository, FileLockService $service): JsonResponse
    {
        $this->authorize('push', $repository);

        try {
            $lock = $service->lock(
                $repository,
                $request->user(),
                $request->string('path')->toString(),
                $request->filled('ref') ? $request->string('ref')->toString() : null,
            );
        } catch (RuntimeException $exception) {
            $existingLock = $service->findLockByPath($repository, $request->string('path')->toString());

            return response()->json([
                'message' => $exception->getMessage(),
                'lock' => $existingLock?->toLfsArray(),
            ], 409);
        }

        return response()->json([
            'lock' => $lock->toLfsArray(),
        ], 201);
    }

    public function verify(Request $request, Repository $repository, FileLockService $service): JsonResponse
    {
        $this->authorize('push', $repository);

        $locks = $service->verifyLocks(
            $repository,
            $request->user(),
            $request->input('cursor'),
            (int) $request->integer('limit', 100),
        );

        return response()->json([
            'ours' => $locks['ours']->map(fn (FileLock $lock) => $lock->toLfsArray())->values(),
            'theirs' => $locks['theirs']->map(fn (FileLock $lock) => $lock->toLfsArray())->values(),
            'next_cursor' => $locks['next_cursor'],
        ]);
    }

    public function unlock(Request $request, Repository $repository, FileLock $lock, FileLockService $service): JsonResponse
    {
        abort_unless($lock->repository_id === $repository->id, 404);

        $force = $request->boolean('force');

        if (! $force && ! $lock->isOwnedBy($request->user())) {
            return response()->json([
                'message' => 'This lock is owned by another user. Retry with force=true if you have permission to unlock it.',
                'lock' => $lock->loadMissing('lockedBy')->toLfsArray(),
            ], 403);
        }

        $this->authorize('unlock', $lock);

        $deletedLock = $service->deleteLock($lock);

        return response()->json([
            'lock' => $deletedLock->toLfsArray(),
        ]);
    }

    public function destroy(Repository $repository, FileLock $lock, FileLockService $service): JsonResponse
    {
        abort_unless($lock->repository_id === $repository->id, 404);

        $this->authorize('unlock', $lock);

        $service->deleteLock($lock);

        return response()->json(status: 204);
    }
}
