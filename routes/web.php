<?php

use App\Http\Controllers\AssetPreviewController;
use App\Http\Controllers\Auth\ForgeSsoController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\FileLockController;
use App\Http\Controllers\LfsStorageController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\PullRequestController;
use App\Http\Controllers\RepositoryBrowserController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\RepositoryForgeController;
use App\Http\Controllers\SshKeyController;
use Illuminate\Support\Facades\Route;

// Public root
Route::get('/', fn () => redirect()->route('dashboard'));

// Forge SSO
Route::get('/auth/forge', [ForgeSsoController::class, 'redirect'])->name('auth.forge');
Route::get('/auth/forge/callback', [ForgeSsoController::class, 'callback'])->name('auth.forge.callback');

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Organizations
    Route::resource('organizations', OrganizationController::class);

    // Organization member management
    Route::post('organizations/{organization}/members', [OrganizationMemberController::class, 'store'])
        ->name('organizations.members.store');
    Route::patch('organizations/{organization}/members/{user}', [OrganizationMemberController::class, 'update'])
        ->name('organizations.members.update');
    Route::delete('organizations/{organization}/members/{user}', [OrganizationMemberController::class, 'destroy'])
        ->name('organizations.members.destroy');

    // Repositories scoped to organization
    Route::prefix('/{organization:slug}')
        ->scopeBindings()
        ->group(function () {
            // Import routes must come before the resource so /repositories/import
            // is matched as a literal path and not as {repository}=import.
            Route::get('/repositories/import', [RepositoryController::class, 'import'])
                ->name('repositories.import');
            Route::post('/repositories/import', [RepositoryController::class, 'importStore'])
                ->name('repositories.import.store');
            Route::resource('repositories', RepositoryController::class);
            Route::post('/repositories/{repository:slug}/sync', [RepositoryController::class, 'sync'])
                ->name('repositories.sync');
            Route::post('/repositories/{repository:slug}/archive', [RepositoryController::class, 'archive'])
                ->name('repositories.archive');
            Route::post('/repositories/{repository:slug}/collaborators', [CollaboratorController::class, 'store'])
                ->name('repositories.collaborators.store');
            Route::delete('/repositories/{repository:slug}/collaborators/{user}', [CollaboratorController::class, 'destroy'])
                ->name('repositories.collaborators.destroy');
            Route::get('/repositories/{repository:slug}/lfs', [LfsStorageController::class, 'index'])
                ->name('repositories.lfs.dashboard');
            Route::get('/repositories/{repository:slug}/locks', [FileLockController::class, 'index'])
                ->name('repositories.locks.index');
            Route::delete('/repositories/{repository:slug}/locks/{fileLock}', [FileLockController::class, 'destroy'])
                ->name('repositories.locks.destroy');

            // Pull Requests — literal routes before scoped {pullRequest} binding
            Route::get('/repositories/{repository:slug}/pull-requests/create', [PullRequestController::class, 'create'])
                ->name('repositories.pull-requests.create');
            Route::post('/repositories/{repository:slug}/pull-requests', [PullRequestController::class, 'store'])
                ->name('repositories.pull-requests.store');
            Route::get('/repositories/{repository:slug}/pull-requests', [PullRequestController::class, 'index'])
                ->name('repositories.pull-requests.index');
            Route::get('/repositories/{repository:slug}/pull-requests/{pullRequest}', [PullRequestController::class, 'show'])
                ->name('repositories.pull-requests.show');
            Route::post('/repositories/{repository:slug}/pull-requests/{pullRequest}/merge', [PullRequestController::class, 'merge'])
                ->name('repositories.pull-requests.merge');
            Route::post('/repositories/{repository:slug}/pull-requests/{pullRequest}/close', [PullRequestController::class, 'close'])
                ->name('repositories.pull-requests.close');
            Route::post('/repositories/{repository:slug}/pull-requests/{pullRequest}/reopen', [PullRequestController::class, 'reopen'])
                ->name('repositories.pull-requests.reopen');
            Route::post('/repositories/{repository:slug}/pull-requests/{pullRequest}/ready', [PullRequestController::class, 'markReady'])
                ->name('repositories.pull-requests.ready');

            // Forge integration — repo-level (1:1 Forge project ↔ Crucible repo)
            // Literal /forge must come before any {repository} slug to avoid conflicts.
            Route::get('/repositories/{repository:slug}/forge', [RepositoryForgeController::class, 'show'])
                ->name('repositories.forge.show');
            Route::put('/repositories/{repository:slug}/forge', [RepositoryForgeController::class, 'update'])
                ->name('repositories.forge.update');
            Route::delete('/repositories/{repository:slug}/forge', [RepositoryForgeController::class, 'destroy'])
                ->name('repositories.forge.destroy');
            Route::post('/repositories/{repository:slug}/forge/sync', [RepositoryForgeController::class, 'sync'])
                ->name('repositories.forge.sync');
            Route::post('/repositories/{repository:slug}/forge/token', [RepositoryForgeController::class, 'generateToken'])
                ->name('repositories.forge.token.generate');
            Route::delete('/repositories/{repository:slug}/forge/token', [RepositoryForgeController::class, 'revokeToken'])
                ->name('repositories.forge.token.revoke');

            // Asset preview — serves files at specific refs for diff comparison
            Route::get('/repositories/{repository:slug}/asset-preview/{ref}/{path}', [AssetPreviewController::class, 'show'])
                ->name('repositories.asset-preview')
                ->where('path', '.+');
            Route::get('/repositories/{repository:slug}/asset-metadata/{ref}/{path}', [AssetPreviewController::class, 'metadata'])
                ->name('repositories.asset-metadata')
                ->where('path', '.+');

            // Git browser — tags, commit history, single commit, raw file
            Route::get('/repositories/{repository:slug}/tags', [RepositoryBrowserController::class, 'tags'])
                ->name('repositories.tags');
            Route::get('/repositories/{repository:slug}/commits/{ref?}', [RepositoryBrowserController::class, 'commits'])
                ->name('repositories.commits')
                ->where('ref', '[^/]*');
            Route::get('/repositories/{repository:slug}/commit/{sha}', [RepositoryBrowserController::class, 'commit'])
                ->name('repositories.commit');
            Route::get('/repositories/{repository:slug}/raw/{ref}/{path}', [RepositoryBrowserController::class, 'raw'])
                ->name('repositories.raw')
                ->where('path', '.+');
        });

    // SSH Keys
    Route::resource('ssh-keys', SshKeyController::class)->only(['index', 'store', 'destroy']);
});
