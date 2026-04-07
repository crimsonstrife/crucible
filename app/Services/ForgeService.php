<?php

namespace App\Services;

use App\Contracts\ForgeIntegrationInterface;
use App\Models\ConnectedApp;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service layer for communicating with a Forge instance.
 *
 * Two authentication modes:
 *
 * 1. M2M (machine-to-machine) — OAuth 2.0 client credentials grant.
 *    Used for system-level endpoints like /api/v1/system/projects.
 *    Requires FORGE_M2M_CLIENT_ID + FORGE_M2M_CLIENT_SECRET.
 *    Generate a client in Forge: php artisan passport:client --client --name="Crucible M2M"
 *
 * 2. User token — the authenticated Forge user's access token, stored in
 *    the connected_apps table after SSO login.  Used for user-scoped endpoints
 *    such as reading issues or posting VCS link notifications.
 */
class ForgeService implements ForgeIntegrationInterface
{
    protected string  $baseUrl;
    protected ?string $m2mClientId;
    protected ?string $m2mClientSecret;
    protected bool    $skipTls;

    public function __construct()
    {
        $this->baseUrl         = rtrim((string) config('crucible.forge.url'), '/');
        $this->m2mClientId     = config('crucible.forge.m2m_client_id') ?: null;
        $this->m2mClientSecret = config('crucible.forge.m2m_client_secret') ?: null;
        $this->skipTls         = (bool) config('crucible.forge.disable_tls_verification', false);
    }

    // ── Configuration ──────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return config('crucible.forge.enabled', false)
            && ! empty($this->baseUrl)
            && ! empty($this->m2mClientId)
            && ! empty($this->m2mClientSecret);
    }

    // ── M2M token acquisition ──────────────────────────────────────────────────

    /**
     * Obtain a client-credentials access token from Forge, cached for 23 hours.
     *
     * Passport client_credentials tokens have a 1-year default TTL, so 23 hours
     * is well within that window.  The cache key includes the client ID so that
     * rotating the M2M client automatically invalidates the cached token.
     */
    protected function getM2mToken(): ?string
    {
        $cacheKey = 'forge_service.m2m_token.' . md5($this->m2mClientId ?? '');

        return Cache::remember($cacheKey, now()->addHours(23), function () {
            $response = $this->httpClient()
                ->asForm()
                ->timeout(10)
                ->post($this->baseUrl . '/oauth/token', [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->m2mClientId,
                    'client_secret' => $this->m2mClientSecret,
                    'scope'         => 'projects:read',
                ]);

            if (! $response->successful()) {
                Log::warning('[ForgeService] Failed to obtain M2M token', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * Retrieve the stored OAuth access token for a given Crucible user.
     * Returns null if the user has not linked their Forge account via SSO.
     */
    protected function getUserToken(User $user): ?string
    {
        $connected = ConnectedApp::where('user_id', $user->id)
            ->where('provider', 'forge')
            ->first();

        if (! $connected || $connected->isExpired()) {
            return null;
        }

        return $connected->access_token;
    }

    // ── Projects (M2M system endpoints) ───────────────────────────────────────

    /**
     * List Forge projects visible to a specific user.
     *
     * Scoped by $forgeUserId so the system token never returns projects the
     * requesting user has no access to.  Returns [] when no user identity is
     * available (user has not yet signed in via Forge SSO).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProjects(?string $forgeUserId = null): array
    {
        if (! $this->isConfigured()) {
            Log::warning('[ForgeService] getProjects called but service is not configured', [
                'baseUrl'      => $this->baseUrl ?: '(empty)',
                'clientId'     => $this->m2mClientId ? 'set' : 'missing',
                'clientSecret' => $this->m2mClientSecret ? 'set' : 'missing',
            ]);

            return [];
        }

        // Without a Forge user identity we cannot scope results — return nothing.
        if ($forgeUserId === null) {
            return [];
        }

        $path = '/api/v1/system/projects?' . http_build_query(['for_forge_user_id' => $forgeUserId]);

        $response = $this->m2mGet($path);

        return $response['data'] ?? $response;
    }

    /**
     * Get a single Forge project by ID (M2M).
     *
     * @return array<string, mixed>|null
     */
    public function getProject(string $id): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $data = $this->m2mGet("/api/v1/system/projects/{$id}");

        return $data['data'] ?? ($data ?: null);
    }

    // ── Issues (user-scoped endpoints) ────────────────────────────────────────

    /**
     * List issues in a Forge project, optionally filtered by search query.
     * Requires the user's OAuth token — returns [] if the user has not linked
     * their Forge account via SSO.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getIssues(string $projectId, string $query = '', int $limit = 50, ?User $user = null): array
    {
        if (! $this->isConfigured() || ! $user) {
            return [];
        }

        $token = $this->getUserToken($user);

        if (! $token) {
            return [];
        }

        $params = array_filter(['project' => $projectId, 'q' => $query, 'per_page' => $limit]);

        return $this->userGet('/api/v1/issues', $params, $token)['data'] ?? [];
    }

    /**
     * Get a single issue by key (e.g. "PROJ-123").
     * Uses the user's OAuth token when provided, falls back to M2M for read access
     * if the issue endpoint allows it (Forge ≥ v1.3 exposes a system/issues route).
     * Gracefully returns null rather than throwing on any failure.
     *
     * @return array<string, mixed>|null
     */
    public function getIssue(string $issueKey, ?User $user = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        // Prefer the user's own token for accurate access scoping.
        $token = $user ? $this->getUserToken($user) : null;

        $data = $token
            ? $this->userGet("/api/v1/issues/{$issueKey}", [], $token)
            : $this->m2mGet("/api/v1/issues/{$issueKey}");

        return $data['data'] ?? ($data ?: null);
    }

    // ── VCS link notifications (Crucible → Forge) ──────────────────────────────

    /**
     * Notify Forge that a branch was created/linked for an issue.
     * Prefers the acting user's token; falls back to M2M if unavailable.
     */
    public function linkBranchToIssue(string $issueKey, array $payload, ?User $user = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $token = $user ? $this->getUserToken($user) : null;

        $result = $token
            ? $this->userPost("/api/issues/{$issueKey}/vcs/link/branch", $payload, $token)
            : $this->m2mPost("/api/issues/{$issueKey}/vcs/link/branch", $payload);

        return $result !== null;
    }

    /**
     * Notify Forge that a PR was created/linked for an issue.
     */
    public function linkPrToIssue(string $issueKey, array $payload, ?User $user = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $token = $user ? $this->getUserToken($user) : null;

        $result = $token
            ? $this->userPost("/api/issues/{$issueKey}/vcs/link/pr", $payload, $token)
            : $this->m2mPost("/api/issues/{$issueKey}/vcs/link/pr", $payload);

        return $result !== null;
    }

    /**
     * Notify Forge that a PR was merged (updates the VCS link state to 'merged').
     */
    public function notifyPrMerged(string $issueKey, int $prNumber, string $prTitle, string $prUrl, ?User $user = null): bool
    {
        return $this->linkPrToIssue($issueKey, [
            'number' => $prNumber,
            'title'  => $prTitle,
            'state'  => 'merged',
            'url'    => $prUrl,
        ], $user);
    }

    // ── HTTP helpers ───────────────────────────────────────────────────────────

    /** Base HTTP client (TLS skip applied when configured). */
    protected function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::acceptJson()->timeout(10);

        if ($this->skipTls) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /** GET using the M2M client-credentials token. */
    protected function m2mGet(string $path): array
    {
        $token = $this->getM2mToken();

        if (! $token) {
            return [];
        }

        $url = $this->baseUrl . $path;

        try {
            $response = $this->httpClient()->withToken($token)->get($url);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            if (in_array($response->status(), [401, 403], true)) {
                // Token rejected — clear cache so the next request re-authenticates.
                Cache::forget('forge_service.m2m_token.' . md5($this->m2mClientId ?? ''));
            }

            Log::warning('[ForgeService] M2M GET non-2xx', [
                'url'    => $url,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[ForgeService] M2M GET exception', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return [];
    }

    /** POST using the M2M client-credentials token. Returns null on failure. */
    protected function m2mPost(string $path, array $data = []): ?array
    {
        $token = $this->getM2mToken();

        if (! $token) {
            return null;
        }

        $url = $this->baseUrl . $path;

        try {
            $response = $this->httpClient()->withToken($token)->post($url, $data);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            if (in_array($response->status(), [401, 403], true)) {
                Cache::forget('forge_service.m2m_token.' . md5($this->m2mClientId ?? ''));
            }

            Log::warning('[ForgeService] M2M POST non-2xx', [
                'url'    => $url,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[ForgeService] M2M POST exception', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /** GET using a user's OAuth access token. */
    protected function userGet(string $path, array $query = [], string $token = ''): array
    {
        $url = $this->baseUrl . $path;

        try {
            $request  = $this->httpClient()->withToken($token);
            $response = empty($query) ? $request->get($url) : $request->get($url, $query);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::warning('[ForgeService] User GET non-2xx', [
                'url'    => $url,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[ForgeService] User GET exception', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return [];
    }

    /** POST using a user's OAuth access token. Returns null on failure. */
    protected function userPost(string $path, array $data = [], string $token = ''): ?array
    {
        $url = $this->baseUrl . $path;

        try {
            $response = $this->httpClient()->withToken($token)->post($url, $data);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::warning('[ForgeService] User POST non-2xx', [
                'url'    => $url,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[ForgeService] User POST exception', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return null;
    }
}
