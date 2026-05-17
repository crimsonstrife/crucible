<?php

namespace Tests\Feature;

use App\Models\ReleaseToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRepository;
use Tests\TestCase;

class ReleaseTokenModelTest extends TestCase
{
    use CreatesRepository, RefreshDatabase;

    public function test_generate_returns_raw_token_and_persists_only_the_hash(): void
    {
        [$owner, , $repository] = $this->createRepositoryRecord();

        ['token' => $token, 'raw' => $raw] = ReleaseToken::generate($repository, $owner, 'Game website');

        $this->assertStringStartsWith('crl_', $raw);
        $this->assertSame(hash('sha256', $raw), $token->token_hash);
        $this->assertSame(substr($raw, 0, 12), $token->token_prefix);
        $this->assertSame('Game website', $token->name);
        $this->assertSame($owner->id, $token->created_by);
        $this->assertSame($repository->id, $token->repository_id);
    }

    public function test_find_by_raw_token_returns_the_token_when_valid(): void
    {
        [$owner, , $repository] = $this->createRepositoryRecord();

        ['token' => $token, 'raw' => $raw] = ReleaseToken::generate($repository, $owner, 'Test');

        $found = ReleaseToken::findByRawToken($raw);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($token));
    }

    public function test_find_by_raw_token_rejects_non_release_prefix(): void
    {
        $this->assertNull(ReleaseToken::findByRawToken('sk_not_a_release_token'));
        $this->assertNull(ReleaseToken::findByRawToken(''));
    }

    public function test_find_by_raw_token_rejects_unknown_token(): void
    {
        $this->assertNull(ReleaseToken::findByRawToken('crl_definitelynotrealnope1234567890ab'));
    }

    public function test_find_by_raw_token_rejects_expired_token(): void
    {
        [$owner, , $repository] = $this->createRepositoryRecord();

        ['raw' => $raw, 'token' => $token] = ReleaseToken::generate($repository, $owner, 'Old');
        $token->forceFill(['expires_at' => now()->subSecond()])->saveQuietly();

        $this->assertNull(ReleaseToken::findByRawToken($raw));
    }

    public function test_find_by_raw_token_allows_future_expiration(): void
    {
        [$owner, , $repository] = $this->createRepositoryRecord();

        ['raw' => $raw] = ReleaseToken::generate($repository, $owner, 'Future', now()->addDay());

        $this->assertNotNull(ReleaseToken::findByRawToken($raw));
    }

    public function test_token_hash_never_appears_in_serialization(): void
    {
        [$owner, , $repository] = $this->createRepositoryRecord();

        ['token' => $token] = ReleaseToken::generate($repository, $owner, 'Hidden');

        $this->assertArrayNotHasKey('token_hash', $token->toArray());
    }
}
