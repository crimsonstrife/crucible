<?php

namespace App\Http\Controllers;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Models\Organization;
use App\Models\Repository;
use App\Services\NativeGitRepositoryService;
use App\Support\DiffParser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RepositoryBrowserController extends Controller
{
    public function __construct(
        protected NativeGitRepositoryService $nativeGit,
        protected RepositoryDriverInterface $driver,
    ) {}

    /**
     * GET /{organization}/{repository}/commits/{ref?}
     *
     * Paginated commit history for a branch/ref.
     */
    public function commits(Request $request, Organization $organization, Repository $repository, string $ref = ''): View
    {
        $this->authorize('view', $repository);

        $this->abortUnlessNative();

        $defaultBranch = $this->driver->defaultBranch($repository);
        $ref           = $ref !== '' ? $ref : $defaultBranch;
        $branches      = $this->driver->exists($repository) ? $this->driver->branches($repository) : [];

        $perPage = 30;
        $page    = max(1, (int) $request->query('page', 1));
        $skip    = ($page - 1) * $perPage;

        $total   = $this->nativeGit->commitCount($repository, $ref);
        $commits = $this->nativeGit->log($repository, $ref, $perPage, $skip);

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return view('repositories.browser.commits', compact(
            'organization',
            'repository',
            'ref',
            'defaultBranch',
            'branches',
            'commits',
            'total',
            'page',
            'perPage',
            'totalPages',
        ));
    }

    /**
     * GET /{organization}/{repository}/commit/{sha}
     *
     * Single commit view with diff.
     */
    public function commit(Request $request, Organization $organization, Repository $repository, string $sha): View
    {
        $this->authorize('view', $repository);

        $this->abortUnlessNative();

        $commit = $this->nativeGit->commitShow($repository, $sha);

        if ($commit === null) {
            abort(404, 'Commit not found.');
        }

        $diffFiles      = DiffParser::parse($commit['diff']);
        $diffTotalAdds  = array_sum(array_column($diffFiles, 'additions'));
        $diffTotalDels  = array_sum(array_column($diffFiles, 'deletions'));

        return view('repositories.browser.commit', compact(
            'organization',
            'repository',
            'commit',
            'diffFiles',
            'diffTotalAdds',
            'diffTotalDels',
        ));
    }

    /**
     * GET /{organization}/{repository}/tags
     */
    public function tags(Request $request, Organization $organization, Repository $repository): View
    {
        $this->authorize('view', $repository);
        $this->abortUnlessNative();

        abort_unless($this->driver->exists($repository), 404, 'Repository not initialized on disk.');

        $tags = $this->nativeGit->tags($repository);

        return view('repositories.browser.tags', compact('organization', 'repository', 'tags'));
    }

    /**
     * GET /{organization}/{repository}/raw/{ref}/{path}
     *
     * Serve the raw file contents from git (inline; use ?download=1 to force download).
     */
    public function raw(Request $request, Organization $organization, Repository $repository, string $ref, string $path): Response
    {
        $this->authorize('view', $repository);

        $this->abortUnlessNative();

        abort_unless($this->driver->exists($repository), 404, 'Repository not initialized on disk.');
        abort_unless($this->nativeGit->hasRevision($repository, $ref), 404, 'Ref not found.');

        $contents = $this->nativeGit->fileContents($repository, $path, $ref);

        if ($contents === null) {
            abort(404, 'File not found.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';

        $disposition = $request->boolean('download')
            ? 'attachment; filename="'.basename($path).'"'
            : 'inline';

        return response($contents, 200, [
            'Content-Type'        => $mime,
            'Content-Length'      => strlen($contents),
            'Content-Disposition' => $disposition,
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    protected function abortUnlessNative(): void
    {
        abort_unless(
            $this->driver instanceof NativeGitDriver,
            404,
            'Git browsing requires the native git backend.',
        );
    }
}
