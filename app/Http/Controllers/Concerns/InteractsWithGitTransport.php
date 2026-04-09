<?php

namespace App\Http\Controllers\Concerns;

use App\Drivers\NativeGitDriver;
use App\Enums\RepositoryVisibility;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

trait InteractsWithGitTransport
{
    protected function resolveTransportRepository(string $orgSlug, string $repoWithGit): Repository
    {
        $repoSlug = preg_replace('/\.git$/i', '', $repoWithGit);

        $organization = Organization::where('slug', $orgSlug)->firstOrFail();

        return $organization->repositories()
            ->where('slug', $repoSlug)
            ->firstOrFail();
    }

    protected function credentialChallenge(string $message = 'Authentication required.'): Response
    {
        return response($message, 401, [
            'WWW-Authenticate' => 'Basic realm="Crucible"',
            'Content-Type' => 'text/plain',
        ]);
    }

    protected function checkTransportReadAccess(Repository $repository): ?Response
    {
        if ($repository->visibility === RepositoryVisibility::Public) {
            return null;
        }

        if (Auth::guest()) {
            return $this->credentialChallenge();
        }

        if (Auth::user()->cannot('view', $repository)) {
            abort(403, 'You do not have read access to this repository.');
        }

        return null;
    }

    protected function checkTransportWriteAccess(Repository $repository): ?Response
    {
        if (Auth::guest()) {
            return $this->credentialChallenge('Authentication required to push.');
        }

        if (Auth::user()->cannot('push', $repository)) {
            abort(403, 'You do not have push access to this repository.');
        }

        return null;
    }

    protected function ensureNativeGitTransportIsActive(): void
    {
        abort_unless(
            $this->repositoryDriver instanceof NativeGitDriver,
            501,
            'Git transport is only available when the native git backend is active.',
        );
    }

    /**
     * Return the raw request body, decoding Content-Encoding if present.
     *
     * Git clients frequently gzip upload-pack / receive-pack request bodies,
     * and the same encoding can be applied to LFS object uploads by clients
     * or upstream proxies. `git upload-pack --stateless-rpc` does not
     * understand gzip, and the LFS backend sanity-checks the stored byte
     * count against the expected size — both paths need an already-decoded
     * buffer, otherwise git reports "fatal: protocol error: bad line length
     * character" or the LFS upload fails with a size mismatch.
     */
    protected function decodedRequestBody(Request $request): string
    {
        $body = $request->getContent();
        $encoding = strtolower(trim((string) $request->header('Content-Encoding', '')));

        if ($encoding === '' || $encoding === 'identity') {
            return $body;
        }

        if ($encoding === 'gzip' || $encoding === 'x-gzip') {
            $decoded = @gzdecode($body);
            if ($decoded === false) {
                abort(400, 'Malformed gzip-encoded request body.');
            }
            return $decoded;
        }

        abort(415, "Unsupported Content-Encoding: {$encoding}");
    }
}
