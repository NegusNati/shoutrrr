<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountSetsController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthOptionsController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\ComposerController;
use App\Http\Controllers\Api\V1\ConnectedAccountsController;
use App\Http\Controllers\Api\V1\ConversationGifController;
use App\Http\Controllers\Api\V1\ConversationMediaController;
use App\Http\Controllers\Api\V1\ConversationVideoUploadController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EngagementController;
use App\Http\Controllers\Api\V1\InstanceSettingsController;
use App\Http\Controllers\Api\V1\LinkedInConnectController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MessagingController;
use App\Http\Controllers\Api\V1\MetaConnectController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PostActionsController;
use App\Http\Controllers\Api\V1\PostGifController;
use App\Http\Controllers\Api\V1\PostImageEditController;
use App\Http\Controllers\Api\V1\PostingScheduleController;
use App\Http\Controllers\Api\V1\PostMediaController;
use App\Http\Controllers\Api\V1\PostMetricsController;
use App\Http\Controllers\Api\V1\PostsController;
use App\Http\Controllers\Api\V1\PostVideoUploadController;
use App\Http\Controllers\Api\V1\PublicInvitationController;
use App\Http\Controllers\Api\V1\PublicShareController;
use App\Http\Controllers\Api\V1\ReplyGifController;
use App\Http\Controllers\Api\V1\ReplyImageEditController;
use App\Http\Controllers\Api\V1\ReplyMediaController;
use App\Http\Controllers\Api\V1\ReplyVideoUploadController;
use App\Http\Controllers\Api\V1\SharesController;
use App\Http\Controllers\Api\V1\SyncPipelinesController;
use App\Http\Controllers\Api\V1\UserConnectionsController;
use App\Http\Controllers\Api\V1\UserNotificationsController;
use App\Http\Controllers\Api\V1\UserProfileController;
use App\Http\Controllers\Api\V1\UserSecurityController;
use App\Http\Controllers\Api\V1\WorkspaceApiKeysController;
use App\Http\Controllers\Api\V1\WorkspaceInvitationsController;
use App\Http\Controllers\Api\V1\WorkspaceMentionsController;
use App\Http\Controllers\Api\V1\WorkspacesController;
use App\Http\Controllers\Api\V1\WorkspaceSettingsController;
use App\Http\Controllers\Gifs\GifBrowserController;
use App\Http\Controllers\Posts\NextSlotController;
use App\Http\Middleware\RecordApiUsage;
use App\Http\Middleware\RequireConfirmedPassword;
use App\Http\Middleware\RequireSessionAuth;
use App\Http\Middleware\RequireWriteScope;
use App\Http\Middleware\ResolveApiWorkspace;
use Illuminate\Support\Facades\Route;

// Public: the SPA's auth pages render before login and need the feature flags
// (registration enabled, social providers, password rules) without a session.
Route::get('auth/options', [AuthOptionsController::class, 'show']);

// Public: the share token is the bearer secret (mirrors /share/{token}), and
// the invitation token feeds the SPA's guest join page.
Route::get('shares/public/{token}', [PublicShareController::class, 'show']);
Route::get('workspace-invitations/token/{token}', [PublicInvitationController::class, 'show']);

// Session-only: invitation accept/deny is keyed to the invitee's user account,
// not a workspace — kept outside the workspace-scoped group.
Route::middleware(['auth:api,sanctum', 'throttle:api'])->group(function (): void {
    Route::post('workspace-invitations/{invitation}/accept', [WorkspaceInvitationsController::class, 'accept']);
    Route::delete('workspace-invitations/{invitation}', [WorkspaceInvitationsController::class, 'deny']);
});

// Session-only: user settings belong to the signed-in user, not a workspace —
// RequireSessionAuth rejects Passport API keys before validation runs.
Route::middleware(['auth:api,sanctum', RequireSessionAuth::class, 'throttle:api'])->group(function (): void {
    Route::get('settings/profile', [UserProfileController::class, 'show']);
    Route::put('settings/profile', [UserProfileController::class, 'updateProfile']);
    Route::delete('settings/profile', [UserProfileController::class, 'destroyProfile']);
    // RequireConfirmedPassword mirrors the web group's password.confirm gate:
    // an unconfirmed session gets a JSON 423 and the SPA shows its
    // confirm-password page (Fortify's /user/confirm-password unlocks it).
    Route::get('settings/security', [UserSecurityController::class, 'show'])
        ->middleware(RequireConfirmedPassword::class);
    Route::put('settings/password', [UserSecurityController::class, 'updatePassword']);
    Route::get('settings/connections', [UserConnectionsController::class, 'show']);
    Route::delete('settings/connections/{socialAccountId}', [UserConnectionsController::class, 'remove']);
    Route::get('settings/notifications', [UserNotificationsController::class, 'show']);
    Route::put('settings/notifications', [UserNotificationsController::class, 'updatePreferences']);
});

// Session-only workspace surfaces: settings, api keys, billing, connected
// accounts, sync pipelines and instance settings act on credentials and the
// current workspace — API keys stay out while ResolveApiWorkspace installs
// the workspace_id Context the shared FormRequests authorize against.
Route::middleware(['auth:api,sanctum', RequireSessionAuth::class, ResolveApiWorkspace::class, 'throttle:api'])->group(function (): void {
    Route::get('settings/workspace', [WorkspaceSettingsController::class, 'showOverviewApi']);
    Route::patch('settings/workspace', [WorkspaceSettingsController::class, 'updateWorkspace']);
    Route::put('settings/workspace/timezone', [WorkspaceSettingsController::class, 'updateTimezoneApi']);
    Route::get('settings/workspace/members', [WorkspaceSettingsController::class, 'showMembersApi']);
    Route::post('settings/workspace/invite', [WorkspaceSettingsController::class, 'invite']);
    Route::patch('settings/workspace/members/{membershipId}', [WorkspaceSettingsController::class, 'updateRole']);
    Route::delete('settings/workspace/members/{membershipId}', [WorkspaceSettingsController::class, 'removeMemberApi']);
    Route::delete('settings/workspace/invitations/{invitationId}', [WorkspaceSettingsController::class, 'cancelInvitationApi']);

    Route::get('settings/workspace/api-keys', [WorkspaceApiKeysController::class, 'list']);
    Route::post('settings/workspace/api-keys', [WorkspaceApiKeysController::class, 'create']);
    Route::delete('settings/workspace/api-keys/{apiKeyId}', [WorkspaceApiKeysController::class, 'remove']);

    Route::get('settings/workspace/subscription', [BillingController::class, 'show']);
    Route::post('billing/checkout', [BillingController::class, 'createCheckout']);
    Route::post('billing/portal', [BillingController::class, 'createPortal']);

    // Connected accounts: connect flows + per-account management. The OAuth
    // pickers pass provider data through the session stash and ResolveApiWorkspace
    // scopes the {accountId} bindings to the workspace.
    Route::get('connected-accounts', [ConnectedAccountsController::class, 'index']);
    Route::get('connected-accounts/connect/meta', [MetaConnectController::class, 'pending']);
    Route::get('connected-accounts/connect/linkedin', [LinkedInConnectController::class, 'pending']);
    Route::post('connected-accounts/connect/bluesky', [ConnectedAccountsController::class, 'connectBluesky']);
    Route::post('connected-accounts/connect/discord', [ConnectedAccountsController::class, 'connectDiscord']);
    Route::post('connected-accounts/connect/meta', [MetaConnectController::class, 'submit']);
    Route::post('connected-accounts/connect/linkedin', [LinkedInConnectController::class, 'submit']);
    Route::patch('connected-accounts/{accountId}/toggle', [ConnectedAccountsController::class, 'toggle']);
    Route::post('connected-accounts/{accountId}/default', [ConnectedAccountsController::class, 'makeDefault']);
    Route::patch('connected-accounts/{accountId}/auto-repost', [ConnectedAccountsController::class, 'autoRepost']);
    Route::post('connected-accounts/{accountId}/refresh-x-tier', [ConnectedAccountsController::class, 'refreshXAccountTier']);
    Route::post('connected-accounts/{accountId}/reconnect', [ConnectedAccountsController::class, 'reconnect']);
    Route::delete('connected-accounts/{accountId}', [ConnectedAccountsController::class, 'destroy']);

    Route::get('sync-pipelines', [SyncPipelinesController::class, 'list']);
    Route::post('sync-pipelines', [SyncPipelinesController::class, 'create']);
    Route::patch('sync-pipelines/{pipelineId}', [SyncPipelinesController::class, 'patch']);
    Route::delete('sync-pipelines/{pipelineId}', [SyncPipelinesController::class, 'remove']);
    Route::post('sync-pipelines/native-tracking/{accountId}', [SyncPipelinesController::class, 'trackNative']);
    Route::delete('sync-pipelines/native-tracking/{accountId}', [SyncPipelinesController::class, 'untrackNative']);

    // Instance-owner settings (owner-gated inside the controller).
    Route::get('instance-settings', [InstanceSettingsController::class, 'show']);
    Route::put('instance-settings', [InstanceSettingsController::class, 'updateSettings']);
    Route::get('instance-settings/polling', [InstanceSettingsController::class, 'showPolling']);
    Route::put('instance-settings/polling', [InstanceSettingsController::class, 'updatePollingSettings']);
    Route::get('instance-settings/platforms', [InstanceSettingsController::class, 'showPlatforms']);
    Route::put('instance-settings/platforms', [InstanceSettingsController::class, 'updatePlatformSettings']);
    Route::get('instance-settings/usage', [InstanceSettingsController::class, 'showUsage']);
    Route::get('instance-settings/usage/x', [InstanceSettingsController::class, 'xUsage']);
    Route::put('instance-settings/usage/workspaces/{workspace}/budget', [InstanceSettingsController::class, 'updateBudget']);
    Route::get('instance-settings/admins', [InstanceSettingsController::class, 'listAdmins']);
    Route::post('instance-settings/admins', [InstanceSettingsController::class, 'addAdmin']);
    Route::delete('instance-settings/admins/{owner}', [InstanceSettingsController::class, 'removeAdmin']);
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

        Route::get('notifications', [NotificationsController::class, 'index']);
        Route::delete('notifications', [NotificationsController::class, 'destroyAll']);
        Route::post('notifications/read-all', [NotificationsController::class, 'markAllRead']);
        Route::delete('notifications/{notification}', [NotificationsController::class, 'destroy']);
        Route::post('notifications/{notification}/read', [NotificationsController::class, 'markRead']);

        Route::get('posts', [PostsController::class, 'index']);
        Route::get('posts/next-slot', [NextSlotController::class, 'show']);
        Route::get('posts/{id}', [PostsController::class, 'show']);
        Route::get('posts/{id}/compose', [ComposerController::class, 'showCompose']);
        Route::get('posts/{id}/metrics', [PostMetricsController::class, 'show'])->middleware('metrics.enabled');
        Route::get('account-sets', [AccountSetsController::class, 'index']);
        Route::get('calendar', [CalendarController::class, 'index']);
        Route::get('posting-schedule', [PostingScheduleController::class, 'show']);
        Route::get('posts/{id}/shares', [SharesController::class, 'index']);

        Route::get('analytics', [AnalyticsController::class, 'report'])->middleware('metrics.enabled');

        Route::get('engagement', [EngagementController::class, 'inbox'])->middleware('engagement.enabled');
        Route::get('engagement/{replyId}/thread', [EngagementController::class, 'thread'])->middleware('engagement.enabled');

        Route::get('messages', [MessagingController::class, 'inbox'])->middleware('messages.enabled');
        Route::get('messages/{conversationId}/thread', [MessagingController::class, 'thread'])->middleware('messages.enabled');

        Route::middleware('gifs.enabled')->group(function (): void {
            Route::get('gifs/{catalog}', [GifBrowserController::class, 'index']);
            Route::get('gifs/{catalog}/recent', [GifBrowserController::class, 'recent']);
        });

        Route::middleware(RequireWriteScope::class)->group(function (): void {
            Route::post('posts', [PostsController::class, 'store']);
            Route::patch('posts/{id}', [PostsController::class, 'update']);
            Route::delete('posts/{id}', [PostsController::class, 'destroy']);

            Route::post('posts/{id}/schedule', [PostActionsController::class, 'schedule']);
            Route::post('posts/{id}/queue', [PostActionsController::class, 'queue']);
            Route::post('posts/{id}/publish', [PostActionsController::class, 'publish']);
            Route::post('posts/{id}/targets/{targetId}/retry', [PostActionsController::class, 'retry']);
            Route::post('posts/{id}/duplicate', [PostsController::class, 'duplicate']);
            Route::post('posts/{id}/metrics/refresh', [PostMetricsController::class, 'refresh'])->middleware(['metrics.enabled', 'throttle:10,1']);
            Route::post('posts/{id}/media', [PostMediaController::class, 'storeMedia']);
            Route::patch('posts/{id}/media/{mediaId}/alt', [PostMediaController::class, 'updateMediaAlt']);
            Route::delete('posts/{id}/media/{mediaId}', [PostMediaController::class, 'removeMedia']);
            Route::post('posts/{id}/media/video-url', [PostVideoUploadController::class, 'signUrl']);
            Route::post('posts/{id}/media/video', [PostVideoUploadController::class, 'storeVideo']);
            Route::post('posts/{id}/image-edit', [PostImageEditController::class, 'storeEdit']);
            Route::put('posts/{id}/image-edit/{mediaId}', [PostImageEditController::class, 'updateEdit']);
            Route::post('posts/{id}/gifs', [PostGifController::class, 'attach'])->middleware('gifs.enabled');

            Route::post('posts/{id}/shares', [SharesController::class, 'store']);
            Route::delete('posts/{id}/shares/{shareId}', [SharesController::class, 'destroy']);

            Route::post('media', [MediaController::class, 'store']);
            Route::delete('media/{mediaId}', [MediaController::class, 'destroy']);

            Route::post('account-sets', [AccountSetsController::class, 'store']);
            Route::patch('account-sets/{set}', [AccountSetsController::class, 'update']);
            Route::delete('account-sets/{set}', [AccountSetsController::class, 'destroy']);

            Route::post('workspace-mentions', [WorkspaceMentionsController::class, 'store']);
            Route::delete('workspace-mentions/{workspaceMention}', [WorkspaceMentionsController::class, 'destroy']);

            Route::put('posting-schedule', [PostingScheduleController::class, 'update']);

            Route::middleware('engagement.enabled')->group(function (): void {
                Route::post('engagement/{replyId}/read', [EngagementController::class, 'markRead']);
                Route::post('engagement/{replyId}/archive', [EngagementController::class, 'archive']);
                Route::post('engagement/{replyId}/reply', [EngagementController::class, 'respond'])->middleware('throttle:30,1');

                Route::middleware('throttle:60,1')->group(function (): void {
                    Route::post('engagement/{replyId}/like', [EngagementController::class, 'like']);
                    Route::delete('engagement/{replyId}/like', [EngagementController::class, 'unlike']);
                    Route::delete('engagement/{replyId}', [EngagementController::class, 'destroyReply']);

                    Route::post('engagement/{replyId}/media', [ReplyMediaController::class, 'store']);
                    Route::post('engagement/{replyId}/image-edit', [ReplyImageEditController::class, 'store']);
                    Route::put('engagement/{replyId}/image-edit/{mediaId}', [ReplyImageEditController::class, 'update']);
                    Route::patch('engagement/{replyId}/media/{mediaId}/alt', [ReplyMediaController::class, 'updateAlt']);
                    Route::delete('engagement/{replyId}/media/{mediaId}', [ReplyMediaController::class, 'destroy']);
                    Route::post('engagement/{replyId}/media/video-url', [ReplyVideoUploadController::class, 'url']);
                    Route::post('engagement/{replyId}/media/video', [ReplyVideoUploadController::class, 'store']);
                    Route::post('engagement/{replyId}/gifs', [ReplyGifController::class, 'store'])->middleware('gifs.enabled');
                });
            });

            Route::middleware('messages.enabled')->group(function (): void {
                Route::post('messages/{conversationId}/read', [MessagingController::class, 'markRead']);
                Route::post('messages/{conversationId}/archive', [MessagingController::class, 'archive']);
                Route::post('messages/{conversationId}/reply', [MessagingController::class, 'respond'])->middleware('throttle:30,1');

                Route::middleware('throttle:60,1')->group(function (): void {
                    Route::post('messages/{conversationId}/media', [ConversationMediaController::class, 'store']);
                    Route::patch('messages/{conversationId}/media/{mediaId}/alt', [ConversationMediaController::class, 'updateAlt']);
                    Route::delete('messages/{conversationId}/media/{mediaId}', [ConversationMediaController::class, 'destroy']);
                    Route::post('messages/{conversationId}/media/video-url', [ConversationVideoUploadController::class, 'url']);
                    Route::post('messages/{conversationId}/media/video', [ConversationVideoUploadController::class, 'store']);
                    Route::post('messages/{conversationId}/gifs', [ConversationGifController::class, 'store'])->middleware('gifs.enabled');
                });
            });
        });
    });
