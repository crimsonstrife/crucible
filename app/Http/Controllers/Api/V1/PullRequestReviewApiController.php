<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewState;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\PullRequestReview;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PullRequestReviewApiController extends Controller
{
    /**
     * GET /{org}/{repo}/pull-requests/{number}/reviews
     */
    public function index(
        Request $request,
        Organization $organization,
        Repository $repository,
        int $number,
    ): JsonResponse {
        abort_unless($repository->organization_id === $organization->id, 404);

        $pr = $repository->pullRequests()->where('number', $number)->firstOrFail();

        $this->authorize('view', $pr);

        $reviews = $pr->reviews()->with('reviewer')->get();

        return response()->json([
            'data' => $reviews->map(fn (PullRequestReview $r) => $this->formatReview($r)),
        ]);
    }

    /**
     * POST /{org}/{repo}/pull-requests/{number}/reviews
     *
     * Body: { state: "approved"|"changes_requested"|"commented", body?: string }
     */
    public function store(
        Request $request,
        Organization $organization,
        Repository $repository,
        int $number,
    ): JsonResponse {
        abort_unless($repository->organization_id === $organization->id, 404);

        $pr = $repository->pullRequests()->where('number', $number)->firstOrFail();

        $this->authorize('view', $pr); // Anyone who can view can review

        $data = $request->validate([
            'state' => ['required', 'string', 'in:approved,changes_requested,commented'],
            'body'  => ['nullable', 'string', 'max:10000'],
        ]);

        $user = $request->user();

        // PR authors cannot approve their own PR
        if ($data['state'] === 'approved' && $user->id === $pr->author_id) {
            return response()->json([
                'message' => 'You cannot approve your own pull request.',
            ], 422);
        }

        $review = $pr->reviews()->create([
            'reviewer_id' => $user->id,
            'state'        => ReviewState::from($data['state']),
            'body'         => $data['body'] ?? null,
        ]);

        $review->load('reviewer');

        return response()->json(
            ['data' => $this->formatReview($review)],
            201,
        );
    }

    private function formatReview(PullRequestReview $review): array
    {
        return [
            'id'         => $review->id,
            'reviewer'   => $review->reviewer?->name,
            'state'      => $review->state->value,
            'body'       => $review->body,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }
}
