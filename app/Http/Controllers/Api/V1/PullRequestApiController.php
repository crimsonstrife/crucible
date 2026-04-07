<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PullRequestStatus;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithForgeApi;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Services\PullRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * API endpoints for pull-request operations.
 * Consumed by Forge (and any other authorised API client) to search/create/update PRs.
 */
class PullRequestApiController extends Controller
{
    use InteractsWithForgeApi;

    public function __construct(
        protected PullRequestService $service,
    ) {}

    /**
     * GET /api/v1/{organization}/{repository}/pull-requests?q=&status=open
     *
     * Returns [{number, title, state, head, base, author, body, forge_issue_key, url}]
     */
    public function index(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $this->ensureForgeIntegrationMatchesRepository($request, $repository);

        $user = $this->apiUser($request);

        abort_unless($user->can('view', $repository), 403);

        $q = (string) $request->query('q', '');
        $status = PullRequestStatus::tryFrom((string) $request->query('status', 'open'))
            ?? PullRequestStatus::Open;
        $limit = min((int) $request->query('per_page', 20), 100);

        $query = $repository->pullRequests()
            ->with('author')
            ->where('status', $status);

        if ($q !== '') {
            $query->where(function ($q2) use ($q) {
                $q2->where('title', 'like', "%{$q}%")
                    ->orWhere('source_branch', 'like', "%{$q}%");
            });
        }

        $prs = $query->orderByDesc('created_at')->limit($limit)->get();

        $data = $prs->map(fn (PullRequest $pr) => $this->formatPr($pr, $organization, $repository));

        return response()->json(['data' => $data->values()]);
    }

    /**
     * POST /api/v1/{organization}/{repository}/pull-requests
     *
     * Body: { title, head, base, body?, forge_issue_key? }
     * Returns {number, title, state, head, base, author, body, forge_issue_key, url}
     */
    public function store(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $this->ensureForgeIntegrationMatchesRepository($request, $repository);

        $user = $this->apiUser($request);

        abort_unless($user->can('push', $repository), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'head' => ['required', 'string', 'max:255'],
            'base' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'forge_issue_key' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $pr = $this->service->create(
                $repository,
                $user,
                $data['title'],
                $data['body'] ?? null,
                $data['head'],
                $data['base'],
                $data['forge_issue_key'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(
            ['data' => $this->formatPr($pr, $organization, $repository)],
            201,
        );
    }

    /**
     * GET /api/v1/{organization}/{repository}/pull-requests/{number}
     *
     * Returns a single PR by its sequential number within the repository.
     */
    public function show(Request $request, Organization $organization, Repository $repository, int $number): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $this->ensureForgeIntegrationMatchesRepository($request, $repository);

        $user = $this->apiUser($request);

        abort_unless($user->can('view', $repository), 403);

        $pr = $repository->pullRequests()->where('number', $number)->firstOrFail();

        return response()->json(['data' => $this->formatPr($pr, $organization, $repository)]);
    }

    /**
     * PATCH /api/v1/{organization}/{repository}/pull-requests/{number}
     *
     * Allows Forge to post-link an issue key to an already-opened PR,
     * or update the title/body after the fact.
     *
     * Body: { forge_issue_key?, title?, body? }
     */
    public function update(Request $request, Organization $organization, Repository $repository, int $number): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $this->ensureForgeIntegrationMatchesRepository($request, $repository);

        $user = $this->apiUser($request);

        abort_unless($user->can('push', $repository), 403);

        $pr = $repository->pullRequests()->where('number', $number)->firstOrFail();

        // Only open PRs can be mutated.
        if ($pr->status !== PullRequestStatus::Open) {
            return response()->json(['message' => 'Only open pull requests can be updated.'], 422);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'forge_issue_key' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $updates = [];

        if (array_key_exists('title', $data)) {
            $updates['title'] = $data['title'];
        }

        if (array_key_exists('body', $data)) {
            $updates['description'] = $data['body'];
        }

        if (array_key_exists('forge_issue_key', $data)) {
            $updates['forge_issue_key'] = $data['forge_issue_key'];
        }

        $pr->fill($updates)->save();

        return response()->json(['data' => $this->formatPr($pr->fresh(), $organization, $repository)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatPr(PullRequest $pr, Organization $organization, Repository $repository): array
    {
        return [
            'number' => $pr->number,
            'title' => $pr->title,
            'state' => $pr->status->value,
            'head' => $pr->source_branch,
            'base' => $pr->target_branch,
            'author' => $pr->author?->name,
            'body' => $pr->description,
            'forge_issue_key' => $pr->forge_issue_key,
            'url' => route('repositories.pull-requests.show', [$organization, $repository, $pr]),
        ];
    }
}
