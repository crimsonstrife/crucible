<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Repository;
use App\Services\BinaryMetadataService;
use App\Services\NativeGitRepositoryService;
use App\Support\MimeDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves asset previews and metadata for diff views.
 *
 * Unlike the raw file endpoint in RepositoryBrowserController, this controller
 * is specifically designed for diff comparisons — it can serve the same file at
 * two different refs (base vs head) and extract metadata for side-by-side display.
 */
class AssetPreviewController extends Controller
{
    public function __construct(
        protected NativeGitRepositoryService $git,
        protected BinaryMetadataService $metadataService,
    ) {}

    /**
     * GET /{org}/{repo}/asset-preview/{ref}/{path}
     *
     * Serve the raw file content at a given ref, with proper MIME headers.
     * Used by <img>, <audio>, and <video> tags in diff views.
     */
    public function show(
        Request $request,
        Organization $organization,
        Repository $repository,
        string $ref,
        string $path,
    ): Response {
        $this->authorize('view', $repository);

        abort_unless($this->git->hasRevision($repository, $ref), 404, 'Ref not found.');

        $contents = $this->git->fileContents($repository, $path, $ref);

        if ($contents === null) {
            abort(404, 'File not found at this ref.');
        }

        // Use extension-based MIME detection for accuracy with game assets
        $mime = MimeDetector::mimeFromExtension($path);

        // Fall back to finfo for unknown extensions
        if ($mime === 'application/octet-stream') {
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
            if ($detected && $detected !== 'application/octet-stream') {
                $mime = $detected;
            }
        }

        return response($contents, 200, [
            'Content-Type'   => $mime,
            'Content-Length' => strlen($contents),
            'Cache-Control'  => 'private, max-age=3600',
        ]);
    }

    /**
     * GET /{org}/{repo}/asset-metadata/{ref}/{path}
     *
     * Return JSON metadata for a binary file at a given ref.
     */
    public function metadata(
        Request $request,
        Organization $organization,
        Repository $repository,
        string $ref,
        string $path,
    ): JsonResponse {
        $this->authorize('view', $repository);

        abort_unless($this->git->hasRevision($repository, $ref), 404, 'Ref not found.');

        $contents = $this->git->fileContents($repository, $path, $ref);

        if ($contents === null) {
            return response()->json(['error' => 'File not found at this ref.'], 404);
        }

        $metadata = $this->metadataService->extract($path, $contents);

        return response()->json(['data' => $metadata]);
    }
}
