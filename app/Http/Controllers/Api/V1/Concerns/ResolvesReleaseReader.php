<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Repository;
use App\Models\ReleaseToken;
use App\Models\User;
use Illuminate\Http\Request;

trait ResolvesReleaseReader
{
    /**
     * Resolve the calling identity for a public release-read endpoint.
     *
     * Priority:
     *   1. Bearer token prefixed `crl_` → ReleaseToken bound to this repository.
     *      Any failure here aborts with 401 (uniform; never leaks repo binding).
     *   2. Bearer token without that prefix → Sanctum user lookup (may be null).
     *   3. No bearer → anonymous (both values null).
     *
     * @return array{user: ?User, releaseToken: ?ReleaseToken}
     */
    protected function resolveReader(Request $request, Repository $repository): array
    {
        $bearer = $request->bearerToken();

        if ($bearer !== null && str_starts_with($bearer, ReleaseToken::TOKEN_PREFIX)) {
            $token = ReleaseToken::findByRawToken($bearer);

            if ($token === null || $token->repository_id !== $repository->id) {
                abort(401, 'Invalid release token.');
            }

            $token->forceFill(['last_used_at' => now()])->saveQuietly();

            return ['user' => null, 'releaseToken' => $token];
        }

        return [
            'user' => auth('sanctum')->user(),
            'releaseToken' => null,
        ];
    }
}
