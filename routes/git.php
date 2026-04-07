<?php

/**
 * Git HTTP Smart Protocol routes.
 *
 * These routes are registered without the `web` middleware group (no session,
 * no CSRF) and use HTTP Basic Auth instead. They speak the Git HTTP Smart
 * Protocol so standard `git clone`, `git fetch`, and `git push` work over
 * HTTPS.
 *
 * URL pattern: /{org}/{repo}.git/{endpoint}
 */

use App\Http\Controllers\GitHttpController;
use App\Http\Controllers\LfsBatchController;
use App\Http\Controllers\LfsLocksController;
use App\Http\Middleware\GitHttpAuthenticate;
use Illuminate\Support\Facades\Route;

Route::middleware(GitHttpAuthenticate::class)
    ->group(function () {

        // Reference discovery (first request in every git operation)
        Route::get('{org}/{repo}/info/refs', [GitHttpController::class, 'infoRefs'])
            ->where('repo', '[\w.\-]+\.git');

        // Clone / fetch data transfer
        Route::post('{org}/{repo}/git-upload-pack', [GitHttpController::class, 'uploadPack'])
            ->where('repo', '[\w.\-]+\.git');

        // Push data transfer
        Route::post('{org}/{repo}/git-receive-pack', [GitHttpController::class, 'receivePack'])
            ->where('repo', '[\w.\-]+\.git');

        // Git LFS object transport
        Route::post('{org}/{repo}/info/lfs/objects/batch', [LfsBatchController::class, 'transportBatch'])
            ->where('repo', '[\w.\-]+\.git')
            ->name('git.lfs.batch');
        Route::put('{org}/{repo}/info/lfs/objects/{oid}', [LfsBatchController::class, 'transportUpload'])
            ->where('repo', '[\w.\-]+\.git')
            ->name('git.lfs.objects.upload');
        Route::get('{org}/{repo}/info/lfs/objects/{oid}', [LfsBatchController::class, 'transportDownload'])
            ->where('repo', '[\w.\-]+\.git')
            ->name('git.lfs.objects.download');

        // Git LFS file locking
        // NOTE: /verify must be registered before /{id} so Laravel doesn't treat
        // the literal "verify" as a lock ID.
        Route::post('{org}/{repo}/info/lfs/locks/verify', [LfsLocksController::class, 'verify'])
            ->where('repo', '[\w.\-]+\.git')
            ->name('git.lfs.locks.verify');
        Route::post('{org}/{repo}/info/lfs/locks', [LfsLocksController::class, 'create'])
            ->where('repo', '[\w.\-]+\.git')
            ->name('git.lfs.locks.create');
        Route::get('{org}/{repo}/info/lfs/locks', [LfsLocksController::class, 'index'])
            ->where('repo', '[\w.\-]+\.git')
            ->name('git.lfs.locks.index');
        Route::delete('{org}/{repo}/info/lfs/locks/{id}', [LfsLocksController::class, 'destroy'])
            ->where('repo', '[\w.\-]+\.git')
            ->name('git.lfs.locks.destroy');
    });
