<?php

use App\Http\Controllers\Api\V1\BranchApiController;
use App\Http\Controllers\Api\V1\ForgeIntegrationApiController;
use App\Http\Controllers\Api\V1\BranchProtectionApiController;
use App\Http\Controllers\Api\V1\CommitStatusApiController;
use App\Http\Controllers\Api\V1\GameEngineApiController;
use App\Http\Controllers\Api\V1\LfsPolicyApiController;
use App\Http\Controllers\Api\V1\OrganizationApiController;
use App\Http\Controllers\Api\V1\PullRequestApiController;
use App\Http\Controllers\Api\V1\PullRequestReviewApiController;
use App\Http\Controllers\Api\V1\RepositoryApiController;
use App\Http\Controllers\Api\V1\SparseCheckoutApiController;
use App\Http\Controllers\Api\V1\WebhookApiController;
use App\Http\Controllers\FileLockApiController;
use App\Http\Controllers\LfsBatchController;
use App\Http\Controllers\LfsChunkedUploadController;
use App\Http\Controllers\TusUploadController;
use App\Http\Requests\FileLocks\LockFileRequest;
use App\Models\FileLock;
use App\Models\Repository;
use App\Services\FileLockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        // ── Current user ─────────────────────────────────────────────────────────
        Route::get('/me', function (Request $request) {
            return response()->json(['data' => $request->user()]);
        });

        // ── Organizations ────────────────────────────────────────────────────────
        Route::get('/organizations', [OrganizationApiController::class, 'index']);
        Route::post('/organizations', [OrganizationApiController::class, 'store']);
        Route::get('/organizations/{organization:slug}', [OrganizationApiController::class, 'show']);
        Route::patch('/organizations/{organization:slug}', [OrganizationApiController::class, 'update']);
        Route::delete('/organizations/{organization:slug}', [OrganizationApiController::class, 'destroy']);

        // Org-nested repositories
        Route::get('/organizations/{organization:slug}/repositories', [OrganizationApiController::class, 'repositories']);
        Route::post('/organizations/{organization:slug}/repositories', [RepositoryApiController::class, 'store']);

        // ── Org-scoped repository operations requiring a user token ──────────────
        Route::prefix('/{organization:slug}/{repository:slug}')
            ->scopeBindings()
            ->group(function () {
                // CRUD
                Route::patch('/', [RepositoryApiController::class, 'update']);
                Route::delete('/', [RepositoryApiController::class, 'destroy']);

                // Git LFS
                Route::post('/info/lfs/objects/batch', [LfsBatchController::class, 'batch']);
                Route::put('/info/lfs/objects/{oid}', [LfsBatchController::class, 'upload'])
                    ->name('api.lfs.objects.upload');
                Route::get('/info/lfs/objects/{oid}', [LfsBatchController::class, 'download'])
                    ->name('api.lfs.objects.download');

                // Chunked LFS uploads
                Route::patch('/lfs-uploads/{session}', [LfsChunkedUploadController::class, 'patch'])
                    ->name('api.lfs.uploads.patch');

                // TUS resumable uploads
                Route::match(['options'], '/tus', [TusUploadController::class, 'options']);
                Route::post('/tus', [TusUploadController::class, 'create']);
                Route::match(['head'], '/tus/{session}', [TusUploadController::class, 'head']);
                Route::patch('/tus/{session}', [TusUploadController::class, 'patch'])
                    ->name('api.tus.upload');
                Route::delete('/tus/{session}', [TusUploadController::class, 'destroy']);

                // LFS Policies
                Route::get('/lfs-policies', [LfsPolicyApiController::class, 'index']);
                Route::post('/lfs-policies', [LfsPolicyApiController::class, 'store']);
                Route::delete('/lfs-policies/{policy}', [LfsPolicyApiController::class, 'destroy']);
                Route::post('/lfs-policies/apply-template', [LfsPolicyApiController::class, 'applyTemplate']);
                Route::get('/lfs-policies/templates', [LfsPolicyApiController::class, 'templates']);
                Route::get('/lfs-policies/gitattributes', [LfsPolicyApiController::class, 'gitattributes']);

                // Branch Protection
                Route::get('/branch-protection', [BranchProtectionApiController::class, 'index']);
                Route::post('/branch-protection', [BranchProtectionApiController::class, 'store']);
                Route::patch('/branch-protection/{rule}', [BranchProtectionApiController::class, 'update']);
                Route::delete('/branch-protection/{rule}', [BranchProtectionApiController::class, 'destroy']);

                // Game Engine Detection & Policies
                Route::get('/engine', [GameEngineApiController::class, 'show']);
                Route::post('/engine/detect', [GameEngineApiController::class, 'detect']);
                Route::get('/engine/recommend', [GameEngineApiController::class, 'recommend']);
                Route::post('/engine/apply', [GameEngineApiController::class, 'apply']);
                Route::post('/generate-gitattributes', [GameEngineApiController::class, 'generateGitattributes']);

                // Webhooks
                Route::get('/webhooks', [WebhookApiController::class, 'index']);
                Route::post('/webhooks', [WebhookApiController::class, 'store']);
                Route::patch('/webhooks/{webhook}', [WebhookApiController::class, 'update']);
                Route::delete('/webhooks/{webhook}', [WebhookApiController::class, 'destroy']);
                Route::get('/webhooks/{webhook}/deliveries', [WebhookApiController::class, 'deliveries']);
                Route::get('/webhooks/events', [WebhookApiController::class, 'events']);

                // Commit Statuses (CI Integration)
                Route::get('/statuses/{sha}', [CommitStatusApiController::class, 'index']);
                Route::post('/statuses/{sha}', [CommitStatusApiController::class, 'store']);

                // Sparse Checkout Profiles
                Route::get('/sparse-profiles', [SparseCheckoutApiController::class, 'index']);
                Route::post('/sparse-profiles', [SparseCheckoutApiController::class, 'store']);
                Route::get('/sparse-profiles/sample', [SparseCheckoutApiController::class, 'sampleConfig']);
                Route::get('/sparse-profiles/{profile}', [SparseCheckoutApiController::class, 'show']);
                Route::patch('/sparse-profiles/{profile}', [SparseCheckoutApiController::class, 'update']);
                Route::delete('/sparse-profiles/{profile}', [SparseCheckoutApiController::class, 'destroy']);
                Route::post('/sparse-profiles/sync', [SparseCheckoutApiController::class, 'sync']);

                // Workspace Configuration
                Route::get('/workspace', [SparseCheckoutApiController::class, 'workspaceConfig']);

                // File Locks
                Route::get('/locks', [FileLockApiController::class, 'index']);
                Route::post('/locks', [FileLockApiController::class, 'store']);
                Route::post('/locks/verify', [FileLockApiController::class, 'verify']);
                Route::post('/locks/{lock}/unlock', [FileLockApiController::class, 'unlock']);
                Route::delete('/locks/{lock}', [FileLockApiController::class, 'destroy']);
            });

        // ── Legacy unscoped LFS/lock routes (kept for git-lfs client backwards compat) ──
        // These routes resolve Organization from the Repository's relationship
        // so the controller signature stays consistent with the scoped routes.
        Route::post('/{repository:slug}/info/lfs/objects/batch', [LfsBatchController::class, 'batch']);
        Route::put('/{repository:slug}/info/lfs/objects/{oid}', [LfsBatchController::class, 'upload']);
        Route::get('/{repository:slug}/info/lfs/objects/{oid}', [LfsBatchController::class, 'download']);
        Route::get('/{repository:slug}/locks', fn (Request $request, Repository $repository, FileLockService $service) => app(FileLockApiController::class)->index($request, $repository->organization, $repository, $service));
        Route::post('/{repository:slug}/locks', fn (LockFileRequest $request, Repository $repository, FileLockService $service) => app(FileLockApiController::class)->store($request, $repository->organization, $repository, $service));
        Route::post('/{repository:slug}/locks/verify', fn (Request $request, Repository $repository, FileLockService $service) => app(FileLockApiController::class)->verify($request, $repository->organization, $repository, $service));
        Route::post('/{repository:slug}/locks/{lock}/unlock', fn (Request $request, Repository $repository, FileLock $lock, FileLockService $service) => app(FileLockApiController::class)->unlock($request, $repository->organization, $repository, $lock, $service));
        Route::delete('/{repository:slug}/locks/{lock}', fn (Request $request, Repository $repository, FileLock $lock, FileLockService $service) => app(FileLockApiController::class)->destroy($repository->organization, $repository, $lock, $service));
    });

    // ── Repo-scoped Forge API routes (user token, global app token, or legacy repo token) ──
    Route::middleware('forge.api')
        ->get('/repositories', [RepositoryApiController::class, 'index']);

    Route::middleware('forge.api')
        ->prefix('/{organization:slug}/{repository:slug}')
        ->scopeBindings()
        ->group(function () {
            Route::get('/', [RepositoryApiController::class, 'show']);

            // Branches
            Route::get('/branches', [BranchApiController::class, 'index']);
            Route::post('/branches', [BranchApiController::class, 'store']);
            Route::get('/default-branch', [BranchApiController::class, 'defaultBranch']);

            // Pull Requests
            Route::get('/pull-requests', [PullRequestApiController::class, 'index']);
            Route::post('/pull-requests', [PullRequestApiController::class, 'store']);
            Route::get('/pull-requests/{number}', [PullRequestApiController::class, 'show'])->where('number', '[0-9]+');
            Route::patch('/pull-requests/{number}', [PullRequestApiController::class, 'update'])->where('number', '[0-9]+');

            // PR Reviews
            Route::get('/pull-requests/{number}/reviews', [PullRequestReviewApiController::class, 'index'])->where('number', '[0-9]+');
            Route::post('/pull-requests/{number}/reviews', [PullRequestReviewApiController::class, 'store'])->where('number', '[0-9]+');

            // Forge Integration (programmatic link management)
            Route::post('/forge-integration', [ForgeIntegrationApiController::class, 'store']);
            Route::delete('/forge-integration', [ForgeIntegrationApiController::class, 'destroy']);
        });
});
