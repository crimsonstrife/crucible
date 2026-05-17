<?php

namespace Tests\Feature;

use App\Models\ReleaseToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesRepository;
use Tests\TestCase;

class ReleaseTokenApiTest extends TestCase
{
    use CreatesRepository, RefreshDatabase;

    public function test_owner_can_create_token_and_receives_raw_value_once(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord();

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/{$org->slug}/{$repo->slug}/release-tokens", [
            'name' => 'Game website',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Game website');
        $response->assertJsonStructure(['data' => ['id', 'name', 'token_prefix', 'token'], 'warning']);

        $raw = $response->json('data.token');
        $this->assertStringStartsWith('crl_', $raw);

        // Hash stored in DB matches; raw not stored anywhere.
        $this->assertDatabaseHas('release_tokens', [
            'repository_id' => $repo->id,
            'name' => 'Game website',
            'token_hash' => hash('sha256', $raw),
        ]);
    }

    public function test_listing_tokens_never_includes_raw_value(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord();
        ReleaseToken::generate($repo, $owner, 'Existing');

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/release-tokens");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonMissingPath('data.0.token');
        $response->assertJsonMissingPath('data.0.token_hash');
    }

    public function test_non_maintainer_collaborator_cannot_create_token(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord();
        $reader = User::factory()->create();
        $repo->collaborators()->attach($reader, ['role' => 'read']);

        Sanctum::actingAs($reader);

        $response = $this->postJson("/api/v1/{$org->slug}/{$repo->slug}/release-tokens", [
            'name' => 'Sneaky',
        ]);

        $response->assertForbidden();
    }

    public function test_revoked_token_stops_working_immediately(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord(visibility: 'private');
        ['token' => $token, 'raw' => $raw] = ReleaseToken::generate($repo, $owner, 'Revoked');

        // Verify the token works first (uses read endpoint).
        $this->getJson(
            "/api/v1/{$org->slug}/{$repo->slug}/releases",
            ['Authorization' => "Bearer {$raw}"],
        )->assertOk();

        // Revoke via API.
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/{$org->slug}/{$repo->slug}/release-tokens/{$token->id}")
            ->assertNoContent();

        // Token should now be invalid (soft-deleted → not found by findByRawToken).
        $this->getJson(
            "/api/v1/{$org->slug}/{$repo->slug}/releases",
            ['Authorization' => "Bearer {$raw}"],
        )->assertUnauthorized();
    }

    public function test_create_validates_name_and_optional_future_expires_at(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord();
        Sanctum::actingAs($owner);

        // Missing name.
        $this->postJson("/api/v1/{$org->slug}/{$repo->slug}/release-tokens", [])
            ->assertStatus(422);

        // Past expiration.
        $this->postJson("/api/v1/{$org->slug}/{$repo->slug}/release-tokens", [
            'name' => 'Past',
            'expires_at' => now()->subDay()->toIso8601String(),
        ])->assertStatus(422);
    }
}
