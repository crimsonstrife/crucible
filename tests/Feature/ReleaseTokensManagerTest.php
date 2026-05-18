<?php

namespace Tests\Feature;

use App\Livewire\Repositories\ReleaseTokensManager;
use App\Models\ReleaseToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesRepository;
use Tests\TestCase;

class ReleaseTokensManagerTest extends TestCase
{
    use CreatesRepository, RefreshDatabase;

    public function test_owner_can_generate_token_and_raw_is_returned_once(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->actingAs($owner);

        $component = Livewire::test(ReleaseTokensManager::class, ['repository' => $repo])
            ->set('name', 'Game website prod')
            ->set('expiresIn', 'never')
            ->call('createToken')
            ->assertHasNoErrors();

        $raw = $component->get('generatedTokenRaw');
        $this->assertNotNull($raw);
        $this->assertStringStartsWith('crl_', $raw);

        // The stored row stores only the hash, not the raw value.
        $token = ReleaseToken::query()->where('repository_id', $repo->id)->first();
        $this->assertNotNull($token);
        $this->assertSame(hash('sha256', $raw), $token->token_hash);
        $this->assertNull($token->expires_at);

        // Form is reset for next-token, but the raw is still displayed until dismissed.
        $component->assertSet('name', '');
    }

    public function test_token_with_30d_expiry(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->actingAs($owner);

        Livewire::test(ReleaseTokensManager::class, ['repository' => $repo])
            ->set('name', 'Temp')
            ->set('expiresIn', '30d')
            ->call('createToken')
            ->assertHasNoErrors();

        $token = ReleaseToken::query()->where('repository_id', $repo->id)->first();
        $this->assertNotNull($token->expires_at);
        $this->assertEqualsWithDelta(30, now()->diffInDays($token->expires_at), 1);
    }

    public function test_name_is_required(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->actingAs($owner);

        Livewire::test(ReleaseTokensManager::class, ['repository' => $repo])
            ->set('name', '')
            ->call('createToken')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_invalid_expiry_rejected(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->actingAs($owner);

        Livewire::test(ReleaseTokensManager::class, ['repository' => $repo])
            ->set('name', 'x')
            ->set('expiresIn', '5d')
            ->call('createToken')
            ->assertHasErrors(['expiresIn']);
    }

    public function test_revoke_soft_deletes_token(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();
        $token = ReleaseToken::generate($repo, $owner, 'Test')['token'];

        $this->actingAs($owner);

        Livewire::test(ReleaseTokensManager::class, ['repository' => $repo])
            ->call('revokeToken', $token->id);

        $this->assertSoftDeleted('release_tokens', ['id' => $token->id]);
    }

    public function test_dismiss_clears_displayed_raw_token(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->actingAs($owner);

        Livewire::test(ReleaseTokensManager::class, ['repository' => $repo])
            ->set('name', 'Test')
            ->call('createToken')
            ->call('dismissGeneratedToken')
            ->assertSet('generatedTokenRaw', null)
            ->assertSet('generatedTokenName', null);
    }

    public function test_non_maintainer_denied_on_mount(): void
    {
        [, , $repo] = $this->createRepositoryRecord();
        $reader = User::factory()->create();
        $repo->collaborators()->attach($reader, ['role' => 'read']);

        $this->actingAs($reader);

        Livewire::test(ReleaseTokensManager::class, ['repository' => $repo])
            ->assertStatus(403);
    }

    public function test_non_maintainer_denied_on_create(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();
        $reader = User::factory()->create();
        $repo->collaborators()->attach($reader, ['role' => 'read']);

        // Mount as owner (allowed), then re-auth as reader and try createToken.
        // Since the manager is per-request, this simulates a stale session forging
        // a wire:call. The authorize() inside createToken should still block.
        $this->actingAs($owner);
        $component = Livewire::test(ReleaseTokensManager::class, ['repository' => $repo]);

        $this->actingAs($reader);
        $component
            ->set('name', 'sneaky')
            ->call('createToken')
            ->assertStatus(403);

        $this->assertSame(0, $repo->releaseTokens()->count());
    }
}
