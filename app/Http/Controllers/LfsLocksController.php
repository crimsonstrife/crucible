<?php

namespace App\Http\Controllers;

use App\Contracts\RepositoryDriverInterface;
use App\Http\Controllers\Concerns\InteractsWithGitTransport;
use App\Services\FileLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Git LFS File Locking API
 *
 * Implements the LFS locking protocol described in:
 *   https://github.com/git-lfs/git-lfs/blob/main/docs/api/locking.md
 *
 * All endpoints require authentication. All responses use
 * Content-Type: application/vnd.git-lfs+json.
 *
 * Endpoints (all under /{org}/{repo}.git/info/lfs/locks):
 *   POST   /              → create a lock
 *   GET    /              → list locks
 *   POST   /verify        → verify locks (split into ours / theirs)
 *   DELETE /{id}          → release a lock
 */
class LfsLocksController extends Controller
{
    use InteractsWithGitTransport;

    const LFS_JSON = 'application/vnd.git-lfs+json';

    public function __construct(
        protected FileLockService $fileLockService,
        protected RepositoryDriverInterface $repositoryDriver,
    ) {}

    /**
     * POST /{org}/{repo}.git/info/lfs/locks
     *
     * Create a new file lock.
     */
    public function create(Request $request, string $org, string $repo): JsonResponse
    {
        $repository = $this->resolveTransportRepository($org, $repo);

        if ($challenge = $this->checkTransportWriteAccess($repository)) {
            return response()->json(
                ['message' => 'Authentication required.'],
                401,
                ['Content-Type' => self::LFS_JSON],
            );
        }

        if ($repository->is_archived) {
            return response()->json(
                ['message' => 'Repository is archived and read-only.'],
                403,
                ['Content-Type' => self::LFS_JSON],
            );
        }

        $validated = $request->validate([
            'path'     => ['required', 'string', 'max:1024'],
            'ref'      => ['nullable', 'array'],
            'ref.name' => ['nullable', 'string'],
        ]);

        $ref = $validated['ref']['name'] ?? null;

        try {
            $lock = $this->fileLockService->lock(
                $repository,
                Auth::user(),
                $validated['path'],
                $ref,
            );
        } catch (RuntimeException $e) {
            // File is already locked — return 409 with the existing lock
            $existing = $this->fileLockService->findLockByPath($repository, $validated['path']);

            return response()->json([
                'lock'    => $existing?->toLfsArray(),
                'message' => $e->getMessage(),
            ], 409, ['Content-Type' => self::LFS_JSON]);
        }

        return response()->json(
            ['lock' => $lock->toLfsArray()],
            201,
            ['Content-Type' => self::LFS_JSON],
        );
    }

    /**
     * GET /{org}/{repo}.git/info/lfs/locks
     *
     * List locks for the repository.
     * Query params: path, id, cursor, limit
     */
    public function index(Request $request, string $org, string $repo): JsonResponse
    {
        $repository = $this->resolveTransportRepository($org, $repo);

        if ($challenge = $this->checkTransportReadAccess($repository)) {
            return response()->json(
                ['message' => 'Authentication required.'],
                401,
                ['Content-Type' => self::LFS_JSON],
            );
        }

        $filters = array_filter([
            'path' => $request->query('path'),
            'id'   => $request->query('id'),
        ]);

        $limit  = max(1, min((int) ($request->query('limit', 100)), 100));
        $cursor = $request->query('cursor');

        $result = $this->fileLockService->paginateLocks($repository, $cursor, $limit, $filters);

        $payload = [
            'locks' => $result['locks']->map->toLfsArray()->values(),
        ];

        if ($result['next_cursor'] !== null) {
            $payload['next_cursor'] = $result['next_cursor'];
        }

        return response()->json($payload, 200, ['Content-Type' => self::LFS_JSON]);
    }

    /**
     * POST /{org}/{repo}.git/info/lfs/locks/verify
     *
     * Split all locks into "ours" (owned by the authenticated user) and
     * "theirs" (owned by someone else).
     */
    public function verify(Request $request, string $org, string $repo): JsonResponse
    {
        $repository = $this->resolveTransportRepository($org, $repo);

        // verify always requires a logged-in user
        if (Auth::guest()) {
            return response()->json(
                ['message' => 'Authentication required.'],
                401,
                [
                    'Content-Type'    => self::LFS_JSON,
                    'WWW-Authenticate' => 'Basic realm="Crucible"',
                ],
            );
        }

        if ($challenge = $this->checkTransportReadAccess($repository)) {
            return response()->json(
                ['message' => 'You do not have read access to this repository.'],
                403,
                ['Content-Type' => self::LFS_JSON],
            );
        }

        $limit  = max(1, min((int) ($request->input('limit', 100)), 100));
        $cursor = $request->input('cursor');

        $result = $this->fileLockService->verifyLocks($repository, Auth::user(), $cursor, $limit);

        $payload = [
            'ours'   => $result['ours']->map->toLfsArray()->values(),
            'theirs' => $result['theirs']->map->toLfsArray()->values(),
        ];

        if ($result['next_cursor'] !== null) {
            $payload['next_cursor'] = $result['next_cursor'];
        }

        return response()->json($payload, 200, ['Content-Type' => self::LFS_JSON]);
    }

    /**
     * DELETE /{org}/{repo}.git/info/lfs/locks/{id}
     *
     * Release a lock. The authenticated user must own the lock, or
     * must pass "force": true and have push access.
     */
    public function destroy(Request $request, string $org, string $repo, string $id): JsonResponse
    {
        $repository = $this->resolveTransportRepository($org, $repo);

        if ($challenge = $this->checkTransportWriteAccess($repository)) {
            return response()->json(
                ['message' => 'Authentication required.'],
                401,
                ['Content-Type' => self::LFS_JSON],
            );
        }

        $lock = $this->fileLockService->findLockById($repository, $id);

        if ($lock === null) {
            return response()->json(
                ['message' => "Lock {$id} not found."],
                404,
                ['Content-Type' => self::LFS_JSON],
            );
        }

        $force = (bool) $request->input('force', false);
        $user  = Auth::user();

        if (! $force && ! $lock->isOwnedBy($user)) {
            return response()->json([
                'lock'    => $lock->toLfsArray(),
                'message' => 'You do not own this lock. Pass "force": true to release it.',
            ], 403, ['Content-Type' => self::LFS_JSON]);
        }

        $deleted = $this->fileLockService->deleteLock($lock);

        return response()->json(
            ['lock' => $deleted->toLfsArray()],
            200,
            ['Content-Type' => self::LFS_JSON],
        );
    }
}
