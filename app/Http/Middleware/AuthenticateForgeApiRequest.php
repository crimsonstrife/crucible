<?php

namespace App\Http\Middleware;

use App\Models\AppToken;
use App\Models\ForgeIntegration;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateForgeApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('sanctum');

        if ($guard->check()) {
            $user = $guard->user();
            $request->setUserResolver(static fn () => $user);

            return $next($request);
        }

        $token = $request->bearerToken();

        if (! $token) {
            return $this->unauthenticated();
        }

        $appToken = AppToken::findByRawToken($token);

        if ($appToken) {
            if (! $appToken->can('forge.api')) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            $user = $this->resolveScopedUser($request);

            if (! $user) {
                return response()->json([
                    'message' => 'No Crucible user is linked to that Forge account.',
                ], 403);
            }

            $appToken->touchLastUsed();

            $request->attributes->set('app_token', $appToken);
            $request->attributes->set('forge_auth_mode', 'app_token');
            $request->attributes->set('forge_user_id', $this->scopedForgeUserId($request));
            $request->setUserResolver(static fn () => $user);

            return $next($request);
        }

        $integration = ForgeIntegration::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return $this->unauthenticated();
        }

        $user = $this->resolveScopedUser($request);

        if (! $user) {
            return response()->json([
                'message' => 'No Crucible user is linked to that Forge account.',
            ], 403);
        }

        $integration->forceFill([
            'api_token_last_used_at' => now(),
        ])->saveQuietly();

        $request->attributes->set('forge_integration', $integration);
        $request->attributes->set('forge_auth_mode', 'integration_token');
        $request->attributes->set('forge_user_id', $this->scopedForgeUserId($request));
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }

    protected function unauthenticated(): JsonResponse
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    private function scopedForgeUserId(Request $request): string
    {
        return trim((string) (
            $request->input('for_forge_user_id')
            ?: $request->input('for_user')
            ?: $request->header('X-Forge-User-Id', '')
        ));
    }

    private function resolveScopedUser(Request $request): ?User
    {
        $forgeUserId = $this->scopedForgeUserId($request);

        if ($forgeUserId === '') {
            return null;
        }

        return User::query()
            ->where('forge_user_id', $forgeUserId)
            ->first();
    }
}
