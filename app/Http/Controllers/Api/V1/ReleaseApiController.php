<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReleaseCategory;
use App\Http\Controllers\Api\V1\Concerns\ResolvesReleaseReader;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Release;
use App\Models\Repository;
use App\Services\NativeGitRepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReleaseApiController extends Controller
{
    use ResolvesReleaseReader;

    public function __construct(
        protected NativeGitRepositoryService $git,
    ) {}

    // ── Reads (no auth middleware on the routes — optional auth here) ────────

    public function index(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);

        ['user' => $user, 'releaseToken' => $releaseToken] = $this->resolveReader($request, $repository);

        if ($releaseToken === null && ! Gate::forUser($user)->allows('viewReleases', $repository)) {
            abort($user ? 403 : 404);
        }

        $canManage = $user !== null && $user->can('manageReleases', $repository);

        $query = $repository->releases()->with(['author', 'entries']);
        if (! $canManage) {
            $query->published();
        }

        $limit = max(1, min(200, (int) $request->query('limit', 30)));
        $skip  = max(0, (int) $request->query('skip', 0));

        $total = (clone $query)->count();
        $rows  = $query->orderByDesc('published_at')->orderByDesc('created_at')->skip($skip)->take($limit)->get();
        $nextSkip = ($skip + $rows->count() < $total) ? $skip + $rows->count() : null;

        return response()->json([
            'data'      => $rows->map(fn (Release $r) => $this->transform($r))->all(),
            'total'     => $total,
            'next_skip' => $nextSkip,
        ]);
    }

    public function show(Request $request, Organization $organization, Repository $repository, Release $release): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        abort_unless($release->repository_id === $repository->id, 404);

        ['user' => $user, 'releaseToken' => $releaseToken] = $this->resolveReader($request, $repository);

        if ($releaseToken === null && ! Gate::forUser($user)->allows('viewReleases', $repository)) {
            abort($user ? 403 : 404);
        }

        $canManage = $user !== null && $user->can('manageReleases', $repository);

        if (! $canManage && ($release->is_draft || $release->published_at === null)) {
            abort(404);
        }

        $release->load(['author', 'entries']);

        return response()->json(['data' => $this->transform($release)]);
    }

    public function latest(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);

        ['user' => $user, 'releaseToken' => $releaseToken] = $this->resolveReader($request, $repository);

        if ($releaseToken === null && ! Gate::forUser($user)->allows('viewReleases', $repository)) {
            abort($user ? 403 : 404);
        }

        $latest = $repository->releases()
            ->published()
            ->where('is_latest', true)
            ->with(['author', 'entries'])
            ->first();

        if ($latest === null) {
            return response()->json(['message' => 'No published release.'], 404);
        }

        return response()->json(['data' => $this->transform($latest)]);
    }

    // ── Writes (routes wrap these in auth:sanctum) ───────────────────────────

    public function store(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('manageReleases', $repository), 403);

        $validated = $this->validateRelease($request, $repository, isUpdate: false);

        $commitSha = $this->git->resolveSha($repository, $validated['tag_name']);
        if ($commitSha === null) {
            return response()->json([
                'errors' => ['tag_name' => ['Tag not found in repository.']],
                'message' => 'Tag not found in repository.',
            ], 422);
        }

        $release = DB::transaction(function () use ($repository, $user, $validated, $commitSha) {
            $isDraft = (bool) ($validated['is_draft'] ?? false);
            $publishedAt = $isDraft ? null : ($validated['published_at'] ?? now());

            $release = $repository->releases()->create([
                'author_id'     => $user->id,
                'tag_name'      => $validated['tag_name'],
                'commit_sha'    => $commitSha,
                'name'          => $validated['name'] ?? null,
                'body'          => $validated['body'] ?? null,
                'is_draft'      => $isDraft,
                'is_prerelease' => (bool) ($validated['is_prerelease'] ?? false),
                'published_at'  => $publishedAt,
            ]);

            $this->syncEntries($release, $validated['entries'] ?? []);

            return $release;
        });

        $release->load(['author', 'entries']);

        return response()->json(['data' => $this->transform($release)], 201);
    }

    public function update(Request $request, Organization $organization, Repository $repository, Release $release): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        abort_unless($release->repository_id === $repository->id, 404);
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('manageReleases', $repository), 403);

        $validated = $this->validateRelease($request, $repository, isUpdate: true, currentReleaseId: $release->id);

        DB::transaction(function () use ($release, $repository, $validated) {
            if (array_key_exists('tag_name', $validated) && $validated['tag_name'] !== $release->tag_name) {
                $commitSha = $this->git->resolveSha($repository, $validated['tag_name']);
                abort_if($commitSha === null, 422, 'Tag not found in repository.');
                $release->tag_name = $validated['tag_name'];
                $release->commit_sha = $commitSha;
            }

            foreach (['name', 'body', 'is_draft', 'is_prerelease'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $release->{$field} = $validated[$field];
                }
            }

            if ($release->is_draft) {
                $release->published_at = null;
            } elseif ($release->published_at === null) {
                $release->published_at = $validated['published_at'] ?? now();
            } elseif (array_key_exists('published_at', $validated)) {
                $release->published_at = $validated['published_at'];
            }

            $release->save();

            if (array_key_exists('entries', $validated)) {
                $this->syncEntries($release, $validated['entries']);
            }
        });

        $release->refresh()->load(['author', 'entries']);

        return response()->json(['data' => $this->transform($release)]);
    }

    public function destroy(Request $request, Organization $organization, Repository $repository, Release $release): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        abort_unless($release->repository_id === $repository->id, 404);
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('manageReleases', $repository), 403);

        $release->delete();

        return response()->json(null, 204);
    }

    public function tagsAvailable(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('manageReleases', $repository), 403);

        $usedTags = $repository->releases()->pluck('tag_name')->all();
        $tags = collect($this->git->tags($repository))
            ->filter(fn (array $t) => ! in_array($t['name'] ?? null, $usedTags, true))
            ->values()
            ->map(fn (array $t) => [
                'name'    => $t['name'] ?? null,
                'sha'     => $t['sha'] ?? null,
                'subject' => $t['subject'] ?? null,
                'date'    => $t['date'] ?? null,
            ]);

        return response()->json(['data' => $tags]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function validateRelease(Request $request, Repository $repository, bool $isUpdate, ?string $currentReleaseId = null): array
    {
        $tagNameRule = $isUpdate ? ['sometimes', 'string', 'max:255'] : ['required', 'string', 'max:255'];
        $uniqueTag = Rule::unique('releases', 'tag_name')->where('repository_id', $repository->id);
        if ($isUpdate && $currentReleaseId !== null) {
            $uniqueTag = $uniqueTag->ignore($currentReleaseId);
        }
        $tagNameRule[] = $uniqueTag;

        $categoryValues = array_map(fn ($c) => $c->value, ReleaseCategory::cases());

        return $request->validate([
            'tag_name'              => $tagNameRule,
            'name'                  => ['nullable', 'string', 'max:255'],
            'body'                  => ['nullable', 'string'],
            'is_draft'              => ['boolean'],
            'is_prerelease'         => ['boolean'],
            'published_at'          => ['nullable', 'date'],
            'entries'               => ['array'],
            'entries.*.category'    => ['required_with:entries.*', 'string', Rule::in($categoryValues)],
            'entries.*.description' => ['required_with:entries.*', 'string', 'max:2000'],
            'entries.*.position'    => ['nullable', 'integer', 'min:0'],
        ]);
    }

    protected function syncEntries(Release $release, array $entries): void
    {
        $release->entries()->delete();

        foreach ($entries as $i => $entry) {
            $release->entries()->create([
                'category'    => $entry['category'],
                'description' => $entry['description'],
                'position'    => $entry['position'] ?? $i,
            ]);
        }
    }

    protected function transform(Release $release): array
    {
        return [
            'id'            => $release->id,
            'tag_name'      => $release->tag_name,
            'name'          => $release->name,
            'slug'          => $release->slug,
            'commit_sha'    => $release->commit_sha,
            'body'          => $release->body,
            'is_draft'      => $release->is_draft,
            'is_prerelease' => $release->is_prerelease,
            'is_latest'     => $release->is_latest,
            'published_at'  => $release->published_at?->toIso8601String(),
            'created_at'    => $release->created_at?->toIso8601String(),
            'updated_at'    => $release->updated_at?->toIso8601String(),
            'author'        => $release->author ? [
                'id'   => $release->author->id,
                'name' => $release->author->name,
            ] : null,
            'entries' => $release->entries->map(fn ($e) => [
                'id'          => $e->id,
                'category'    => $e->category->value,
                'description' => $e->description,
                'position'    => $e->position,
            ])->all(),
        ];
    }
}
