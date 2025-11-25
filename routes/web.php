<?php

use App\Http\Controllers\Auth\ForgeOAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');
});

// Forge OAuth routes (placeholder)
Route::prefix('auth/forge')->name('auth.forge.')->group(function () {
    Route::get('redirect', [ForgeOAuthController::class, 'redirect'])->name('redirect');
    Route::get('callback', [ForgeOAuthController::class, 'callback'])->name('callback');
});
