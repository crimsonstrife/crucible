<?php

namespace Tests\Feature;

use App\Livewire\Repositories\CreateTagForm;
use App\Livewire\Repositories\ReleaseForm;
use App\Models\Release;
use App\Models\Repository;
use App\Models\User;
use App\Services\NativeGitRepositoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\Concerns\CreatesRepository;
use Tests\TestCase;

class ReleaseWebUiTest extends TestCase
{
    use CreatesRepository, RefreshDatabase;

    // ── Release form (create) ────────────────────────────────────────────────

    public function test_release_form_mounts_with_preselected_tag(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->mockGitTags([
            ['name' => 'v1.0.0', 'sha' => str_repeat('a', 40), 'subject' => 'first', 'date' => null, 'type' => 'commit'],
        ]);

        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo, 'preselectedTag' => 'v1.0.0'])
            ->assertSet('tagName', 'v1.0.0');
    }

    public function test_release_form_mount_denied_for_non_maintainer(): void
    {
        [, , $repo] = $this->createRepositoryRecord();
        $reader = User::factory()->create();
        $repo->collaborators()->attach($reader, ['role' => 'read']);

        $this->mockGitTags([]);
        $this->actingAs($reader);

        Livewire::test(ReleaseForm::class, ['repository' => $repo])
            ->assertStatus(403);
    }

    public function test_release_form_creates_release_with_entries(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->mockGitResolves(['v1.0.0' => str_repeat('a', 40)]);

        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo, 'preselectedTag' => 'v1.0.0'])
            ->set('name', 'First release')
            ->set('body', 'Initial')
            ->call('addEntry', 'new')
            ->set('entries.0.description', 'Multiplayer')
            ->call('addEntry', 'fixed')
            ->set('entries.1.description', 'Crash on save')
            ->call('save')
            ->assertHasNoErrors();

        $release = $repo->releases()->where('tag_name', 'v1.0.0')->first();
        $this->assertNotNull($release);
        $this->assertSame(str_repeat('a', 40), $release->commit_sha);
        $this->assertSame(2, $release->entries()->count());
    }

    public function test_release_form_rejects_unknown_tag(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->mockGitResolves([]);

        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo])
            ->set('tagName', 'v9.9.9')
            ->call('save')
            ->assertHasErrors(['tagName']);

        $this->assertSame(0, $repo->releases()->count());
    }

    public function test_release_form_rejects_duplicate_tag(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();
        Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0']);

        $this->mockGitResolves(['v1.0.0' => str_repeat('a', 40)]);

        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo])
            ->set('tagName', 'v1.0.0')
            ->call('save')
            ->assertHasErrors(['tagName']);
    }

    public function test_release_form_draft_does_not_set_published_at(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->mockGitResolves(['v1.0.0' => str_repeat('a', 40)]);

        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo])
            ->set('tagName', 'v1.0.0')
            ->set('isDraft', true)
            ->call('save')
            ->assertHasNoErrors();

        $release = $repo->releases()->first();
        $this->assertTrue($release->is_draft);
        $this->assertNull($release->published_at);
        $this->assertFalse($release->is_latest);
    }

    // ── Release form (edit) ──────────────────────────────────────────────────

    public function test_release_form_loads_existing_release_state(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();
        $release = Release::factory()->for($repo)->create([
            'tag_name' => 'v1.0.0',
            'name' => 'First',
            'body' => 'old body',
            'is_prerelease' => true,
        ]);

        $this->mockGitTags([]);

        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo, 'release' => $release])
            ->assertSet('tagName', 'v1.0.0')
            ->assertSet('name', 'First')
            ->assertSet('body', 'old body')
            ->assertSet('isPrerelease', true);
    }

    public function test_release_form_updates_body(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();
        $release = Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0', 'body' => 'old']);

        $this->mockGitTags([]);

        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo, 'release' => $release])
            ->set('body', 'new body')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('new body', $release->fresh()->body);
    }

    public function test_release_form_delete_soft_deletes(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();
        $release = Release::factory()->for($repo)->create(['tag_name' => 'v1.0.0']);

        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo, 'release' => $release])
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('releases', ['id' => $release->id]);
    }

    public function test_release_form_remove_entry_reindexes(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->actingAs($owner);

        Livewire::test(ReleaseForm::class, ['repository' => $repo])
            ->call('addEntry', 'new')
            ->call('addEntry', 'fixed')
            ->call('addEntry', 'improved')
            ->call('removeEntry', 1)
            ->assertSet('entries', [
                ['category' => 'new', 'description' => ''],
                ['category' => 'improved', 'description' => ''],
            ]);
    }

    // ── CreateTagForm ────────────────────────────────────────────────────────

    public function test_create_tag_form_creates_tag_via_service(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $createTagCalled = false;

        $this->mock(NativeGitRepositoryService::class, function (MockInterface $m) use ($repo, &$createTagCalled) {
            $m->shouldReceive('branches')->andReturn(['main', 'dev']);
            $m->shouldReceive('resolveSha')
                ->with(\Mockery::on(fn (Repository $r) => $r->id === $repo->id), 'main')
                ->andReturn(str_repeat('a', 40));
            $m->shouldReceive('resolveSha')
                ->with(\Mockery::on(fn (Repository $r) => $r->id === $repo->id), 'v1.0.0')
                ->andReturn(null);
            $m->shouldReceive('createTag')
                ->andReturnUsing(function () use (&$createTagCalled) {
                    $createTagCalled = true;
                });
        });

        $this->actingAs($owner);

        Livewire::test(CreateTagForm::class, ['repository' => $repo])
            ->call('toggle')
            ->set('tagName', 'v1.0.0')
            ->set('fromRef', 'main')
            ->call('createTag')
            ->assertHasNoErrors()
            ->assertSet('createdTag', 'v1.0.0');

        $this->assertTrue($createTagCalled);
    }

    public function test_create_tag_form_rejects_invalid_name(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->mock(NativeGitRepositoryService::class, function (MockInterface $m) {
            $m->shouldReceive('branches')->andReturn(['main']);
        });

        $this->actingAs($owner);

        Livewire::test(CreateTagForm::class, ['repository' => $repo])
            ->call('toggle')
            ->set('tagName', 'invalid name with spaces')
            ->set('fromRef', 'main')
            ->call('createTag')
            ->assertHasErrors(['tagName']);
    }

    public function test_create_tag_form_rejects_existing_tag(): void
    {
        [$owner, , $repo] = $this->createRepositoryRecord();

        $this->mock(NativeGitRepositoryService::class, function (MockInterface $m) use ($repo) {
            $m->shouldReceive('branches')->andReturn(['main']);
            $m->shouldReceive('resolveSha')
                ->with(\Mockery::on(fn (Repository $r) => $r->id === $repo->id), 'main')
                ->andReturn(str_repeat('a', 40));
            $m->shouldReceive('resolveSha')
                ->with(\Mockery::on(fn (Repository $r) => $r->id === $repo->id), 'v1.0.0')
                ->andReturn(str_repeat('b', 40));  // already exists
            $m->shouldNotReceive('createTag');
        });

        $this->actingAs($owner);

        Livewire::test(CreateTagForm::class, ['repository' => $repo])
            ->call('toggle')
            ->set('tagName', 'v1.0.0')
            ->set('fromRef', 'main')
            ->call('createTag')
            ->assertHasErrors(['tagName']);
    }

    public function test_create_tag_form_requires_push_permission(): void
    {
        [, , $repo] = $this->createRepositoryRecord();
        $reader = User::factory()->create();
        $repo->collaborators()->attach($reader, ['role' => 'read']);

        $this->mock(NativeGitRepositoryService::class, function (MockInterface $m) {
            $m->shouldReceive('branches')->andReturn(['main']);
            $m->shouldNotReceive('createTag');
        });

        $this->actingAs($reader);

        Livewire::test(CreateTagForm::class, ['repository' => $repo])
            ->call('createTag')
            ->assertStatus(403);
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

    protected function mockGitTags(array $tags): void
    {
        $this->mock(NativeGitRepositoryService::class, function (MockInterface $m) use ($tags) {
            $m->shouldReceive('tags')->andReturn($tags);
            $m->shouldReceive('branches')->andReturn(['main']);
            $m->shouldReceive('resolveSha')->andReturn(null);
        });
    }
}
