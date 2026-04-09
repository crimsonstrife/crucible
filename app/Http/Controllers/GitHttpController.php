<?php

namespace App\Http\Controllers;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Enums\RepositoryVisibility;
use App\Models\Organization;
use App\Models\Repository;
use App\Services\NativeGitRepositoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Git HTTP Smart Protocol handler.
 *
 * Implements the stateless HTTP transport described in:
 *   https://www.git-scm.com/docs/http-backend
 *   https://git-scm.com/docs/gitprotocol-http
 *
 * Endpoints (all prefixed with /{org}/{repo}.git):
 *   GET  /info/refs?service=git-upload-pack   → advertise refs for clone/fetch
 *   GET  /info/refs?service=git-receive-pack  → advertise refs for push
 *   POST /git-upload-pack                     → serve pack data (clone/fetch)
 *   POST /git-receive-pack                    → accept pack data (push)
 */
class GitHttpController extends Controller
{
    public function __construct(
        protected RepositoryDriverInterface $repositoryDriver,
        protected NativeGitRepositoryService $nativeGit,
    ) {}

    protected function resolveRepo(string $orgSlug, string $repoWithGit): Repository
    {
        $repoSlug = preg_replace('/\.git$/i', '', $repoWithGit);

        $org = Organization::where('slug', $orgSlug)->firstOrFail();

        return $org->repositories()
            ->where('slug', $repoSlug)
            ->firstOrFail();
    }

    /**
     * Return a 401 challenge response so git knows to prompt for credentials.
     * Must use 401 (not 403) — git only retries with credentials on 401.
     */
    protected function credentialChallenge(string $message = 'Authentication required.'): Response
    {
        return response($message, 401, [
            'WWW-Authenticate' => 'Basic realm="Crucible"',
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Enforce read access for a repository.
     * - Public  → always allowed (including anonymous)
     * - Internal → authenticated org member
     * - Private  → authenticated collaborator or owner
     *
     * Returns a 401 Response if credentials are needed, null if access is granted.
     */
    protected function checkReadAccess(Repository $repository): ?Response
    {
        if ($repository->visibility === RepositoryVisibility::Public) {
            return null;
        }

        // Non-public: must be authenticated
        if (Auth::guest()) {
            return $this->credentialChallenge();
        }

        // Delegate to RepositoryPolicy::view()
        if (Auth::user()->cannot('view', $repository)) {
            abort(403, 'You do not have read access to this repository.');
        }

        return null;
    }

    /**
     * Enforce write (push) access for a repository.
     * Always requires authentication; delegates to RepositoryPolicy::push().
     */
    protected function checkWriteAccess(Repository $repository): ?Response
    {
        if (Auth::guest()) {
            return $this->credentialChallenge('Authentication required to push.');
        }

        if (Auth::user()->cannot('push', $repository)) {
            abort(403, 'You do not have push access to this repository.');
        }

        return null;
    }

    /** Build a git pkt-line–encoded string. */
    protected function pktLine(string $data): string
    {
        return sprintf('%04x', strlen($data) + 4).$data;
    }

    protected function ensureNativeBackendIsActive(): void
    {
        abort_unless(
            $this->repositoryDriver instanceof NativeGitDriver,
            501,
            'Git smart HTTP is only available when the native git backend is active.',
        );
    }

    // ── Routes ────────────────────────────────────────────────────────────────

    /**
     * GET /{org}/{repo}.git/info/refs?service=git-upload-pack|git-receive-pack
     *
     * Reference discovery — the first request in every git operation.
     */
    public function infoRefs(Request $request, string $org, string $repo): Response
    {
        $service = $request->query('service', '');

        if (! in_array($service, ['git-upload-pack', 'git-receive-pack'], true)) {
            abort(400, 'Invalid or missing service parameter.');
        }

        $repository = $this->resolveRepo($org, $repo);
        $this->ensureNativeBackendIsActive();

        // Enforce access — reads need view permission, writes need push permission
        if ($service === 'git-receive-pack') {
            if ($repository->is_archived) {
                abort(403, 'Repository is archived and read-only.');
            }
            if ($challenge = $this->checkWriteAccess($repository)) {
                return $challenge;
            }
        } else {
            if ($challenge = $this->checkReadAccess($repository)) {
                return $challenge;
            }
        }

        if (! $this->nativeGit->exists($repository)) {
            abort(404, 'Repository not initialized on disk.');
        }

        try {
            $advertisement = $this->nativeGit->advertiseRefs($repository, $service);
        } catch (RuntimeException $exception) {
            abort(500, $exception->getMessage());
        }

        // The Smart HTTP advertisement format prepends a pkt-line service header + flush
        $body = $this->pktLine("# service={$service}\n").'0000'.$advertisement;

        return response($body, 200, [
            'Content-Type' => "application/x-{$service}-advertisement",
            'Cache-Control' => 'no-cache, no-store',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * POST /{org}/{repo}.git/git-upload-pack
     *
     * Serve pack data for clone / fetch.
     */
    public function uploadPack(Request $request, string $org, string $repo): Response
    {
        $repository = $this->resolveRepo($org, $repo);
        $this->ensureNativeBackendIsActive();

        if ($challenge = $this->checkReadAccess($repository)) {
            return $challenge;
        }

        if (! $this->nativeGit->exists($repository)) {
            abort(404, 'Repository not initialized on disk.');
        }

        try {
            $output = $this->nativeGit->handleStatelessRpc(
                $repository,
                'git-upload-pack',
                $this->decodedRequestBody($request),
            );
        } catch (RuntimeException $exception) {
            abort(500, $exception->getMessage());
        }

        return response($output, 200, [
            'Content-Type' => 'application/x-git-upload-pack-result',
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }

    /**
     * POST /{org}/{repo}.git/git-receive-pack
     *
     * Accept pack data for push.
     */
    public function receivePack(Request $request, string $org, string $repo): Response
    {
        $repository = $this->resolveRepo($org, $repo);
        $this->ensureNativeBackendIsActive();

        if ($repository->is_archived) {
            abort(403, 'Repository is archived and read-only.');
        }

        if ($challenge = $this->checkWriteAccess($repository)) {
            return $challenge;
        }

        if (! $this->nativeGit->exists($repository)) {
            abort(404, 'Repository not initialized on disk.');
        }

        try {
            $output = $this->nativeGit->handleStatelessRpc(
                $repository,
                'git-receive-pack',
                $this->decodedRequestBody($request),
            );
        } catch (RuntimeException $exception) {
            abort(500, $exception->getMessage());
        }

        return response($output, 200, [
            'Content-Type' => 'application/x-git-receive-pack-result',
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }

    /**
     * Return the raw request body, decoding Content-Encoding if present.
     *
     * Git clients frequently gzip upload-pack / receive-pack request bodies.
     * `git upload-pack --stateless-rpc` does not understand gzip, so we must
     * decode here before piping to stdin, otherwise git reports
     * "fatal: protocol error: bad line length character".
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
                abort(400, 'Malformed gzip-encoded git request body.');
            }
            return $decoded;
        }

        abort(415, "Unsupported Content-Encoding: {$encoding}");
    }
}
