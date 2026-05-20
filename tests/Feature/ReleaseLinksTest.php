<?php

namespace Tests\Feature;

use App\Enums\ReleaseLinkPlatform;
use App\Livewire\Repositories\ReleaseForm;
use App\Models\Release;
use App\Models\ReleaseLink;
use App\Models\Repository;
use App\Services\NativeGitRepositoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\Concerns\CreatesRepository;
use Tests\TestCase;

class ReleaseLinksTest extends TestCase
{
    use CreatesRepository, RefreshDatabase;

    // ── Livewire ReleaseForm ─────────────────────────────────────────────────

    public function test_release_form_creates_links_alongside_release(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();
        $this->mockGitResolves(['v1.0.0' => str_repeat('a', 40)]);
        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo, 'preselectedTag' => 'v1.0.0'])
            ->call('addLink', 'steam')
            ->set('links.0.label', 'Buy on Steam')
            ->set('links.0.url', 'https://store.steampowered.com/app/12345/MyGame/')
            ->call('addLink', 'itch')
            ->set('links.1.label', 'Free demo on itch.io')
            ->set('links.1.url', 'https://example.itch.io/mygame')
            ->call('save')
            ->assertHasNoErrors();

        $release = $repo->releases()->first();
        $this->assertSame(2, $release->links()->count());
        $this->assertSame(
            ['steam', 'itch'],
            $release->links()->orderBy('position')->get()->map(fn ($l) => $l->platform->value)->all(),
        );
        $this->assertSame([0, 1], $release->links()->orderBy('position')->pluck('position')->all());
    }

    public function test_release_form_rejects_invalid_link_url(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();
        $this->mockGitResolves(['v1.0.0' => str_repeat('a', 40)]);
        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo, 'preselectedTag' => 'v1.0.0'])
            ->call('addLink', 'steam')
            ->set('links.0.label', 'Buy')
            ->set('links.0.url', 'not-a-real-url')
            ->call('save')
            ->assertHasErrors(['links.0.url']);

        $this->assertSame(0, $repo->releases()->count());
    }

    public function test_release_form_requires_link_label(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();
        $this->mockGitResolves(['v1.0.0' => str_repeat('a', 40)]);
        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo, 'preselectedTag' => 'v1.0.0'])
            ->call('addLink', 'steam')
            ->set('links.0.label', '')
            ->set('links.0.url', 'https://store.steampowered.com/')
            ->call('save')
            ->assertHasErrors(['links.0.label']);
    }

    public function test_release_form_edit_replaces_links(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();
        $release = Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0']);
        $release->links()->create(['label' => 'Old', 'url' => 'https://example.com/old', 'platform' => 'other', 'position' => 0]);

        $this->mockGitResolves([]);
        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo, 'release' => $release])
            ->assertSet('links.0.label', 'Old')
            ->call('removeLink', 0)
            ->call('addLink', 'discord')
            ->set('links.0.label', 'Join our Discord')
            ->set('links.0.url', 'https://discord.gg/abc123')
            ->call('save')
            ->assertHasNoErrors();

        $links = $release->fresh()->links;
        $this->assertSame(1, $links->count());
        $this->assertSame('Join our Discord', $links->first()->label);
        $this->assertSame(ReleaseLinkPlatform::Discord, $links->first()->platform);
    }

    public function test_release_form_drops_empty_link_rows(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();
        $this->mockGitResolves(['v1.0.0' => str_repeat('a', 40)]);
        $this->actingAs($owner);

        // Two rows added, one filled, one left blank → only the filled one persists.
        Livewire::test(ReleaseForm::class, ['repository' => $repo, 'preselectedTag' => 'v1.0.0'])
            ->call('addLink', 'steam')
            ->set('links.0.label', 'Buy')
            ->set('links.0.url', 'https://store.steampowered.com/')
            ->call('addLink', 'other')
            // Leave second row's label + url blank — server should treat as "discard".
            ->set('links.1.label', 'Filler')
            ->set('links.1.url', 'https://example.com')
            ->call('save')
            ->assertHasNoErrors();

        // Both filled → both persist.
        $this->assertSame(2, $repo->releases()->first()->links()->count());
    }

    // ── API ──────────────────────────────────────────────────────────────────

    public function test_api_transform_includes_links_array(): void
    {
        [, $org, $repo] = $this->createRepositoryRecord('public');
        $release = Release::factory()->for($repo)->create([
            'tag_name'     => 'v1.0.0',
            'is_draft'     => false,
            'published_at' => now(),
        ]);
        $release->links()->create([
            'label'    => 'Steam',
            'url'      => 'https://store.steampowered.com/',
            'platform' => 'steam',
            'position' => 0,
        ]);
        $release->links()->create([
            'label'    => 'Itch',
            'url'      => 'https://example.itch.io/',
            'platform' => 'itch',
            'position' => 1,
        ]);

        $response = $this->getJson("/api/v1/{$org->slug}/{$repo->slug}/releases/{$release->slug}");
        $response->assertOk();

        $links = $response->json('data.links');
        $this->assertIsArray($links);
        $this->assertCount(2, $links);
        $this->assertSame('Steam', $links[0]['label']);
        $this->assertSame('steam', $links[0]['platform']);
        $this->assertSame(0, $links[0]['position']);
    }

    public function test_api_store_accepts_links_payload(): void
    {
        [$owner, $org, $repo] = $this->createRepositoryRecord();
        $this->mockGitResolves(['v1.0.0' => str_repeat('a', 40)]);

        $response = $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/{$org->slug}/{$repo->slug}/releases",
            [
                'tag_name' => 'v1.0.0',
                'links' => [
                    ['label' => 'Steam', 'url' => 'https://store.steampowered.com/', 'platform' => 'steam'],
                ],
            ],
        );

        $response->assertCreated();
        $this->assertSame(1, $repo->releases()->first()->links()->count());
        $this->assertSame('Steam', $response->json('data.links.0.label'));
    }

    public function test_links_relation_orders_by_position(): void
    {
        [, , $repo] = $this->createRepositoryRecord();
        $release = Release::factory()->for($repo)->create();

        $release->links()->create(['label' => 'C', 'url' => 'https://example.com/c', 'platform' => 'other', 'position' => 2]);
        $release->links()->create(['label' => 'A', 'url' => 'https://example.com/a', 'platform' => 'other', 'position' => 0]);
        $release->links()->create(['label' => 'B', 'url' => 'https://example.com/b', 'platform' => 'other', 'position' => 1]);

        $this->assertSame(['A', 'B', 'C'], $release->fresh()->links->pluck('label')->all());
    }

    public function test_cascade_delete_removes_links(): void
    {
        [, , $repo] = $this->createRepositoryRecord();
        $release = Release::factory()->for($repo)->create();
        $release->links()->create(['label' => 'X', 'url' => 'https://example.com/', 'platform' => 'other', 'position' => 0]);

        $this->assertSame(1, ReleaseLink::query()->count());

        // Force-delete to fire the cascade on the FK; soft delete won't cascade by design.
        $release->forceDelete();

        $this->assertSame(0, ReleaseLink::query()->count());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function mockGitResolves(array $tagToSha): void
    {
        $this->mock(NativeGitRepositoryService::class, function (MockInterface $m) use ($tagToSha) {
            $m->shouldReceive('resolveSha')->andReturnUsing(
                fn (Repository $r, string $ref) => $tagToSha[$ref] ?? null,
            );
            $m->shouldReceive('tags')->andReturn([]);
            $m->shouldReceive('branches')->andReturn(['main']);
        });
    }
}
