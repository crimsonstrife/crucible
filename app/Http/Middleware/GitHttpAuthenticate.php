<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP Basic Auth middleware for git clone/push.
 *
 * Accepts:
 *   - Username = email, Password = account password
 *   - Username = email, Password = Sanctum personal access token
 *
 * Anonymous access is allowed for public repository read operations
 * (git-upload-pack / info/refs?service=git-upload-pack). The
 * GitHttpController enforces visibility on private repos.
 *
 * Push (git-receive-pack) always requires authentication.
 */
class GitHttpAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = $request->getUser();
        $password = $request->getPassword();

        if ($username && $password) {
            $user = $this->resolveUser($username, $password);

            if (! $user) {
                return $this->requireAuth('Invalid credentials.');
            }

            auth()->setUser($user);
            return $next($request);
        }

        // No credentials: allow anonymous reads, reject anonymous writes
        $isWriteOp = str_contains($request->path(), 'git-receive-pack')
                  || $request->query('service') === 'git-receive-pack';

        if ($isWriteOp) {
            return $this->requireAuth('Authentication required to push.');
        }

        return $next($request);
    }

    protected function resolveUser(string $username, string $password): ?User
    {
        /** @var User|null $user */
        $user = User::where('email', $username)->first();

        if (! $user) {
            return null;
        }

        // Password check
        if (Hash::check($password, $user->password)) {
            return $user;
        }

        // Sanctum personal access token (hashed SHA-256 in the tokens table)
        $hashedToken = hash('sha256', $password);
        if ($user->tokens()->where('token', $hashedToken)->exists()) {
            return $user;
        }

        return null;
    }

    protected function requireAuth(string $message = 'Authentication required.'): Response
    {
        return response($message, 401, [
            'WWW-Authenticate' => 'Basic realm="Crucible"',
            'Content-Type'     => 'text/plain',
        ]);
    }
}
