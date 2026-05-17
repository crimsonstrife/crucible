<?php

namespace App\Livewire\Repositories;

use App\Models\Repository;
use App\Services\NativeGitRepositoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

class CreateTagForm extends Component
{
    use AuthorizesRequests;

    public Repository $repository;

    public string $tagName = '';

    public string $fromRef = '';

    public string $message = '';

    public ?string $createdTag = null;

    public bool $open = false;

    public function mount(): void
    {
        $this->fromRef = $this->repository->default_branch ?? 'main';
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
        if (! $this->open) {
            $this->resetExcept('repository', 'fromRef');
        }
    }

    public function createTag(NativeGitRepositoryService $git): void
    {
        $this->authorize('push', $this->repository);

        $validated = $this->validate([
            'tagName' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._\-\/]+$/'],
            'fromRef' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'tagName.regex' => 'Tag name may only contain letters, numbers, dots, dashes, slashes, and underscores.',
        ]);

        $sha = $git->resolveSha($this->repository, $validated['fromRef']);
        if ($sha === null) {
            $this->addError('fromRef', 'Branch or ref not found.');
            return;
        }

        if ($git->resolveSha($this->repository, $validated['tagName']) !== null) {
            $this->addError('tagName', 'A tag with this name already exists.');
            return;
        }

        try {
            $git->createTag($this->repository, $validated['tagName'], $sha, $validated['message'] ?? null);
        } catch (RuntimeException $e) {
            $this->addError('tagName', $e->getMessage());
            return;
        }

        $this->createdTag = $validated['tagName'];
        $this->reset(['tagName', 'message']);
        $this->dispatch('tag-created', tag: $this->createdTag);
    }

    public function dismissSuccess(): void
    {
        $this->createdTag = null;
    }

    #[Computed]
    public function branches(): array
    {
        try {
            return app(NativeGitRepositoryService::class)->branches($this->repository);
        } catch (\Throwable) {
            return [];
        }
    }

    public function render()
    {
        return view('livewire.repositories.create-tag-form');
    }
}
