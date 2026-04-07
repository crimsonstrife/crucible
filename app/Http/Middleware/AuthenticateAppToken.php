<?php

namespace App\Http\Middleware;

use App\Models\AppToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAppToken
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $raw = $request->bearerToken();

        if (! $raw) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = AppToken::findByRawToken($raw);

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        foreach ($abilities as $ability) {
            if (! $token->can($ability)) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        $token->touchLastUsed();
        $request->attributes->set('app_token', $token);

        return $next($request);
    }
}
