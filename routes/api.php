<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountSetsController;
use App\Http\Controllers\Api\V1\AuthOptionsController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\ConnectedAccountsController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GifsController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\NextSlotController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PlatformLimitsController;
use App\Http\Controllers\Api\V1\PostActionsController;
use App\Http\Controllers\Api\V1\PostGifController;
use App\Http\Controllers\Api\V1\PostImageEditController;
use App\Http\Controllers\Api\V1\PostingScheduleController;
use App\Http\Controllers\Api\V1\PostMediaController;
use App\Http\Controllers\Api\V1\PostMetricsRefreshController;
use App\Http\Controllers\Api\V1\PostsController;
use App\Http\Controllers\Api\V1\PostVideoUploadController;
use App\Http\Controllers\Api\V1\PublicShareController;
use App\Http\Controllers\Api\V1\Settings\ConnectionsController as ConnectionsSettingsController;
use App\Http\Controllers\Api\V1\Settings\NativeTrackingController;
use App\Http\Controllers\Api\V1\Settings\NotificationPreferencesController as NotificationSettingsController;
use App\Http\Controllers\Api\V1\Settings\ProfileController as ProfileSettingsController;
use App\Http\Controllers\Api\V1\Settings\SecurityController as SecuritySettingsController;
use App\Http\Controllers\Api\V1\Settings\SyncPipelinesController;
use App\Http\Controllers\Api\V1\Settings\WorkspaceApiKeysController;
use App\Http\Controllers\Api\V1\Settings\WorkspaceSettingsController as WorkspaceSettingsApiController;
use App\Http\Controllers\Api\V1\Settings\WorkspaceSubscriptionController;
use App\Http\Controllers\Api\V1\SharesController;
use App\Http\Controllers\Api\V1\WorkspaceInvitationsController;
use App\Http\Controllers\Api\V1\WorkspaceMentionsController;
use App\Http\Controllers\Api\V1\WorkspacesController;
use App\Http\Middleware\RecordApiUsage;
use App\Http\Middleware\RequireConfirmedPassword;
use App\Http\Middleware\RequireSessionAuth;
use App\Http\Middleware\RequireWriteScope;
use App\Http\Middleware\ResolveApiWorkspace;
use Illuminate\Support\Facades\Route;

// Public: the SPA's auth pages render before login and need the feature flags
// (registration enabled, social providers, password rules) without a session.
Route::get('auth/options', [AuthOptionsController::class, 'show']);

// Public: share links and the workspace-invitation landing render without a
// session (the token is the capability). Throttles mirror the web routes the
// SPA pages replace.
Route::get('shares/{token}', [PublicShareController::class, 'show'])
    ->middleware('throttle:30,1')
    ->where('token', '[A-Za-z0-9\\-]+');
Route::get('invitations/{token}', [WorkspaceInvitationsController::class, 'show'])
    ->middleware('throttle:5,1');

// Session-only: invitation accept/deny is keyed to the invitee's user account,
// not a workspace — kept outside the workspace-scoped group.
Route::middleware(['auth:api,sanctum', 'throttle:api'])->group(function (): void {
    Route::post('workspace-invitations/{invitation}/accept', [WorkspaceInvitationsController::class, 'accept']);
    Route::delete('workspace-invitations/{invitation}', [WorkspaceInvitationsController::class, 'deny']);
});

// Session-only: user settings are bound to the signed-in user, not a
// workspace — RequireSessionAuth rejects API keys before validation runs.
Route::middleware(['auth:api,sanctum', RequireSessionAuth::class, 'throttle:api'])->group(function (): void {
    Route::patch('settings/profile', [ProfileSettingsController::class, 'update']);
    Route::delete('settings/profile', [ProfileSettingsController::class, 'destroy']);
    // RequireConfirmedPassword mirrors the web group's password.confirm gate:
    // an unconfirmed session gets a JSON 423 and the SPA shows its
    // confirm-password page (Fortify's /user/confirm-password unlocks it).
    Route::get('settings/security', [SecuritySettingsController::class, 'show'])
        ->middleware(RequireConfirmedPassword::class);
    Route::put('settings/password', [SecuritySettingsController::class, 'updatePassword']);
    Route::get('settings/connections', [ConnectionsSettingsController::class, 'index']);
    Route::delete('settings/connections/{socialAccount}', [ConnectionsSettingsController::class, 'destroy']);
    Route::get('settings/notifications', [NotificationSettingsController::class, 'show']);
    Route::put('settings/notifications', [NotificationSettingsController::class, 'update']);
});

// Session-only: workspace settings act on the signed-in user's *current*
// workspace — RequireSessionAuth keeps API keys out while ResolveApiWorkspace
// installs the workspace_id Context the shared Workspace FormRequests
// authorize against.
Route::middleware(['auth:api,sanctum', RequireSessionAuth::class, ResolveApiWorkspace::class, 'throttle:api'])->group(function (): void {
    Route::get('settings/workspace', [WorkspaceSettingsApiController::class, 'overview']);
    Route::patch('settings/workspace', [WorkspaceSettingsApiController::class, 'update']);
    Route::delete('settings/workspace', [WorkspaceSettingsApiController::class, 'destroy']);
    Route::post('settings/workspace/leave', [WorkspaceSettingsApiController::class, 'leave']);
    Route::post('settings/workspace/transfer', [WorkspaceSettingsApiController::class, 'transferOwnership']);
    Route::put('settings/workspace/timezone', [WorkspaceSettingsApiController::class, 'updateTimezone']);
    Route::get('settings/workspace/members', [WorkspaceSettingsApiController::class, 'members']);
    Route::post('settings/workspace/invitations', [WorkspaceSettingsApiController::class, 'invite']);
    Route::patch('settings/workspace/members/{membership}', [WorkspaceSettingsApiController::class, 'updateMemberRole']);
    Route::delete('settings/workspace/members/{membership}', [WorkspaceSettingsApiController::class, 'removeMember']);
    Route::delete('settings/workspace/invitations/{invitation}', [WorkspaceSettingsApiController::class, 'cancelInvitation']);

    Route::get('settings/workspace/api-keys', [WorkspaceApiKeysController::class, 'index']);
    Route::post('settings/workspace/api-keys', [WorkspaceApiKeysController::class, 'store']);
    Route::delete('settings/workspace/api-keys/{apiKey}', [WorkspaceApiKeysController::class, 'destroy']);

    Route::get('settings/workspace/subscription', [WorkspaceSubscriptionController::class, 'show']);
    Route::post('settings/workspace/subscription/checkout', [WorkspaceSubscriptionController::class, 'checkout']);
    Route::post('settings/workspace/subscription/portal', [WorkspaceSubscriptionController::class, 'portal']);

    // Literal segment before the {syncPipeline} wildcard.
    Route::post('sync/native-tracking/{account}', [NativeTrackingController::class, 'store']);
    Route::delete('sync/native-tracking/{account}', [NativeTrackingController::class, 'destroy']);
    Route::get('sync', [SyncPipelinesController::class, 'index']);
    Route::post('sync', [SyncPipelinesController::class, 'store']);
    Route::patch('sync/{syncPipeline}', [SyncPipelinesController::class, 'update']);
    Route::delete('sync/{syncPipeline}', [SyncPipelinesController::class, 'destroy']);
});

// Dual-auth: Passport API keys (auth:api) for external automation OR session
// cookies (auth:sanctum) for the first-party SPA.
Route::middleware(['auth:api,sanctum', ResolveApiWorkspace::class, 'throttle:api', RecordApiUsage::class])
    ->group(function (): void {
        Route::get('me', [MeController::class, 'show']);
        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::get('workspaces', [WorkspacesController::class, 'index']);
        Route::post('workspaces', [WorkspacesController::class, 'store']);
        Route::post('workspaces/switch', [WorkspacesController::class, 'switch']);

        Route::post('onboarding/welcomed', [OnboardingController::class, 'welcomed']);
        Route::post('onboarding/dismiss', [OnboardingController::class, 'dismiss']);
        Route::post('onboarding/steps/complete', [OnboardingController::class, 'completeStep']);

        Route::get('workspace-mentions', [WorkspaceMentionsController::class, 'index']);
        Route::delete('workspace-mentions/{workspaceMention}', [WorkspaceMentionsController::class, 'destroy']);

        Route::get('notifications', [NotificationsController::class, 'index']);
        Route::delete('notifications', [NotificationsController::class, 'destroyAll']);
        Route::post('notifications/read-all', [NotificationsController::class, 'markAllRead']);
        Route::delete('notifications/{notification}', [NotificationsController::class, 'destroy']);
        Route::post('notifications/{notification}/read', [NotificationsController::class, 'markRead']);

        Route::get('connected-accounts', [ConnectedAccountsController::class, 'index']);
        Route::get('posts', [PostsController::class, 'index']);
        // Registered before posts/{id} so the literal segment isn't bound as an id.
        Route::get('posts/next-slot', [NextSlotController::class, 'show']);
        Route::get('posts/{id}', [PostsController::class, 'show']);
        Route::get('account-sets', [AccountSetsController::class, 'index']);
        Route::get('calendar', [CalendarController::class, 'index']);
        Route::get('posting-schedule', [PostingScheduleController::class, 'show']);
        Route::get('posts/{id}/shares', [SharesController::class, 'index']);
        Route::get('posts/{id}/metrics', [PostMetricsRefreshController::class, 'show'])
            ->middleware('metrics.enabled');
        Route::get('platform-limits', [PlatformLimitsController::class, 'index']);

        Route::middleware(['gifs.enabled', 'throttle:120,1'])->group(function (): void {
            Route::get('gifs/{catalog}/recent', [GifsController::class, 'recent']);
            Route::get('gifs/{catalog}', [GifsController::class, 'index']);
        });

        Route::middleware(RequireWriteScope::class)->group(function (): void {
            Route::post('posts', [PostsController::class, 'store']);
            Route::post('workspace-mentions', [WorkspaceMentionsController::class, 'store']);
            Route::patch('posts/{id}', [PostsController::class, 'update']);
            Route::delete('posts/{id}', [PostsController::class, 'destroy']);

            Route::post('posts/{id}/schedule', [PostActionsController::class, 'schedule']);
            Route::post('posts/{id}/queue', [PostActionsController::class, 'queue']);
            Route::post('posts/{id}/publish', [PostActionsController::class, 'publish']);
            Route::post('posts/{id}/targets/{targetId}/retry', [PostActionsController::class, 'retry']);
            Route::post('posts/{id}/duplicate', [PostsController::class, 'duplicate']);
            // dispatchSync skips the queued job's per-platform rate limiting, so
            // throttle here — each hit is a real, metered API read across every
            // published target on the post (mirrors the web route).
            Route::post('posts/{id}/metrics/refresh', [PostMetricsRefreshController::class, 'store'])
                ->middleware(['metrics.enabled', 'throttle:10,1']);

            Route::post('posts/{id}/shares', [SharesController::class, 'store']);
            Route::delete('posts/{id}/shares/{shareId}', [SharesController::class, 'destroy']);

            Route::post('media', [MediaController::class, 'store']);
            Route::delete('media/{mediaId}', [MediaController::class, 'destroy']);

            // Media uploads are throttled to bound abuse (presigned-URL minting /
            // storage flooding) — mirrors the web group's throttle:60,1.
            Route::middleware('throttle:60,1')->group(function (): void {
                Route::post('posts/{post}/media', [PostMediaController::class, 'store']);
                Route::patch('posts/{post}/media/{media}/alt', [PostMediaController::class, 'updateAlt']);
                Route::post('posts/{post}/media/video-url', [PostVideoUploadController::class, 'url']);
                Route::post('posts/{post}/media/video', [PostVideoUploadController::class, 'store']);
                Route::post('posts/{post}/image-edit', [PostImageEditController::class, 'store']);
                Route::put('posts/{post}/image-edit/{media}', [PostImageEditController::class, 'update']);
                Route::post('posts/{post}/gifs', [PostGifController::class, 'store'])
                    ->middleware('gifs.enabled');
            });
            Route::delete('posts/{post}/media/{media}', [PostMediaController::class, 'destroy']);

            Route::post('account-sets', [AccountSetsController::class, 'store']);
            Route::patch('account-sets/{set}', [AccountSetsController::class, 'update']);
            Route::delete('account-sets/{set}', [AccountSetsController::class, 'destroy']);
        });
    });
