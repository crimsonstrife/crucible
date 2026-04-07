<?php

namespace Tests\Feature;

use App\Enums\MergeStrategy;
use App\Enums\PullRequestStatus;
use App\Enums\ReviewState;
use App\Models\BranchProtectionRule;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\PullRequestReview;
use App\Models\Repository;
use App\Models\User;
use App\Services\BranchProtectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Repository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->org = Organization::create([
            'name'        => 'Test Org',
            'slug'        => 'test-org',
            'is_personal' => false,
        ]);
        $this->repo = Repository::create([
            'organization_id' => $this->org->id,
            'owner_id'        => $this->user->id,
            'name'            => 'test-repo',
            'slug'            => 'test-repo',
            'default_branch'  => 'main',
        ]);
    }

    // ── MergeStrategy Enum ────────────────────────────────────────────

    public function test_merge_strategy_enum_has_expected_cases(): void
    {
        $cases = MergeStrategy::cases();
        $this->assertCount(4, $cases);
        $this->assertNotNull(MergeStrategy::MergeCommit);
        $this->assertNotNull(MergeStrategy::Squash);
        $this->assertNotNull(MergeStrategy::Rebase);
        $this->assertNotNull(MergeStrategy::FastForward);
    }

    public function test_merge_strategy_has_labels(): void
    {
        $this->assertNotEmpty(MergeStrategy::MergeCommit->label());
        $this->assertNotEmpty(MergeStrategy::Squash->description());
    }

    // ── ReviewState Enum ──────────────────────────────────────────────

    public function test_review_state_enum_has_expected_cases(): void
    {
        $cases = ReviewState::cases();
        $this->assertCount(4, $cases);
        $this->assertEquals('approved', ReviewState::Approved->value);
        $this->assertEquals('changes_requested', ReviewState::ChangesRequested->value);
    }

    // ── PullRequest Model — Draft & Strategy ──────────────────────────

    public function test_pull_request_supports_draft_flag(): void
    {
        $pr = PullRequest::create([
            'repository_id'  => $this->repo->id,
            'number'         => 1,
            'title'          => 'Test PR',
            'author_id'      => $this->user->id,
            'source_branch'  => 'feature',
            'target_branch'  => 'main',
            'status'         => PullRequestStatus::Open,
            'is_draft'       => true,
            'merge_strategy' => MergeStrategy::Squash,
            'head_sha'       => str_repeat('a', 40),
            'base_sha'       => str_repeat('b', 40),
        ]);

        $this->assertTrue($pr->isDraft());
        $this->assertEquals(MergeStrategy::Squash, $pr->merge_strategy);
    }

    public function test_pull_request_default_merge_strategy(): void
    {
        $pr = PullRequest::create([
            'repository_id'  => $this->repo->id,
            'number'         => 1,
            'title'          => 'Test PR',
            'author_id'      => $this->user->id,
            'source_branch'  => 'feature',
            'target_branch'  => 'main',
            'status'         => PullRequestStatus::Open,
            'head_sha'       => str_repeat('a', 40),
            'base_sha'       => str_repeat('b', 40),
        ]);

        $this->assertFalse($pr->isDraft());
        $this->assertEquals(MergeStrategy::MergeCommit, $pr->merge_strategy);
    }

    // ── PullRequest Reviews ───────────────────────────────────────────

    public function test_pull_request_reviews_relationship(): void
    {
        $pr = PullRequest::create([
            'repository_id'  => $this->repo->id,
            'number'         => 1,
            'title'          => 'Test PR',
            'author_id'      => $this->user->id,
            'source_branch'  => 'feature',
            'target_branch'  => 'main',
            'status'         => PullRequestStatus::Open,
            'head_sha'       => str_repeat('a', 40),
            'base_sha'       => str_repeat('b', 40),
        ]);

        $reviewer = User::factory()->create();

        PullRequestReview::create([
            'pull_request_id' => $pr->id,
            'reviewer_id'     => $reviewer->id,
            'state'           => ReviewState::Approved,
            'body'            => 'LGTM',
        ]);

        $pr->refresh();
        $this->assertCount(1, $pr->reviews);
        $this->assertEquals(1, $pr->approvalCount());
        $this->assertFalse($pr->hasChangesRequested());
    }

    public function test_approval_count_uses_latest_review_per_reviewer(): void
    {
        $pr = PullRequest::create([
            'repository_id'  => $this->repo->id,
            'number'         => 1,
            'title'          => 'Test PR',
            'author_id'      => $this->user->id,
            'source_branch'  => 'feature',
            'target_branch'  => 'main',
            'status'         => PullRequestStatus::Open,
            'head_sha'       => str_repeat('a', 40),
            'base_sha'       => str_repeat('b', 40),
        ]);

        $reviewer = User::factory()->create();

        // First review: approved
        PullRequestReview::create([
            'pull_request_id' => $pr->id,
            'reviewer_id'     => $reviewer->id,
            'state'           => ReviewState::Approved,
            'body'            => 'LGTM',
            'created_at'      => now()->subHour(),
        ]);

        // Second review by same reviewer: changes requested (overrides approval)
        PullRequestReview::create([
            'pull_request_id' => $pr->id,
            'reviewer_id'     => $reviewer->id,
            'state'           => ReviewState::ChangesRequested,
            'body'            => 'Needs work',
            'created_at'      => now(),
        ]);

        $pr->refresh();
        $this->assertEquals(0, $pr->approvalCount());
        $this->assertTrue($pr->hasChangesRequested());
    }

    public function test_requested_reviewers_relationship(): void
    {
        $pr = PullRequest::create([
            'repository_id'  => $this->repo->id,
            'number'         => 1,
            'title'          => 'Test PR',
            'author_id'      => $this->user->id,
            'source_branch'  => 'feature',
            'target_branch'  => 'main',
            'status'         => PullRequestStatus::Open,
            'head_sha'       => str_repeat('a', 40),
            'base_sha'       => str_repeat('b', 40),
        ]);

        $reviewer1 = User::factory()->create();
        $reviewer2 = User::factory()->create();

        $pr->requestedReviewers()->attach([$reviewer1->id, $reviewer2->id]);

        $pr->refresh();
        $this->assertCount(2, $pr->requestedReviewers);
    }

    // ── Branch Protection Rules ───────────────────────────────────────

    public function test_branch_protection_rule_matches_exact_pattern(): void
    {
        $rule = BranchProtectionRule::create([
            'repository_id'      => $this->repo->id,
            'pattern'            => 'main',
            'require_pull_request' => true,
            'required_approvals' => 1,
        ]);

        $this->assertTrue($rule->matches('main'));
        $this->assertFalse($rule->matches('develop'));
    }

    public function test_branch_protection_rule_matches_glob_pattern(): void
    {
        $rule = BranchProtectionRule::create([
            'repository_id'      => $this->repo->id,
            'pattern'            => 'release/*',
            'require_pull_request' => true,
        ]);

        $this->assertTrue($rule->matches('release/v1.0'));
        $this->assertTrue($rule->matches('release/v2.0'));
        $this->assertFalse($rule->matches('feature/foo'));
    }

    public function test_branch_protection_service_can_push_violations(): void
    {
        BranchProtectionRule::create([
            'repository_id'      => $this->repo->id,
            'pattern'            => 'main',
            'require_pull_request' => true,
        ]);

        $service = app(BranchProtectionService::class);
        $violations = $service->canPush($this->repo, 'main', $this->user);

        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('requires a pull request', $violations[0]);
    }

    public function test_branch_protection_service_allows_unprotected_branch(): void
    {
        BranchProtectionRule::create([
            'repository_id'      => $this->repo->id,
            'pattern'            => 'main',
            'require_pull_request' => true,
        ]);

        $service = app(BranchProtectionService::class);
        $violations = $service->canPush($this->repo, 'feature/foo', $this->user);

        $this->assertEmpty($violations);
    }

    public function test_branch_protection_merge_requirements_check(): void
    {
        BranchProtectionRule::create([
            'repository_id'      => $this->repo->id,
            'pattern'            => 'main',
            'require_pull_request' => true,
            'required_approvals' => 2,
        ]);

        $pr = PullRequest::create([
            'repository_id'  => $this->repo->id,
            'number'         => 1,
            'title'          => 'Test PR',
            'author_id'      => $this->user->id,
            'source_branch'  => 'feature',
            'target_branch'  => 'main',
            'status'         => PullRequestStatus::Open,
            'head_sha'       => str_repeat('a', 40),
            'base_sha'       => str_repeat('b', 40),
        ]);

        $service = app(BranchProtectionService::class);
        $violations = $service->checkMergeRequirements($pr);

        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('Requires 2 approval(s)', $violations[0]);
    }

    public function test_branch_protection_merge_requirements_met(): void
    {
        BranchProtectionRule::create([
            'repository_id'      => $this->repo->id,
            'pattern'            => 'main',
            'require_pull_request' => true,
            'required_approvals' => 1,
        ]);

        $pr = PullRequest::create([
            'repository_id'  => $this->repo->id,
            'number'         => 1,
            'title'          => 'Test PR',
            'author_id'      => $this->user->id,
            'source_branch'  => 'feature',
            'target_branch'  => 'main',
            'status'         => PullRequestStatus::Open,
            'head_sha'       => str_repeat('a', 40),
            'base_sha'       => str_repeat('b', 40),
        ]);

        $reviewer = User::factory()->create();
        PullRequestReview::create([
            'pull_request_id' => $pr->id,
            'reviewer_id'     => $reviewer->id,
            'state'           => ReviewState::Approved,
        ]);

        $service = app(BranchProtectionService::class);
        $violations = $service->checkMergeRequirements($pr);

        $this->assertEmpty($violations);
    }

    public function test_branch_protection_deletion_disallowed(): void
    {
        BranchProtectionRule::create([
            'repository_id'    => $this->repo->id,
            'pattern'          => 'main',
            'allow_deletion'   => false,
        ]);

        $service = app(BranchProtectionService::class);
        $this->assertFalse($service->allowsDeletion($this->repo, 'main'));
        $this->assertTrue($service->allowsDeletion($this->repo, 'feature/foo'));
    }

    public function test_branch_protection_force_push_disallowed(): void
    {
        BranchProtectionRule::create([
            'repository_id'    => $this->repo->id,
            'pattern'          => 'main',
            'allow_force_push' => false,
        ]);

        $service = app(BranchProtectionService::class);
        $this->assertFalse($service->allowsForcePush($this->repo, 'main'));
        $this->assertTrue($service->allowsForcePush($this->repo, 'develop'));
    }

    // ── Repository branchProtectionRules relationship ─────────────────

    public function test_repository_has_branch_protection_rules(): void
    {
        BranchProtectionRule::create([
            'repository_id' => $this->repo->id,
            'pattern'       => 'main',
        ]);

        $this->repo->refresh();
        $this->assertCount(1, $this->repo->branchProtectionRules);
    }
}
