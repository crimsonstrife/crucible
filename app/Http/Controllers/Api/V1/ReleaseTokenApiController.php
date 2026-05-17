<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ReleaseToken;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseTokenApiController extends Controller
{
    public function index(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('manageReleases', $repository), 403);

        $tokens = $repository->releaseTokens()
            ->with('creator')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ReleaseToken $t) => $this->transform($t));

        return response()->json(['data' => $tokens]);
    }

    public function store(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('manageReleases', $repository), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $expiresAt = isset($validated['expires_at'])
            ? \Carbon\Carbon::parse($validated['expires_at'])
            : null;

        ['token' => $token, 'raw' => $raw] = ReleaseToken::generate(
            $repository,
            $user,
            $validated['name'],
            $expiresAt,
        );

        $token->load('creator');

        return response()->json([
            'data' => $this->transform($token) + ['token' => $raw],
            'warning' => 'Store this token now — it cannot be retrieved later.',
        ], 201);
    }

    public function destroy(Request $request, Organization $organization, Repository $repository, ReleaseToken $releaseToken): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        abort_unless($releaseToken->repository_id === $repository->id, 404);
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('manageReleases', $repository), 403);

        $releaseToken->delete();

        return response()->json(null, 204);
    }

    protected function transform(ReleaseToken $token): array
    {
        return [
            'id'           => $token->id,
            'name'         => $token->name,
            'token_prefix' => $token->token_prefix,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at'   => $token->expires_at?->toIso8601String(),
            'created_at'   => $token->created_at?->toIso8601String(),
            'creator'      => $token->creator ? [
                'id'   => $token->creator->id,
                'name' => $token->creator->name,
            ] : null,
        ];
    }
}
