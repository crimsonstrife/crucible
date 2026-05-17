<?php

namespace Tests\Feature;

use App\Models\Release;
use App\Models\ReleaseToken;
use App\Models\Repository;
use App\Models\User;
use App\Services\NativeGitRepositoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\Concerns\CreatesRepository;
use Tests\TestCase;

class ReleaseApiTest extends TestCase
{
    use CreatesRepository, RefreshDatabase;

    // ── Anonymous reads ──────────────────────────────────────────────────────

    public function test_anonymous_can_list_releases_on_public_repository(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord(visibility: 'public');
        Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0']);

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases");

        $response->assertOk();
        $response->assertJsonPath('data.0.tag_name', 'v1.0.0');
        $response->assertJsonPath('total', 1);
    }

    public function test_anonymous_listing_excludes_drafts(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord(visibility: 'public');
        Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0']);
        Release::factory()->for($repo)->draft()->create(['tag_name' => 'v2.0.0-draft']);

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases");

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('data.0.tag_name', 'v1.0.0');
    }

    public function test_anonymous_on_private_repository_returns_404_not_403(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases");

        $response->assertNotFound();
    }

    public function test_show_returns_404_for_anonymous_on_draft_in_public_repo(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord(visibility: 'public');
        $draft = Release::factory()->for($repo)->draft()->create(['tag_name' => 'v9.9.9-draft']);

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases/{$draft->slug}");

        $response->assertNotFound();
    }

    public function test_latest_returns_the_release_flagged_as_latest(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord(visibility: 'public');
        Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0', 'published_at' => now()->subDays(5)]);
        Release::factory()->for($repo)->create(['tag_name' => 'v2.0.0', 'published_at' => now()]);

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases/latest");

        $response->assertOk();
        $response->assertJsonPath('data.tag_name', 'v2.0.0');
        $response->assertJsonPath('data.is_latest', true);
    }

    public function test_latest_returns_404_when_no_releases_published(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord(visibility: 'public');

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases/latest");

        $response->assertNotFound();
    }

    // ── Authenticated user reads ─────────────────────────────────────────────

    public function test_authenticated_user_without_access_to_private_repo_gets_403(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider);

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases");

        $response->assertForbidden();
    }

    public function test_collaborator_with_read_role_sees_published_releases_but_not_drafts(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        $reader = User::factory()->create();
        $repo->collaborators()->attach($reader, ['role' => 'read']);

        Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0']);
        Release::factory()->for($repo)->draft()->create(['tag_name' => 'v2.0.0-draft']);

        Sanctum::actingAs($reader);

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases");

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('data.0.tag_name', 'v1.0.0');
    }

    public function test_maintainer_collaborator_sees_drafts(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        $maintainer = User::factory()->create();
        $repo->collaborators()->attach($maintainer, ['role' => 'maintain']);

        Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0']);
        Release::factory()->for($repo)->draft()->create(['tag_name' => 'v2.0.0-draft']);

        Sanctum::actingAs($maintainer);

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases");

        $response->assertOk();
        $response->assertJsonPath('total', 2);
    }

    // ── Release token reads ──────────────────────────────────────────────────

    public function test_release_token_grants_read_on_its_repository(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        ['raw' => $raw] = ReleaseToken::generate($repo, $owner, 'Website');
        Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0']);

        $response = $this->getJson(
            "/api/v1/{$org->slug}/{$repo->slug}/releases",
            ['Authorization' => "Bearer {$raw}"],
        );

        $response->assertOk();
        $response->assertJsonPath('total', 1);
    }

    public function test_release_token_used_against_other_repository_returns_401(): void
    {
        [$ownerA, $orgA, $repoA] = $this->createRepositoryRecord(visibility: 'private');
        [, $orgB, $repoB] = $this->createRepositoryRecord(visibility: 'private');
        ['raw' => $raw] = ReleaseToken::generate($repoA, $ownerA, 'A only');

        $response = $this->getJson(
            "/api/v1/{$orgB->slug}/{$repoB->slug}/releases",
            ['Authorization' => "Bearer {$raw}"],
        );

        $response->assertUnauthorized();
    }

    public function test_expired_release_token_returns_401(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        ['token' => $token, 'raw' => $raw] = ReleaseToken::generate($repo, $owner, 'Expired');
        $token->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();

        $response = $this->getJson(
            "/api/v1/{$org->slug}/{$repo->slug}/releases",
            ['Authorization' => "Bearer {$raw}"],
        );

        $response->assertUnauthorized();
    }

    public function test_release_token_never_sees_drafts(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        ['raw' => $raw] = ReleaseToken::generate($repo, $owner, 'Token');
        Release::factory()->for($repo)->draft()->create(['tag_name' => 'v9.9.9-draft']);

        $response = $this->getJson(
            "/api/v1/{$org->slug}/{$repo->slug}/releases",
            ['Authorization' => "Bearer {$raw}"],
        );

        $response->assertOk();
        $response->assertJsonPath('total', 0);
    }

    public function test_release_token_use_bumps_last_used_at(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord(visibility: 'public');
        ['token' => $token, 'raw' => $raw] = ReleaseToken::generate($repo, $owner, 'Track');
        $this->assertNull($token->last_used_at);

        $this->getJson(
            "/api/v1/{$org->slug}/{$repo->slug}/releases",
            ['Authorization' => "Bearer {$raw}"],
        )->assertOk();

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    // ── Writes (create/update/delete) ────────────────────────────────────────

    public function test_owner_can_create_release_for_valid_tag(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');

        $this->mockGitResolves(['v1.0.0' => str_repeat('a', 40)]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/{$org->slug}/{$repo->slug}/releases", [
            'tag_name' => 'v1.0.0',
            'name' => 'First',
            'body' => 'Initial release',
            'entries' => [
                ['category' => 'new', 'description' => 'Multiplayer'],
                ['category' => 'fixed', 'description' => 'Crash on save'],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.tag_name', 'v1.0.0');
        $response->assertJsonPath('data.commit_sha', str_repeat('a', 40));
        $response->assertJsonCount(2, 'data.entries');

        $this->assertDatabaseHas('releases', ['repository_id' => $repo->id, 'tag_name' => 'v1.0.0']);
    }

    public function test_create_release_with_unknown_tag_returns_422(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');

        $this->mockGitResolves([]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/{$org->slug}/{$repo->slug}/releases", [
            'tag_name' => 'v999.0.0',
            'body' => 'Nope',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Tag not found', $response->json('message') ?? '');
    }

    public function test_non_maintainer_collaborator_cannot_create_release(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        $reader = User::factory()->create();
        $repo->collaborators()->attach($reader, ['role' => 'read']);

        $this->mockGitResolves(['v1.0.0' => str_repeat('a', 40)]);

        Sanctum::actingAs($reader);

        $response = $this->postJson("/api/v1/{$org->slug}/{$repo->slug}/releases", [
            'tag_name' => 'v1.0.0',
        ]);

        $response->assertForbidden();
    }

    public function test_duplicate_tag_per_repo_returns_422(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0']);

        $this->mockGitResolves(['v1.0.0' => str_repeat('a', 40)]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/{$org->slug}/{$repo->slug}/releases", [
            'tag_name' => 'v1.0.0',
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_update_release_body(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        $release = Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0', 'body' => 'old']);

        Sanctum::actingAs($owner);

        $response = $this->patchJson("/api/v1/{$org->slug}/{$repo->slug}/releases/{$release->slug}", [
            'body' => 'new body text',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.body', 'new body text');
    }

    public function test_owner_can_delete_release(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        $release = Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0']);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/v1/{$org->slug}/{$repo->slug}/releases/{$release->slug}");

        $response->assertNoContent();
        $this->assertSoftDeleted('releases', ['id' => $release->id]);
    }

    public function test_tags_available_excludes_tags_already_used(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0']);

        $this->mock(NativeGitRepositoryService::class, function (MockInterface $m) {
            $m->shouldReceive('tags')->andReturn([
                ['name' => 'v1.0.0', 'sha' => str_repeat('a', 40), 'subject' => 'first', 'date' => '2026-01-01'],
                ['name' => 'v1.1.0', 'sha' => str_repeat('b', 40), 'subject' => 'second', 'date' => '2026-02-01'],
            ]);
        });

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases/tags-available");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'v1.1.0');
    }

    /**
     * Bind a fake NativeGitRepositoryService that returns the given map from resolveSha,
     * and an empty list from tags().
     */
    protected function mockGitResolves(array $tagToSha): void
    {
        $this->mock(NativeGitRepositoryService::class, function (MockInterface $m) use ($tagToSha) {
            $m->shouldReceive('resolveSha')->andReturnUsing(
                fn (Repository $r, string $ref) => $tagToSha[$ref] ?? null,
            );
            $m->shouldReceive('tags')->andReturn([]);
        });
    }
}
