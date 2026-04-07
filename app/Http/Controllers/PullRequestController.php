<?php

namespace App\Http\Controllers;

use App\Enums\MergeStrategy;
use App\Enums\PullRequestStatus;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Services\ForgeService;
use App\Services\PullRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PullRequestController extends Controller
{
    public function __construct(
        protected PullRequestService $service,
        protected ForgeService $forge,
    ) {}

    public function index(Request $request, Organization $organization, Repository $repository): View
    {
        $this->authorize('view', $repository);

        $status = PullRequestStatus::tryFrom($request->query('status', 'open'))
            ?? PullRequestStatus::Open;

        $pullRequests = $repository->pullRequests()
            ->with(['author'])
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            PullRequestStatus::Open->value   => $repository->pullRequests()->where('status', 'open')->count(),
            PullRequestStatus::Merged->value => $repository->pullRequests()->where('status', 'merged')->count(),
            PullRequestStatus::Closed->value => $repository->pullRequests()->where('status', 'closed')->count(),
        ];

        return view('repositories.pull-requests.index', compact(
            'organization', 'repository', 'pullRequests', 'status', 'counts',
        ));
    }

    public function create(Request $request, Organization $organization, Repository $repository): View
    {
        $this->authorize('push', $repository);

        $branches      = app(\App\Services\NativeGitRepositoryService::class)->branches($repository);
        $defaultBranch = $repository->default_branch ?? 'main';
        $sourceBranch  = $request->query('source', '');
        $targetBranch  = $request->query('target', $defaultBranch);

        // Load PR template from repo
        $prTemplate = $this->service->loadTemplate($repository);

        // Forge issue pre-fill (e.g. from ?issue=PROJ-123 link on the Forge issue board)
        $forgeIssueKey = $request->query('issue', '');
        $forgeIssue    = null;
        $forgeEnabled  = $this->forge->isConfigured()
            && $repository->forgeIntegration?->is_active;

        if ($forgeEnabled && $forgeIssueKey) {
            $forgeIssue = $this->forge->getIssue($forgeIssueKey, auth()->user());
        }

        $mergeStrategies = MergeStrategy::cases();

        return view('repositories.pull-requests.create', compact(
            'organization', 'repository', 'branches', 'defaultBranch',
            'sourceBranch', 'targetBranch', 'forgeEnabled', 'forgeIssueKey',
            'forgeIssue', 'prTemplate', 'mergeStrategies',
        ));
    }

    public function store(Request $request, Organization $organization, Repository $repository): RedirectResponse
    {
        $this->authorize('push', $repository);

        $data = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:10000'],
            'source_branch'   => ['required', 'string', 'max:255'],
            'target_branch'   => ['required', 'string', 'max:255'],
            'forge_issue_key' => ['nullable', 'string', 'max:50'],
            'is_draft'        => ['nullable', 'boolean'],
            'merge_strategy'  => ['nullable', 'string', 'in:merge_commit,squash,rebase,fast_forward'],
        ]);

        try {
            $pr = $this->service->create(
                $repository,
                auth()->user(),
                $data['title'],
                $data['description'] ?? null,
                $data['source_branch'],
                $data['target_branch'],
                $data['forge_issue_key'] ?? null,
                (bool) ($data['is_draft'] ?? false),
                isset($data['merge_strategy']) ? MergeStrategy::from($data['merge_strategy']) : null,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['source_branch' => $e->getMessage()]);
        }

        $label = $pr->isDraft() ? "Draft pull request #{$pr->number} opened." : "Pull request #{$pr->number} opened.";

        return redirect()
            ->route('repositories.pull-requests.show', [$organization, $repository, $pr])
            ->with('success', $label);
    }

    public function show(Request $request, Organization $organization, Repository $repository, PullRequest $pullRequest): View
    {
        $this->authorize('view', $pullRequest);

        abort_unless($pullRequest->repository_id === $repository->id, 404);

        $pullRequest->load(['author', 'mergedBy', 'reviews.reviewer', 'requestedReviewers']);
        $diff     = $this->service->diff($pullRequest);
        $readiness = $pullRequest->isOpen()
            ? $this->service->mergeReadiness($pullRequest)
            : ['mergeable' => false, 'conflicts' => false, 'protection_violations' => [], 'is_draft' => false];

        // Fetch linked Forge issue metadata (best-effort, null if unconfigured or not found).
        $forgeIssue = $pullRequest->forge_issue_key
            ? $this->forge->getIssue($pullRequest->forge_issue_key, auth()->user())
            : null;

        $mergeStrategies = MergeStrategy::cases();

        return view('repositories.pull-requests.show', compact(
            'organization', 'repository', 'pullRequest', 'diff', 'readiness',
            'forgeIssue', 'mergeStrategies',
        ));
    }

    public function merge(Request $request, Organization $organization, Repository $repository, PullRequest $pullRequest): RedirectResponse
    {
        $this->authorize('merge', $pullRequest);
        abort_unless($pullRequest->repository_id === $repository->id, 404);

        $data = $request->validate([
            'merge_strategy' => ['nullable', 'string', 'in:merge_commit,squash,rebase,fast_forward'],
        ]);

        $strategy = isset($data['merge_strategy'])
            ? MergeStrategy::from($data['merge_strategy'])
            : null;

        try {
            $this->service->merge($pullRequest, auth()->user(), $strategy);
        } catch (RuntimeException $e) {
            return back()->withErrors(['merge' => $e->getMessage()]);
        }

        return redirect()
            ->route('repositories.pull-requests.show', [$organization, $repository, $pullRequest])
            ->with('success', "Pull request #{$pullRequest->number} merged.");
    }

    public function close(Request $request, Organization $organization, Repository $repository, PullRequest $pullRequest): RedirectResponse
    {
        $this->authorize('close', $pullRequest);
        abort_unless($pullRequest->repository_id === $repository->id, 404);

        try {
            $this->service->close($pullRequest, auth()->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['close' => $e->getMessage()]);
        }

        return back()->with('success', "Pull request #{$pullRequest->number} closed.");
    }

    public function reopen(Request $request, Organization $organization, Repository $repository, PullRequest $pullRequest): RedirectResponse
    {
        $this->authorize('reopen', $pullRequest);
        abort_unless($pullRequest->repository_id === $repository->id, 404);

        try {
            $this->service->reopen($pullRequest, auth()->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['reopen' => $e->getMessage()]);
        }

        return back()->with('success', "Pull request #{$pullRequest->number} reopened.");
    }

    public function markReady(Request $request, Organization $organization, Repository $repository, PullRequest $pullRequest): RedirectResponse
    {
        $this->authorize('close', $pullRequest); // same permission level as close
        abort_unless($pullRequest->repository_id === $repository->id, 404);

        try {
            $this->service->markReady($pullRequest, auth()->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['draft' => $e->getMessage()]);
        }

        return back()->with('success', "Pull request #{$pullRequest->number} marked as ready for review.");
    }
}
