<?php

namespace App\Livewire\Repositories;

use App\Enums\ReleaseCategory;
use App\Models\Release;
use App\Models\Repository;
use App\Services\NativeGitRepositoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ReleaseForm extends Component
{
    use AuthorizesRequests;

    public Repository $repository;

    public ?Release $release = null;

    public string $tagName = '';

    public string $name = '';

    public string $body = '';

    public bool $isDraft = false;

    public bool $isPrerelease = false;

    /** @var array<int, array{category: string, description: string}> */
    public array $entries = [];

    public function mount(?string $preselectedTag = null): void
    {
        if ($this->release !== null) {
            $this->authorize('manageReleases', $this->repository);
            $this->tagName = $this->release->tag_name;
            $this->name = (string) $this->release->name;
            $this->body = (string) $this->release->body;
            $this->isDraft = (bool) $this->release->is_draft;
            $this->isPrerelease = (bool) $this->release->is_prerelease;
            $this->entries = $this->release->entries->map(fn ($e) => [
                'category' => $e->category->value,
                'description' => $e->description,
            ])->all();
        } else {
            $this->authorize('manageReleases', $this->repository);
            if ($preselectedTag !== null && $preselectedTag !== '') {
                $this->tagName = $preselectedTag;
            }
        }
    }

    public function addEntry(string $category = 'new'): void
    {
        $this->entries[] = ['category' => $category, 'description' => ''];
    }

    public function removeEntry(int $index): void
    {
        unset($this->entries[$index]);
        $this->entries = array_values($this->entries);
    }

    public function save(NativeGitRepositoryService $git): void
    {
        $this->authorize('manageReleases', $this->repository);

        $categoryValues = array_map(fn ($c) => $c->value, ReleaseCategory::cases());

        $validated = $this->validate([
            'tagName'      => ['required', 'string', 'max:255'],
            'name'         => ['nullable', 'string', 'max:255'],
            'body'         => ['nullable', 'string'],
            'isDraft'      => ['boolean'],
            'isPrerelease' => ['boolean'],
            'entries'      => ['array'],
            'entries.*.category'    => ['required', 'string', 'in:'.implode(',', $categoryValues)],
            'entries.*.description' => ['required', 'string', 'max:2000'],
        ]);

        $isCreate = $this->release === null;

        if ($isCreate || $validated['tagName'] !== $this->release->tag_name) {
            $sha = $git->resolveSha($this->repository, $validated['tagName']);
            if ($sha === null) {
                $this->addError('tagName', 'Tag not found in repository.');
                return;
            }
            $commitSha = $sha;
        } else {
            $commitSha = $this->release->commit_sha;
        }

        $duplicate = $this->repository->releases()
            ->where('tag_name', $validated['tagName'])
            ->when($this->release !== null, fn ($q) => $q->where('id', '!=', $this->release->id))
            ->exists();
        if ($duplicate) {
            $this->addError('tagName', 'A release for this tag already exists.');
            return;
        }

        $publishedAt = $validated['isDraft'] ? null : ($this->release?->published_at ?? now());

        $entries = collect($validated['entries'] ?? [])
            ->filter(fn ($e) => trim($e['description'] ?? '') !== '')
            ->values()
            ->all();

        $release = DB::transaction(function () use ($isCreate, $validated, $commitSha, $publishedAt, $entries) {
            if ($isCreate) {
                $release = $this->repository->releases()->create([
                    'author_id'     => auth()->id(),
                    'tag_name'      => $validated['tagName'],
                    'commit_sha'    => $commitSha,
                    'name'          => $validated['name'] ?: null,
                    'body'          => $validated['body'] ?: null,
                    'is_draft'      => $validated['isDraft'],
                    'is_prerelease' => $validated['isPrerelease'],
                    'published_at'  => $publishedAt,
                ]);
            } else {
                $this->release->fill([
                    'tag_name'      => $validated['tagName'],
                    'commit_sha'    => $commitSha,
                    'name'          => $validated['name'] ?: null,
                    'body'          => $validated['body'] ?: null,
                    'is_draft'      => $validated['isDraft'],
                    'is_prerelease' => $validated['isPrerelease'],
                    'published_at'  => $publishedAt,
                ])->save();
                $release = $this->release;
            }

            $release->entries()->delete();
            foreach ($entries as $i => $entry) {
                $release->entries()->create([
                    'category'    => $entry['category'],
                    'description' => $entry['description'],
                    'position'    => $i,
                ]);
            }

            return $release;
        });

        session()->flash('success', $isCreate ? 'Release created.' : 'Release updated.');
        $this->redirectRoute('repositories.releases.show', [
            $this->repository->organization,
            $this->repository,
            $release,
        ], navigate: false);
    }

    public function delete(): void
    {
        $this->authorize('manageReleases', $this->repository);
        abort_if($this->release === null, 404);

        $this->release->delete();
        session()->flash('success', 'Release deleted.');
        $this->redirectRoute('repositories.releases.index', [
            $this->repository->organization,
            $this->repository,
        ], navigate: false);
    }

    #[Computed]
    public function availableTags(): array
    {
        try {
            $git = app(NativeGitRepositoryService::class);
            $used = $this->repository->releases()
                ->when($this->release !== null, fn ($q) => $q->where('id', '!=', $this->release->id))
                ->pluck('tag_name')
                ->all();

            return collect($git->tags($this->repository))
                ->reject(fn ($t) => in_array($t['name'] ?? null, $used, true))
                ->map(fn ($t) => ['name' => $t['name'], 'sha' => $t['sha'], 'subject' => $t['subject'] ?? ''])
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    #[Computed]
    public function categories(): array
    {
        return ReleaseCategory::cases();
    }

    public function render()
    {
        return view('livewire.repositories.release-form');
    }
}
