<?php

namespace App\Livewire\Repositories;

use App\Models\ReleaseToken;
use App\Models\Repository;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Livewire\Component;

class ReleaseTokensManager extends Component
{
    use AuthorizesRequests;

    public Repository $repository;

    public string $name = '';

    /** One of: never | 30d | 90d | 1y */
    public string $expiresIn = 'never';

    /** Raw token shown once after a successful generate. Cleared on dismiss. */
    public ?string $generatedTokenRaw = null;

    public ?string $generatedTokenName = null;

    public function mount(): void
    {
        $this->authorize('manageReleases', $this->repository);
    }

    public function createToken(): void
    {
        $this->authorize('manageReleases', $this->repository);

        $validated = $this->validate([
            'name'      => ['required', 'string', 'max:100'],
            'expiresIn' => ['required', 'in:never,30d,90d,1y'],
        ]);

        $expiresAt = match ($validated['expiresIn']) {
            '30d' => Carbon::now()->addDays(30),
            '90d' => Carbon::now()->addDays(90),
            '1y'  => Carbon::now()->addYear(),
            default => null,
        };

        $result = ReleaseToken::generate(
            $this->repository,
            auth()->user(),
            $validated['name'],
            $expiresAt,
        );

        $this->generatedTokenRaw  = $result['raw'];
        $this->generatedTokenName = $result['token']->name;
        $this->reset(['name', 'expiresIn']);
        $this->expiresIn = 'never';
    }

    public function revokeToken(string $tokenId): void
    {
        $this->authorize('manageReleases', $this->repository);

        $token = $this->repository->releaseTokens()->whereKey($tokenId)->firstOrFail();
        $token->delete();

        session()->flash('release-tokens-success', 'Token revoked.');
    }

    public function dismissGeneratedToken(): void
    {
        $this->generatedTokenRaw  = null;
        $this->generatedTokenName = null;
    }

    public function render()
    {
        $tokens = $this->repository
            ->releaseTokens()
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.repositories.release-tokens-manager', [
            'tokens' => $tokens,
        ]);
    }
}
