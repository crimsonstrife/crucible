<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommitStatusState;
use App\Http\Controllers\Controller;
use App\Models\CommitStatus;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommitStatusApiController extends Controller
{
    /**
     * List all statuses for a specific commit SHA.
     *
     * GET /{org}/{repo}/statuses/{sha}
     */
    public function index(Organization $organization, Repository $repository, string $sha): JsonResponse
    {
        $statuses = $repository->commitStatuses()
            ->where('sha', $sha)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (CommitStatus $s) => [
                'id'          => $s->id,
                'context'     => $s->context,
                'state'       => $s->state->value,
                'description' => $s->description,
                'target_url'  => $s->target_url,
                'creator'     => $s->creator?->name,
                'created_at'  => $s->created_at,
                'updated_at'  => $s->updated_at,
            ]);

        // Compute combined status
        $combined = $this->combinedState($statuses->pluck('state')->all());

        return response()->json([
            'sha'      => $sha,
            'state'    => $combined,
            'statuses' => $statuses,
            'total'    => $statuses->count(),
        ]);
    }

    /**
     * Create or update a commit status.
     *
     * POST /{org}/{repo}/statuses/{sha}
     *
     * Upserting on (repository_id, sha, context) — same context overwrites.
     */
    public function store(Request $request, Organization $organization, Repository $repository, string $sha): JsonResponse
    {
        $validated = $request->validate([
            'context'     => ['required', 'string', 'max:255'],
            'state'       => ['required', Rule::in(array_column(CommitStatusState::cases(), 'value'))],
            'description' => ['nullable', 'string', 'max:255'],
            'target_url'  => ['nullable', 'url', 'max:2048'],
        ]);

        $status = CommitStatus::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'sha'           => $sha,
                'context'       => $validated['context'],
            ],
            [
                'state'       => $validated['state'],
                'description' => $validated['description'] ?? null,
                'target_url'  => $validated['target_url'] ?? null,
                'creator_id'  => $request->user()?->id,
            ],
        );

        $wasCreated = $status->wasRecentlyCreated;

        return response()->json([
            'data' => [
                'id'          => $status->id,
                'context'     => $status->context,
                'state'       => $status->state->value,
                'description' => $status->description,
                'target_url'  => $status->target_url,
                'creator'     => $status->creator?->name,
                'created_at'  => $status->created_at,
                'updated_at'  => $status->updated_at,
            ],
        ], $wasCreated ? 201 : 200);
    }

    /**
     * Compute the combined status from a list of individual states.
     *
     * Rules (follows GitHub convention):
     *  - If any status is "error" or "failure" => "failure"
     *  - If any status is "pending" => "pending"
     *  - If all are "success" => "success"
     *  - If empty => "pending" (no checks reported yet)
     */
    private function combinedState(array $states): string
    {
        if (empty($states)) {
            return 'pending';
        }

        if (in_array('error', $states, true) || in_array('failure', $states, true)) {
            return 'failure';
        }

        if (in_array('pending', $states, true)) {
            return 'pending';
        }

        return 'success';
    }
}
