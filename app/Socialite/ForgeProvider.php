<?php

namespace App\Socialite;

use GuzzleHttp\Client;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * Custom Socialite driver for authenticating with a Forge OAuth 2.0 server.
 *
 * Forge uses Laravel Passport as its OAuth server.  A Passport client
 * (Authorization Code grant) must be created in Forge for this Crucible
 * installation.  Configure via:
 *
 *   FORGE_ENABLED=true
 *   FORGE_URL=https://forge.example.com
 *   FORGE_CLIENT_ID=<passport client uuid>
 *   FORGE_CLIENT_SECRET=<passport client secret>
 *   FORGE_REDIRECT_URI=https://crucible.example.com/auth/forge/callback
 *
 * For local development with a self-signed certificate also set:
 *   FORGE_DISABLE_TLS_VERIFICATION=true
 */
class ForgeProvider extends AbstractProvider
{
    /** Scopes requested during SSO: profile for identity, plus API access for integration features. */
    protected $scopes = ['profile', 'projects:read', 'issues:read'];

    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(
            rtrim(config('crucible.forge.url'), '/') . '/oauth/authorize',
            $state
        );
    }

    protected function getTokenUrl(): string
    {
        return rtrim(config('crucible.forge.url'), '/') . '/oauth/token';
    }

    /**
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            rtrim(config('crucible.forge.url'), '/') . '/api/v1/me',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ],
            ]
        );

        $body = json_decode((string) $response->getBody(), true);

        return $body['data'] ?? $body;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject($user): User
    {
        return (new User)->setRaw($user)->map([
            'id'       => $user['id'] ?? null,
            'name'     => $user['name'] ?? null,
            'email'    => $user['email'] ?? null,
            'avatar'   => $user['avatar_url'] ?? null,
            'nickname' => $user['name'] ?? null,
        ]);
    }

    /**
     * Override Guzzle client to disable TLS verification in local dev
     * when Forge is running with a self-signed / localhost certificate.
     */
    protected function getHttpClient(): Client
    {
        if (config('crucible.forge.disable_tls_verification', false)) {
            return new Client(['verify' => false]);
        }

        return parent::getHttpClient();
    }
}
