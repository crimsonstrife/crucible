<?php

namespace Tests\Feature;

use App\Models\Release;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRepository;
use Tests\TestCase;

class ReleaseModelTest extends TestCase
{
    use CreatesRepository, RefreshDatabase;

    public function test_slug_is_generated_from_tag_name(): void
    {
        [, , $repository] = $this->createRepositoryRecord();

        $release = Release::create([
            'repository_id' => $repository->id,
            'tag_name' => 'v1.2.3',
            'commit_sha' => str_repeat('a', 40),
            'published_at' => now(),
        ]);

        $this->assertSame('v1-2-3', $release->slug);
    }

    public function test_published_scope_excludes_drafts_and_unpublished(): void
    {
        [, , $repository] = $this->createRepositoryRecord();

        Release::factory()->for($repository)->create(['tag_name' => 'v1.0.0']);
        Release::factory()->for($repository)->draft()->create(['tag_name' => 'v2.0.0-draft']);
        Release::factory()->for($repository)->create(['tag_name' => 'v3.0.0', 'published_at' => null]);

        $published = Release::query()->published()->get();

        $this->assertCount(1, $published);
        $this->assertSame('v1.0.0', $published->first()->tag_name);
    }

    public function test_is_latest_is_assigned_to_the_newest_published_release(): void
    {
        [, , $repository] = $this->createRepositoryRecord();

        $older = Release::factory()->for($repository)->create([
            'tag_name' => 'v1.0.0',
            'published_at' => now()->subDays(5),
        ]);

        $this->assertTrue($older->fresh()->is_latest);

        $newer = Release::factory()->for($repository)->create([
            'tag_name' => 'v2.0.0',
            'published_at' => now(),
        ]);

        $this->assertFalse($older->fresh()->is_latest);
        $this->assertTrue($newer->fresh()->is_latest);
    }

    public function test_drafts_and_prereleases_never_become_latest(): void
    {
        [, , $repository] = $this->createRepositoryRecord();

        Release::factory()->for($repository)->draft()->create(['tag_name' => 'v1.0.0-draft']);
        Release::factory()->for($repository)->prerelease()->create([
            'tag_name' => 'v1.0.0-rc1',
            'published_at' => now(),
        ]);

        $this->assertSame(0, Release::query()->where('is_latest', true)->count());
    }

    public function test_is_latest_recomputes_when_newest_release_is_deleted(): void
    {
        [, , $repository] = $this->createRepositoryRecord();

        $older = Release::factory()->for($repository)->create([
            'tag_name' => 'v1.0.0',
            'published_at' => now()->subDays(5),
        ]);
        $newer = Release::factory()->for($repository)->create([
            'tag_name' => 'v2.0.0',
            'published_at' => now(),
        ]);

        $this->assertTrue($newer->fresh()->is_latest);
        $this->assertFalse($older->fresh()->is_latest);

        $newer->delete();

        $this->assertTrue($older->fresh()->is_latest);
    }

    public function test_unique_tag_per_repository_is_enforced(): void
    {
        [, , $repository] = $this->createRepositoryRecord();

        Release::factory()->for($repository)->create(['tag_name' => 'v1.0.0']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Release::factory()->for($repository)->create(['tag_name' => 'v1.0.0']);
    }

    public function test_entries_come_back_in_semantic_category_order_not_alphabetical(): void
    {
        [, , $repository] = $this->createRepositoryRecord();
        $release = Release::factory()->for($repository)->create(['tag_name' => 'v1.0.0']);

        // Insert in mixed order so alphabetical would yield: deprecated, fixed, new, removed.
        // Semantic (sortOrder) should yield: new, fixed, deprecated, removed.
        $release->entries()->create(['category' => 'deprecated', 'description' => 'A',  'position' => 0]);
        $release->entries()->create(['category' => 'new',        'description' => 'B',  'position' => 0]);
        $release->entries()->create(['category' => 'removed',    'description' => 'C',  'position' => 0]);
        $release->entries()->create(['category' => 'fixed',      'description' => 'D',  'position' => 0]);

        $this->assertSame(
            ['new', 'fixed', 'deprecated', 'removed'],
            $release->fresh()->entries->pluck('category.value')->all(),
        );
    }

    public function test_entries_within_a_category_are_ordered_by_position(): void
    {
        [, , $repository] = $this->createRepositoryRecord();
        $release = Release::factory()->for($repository)->create(['tag_name' => 'v1.0.0']);

        $release->entries()->create(['category' => 'new', 'description' => 'second', 'position' => 1]);
        $release->entries()->create(['category' => 'new', 'description' => 'first',  'position' => 0]);

        $this->assertSame(
            ['first', 'second'],
            $release->fresh()->entries->pluck('description')->all(),
        );
    }
}
