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
                    'scope'         => 'projects:read issues:read organizations:read',
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
     * Automatically refreshes the token when expired if a refresh token is available.
     */
    protected function getUserToken(User $user): ?string
    {
        $connected = ConnectedApp::where('user_id', $user->id)
            ->where('provider', 'forge')
            ->first();

        if (! $connected) {
            return null;
        }

        if ($connected->isExpired()) {
            if (! $connected->refresh_token) {
                return null;
            }

            $refreshed = $this->refreshUserToken($connected);

            if (! $refreshed) {
                return null;
            }

            return $refreshed;
        }

        return $connected->access_token;
    }

    /**
     * Refresh an expired user OAuth token using the stored refresh token.
     * Updates the ConnectedApp record with the new tokens on success.
     */
    protected function refreshUserToken(ConnectedApp $connected): ?string
    {
        try {
            $response = $this->httpClient()
                ->asForm()
                ->timeout(10)
                ->post($this->baseUrl . '/oauth/token', [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $connected->refresh_token,
                    'client_id'     => config('crucible.forge.client_id'),
                    'client_secret' => config('crucible.forge.client_secret'),
                    'scope'         => '',
                ]);

            if (! $response->successful()) {
                Log::warning('[ForgeService] Failed to refresh user token', [
                    'user_id' => $connected->user_id,
                    'status'  => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            $connected->update([
                'access_token'     => $data['access_token'],
                'refresh_token'    => $data['refresh_token'] ?? $connected->refresh_token,
                'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 1296000),
            ]);

            return $data['access_token'];
        } catch (\Throwable $e) {
            Log::error('[ForgeService] Token refresh exception', [
                'user_id' => $connected->user_id,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
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

    // ── Organizations (M2M system endpoint) ──────────────────────────────────────

    /**
     * List Forge organizations visible to a specific user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrganizations(?string $forgeUserId = null): array
    {
        if (! $this->isConfigured() || $forgeUserId === null) {
            return [];
        }

        $path = '/api/v1/system/organizations?' . http_build_query(['for_forge_user_id' => $forgeUserId]);

        $response = $this->m2mGet($path);

        return $response['data'] ?? $response;
    }

    // ── Project Issues (M2M system endpoint) ────────────────────────────────────

    /**
     * List issues for a Forge project via the M2M system endpoint.
     *
     * Used by the repository Issues tab to display linked project issues.
     * Scoped by the requesting user's forge_user_id.
     *
     * @return array{data: array, current_page: int, last_page: int, total: int}
     */
    public function getProjectIssues(string $projectId, ?string $forgeUserId = null, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (! $this->isConfigured() || ! $forgeUserId) {
            return ['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0];
        }

        $params = array_filter(array_merge([
            'for_forge_user_id' => $forgeUserId,
            'page' => $page,
            'per_page' => $perPage,
        ], $filters));

        $path = "/api/v1/system/projects/{$projectId}/issues?" . http_build_query($params);

        $response = $this->m2mGet($path);

        return [
            'data' => $response['data'] ?? [],
            'current_page' => $response['current_page'] ?? $response['meta']['current_page'] ?? 1,
            'last_page' => $response['last_page'] ?? $response['meta']['last_page'] ?? 1,
            'total' => $response['total'] ?? $response['meta']['total'] ?? 0,
        ];
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
