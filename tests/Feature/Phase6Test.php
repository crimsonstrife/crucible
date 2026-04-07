<?php

namespace Tests\Feature;

use App\Enums\CommitStatusState;
use App\Events\FileLocked;
use App\Events\FileUnlocked;
use App\Events\PullRequestMerged;
use App\Events\PullRequestOpened;
use App\Events\PushReceived;
use App\Events\ReviewSubmitted;
use App\Models\CommitStatus;
use App\Models\Repository;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6Test extends TestCase
{
    use RefreshDatabase;

    // ── Event Payload Tests ─────────────────────────────────────────────────

    public function test_push_received_event_generates_correct_payload(): void
    {
        $repo = new Repository([
            'id'   => (string) Str::uuid(),
            'name' => 'Test Repo',
            'slug' => 'test-repo',
        ]);

        $event = new PushReceived(
            repository: $repo,
            ref: 'refs/heads/main',
            beforeSha: str_repeat('0', 40),
            afterSha: str_repeat('a', 40),
            pusherName: 'John',
            pusherEmail: 'john@example.com',
        );

        $payload = $event->toWebhookPayload();

        $this->assertSame('push', $payload['event']);
        $this->assertSame('refs/heads/main', $payload['ref']);
        $this->assertSame(str_repeat('0', 40), $payload['before']);
        $this->assertSame(str_repeat('a', 40), $payload['after']);
        $this->assertSame('John', $payload['pusher']['name']);
    }

    public function test_pull_request_opened_event_payload(): void
    {
        $repo = new Repository([
            'id'   => (string) Str::uuid(),
            'name' => 'Test Repo',
            'slug' => 'test-repo',
        ]);

        $pr = new \App\Models\PullRequest([
            'id'            => (string) Str::uuid(),
            'number'        => 42,
            'title'         => 'Add feature',
            'source_branch' => 'feature/x',
            'target_branch' => 'main',
            'is_draft'      => false,
        ]);
        $pr->setRelation('repository', $repo);

        $event = new PullRequestOpened($pr);
        $payload = $event->toWebhookPayload();

        $this->assertSame('pull_request.opened', $payload['event']);
        $this->assertSame(42, $payload['pull_request']['number']);
        $this->assertSame('Add feature', $payload['pull_request']['title']);
    }

    public function test_pull_request_merged_event_payload(): void
    {
        $repo = new Repository([
            'id'   => (string) Str::uuid(),
            'name' => 'Test Repo',
            'slug' => 'test-repo',
        ]);

        $pr = new \App\Models\PullRequest([
            'id'            => (string) Str::uuid(),
            'number'        => 10,
            'title'         => 'Fix bug',
            'source_branch' => 'fix/bug',
            'target_branch' => 'main',
        ]);
        $pr->setRelation('repository', $repo);

        $event = new PullRequestMerged($pr, str_repeat('b', 40));
        $payload = $event->toWebhookPayload();

        $this->assertSame('pull_request.merged', $payload['event']);
        $this->assertSame(str_repeat('b', 40), $payload['pull_request']['merge_sha']);
    }

    public function test_file_unlocked_event_payload(): void
    {
        $repo = new Repository([
            'id'   => (string) Str::uuid(),
            'name' => 'Test Repo',
            'slug' => 'test-repo',
        ]);

        $event = new FileUnlocked($repo, 'assets/hero.uasset', 'Alice');
        $payload = $event->toWebhookPayload();

        $this->assertSame('file.unlocked', $payload['event']);
        $this->assertSame('assets/hero.uasset', $payload['path']);
        $this->assertSame('Alice', $payload['owner']);
    }

    // ── CommitStatusState Enum ──────────────────────────────────────────────

    public function test_commit_status_state_enum_values(): void
    {
        $this->assertSame('pending', CommitStatusState::Pending->value);
        $this->assertSame('success', CommitStatusState::Success->value);
        $this->assertSame('failure', CommitStatusState::Failure->value);
        $this->assertSame('error', CommitStatusState::Error->value);
    }

    public function test_commit_status_state_is_passed(): void
    {
        $this->assertTrue(CommitStatusState::Success->isPassed());
        $this->assertFalse(CommitStatusState::Pending->isPassed());
        $this->assertFalse(CommitStatusState::Failure->isPassed());
        $this->assertFalse(CommitStatusState::Error->isPassed());
    }

    public function test_commit_status_state_is_terminal(): void
    {
        $this->assertTrue(CommitStatusState::Success->isTerminal());
        $this->assertTrue(CommitStatusState::Failure->isTerminal());
        $this->assertTrue(CommitStatusState::Error->isTerminal());
        $this->assertFalse(CommitStatusState::Pending->isTerminal());
    }

    // ── Webhook Model ───────────────────────────────────────────────────────

    public function test_webhook_subscribes_to_exact_event(): void
    {
        $wh = new Webhook(['events' => ['push', 'pull_request.opened']]);

        $this->assertTrue($wh->subscribesTo('push'));
        $this->assertTrue($wh->subscribesTo('pull_request.opened'));
        $this->assertFalse($wh->subscribesTo('pull_request.merged'));
        $this->assertFalse($wh->subscribesTo('file.locked'));
    }

    public function test_webhook_subscribes_to_wildcard(): void
    {
        $wh = new Webhook(['events' => ['*']]);

        $this->assertTrue($wh->subscribesTo('push'));
        $this->assertTrue($wh->subscribesTo('pull_request.opened'));
        $this->assertTrue($wh->subscribesTo('file.locked'));
    }

    public function test_webhook_subscribes_to_prefix(): void
    {
        $wh = new Webhook(['events' => ['pull_request']]);

        $this->assertTrue($wh->subscribesTo('pull_request.opened'));
        $this->assertTrue($wh->subscribesTo('pull_request.merged'));
        $this->assertFalse($wh->subscribesTo('push'));
    }

    // ── WebhookService ──────────────────────────────────────────────────────

    public function test_compute_and_verify_signature(): void
    {
        $payload = '{"event":"push"}';
        $secret = 'my-webhook-secret';

        $signature = WebhookService::computeSignature($payload, $secret);

        $this->assertStringStartsWith('sha256=', $signature);
        $this->assertTrue(WebhookService::verifySignature($payload, $secret, $signature));
        $this->assertFalse(WebhookService::verifySignature($payload, 'wrong-secret', $signature));
    }

    public function test_verify_signature_rejects_tampered_payload(): void
    {
        $secret = 'secret123';
        $signature = WebhookService::computeSignature('original', $secret);

        $this->assertFalse(WebhookService::verifySignature('tampered', $secret, $signature));
    }

    // ── CommitStatus Model ──────────────────────────────────────────────────

    public function test_commit_status_model_casts_state(): void
    {
        $status = new CommitStatus([
            'state' => 'success',
        ]);

        $this->assertInstanceOf(CommitStatusState::class, $status->state);
        $this->assertSame(CommitStatusState::Success, $status->state);
    }

    public function test_commit_status_all_states(): void
    {
        foreach (CommitStatusState::cases() as $state) {
            $status = new CommitStatus(['state' => $state->value]);
            $this->assertSame($state, $status->state);
        }
    }

    // ── Repository Relationships ────────────────────────────────────────────

    public function test_repository_has_webhooks_relationship(): void
    {
        $repo = new Repository;
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $repo->webhooks());
    }

    public function test_repository_has_commit_statuses_relationship(): void
    {
        $repo = new Repository;
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $repo->commitStatuses());
    }

    // ── Webhook Supported Events ────────────────────────────────────────────

    public function test_supported_events_list_is_complete(): void
    {
        $events = WebhookService::SUPPORTED_EVENTS;

        $this->assertContains('push', $events);
        $this->assertContains('pull_request.opened', $events);
        $this->assertContains('pull_request.merged', $events);
        $this->assertContains('pull_request.closed', $events);
        $this->assertContains('review.submitted', $events);
        $this->assertContains('file.locked', $events);
        $this->assertContains('file.unlocked', $events);
    }

    // ── BranchProtection + Status Checks Integration ────────────────────────

    public function test_branch_protection_checks_commit_statuses(): void
    {
        $org = \App\Models\Organization::create([
            'id'        => (string) Str::uuid(),
            'name'      => 'Test Org',
            'slug'      => 'test-org',
            'owner_id'  => ($user = \App\Models\User::create([
                'id'       => (string) Str::uuid(),
                'name'     => 'Test',
                'email'    => 'test@example.com',
                'password' => bcrypt('password'),
            ]))->id,
        ]);

        $repo = Repository::create([
            'id'              => (string) Str::uuid(),
            'organization_id' => $org->id,
            'owner_id'        => $user->id,
            'name'            => 'Status Test',
            'slug'            => 'status-test',
        ]);

        $sha = str_repeat('c', 40);

        // Create a passing status
        CommitStatus::create([
            'id'            => (string) Str::uuid(),
            'repository_id' => $repo->id,
            'sha'           => $sha,
            'context'       => 'ci/build',
            'state'         => 'success',
        ]);

        // Create a failing status
        CommitStatus::create([
            'id'            => (string) Str::uuid(),
            'repository_id' => $repo->id,
            'sha'           => $sha,
            'context'       => 'ci/tests',
            'state'         => 'failure',
        ]);

        $service = app(\App\Services\BranchProtectionService::class);

        // Check with both contexts required
        $violations = $service->checkStatusChecks($repo, $sha, ['ci/build', 'ci/tests']);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('ci/tests', $violations[0]);
        $this->assertStringContainsString('failure', $violations[0]);

        // Check with a missing context
        $violations = $service->checkStatusChecks($repo, $sha, ['ci/build', 'ci/lint']);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('ci/lint', $violations[0]);
        $this->assertStringContainsString('not reported', $violations[0]);

        // Check with only passing context
        $violations = $service->checkStatusChecks($repo, $sha, ['ci/build']);

        $this->assertEmpty($violations);
    }

    // ── Webhook Dispatch ────────────────────────────────────────────────────

    public function test_webhook_service_dispatch_queues_jobs_for_matching_webhooks(): void
    {
        $org = \App\Models\Organization::create([
            'id'        => (string) Str::uuid(),
            'name'      => 'Dispatch Org',
            'slug'      => 'dispatch-org',
            'owner_id'  => ($user = \App\Models\User::create([
                'id'       => (string) Str::uuid(),
                'name'     => 'Test',
                'email'    => 'dispatch@example.com',
                'password' => bcrypt('password'),
            ]))->id,
        ]);

        $repo = Repository::create([
            'id'              => (string) Str::uuid(),
            'organization_id' => $org->id,
            'owner_id'        => $user->id,
            'name'            => 'Dispatch Repo',
            'slug'            => 'dispatch-repo',
        ]);

        // Create two webhooks — one subscribes to push, one to PR events
        Webhook::create([
            'id'            => (string) Str::uuid(),
            'repository_id' => $repo->id,
            'url'           => 'https://example.com/push-hook',
            'events'        => ['push'],
            'is_active'     => true,
        ]);

        Webhook::create([
            'id'            => (string) Str::uuid(),
            'repository_id' => $repo->id,
            'url'           => 'https://example.com/pr-hook',
            'events'        => ['pull_request'],
            'is_active'     => true,
        ]);

        // Inactive webhook should not be dispatched
        Webhook::create([
            'id'            => (string) Str::uuid(),
            'repository_id' => $repo->id,
            'url'           => 'https://example.com/inactive',
            'events'        => ['*'],
            'is_active'     => false,
        ]);

        \Illuminate\Support\Facades\Queue::fake();

        $service = app(WebhookService::class);

        // Dispatch a push event — should match 1 webhook
        $count = $service->dispatch($repo, 'push', ['event' => 'push']);
        $this->assertSame(1, $count);

        // Dispatch a PR opened event — should match 1 webhook (prefix match)
        $count = $service->dispatch($repo, 'pull_request.opened', ['event' => 'pull_request.opened']);
        $this->assertSame(1, $count);

        // Dispatch a file.locked event — should match 0 active webhooks
        $count = $service->dispatch($repo, 'file.locked', ['event' => 'file.locked']);
        $this->assertSame(0, $count);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\DeliverWebhookJob::class, 2);
    }
}
