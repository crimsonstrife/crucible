<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithForgeApi;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use App\Services\NativeGitRepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API endpoints for reading commit history and single-commit metadata.
 *
 * Consumed primarily by the Crucible UE plugin's History dialog, but usable
 * by any Forge-api-authenticated client. These endpoints are read-only and
 * delegate to NativeGitRepositoryService::log / commitCount / commitShow.
 */
class CommitApiController extends Controller
{
    use InteractsWithForgeApi;

    public function __construct(
        protected NativeGitRepositoryService $git,
    ) {}

    /**
     * GET /api/v1/{organization}/{repository}/commits
     *
     * Query params:
     *   ref   (optional) — branch/tag/sha to log from; defaults to the
     *                      repository's default branch
     *   path  (optional) — limit history to commits touching this path
     *   limit (optional) — max entries to return (1..200, default 30)
     *   skip  (optional) — number of entries to skip (pagination cursor)
     *
     * Returns: {
     *   data: [{sha, short_sha, subject, author_name, author_email, author_date}],
     *   total: int,
     *   next_skip: int|null
     * }
     */
    public function index(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $this->ensureForgeIntegrationMatchesRepository($request, $repository);

        $user = $this->apiUser($request);
        abort_unless($user->can('view', $repository), 403);

        $ref   = $request->query('ref') ?: null;
        $path  = $request->query('path') ?: null;
        $limit = max(1, min(200, (int) $request->query('limit', 30)));
        $skip  = max(0, (int) $request->query('skip', 0));

        $commits = $this->git->log($repository, $ref, $limit, $skip, $path);
        $total   = $this->git->commitCount($repository, $ref, $path);

        $data = array_map(fn (array $c) => [
            'sha'          => $c['sha'],
            'short_sha'    => $c['short_sha'],
            'subject'      => $c['subject'],
            'author_name'  => $c['author_name'],
            'author_email' => $c['author_email'],
            'author_date'  => $c['author_date']?->toIso8601String(),
        ], $commits);

        $nextSkip = ($skip + count($commits) < $total) ? $skip + count($commits) : null;

        return response()->json([
            'data'      => $data,
            'total'     => $total,
            'next_skip' => $nextSkip,
        ]);
    }

    /**
     * GET /api/v1/{organization}/{repository}/commits/{sha}
     *
     * Returns full metadata for a single commit including file stat summary
     * and unified diff. 404 if the sha is not a valid commit.
     */
    public function show(Request $request, Organization $organization, Repository $repository, string $sha): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $this->ensureForgeIntegrationMatchesRepository($request, $repository);

        $user = $this->apiUser($request);
        abort_unless($user->can('view', $repository), 403);

        $commit = $this->git->commitShow($repository, $sha);

        if ($commit === null) {
            return response()->json(['message' => 'Commit not found.'], 404);
        }

        return response()->json([
            'data' => [
                'sha'          => $commit['sha'],
                'short_sha'    => $commit['short_sha'],
                'subject'      => $commit['subject'],
                'body'         => $commit['body'],
                'author_name'  => $commit['author_name'],
                'author_email' => $commit['author_email'],
                'author_date'  => $commit['author_date']?->toIso8601String(),
                'parent_shas'  => $commit['parent_shas'],
                'stat'         => $commit['stat'],
                'diff'         => $commit['diff'],
            ],
        ]);
    }
}
