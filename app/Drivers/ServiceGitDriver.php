<?php

namespace App\Drivers;

use App\Contracts\RepositoryDriverInterface;
use App\Models\Repository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ServiceGitDriver implements RepositoryDriverInterface
{
    protected function client()
    {
        return Http::withToken(config('crucible.git.service_token'))
            ->baseUrl(rtrim(config('crucible.git.service_url'), '/'))
            ->acceptJson()
            ->timeout(30);
    }

    protected function post(string $path, array $data = []): array
    {
        $response = $this->client()->post($path, $data);

        if ($response->failed()) {
            Log::error('[ServiceGitDriver] request failed', ['path' => $path, 'status' => $response->status()]);
            throw new RuntimeException("Git service error: {$response->status()}");
        }

        return $response->json() ?? [];
    }

    protected function get(string $path): array
    {
        $response = $this->client()->get($path);

        if ($response->failed()) {
            return [];
        }

        return $response->json() ?? [];
    }

    public function initialize(Repository $repo): void
    {
        $this->post('/repositories', [
            'id'   => $repo->id,
            'slug' => $repo->slug,
            'org'  => $repo->organization->slug,
        ]);
    }

    public function clone(string $remote, Repository $repo): void
    {
        $this->post('/repositories/clone', [
            'id'     => $repo->id,
            'remote' => $remote,
            'org'    => $repo->organization->slug,
            'slug'   => $repo->slug,
        ]);
    }

    public function branches(Repository $repo): array
    {
        $data = $this->get("/repositories/{$repo->id}/branches");

        return $data['branches'] ?? [];
    }

    public function defaultBranch(Repository $repo): string
    {
        $data = $this->get("/repositories/{$repo->id}");

        return $data['default_branch'] ?? ($repo->default_branch ?? 'main');
    }

    public function archive(Repository $repo): void
    {
        $this->post("/repositories/{$repo->id}/archive");
    }

    public function delete(Repository $repo): void
    {
        $this->client()->delete("/repositories/{$repo->id}");
    }

    public function exists(Repository $repo): bool
    {
        $response = $this->client()->get("/repositories/{$repo->id}");

        return $response->successful();
    }

    public function size(Repository $repo): int
    {
        $data = $this->get("/repositories/{$repo->id}");

        return (int) ($data['size_bytes'] ?? 0);
    }
}
