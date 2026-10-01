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
use App\Http\Controllers\Api\V1\Settings\ConnectionsController as ConnectionsSettingsController;
use App\Http\Controllers\Api\V1\Settings\NotificationPreferencesController as NotificationSettingsController;
use App\Http\Controllers\Api\V1\Settings\ProfileController as ProfileSettingsController;
use App\Http\Controllers\Api\V1\Settings\SecurityController as SecuritySettingsController;
use App\Http\Controllers\Api\V1\Settings\WorkspaceApiKeysController;
use App\Http\Controllers\Api\V1\Settings\WorkspaceSettingsController;
use App\Http\Controllers\Api\V1\Settings\WorkspaceSubscriptionController;
use App\Http\Controllers\Api\V1\SharesController;
use App\Http\Controllers\Api\V1\WorkspaceInvitationsController;
use App\Http\Controllers\Api\V1\WorkspaceMentionsController;
use App\Http\Controllers\Api\V1\WorkspacesController;
use App\Http\Middleware\RecordApiUsage;
use App\Http\Middleware\RequireSessionAuth;
use App\Http\Middleware\RequireWriteScope;
use App\Http\Middleware\ResolveApiWorkspace;
use Illuminate\Support\Facades\Route;

// Public: the SPA's auth pages render before login and need the feature flags
// (registration enabled, social providers, password rules) without a session.
Route::get('auth/options', [AuthOptionsController::class, 'show']);

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
    Route::get('settings/security', [SecuritySettingsController::class, 'show']);
    Route::put('settings/password', [SecuritySettingsController::class, 'updatePassword']);
    Route::get('settings/connections', [ConnectionsSettingsController::class, 'index']);
    Route::delete('settings/connections/{socialAccount}', [ConnectionsSettingsController::class, 'destroy']);
    Route::get('settings/notifications', [NotificationSettingsController::class, 'show']);
    Route::put('settings/notifications', [NotificationSettingsController::class, 'update']);
});

// Session-only + current-workspace bound: workspace settings operate on the
// caller's current_workspace_id, so API keys are rejected (session surface)
// and the workspace Context must resolve for the shared FormRequests.
Route::middleware([
    'auth:api,sanctum',
    RequireSessionAuth::class,
    ResolveApiWorkspace::class,
    'throttle:api',
])->group(function (): void {
    Route::get('settings/workspace', [WorkspaceSettingsController::class, 'show']);
    Route::patch('settings/workspace', [WorkspaceSettingsController::class, 'update']);
    Route::delete('settings/workspace', [WorkspaceSettingsController::class, 'destroy']);
    Route::put('settings/workspace/timezone', [WorkspaceSettingsController::class, 'updateTimezone']);
    Route::get('settings/workspace/members', [WorkspaceSettingsController::class, 'members']);
    Route::post('settings/workspace/invite', [WorkspaceSettingsController::class, 'inviteUser']);
    Route::patch('settings/workspace/members/{membership}', [WorkspaceSettingsController::class, 'updateMemberRole']);
    Route::delete('settings/workspace/members/{membership}', [WorkspaceSettingsController::class, 'removeMember']);
    Route::delete('settings/workspace/invitations/{invitation}', [WorkspaceSettingsController::class, 'cancelInvitation']);
    Route::post('settings/workspace/leave', [WorkspaceSettingsController::class, 'leave']);
    Route::post('settings/workspace/transfer', [WorkspaceSettingsController::class, 'transferOwnership']);
    Route::get('settings/workspace/api-keys', [WorkspaceApiKeysController::class, 'index']);
    Route::post('settings/workspace/api-keys', [WorkspaceApiKeysController::class, 'store']);
    Route::delete('settings/workspace/api-keys/{apiKey}', [WorkspaceApiKeysController::class, 'destroy']);
    Route::get('settings/workspace/subscription', [WorkspaceSubscriptionController::class, 'show']);
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
