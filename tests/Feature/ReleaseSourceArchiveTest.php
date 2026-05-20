<?php

namespace Tests\Feature;

use App\Models\Release;
use App\Models\ReleaseToken;
use App\Models\Repository;
use App\Models\User;
use App\Services\NativeGitRepositoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesRepository;
use Tests\TestCase;

/**
 * Auth matrix + response shape tests for the auto-generated source archive
 * endpoint. The underlying git archive correctness is covered separately by
 * NativeGitRepositoryServiceTest — here we stub the service so we don't need
 * a real repo on disk and can focus on auth/visibility/headers.
 */
class ReleaseSourceArchiveTest extends TestCase
{
    use CreatesRepository, RefreshDatabase;

    public function test_anonymous_on_public_repo_gets_archive(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord('public');
        $release = $this->publishedRelease($repo);

        $this->stubArchive('PK'.str_repeat("\x00", 30));

        $response = $this->get("/api/v1/{$org->slug}/{$repo->slug}/releases/{$release->slug}/source.zip");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename="'.$repo->slug.'-v1.0.0.zip"',
        );
        $response->assertHeader('ETag', '"'.$release->commit_sha.'-zip"');
        // Symfony normalises and alphabetises Cache-Control directives.
        $response->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    }

    public function test_anonymous_on_private_repo_gets_404(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord('private');
        $release = $this->publishedRelease($repo);

        $response = $this->get("/api/v1/{$org->slug}/{$repo->slug}/releases/{$release->slug}/source.zip");

        $response->assertNotFound();
    }

    public function test_sanctum_user_can_view_private_release_archive(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord('private');
        $release = $this->publishedRelease($repo);

        $this->stubArchive('PK'.str_repeat("\x00", 30));

        $response = $this->actingAs($owner, 'sanctum')
            ->get("/api/v1/{$org->slug}/{$repo->slug}/releases/{$release->slug}/source.zip");

        $response->assertOk();
    }

    public function test_sanctum_user_without_view_gets_403_on_private(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord('private');
        $release = $this->publishedRelease($repo);
        $other = User::factory()->create();

        $response = $this->actingAs($other, 'sanctum')
            ->get("/api/v1/{$org->slug}/{$repo->slug}/releases/{$release->slug}/source.zip");

        $response->assertForbidden();
    }

    public function test_release_token_grants_archive_access_on_bound_repo(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord('private');
        $release = $this->publishedRelease($repo);
        $token = ReleaseToken::generate($repo, $owner, 'test')['raw'];

        $this->stubArchive('PK'.str_repeat("\x00", 30));

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get("/api/v1/{$org->slug}/{$repo->slug}/releases/{$release->slug}/source.zip");

        $response->assertOk();
    }

    public function test_release_token_for_other_repo_returns_401(): void
    {
        [$owner, , $repoA] = $this->createRepositoryRecord('private');
        [, $orgB, $repoB] = $this->createRepositoryRecord('private');
        $releaseB = $this->publishedRelease($repoB);

        $tokenForA = ReleaseToken::generate($repoA, $owner, 'A only')['raw'];

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$tokenForA])
            ->get("/api/v1/{$orgB->slug}/{$repoB->slug}/releases/{$releaseB->slug}/source.zip");

        $response->assertUnauthorized();
    }

    public function test_draft_release_archive_hidden_from_anonymous(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord('public');
        $draft = Release::factory()->for($repo)->create([
            'tag_name'     => 'v0.1.0',
            'is_draft'     => true,
            'published_at' => null,
        ]);

        $response = $this->get("/api/v1/{$org->slug}/{$repo->slug}/releases/{$draft->slug}/source.zip");

        $response->assertNotFound();
    }

    public function test_draft_archive_visible_to_maintainer(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord('public');
        $draft = Release::factory()->for($repo)->create([
            'tag_name'     => 'v0.1.0',
            'is_draft'     => true,
            'published_at' => null,
        ]);

        $this->stubArchive('PK'.str_repeat("\x00", 30));

        $response = $this->actingAs($owner, 'sanctum')
            ->get("/api/v1/{$org->slug}/{$repo->slug}/releases/{$draft->slug}/source.zip");

        $response->assertOk();
    }

    public function test_if_none_match_returns_304_without_invoking_archive(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord('public');
        $release = $this->publishedRelease($repo);
        $etag = '"'.$release->commit_sha.'-zip"';

        $this->mock(NativeGitRepositoryService::class, function (MockInterface $m) {
            $m->shouldNotReceive('archive');
        });

        $response = $this->withHeaders(['If-None-Match' => $etag])
            ->get("/api/v1/{$org->slug}/{$repo->slug}/releases/{$release->slug}/source.zip");

        $response->assertStatus(304);
        $response->assertHeader('ETag', $etag);
    }

    public function test_tar_gz_route_serves_gzip_content_type(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord('public');
        $release = $this->publishedRelease($repo);

        $this->stubArchive("\x1f\x8b\x08\x00".str_repeat("\x00", 20));

        $response = $this->get("/api/v1/{$org->slug}/{$repo->slug}/releases/{$release->slug}/source.tar.gz");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/gzip');
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename="'.$repo->slug.'-v1.0.0.tar.gz"',
        );
    }

    public function test_api_show_response_includes_source_archive_urls(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord('public');
        $release = $this->publishedRelease($repo);

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases/{$release->slug}");
        $response->assertOk();

        $archives = $response->json('data.source_archives');
        $this->assertIsArray($archives);
        $this->assertCount(2, $archives);
        $this->assertSame('zip', $archives[0]['format']);
        $this->assertStringEndsWith("/releases/{$release->slug}/source.zip", $archives[0]['url']);
        $this->assertSame('tar.gz', $archives[1]['format']);
    }

    public function test_api_response_omits_archives_for_drafts(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord('public');
        $draft = Release::factory()->for($repo)->create([
            'tag_name'     => 'v0.1.0',
            'is_draft'     => true,
            'published_at' => null,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases/{$draft->slug}");

        $response->assertOk();
        $this->assertNull($response->json('data.source_archives'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function publishedRelease(Repository $repo): Release
    {
        return Release::factory()->for($repo)->create([
            'tag_name'     => 'v1.0.0',
            'commit_sha'   => str_repeat('a', 40),
            'is_draft'     => false,
            'published_at' => now(),
        ]);
    }

    /**
     * Stub NativeGitRepositoryService::archive() so tests don't need a real
     * git binary or on-disk repo. Returns a Process whose stdout will yield
     * the provided body string in one chunk.
     */
    protected function stubArchive(string $body): void
    {
        $this->mock(NativeGitRepositoryService::class, function (MockInterface $m) use ($body) {
            // Wrap in a `cat`/`echo` so we hand back a real Process that
            // produces the exact body via its iterator. Use `printf` for
            // binary-safe output of a fixed string.
            $m->shouldReceive('archive')->andReturnUsing(function () use ($body) {
                $process = Process::fromShellCommandline('printf %s "$BODY"');
                $process->setEnv(['BODY' => $body]);
                $process->setTimeout(null);
                $process->start();
                return $process;
            });
        });
    }
}
