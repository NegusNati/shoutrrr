<?php

use App\Http\Controllers\Uploads\StreamedUploadController;
use App\Http\Middleware\CaptureMcpWorkspaceSelection;
use App\Http\Middleware\EnsureConversationSupportsDirectMessageMedia;
use App\Http\Middleware\EnsureEngagementEnabled;
use App\Http\Middleware\EnsureFeedbackEnabled;
use App\Http\Middleware\EnsureGifsEnabled;
use App\Http\Middleware\EnsureMessagesEnabled;
use App\Http\Middleware\EnsureMetricsEnabled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\WorkspaceMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Registered outside the `web` group: a signed, stateless direct-to-disk
        // video upload receiver. It must NOT carry session/CSRF/workspace
        // middleware — the client PUTs the raw file body with no session — and a
        // relative temporary signature is the sole authorization.
        then: function (): void {
            Route::put('uploads/stream/{path}', StreamedUploadController::class)
                ->where('path', '.*')
                ->middleware(['throttle:60,1', 'signed:relative'])
                ->name('uploads.stream');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'engagement.enabled' => EnsureEngagementEnabled::class,
            'messages.enabled' => EnsureMessagesEnabled::class,
            'metrics.enabled' => EnsureMetricsEnabled::class,
            'feedback.enabled' => EnsureFeedbackEnabled::class,
            'gifs.enabled' => EnsureGifsEnabled::class,
            'conversation.supports-media' => EnsureConversationSupportsDirectMessageMedia::class,
        ]);

        $middleware->web(append: [
            // Outermost: sets the CSP nonce before the view renders and writes
            // the security headers onto the final response.
            SecurityHeaders::class,
            HandleAppearance::class,
            // WorkspaceMiddleware must run early so scoped queries resolve the
            // current workspace before any controller logic executes.
            WorkspaceMiddleware::class,
            // NOTE: AddLinkHeadersForPreloadedAssets is intentionally not
            // registered. It emits a `Link: rel=preload` HTTP header for each
            // Vite asset, but under Octane the underlying preloadedAssets list
            // persists across requests, so behind a TLS-terminating proxy the
            // header gets frozen with http:// URLs from an earlier request and
            // never reflects the per-request https scheme. The browser then
            // blocks those preloads as mixed content. The header is redundant
            // with the <link rel="modulepreload"> tags already rendered into the
            // document (which are generated per request and resolve to https).
            CaptureMcpWorkspaceSelection::class,
        ]);

        // Session-authenticated first-party SPA calls hit /api/v1 with cookies;
        // Sanctum's stateful middleware gives them a session guard while
        // external consumers keep authenticating with Passport API keys.
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Report unhandled exceptions to Sentry. No-op unless a DSN is set, so
        // self-hosted instances without Sentry are unaffected.
        Integration::handles($exceptions);

        // Render exceptions as JSON for API paths and for any client that
        // explicitly asks for JSON (the SPA always sends Accept: json).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
