<?php

namespace App\Http\Controllers;

use App\Models\LfsUploadSession;
use App\Models\Repository;
use App\Services\LfsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Handles chunked LFS uploads for large objects.
 *
 * Flow:
 * 1. LfsBatchController returns a chunked upload URL (instead of a single PUT)
 *    for objects exceeding the chunked threshold.
 * 2. Client sends PATCH requests with Content-Range headers to append chunks.
 * 3. When the upload is complete, the controller finalizes by calling LfsService::store().
 */
class LfsChunkedUploadController extends Controller
{
    public function __construct(
        protected LfsService $lfsService,
    ) {}

    /**
     * PATCH /{org}/{repo}/lfs-uploads/{session}
     *
     * Append a chunk to an in-progress upload session.
     */
    public function patch(Request $request, Repository $repository, LfsUploadSession $session): JsonResponse
    {
        abort_unless($session->repository_id === $repository->id, 404);

        if ($session->isExpired()) {
            $session->delete();
            abort(410, 'Upload session has expired.');
        }

        $contentRange = $request->header('Content-Range');
        $offset = $session->uploaded_bytes;

        // Parse Content-Range: bytes <start>-<end>/<total>
        if ($contentRange && preg_match('/^bytes (\d+)-(\d+)\/(\d+|\*)$/', $contentRange, $matches)) {
            $rangeStart = (int) $matches[1];
            $rangeEnd = (int) $matches[2];

            if ($rangeStart !== $offset) {
                return response()->json([
                    'error' => 'Chunk offset mismatch. Expected offset: '.$offset,
                    'expected_offset' => $offset,
                ], 409);
            }
        }

        $chunk = $request->getContent();
        $chunkSize = strlen($chunk);

        if ($chunkSize === 0) {
            return response()->json(['error' => 'Empty chunk.'], 400);
        }

        // Append chunk to temporary storage.
        $tempPath = $this->tempPathFor($session);
        $disk = Storage::disk('local');

        if ($offset === 0) {
            $disk->put($tempPath, $chunk);
        } else {
            $fullPath = $disk->path($tempPath);
            file_put_contents($fullPath, $chunk, FILE_APPEND);
        }

        $session->update([
            'uploaded_bytes' => $offset + $chunkSize,
        ]);

        // Check if upload is complete.
        if ($session->isComplete()) {
            $stream = $disk->readStream($tempPath);

            $this->lfsService->store(
                $session->repository,
                $session->oid,
                $session->total_size,
                $stream,
            );

            // Clean up.
            $disk->delete($tempPath);
            $session->delete();

            return response()->json([
                'oid'    => $session->oid,
                'size'   => $session->total_size,
                'status' => 'complete',
            ]);
        }

        return response()->json([
            'oid'            => $session->oid,
            'uploaded_bytes' => $session->uploaded_bytes,
            'remaining'      => $session->remainingBytes(),
            'status'         => 'incomplete',
        ], 202);
    }

    protected function tempPathFor(LfsUploadSession $session): string
    {
        return sprintf('crucible-lfs-uploads/%s/%s', $session->repository_id, $session->oid);
    }
}
