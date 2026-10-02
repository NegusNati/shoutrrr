<?php

declare(strict_types=1);

use App\Http\Controllers\ConnectedAccounts\BlueskyOAuthController;
use App\Http\Controllers\ConnectedAccounts\MetaConnectionController;
use App\Http\Controllers\ConnectedAccounts\OAuthConnectionController;
use App\Http\Controllers\OAuth\BlueskyClientMetadataController;
use App\Support\SpaRedirect;
use Illuminate\Support\Facades\Route;

Route::get('oauth/bluesky/client-metadata.json', BlueskyClientMetadataController::class)
    ->name('oauth.bluesky.metadata');

Route::get('oauth/bluesky/jwks.json', [BlueskyClientMetadataController::class, 'jwks'])
    ->name('oauth.bluesky.jwks');

// Only browser-facing GETs remain here: OAuth redirects/callbacks can't be
// fetch calls, and the account mutations all moved to /api/v1/connected-accounts.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('accounts', SpaRedirect::to('/accounts'))->name('accounts.index');

    Route::get('accounts/connect/bluesky/oauth', [BlueskyOAuthController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('accounts.bluesky.oauth');

    Route::get('accounts/callback/bluesky/oauth', [BlueskyOAuthController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('accounts.bluesky.oauth.callback');

    // These bespoke `accounts/{connect,callback}/meta` routes must be registered
    // before the generic `{platform}` wildcard routes below — Laravel matches
    // routes in registration order, and Platform::tryFrom('meta') is null, so
    // the wildcard route's resolveOAuthPlatform() would otherwise 404 first.
    Route::get('accounts/connect/meta', [MetaConnectionController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('accounts.meta.redirect');

    Route::get('accounts/callback/meta', [MetaConnectionController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('accounts.meta.callback');

    Route::get('accounts/connect/{platform}', [OAuthConnectionController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('accounts.connect');

    Route::get('accounts/callback/{platform}', [OAuthConnectionController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('accounts.callback');
});
