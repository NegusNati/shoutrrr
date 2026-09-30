<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountSetsController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthOptionsController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\ConnectedAccountsController;
use App\Http\Controllers\Api\V1\ConversationGifController;
use App\Http\Controllers\Api\V1\ConversationMediaController;
use App\Http\Controllers\Api\V1\ConversationVideoUploadController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EngagementController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MessagingController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PostActionsController;
use App\Http\Controllers\Api\V1\PostingScheduleController;
use App\Http\Controllers\Api\V1\PostsController;
use App\Http\Controllers\Api\V1\ReplyGifController;
use App\Http\Controllers\Api\V1\ReplyMediaController;
use App\Http\Controllers\Api\V1\ReplyVideoUploadController;
use App\Http\Controllers\Api\V1\SharesController;
use App\Http\Controllers\Api\V1\WorkspaceInvitationsController;
use App\Http\Controllers\Api\V1\WorkspaceMentionsController;
use App\Http\Controllers\Api\V1\WorkspacesController;
use App\Http\Controllers\Gifs\GifBrowserController;
use App\Http\Middleware\RecordApiUsage;
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
        Route::get('posts/{id}', [PostsController::class, 'show']);
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

            Route::post('posts/{id}/shares', [SharesController::class, 'store']);
            Route::delete('posts/{id}/shares/{shareId}', [SharesController::class, 'destroy']);

            Route::post('media', [MediaController::class, 'store']);
            Route::delete('media/{mediaId}', [MediaController::class, 'destroy']);

            Route::post('account-sets', [AccountSetsController::class, 'store']);
            Route::patch('account-sets/{set}', [AccountSetsController::class, 'update']);
            Route::delete('account-sets/{set}', [AccountSetsController::class, 'destroy']);

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
